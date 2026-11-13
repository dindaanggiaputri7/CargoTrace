<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\ShipmentTripAssignment;
use App\Models\TrackingProvider;
use App\Models\TrackingSyncLog;
use App\Models\TransportAsset;
use App\Models\TransportTrip;
use App\Models\TripPosition;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TransportController extends Controller
{
    private function tripQuery(Request $request)
    {
        $query = TransportTrip::query()->with([
            'carrier:id,name,code', 'asset:id,name,asset_code,asset_type,identifier_type,identifier_value',
            'origin:id,name,city,country,latitude,longitude',
            'destination:id,name,city,country,latitude,longitude',
            'latestPosition', 'assignments.shipment:id,shipment_number,customer_id,status',
        ]);
        if ($request->user()->isCustomer()) {
            $query->whereHas('assignments.shipment', fn ($q) => $q->where('customer_id', $request->user()->id));
        }
        return $query;
    }

    public function assets(Request $request)
    {
        $query = TransportAsset::with('carrier:id,name,code');
        if ($request->boolean('active_only')) $query->where('is_active', true);
        return response()->json($query->orderBy('name')->paginate(min(max((int)$request->input('per_page', 50),1),100)));
    }

    public function storeAsset(Request $request, AuditService $audit)
    {
        $data = $request->validate([
            'asset_code'=>['required','string','max:60','unique:transport_assets,asset_code'],
            'name'=>['required','string','max:255'],
            'asset_type'=>['required',Rule::in(['VESSEL','TRUCK','AIRCRAFT','TRAIN'])],
            'identifier_type'=>['nullable',Rule::in(['IMO','MMSI','REGISTRATION','ICAO','DEVICE_ID','OTHER'])],
            'identifier_value'=>['nullable','string','max:100'],
            'carrier_id'=>['nullable','exists:carriers,id'],
            'flag_country'=>['nullable','string','max:100'],
            'is_active'=>['sometimes','boolean'],'metadata'=>['nullable','array'],
        ]);
        $asset=TransportAsset::create($data);
        $audit->record($request->user(),'TRANSPORT_ASSET_CREATED','TransportAsset',$asset->id,'Transport asset dibuat.');
        return response()->json(['data'=>$asset->load('carrier')],201);
    }

    public function updateAsset(Request $request, TransportAsset $asset, AuditService $audit)
    {
        $data=$request->validate([
            'asset_code'=>['sometimes','string','max:60',Rule::unique('transport_assets','asset_code')->ignore($asset->id)],
            'name'=>['sometimes','string','max:255'],
            'asset_type'=>['sometimes',Rule::in(['VESSEL','TRUCK','AIRCRAFT','TRAIN'])],
            'identifier_type'=>['nullable',Rule::in(['IMO','MMSI','REGISTRATION','ICAO','DEVICE_ID','OTHER'])],
            'identifier_value'=>['nullable','string','max:100'],'carrier_id'=>['nullable','exists:carriers,id'],
            'flag_country'=>['nullable','string','max:100'],'is_active'=>['sometimes','boolean'],'metadata'=>['nullable','array'],
        ]);
        $before=$asset->only(array_keys($data)); $asset->update($data);
        $audit->record($request->user(),'TRANSPORT_ASSET_UPDATED','TransportAsset',$asset->id,'Transport asset diperbarui.',$before,$asset->fresh()->only(array_keys($data)));
        return response()->json(['data'=>$asset->fresh()->load('carrier')]);
    }

    public function trips(Request $request)
    {
        $data=$request->validate(['status'=>['nullable',Rule::in(['PLANNED','SCHEDULED','ACTIVE','COMPLETED','CANCELLED'])],'search'=>['nullable','string','max:100'],'per_page'=>['nullable','integer','min:1','max:100']]);
        $q=$this->tripQuery($request);
        if (!empty($data['status'])) $q->where('status',$data['status']);
        if (!empty($data['search'])) $q->where(fn($s)=>$s->where('trip_number','like','%'.$data['search'].'%')->orWhereHas('asset',fn($a)=>$a->where('name','like','%'.$data['search'].'%')));
        return response()->json($q->latest()->paginate($data['per_page']??20)->withQueryString());
    }

    public function storeTrip(Request $request, AuditService $audit)
    {
        abort_unless($request->user()->isOperator(),403);
        $data=$request->validate([
            'trip_number'=>['nullable','string','max:60','unique:transport_trips,trip_number'],
            'carrier_id'=>['required','exists:carriers,id'],'transport_asset_id'=>['nullable','exists:transport_assets,id'],
            'transport_mode'=>['required',Rule::in(['SEA','ROAD','AIR','RAIL'])],
            'origin_location_id'=>['required','exists:locations,id'],'destination_location_id'=>['required','exists:locations,id'],
            'scheduled_departure_at'=>['nullable','date'],'scheduled_arrival_at'=>['nullable','date','after:scheduled_departure_at'],
            'notes'=>['nullable','string','max:3000'],
        ]);
        if($data['origin_location_id']===$data['destination_location_id']) throw ValidationException::withMessages(['destination_location_id'=>['Origin dan destination harus berbeda.']]);
        $data['trip_number']=$data['trip_number']??'TRIP-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
        $data['created_by']=$request->user()->id;
        $trip=TransportTrip::create($data);
        $audit->record($request->user(),'TRANSPORT_TRIP_CREATED','TransportTrip',$trip->id,'Transport trip dibuat.');
        return response()->json(['data'=>$trip->load(['carrier','asset','origin','destination'])],201);
    }

    public function updateTrip(Request $request, TransportTrip $trip, AuditService $audit)
    {
        abort_unless($request->user()->isOperator(),403);
        $data=$request->validate([
            'transport_asset_id'=>['nullable','exists:transport_assets,id'],
            'scheduled_departure_at'=>['nullable','date'],'scheduled_arrival_at'=>['nullable','date','after:scheduled_departure_at'],
            'actual_departure_at'=>['nullable','date'],'actual_arrival_at'=>['nullable','date'],
            'status'=>['sometimes',Rule::in(['PLANNED','SCHEDULED','ACTIVE','COMPLETED','CANCELLED'])],
            'notes'=>['nullable','string','max:3000'],
        ]);
        if (isset($data['status'])) {
            $allowed = [
                'PLANNED' => ['SCHEDULED','CANCELLED'],
                'SCHEDULED' => ['ACTIVE','CANCELLED'],
                'ACTIVE' => ['COMPLETED','CANCELLED'],
                'COMPLETED' => [],
                'CANCELLED' => [],
            ];
            if (!in_array($data['status'], $allowed[$trip->status] ?? [], true)) {
                throw ValidationException::withMessages(['status'=>['Transisi status trip tidak valid.']]);
            }
        }
        $before=$trip->only(array_keys($data)); $trip->update($data);
        $audit->record($request->user(),'TRANSPORT_TRIP_UPDATED','TransportTrip',$trip->id,'Transport trip diperbarui.',$before,$trip->fresh()->only(array_keys($data)));
        return response()->json(['data'=>$trip->fresh()->load(['carrier','asset','origin','destination','latestPosition'])]);
    }

    public function assignShipment(Request $request, TransportTrip $trip, AuditService $audit)
    {
        abort_unless($request->user()->isOperator(),403);
        if(in_array($trip->status,['COMPLETED','CANCELLED'],true)) throw ValidationException::withMessages(['trip'=>['Trip yang selesai atau dibatalkan tidak dapat menerima shipment.']]);
        $data=$request->validate([
            'shipment_id'=>['required','exists:shipments,id'],
            'leg_order'=>['required','integer','min:1','max:100',Rule::unique('shipment_trip_assignments','leg_order')->where('shipment_id',$request->input('shipment_id'))],
        ]);
        $shipment=Shipment::findOrFail($data['shipment_id']);
        if($shipment->status==='DELIVERED') throw ValidationException::withMessages(['shipment_id'=>['Shipment yang sudah delivered tidak dapat ditambahkan ke trip baru.']]);
        $assignment=ShipmentTripAssignment::create($data+['transport_trip_id'=>$trip->id,'assigned_at'=>now(),'status'=>'ASSIGNED']);
        $audit->record($request->user(),'SHIPMENT_ASSIGNED_TO_TRIP','ShipmentTripAssignment',$assignment->id,'Shipment ditautkan ke transport trip.');
        return response()->json(['data'=>$assignment->load(['shipment','trip'])],201);
    }

    public function unassignShipment(Request $request, ShipmentTripAssignment $assignment, AuditService $audit)
    {
        abort_unless($request->user()->isOperator(),403);
        $assignment->update(['status'=>'UNASSIGNED','unassigned_at'=>now()]);
        $audit->record($request->user(),'SHIPMENT_UNASSIGNED_FROM_TRIP','ShipmentTripAssignment',$assignment->id,'Shipment dilepas dari trip.');
        return response()->json(['message'=>'Assignment berhasil dilepas.']);
    }

    public function addManualPosition(Request $request, TransportTrip $trip, AuditService $audit)
    {
        abort_unless($request->user()->isOperator(),403);
        $data=$request->validate([
            'latitude'=>['required','numeric','between:-90,90'],'longitude'=>['required','numeric','between:-180,180'],
            'observed_at'=>['required','date'],'speed_knots'=>['nullable','numeric','min:0','max:200'],
            'heading'=>['nullable','numeric','between:0,360'],'notes'=>['nullable','string','max:500'],
        ]);
        if(now()->parse($data['observed_at'])->isFuture()) throw ValidationException::withMessages(['observed_at'=>['Waktu observasi tidak boleh di masa depan.']]);
        $position=TripPosition::create([
            'transport_trip_id'=>$trip->id,'latitude'=>$data['latitude'],'longitude'=>$data['longitude'],
            'observed_at'=>$data['observed_at'],'received_at'=>now(),'speed_knots'=>$data['speed_knots']??null,
            'heading'=>$data['heading']??null,'source'=>'MANUAL','quality'=>'UNVERIFIED',
            'raw_payload'=>['notes'=>$data['notes']??null],
        ]);
        $audit->record($request->user(),'TRIP_POSITION_RECORDED','TripPosition',$position->id,'Posisi trip dimasukkan manual.');
        return response()->json(['data'=>$position],201);
    }

    public function positions(Request $request, TransportTrip $trip)
    {
        $this->authorizeTrip($request,$trip);
        $positions=$trip->positions()->with('provider:id,name,provider_type')->latest('observed_at')->paginate(100);
        return response()->json($positions);
    }

    public function customerJourneys(Request $request)
    {
        abort_unless($request->user()->isCustomer(),403);
        $trips=$this->tripQuery($request)->whereHas('assignments.shipment',fn($q)=>$q->where('customer_id',$request->user()->id))
            ->with(['positions'=>fn($q)=>$q->latest('observed_at')->limit(20)])
            ->orderByDesc('updated_at')->paginate(20);
        return response()->json($trips);
    }

    public function providers()
    {
        return response()->json(['data'=>TrackingProvider::query()->orderBy('name')->get()->makeHidden(['credential_encrypted','webhook_secret_hash'])]);
    }

    public function storeProvider(Request $request, AuditService $audit)
    {
        $data=$request->validate([
            'name'=>['required','string','max:120'],'provider_type'=>['required',Rule::in(['AIS','GPS','CARRIER','PORT','CUSTOMS'])],
            'driver'=>['nullable',Rule::in(['generic_webhook','generic_rest'])],
            'base_url'=>['nullable','url','max:500'],'credential'=>['nullable','string','max:2000'],
            'webhook_secret'=>['nullable','string','min:24','max:200'],'is_active'=>['sometimes','boolean'],
            'poll_interval_seconds'=>['sometimes','integer','min:60','max:86400'],
            'stale_after_minutes'=>['sometimes','integer','min:1','max:10080'],'settings'=>['nullable','array'],
        ]);
        $secret=$data['webhook_secret']??null; unset($data['webhook_secret']);
        $credential=$data['credential']??null; unset($data['credential']);
        $provider=TrackingProvider::create($data);
        if($credential) $provider->credential=$credential;
        if($secret) $provider->webhook_secret_hash=Hash::make($secret);
        $provider->save();
        $audit->record($request->user(),'TRACKING_PROVIDER_CREATED','TrackingProvider',$provider->id,'Konfigurasi provider dibuat.');
        return response()->json(['data'=>$provider->makeHidden(['credential_encrypted','webhook_secret_hash'])],201);
    }

    public function webhook(Request $request, TrackingProvider $provider)
    {
        $started=now();
        $log=TrackingSyncLog::create(['tracking_provider_id'=>$provider->id,'sync_type'=>'WEBHOOK','status'=>'FAILED','started_at'=>$started]);
        try {
            abort_unless($provider->is_active && $provider->webhook_secret_hash && Hash::check((string)$request->header('X-CargoTrace-Webhook-Secret'),$provider->webhook_secret_hash),401,'Invalid provider signature.');
            if (in_array($provider->provider_type, ['CARRIER','PORT','CUSTOMS'], true)) {
                $event = $request->validate([
                    'event_id'=>['required','string','max:120'],
                    'shipment_number'=>['required','string','max:60'],
                    'event_type'=>['required','string','max:60'],
                    'description'=>['required','string','max:2000'],
                    'observed_at'=>['required','date'],
                    'latitude'=>['nullable','numeric','between:-90,90'],
                    'longitude'=>['nullable','numeric','between:-180,180'],
                    'raw'=>['nullable','array'],
                ]);
                $shipment=Shipment::where('shipment_number',$event['shipment_number'])->firstOrFail();
                $existing=\App\Models\ExternalProviderEvent::where('tracking_provider_id',$provider->id)->where('provider_event_id',$event['event_id'])->first();
                if($existing){
                    $log->update(['status'=>'SUCCESS','records_received'=>1,'records_created'=>0,'message'=>'Duplicate external event ignored.','finished_at'=>now()]);
                    return response()->json(['message'=>'Duplicate event ignored.','data'=>$existing],200);
                }
                $record=DB::transaction(function() use ($provider,$event,$shipment) {
                    $record=\App\Models\ExternalProviderEvent::create([
                        'tracking_provider_id'=>$provider->id,'provider_event_id'=>$event['event_id'],
                        'shipment_id'=>$shipment->id,'event_category'=>$provider->provider_type,
                        'event_type'=>$event['event_type'],'description'=>$event['description'],
                        'observed_at'=>$event['observed_at'],'payload'=>$event['raw']??null,
                    ]);
                    \App\Models\TrackingEvent::create([
                        'shipment_id'=>$shipment->id,'event_type'=>strtoupper($provider->provider_type).'_UPDATE',
                        'status'=>$shipment->status,'description'=>$event['description'],
                        'event_time'=>$event['observed_at'],'latitude'=>$event['latitude']??null,
                        'longitude'=>$event['longitude']??null,
                        'metadata'=>['provider'=>$provider->name,'provider_event_id'=>$event['event_id'],'event_type'=>$event['event_type']],
                    ]);
                    return $record;
                });
                $log->update(['status'=>'SUCCESS','records_received'=>1,'records_created'=>1,'finished_at'=>now()]);
                $provider->update(['last_synced_at'=>now()]);
                return response()->json(['data'=>$record],201);
            }
            $data=$request->validate([
                'trip_number'=>['required','string','max:60'],'event_id'=>['required','string','max:120'],
                'latitude'=>['required','numeric','between:-90,90'],'longitude'=>['required','numeric','between:-180,180'],
                'observed_at'=>['required','date'],'speed_knots'=>['nullable','numeric','min:0','max:200'],
                'heading'=>['nullable','numeric','between:0,360'],'raw'=>['nullable','array'],
            ]);
            $trip=TransportTrip::where('trip_number',$data['trip_number'])->firstOrFail();
            $existing=TripPosition::where('tracking_provider_id',$provider->id)->where('provider_event_id',$data['event_id'])->first();
            if($existing) {
                $log->update(['status'=>'SUCCESS','records_received'=>1,'records_created'=>0,'message'=>'Duplicate event ignored.','finished_at'=>now()]);
                return response()->json(['message'=>'Duplicate event ignored.','data'=>$existing],200);
            }
            $position=TripPosition::create([
                'transport_trip_id'=>$trip->id,'tracking_provider_id'=>$provider->id,'provider_event_id'=>$data['event_id'],
                'latitude'=>$data['latitude'],'longitude'=>$data['longitude'],'observed_at'=>$data['observed_at'],
                'received_at'=>now(),'speed_knots'=>$data['speed_knots']??null,'heading'=>$data['heading']??null,
                'source'=>'PROVIDER','quality'=>now()->parse($data['observed_at'])->diffInMinutes(now())>$provider->stale_after_minutes?'STALE':'VERIFIED',
                'raw_payload'=>$data['raw']??null,
            ]);
            $log->update(['transport_trip_id'=>$trip->id,'status'=>'SUCCESS','records_received'=>1,'records_created'=>1,'finished_at'=>now()]);
            $provider->update(['last_synced_at'=>now()]);
            return response()->json(['data'=>$position],201);
        } catch (\Throwable $e) {
            $log->update(['status'=>'FAILED','error_code'=>(string)$e->getCode(),'message'=>'Provider payload rejected or unavailable.','finished_at'=>now()]);
            throw $e;
        }
    }

    private function authorizeTrip(Request $request, TransportTrip $trip): void
    {
        if($request->user()->isCustomer() && !$trip->assignments()->whereHas('shipment',fn($q)=>$q->where('customer_id',$request->user()->id))->exists()) abort(404);
    }
}
