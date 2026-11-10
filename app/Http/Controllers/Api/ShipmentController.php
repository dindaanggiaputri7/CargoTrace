<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\TrackingEvent;
use App\Models\User;
use App\Services\AuditService;
use App\Services\EtaService;
use App\Services\ShipmentLifecycleService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ShipmentController extends Controller
{
    public function index(Request $request, EtaService $etaService)
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(Shipment::STATUSES)],
            'carrier_id' => ['nullable', 'integer', 'exists:carriers,id'],
            'origin_location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'destination_location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'delayed' => ['nullable', Rule::in(['0', '1', 0, 1])],
            'sort' => ['nullable', Rule::in(['created_at', 'expected_arrival_at', 'shipment_number', 'status'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Shipment::query()->with([
            'customer:id,name,email', 'carrier:id,name,code',
            'origin:id,name,city,country,latitude,longitude',
            'destination:id,name,city,country,latitude,longitude',
        ]);

        if ($request->user()->isCustomer()) {
            $query->where('customer_id', $request->user()->id);
        }

        $query->when($request->filled('search'), function ($q) use ($request) {
            $term = '%'.$request->input('search').'%';
            $q->where(fn ($s) => $s->where('shipment_number', 'like', $term)
                ->orWhere('customer_reference', 'like', $term)
                ->orWhere('cargo_description', 'like', $term));
        });
        $query->when($request->filled('status'), fn ($q) => $q->where('status', $data['status']));
        $query->when($request->filled('carrier_id'), fn ($q) => $q->where('carrier_id', $data['carrier_id']));
        $query->when($request->filled('origin_location_id'), fn ($q) => $q->where('origin_location_id', $data['origin_location_id']));
        $query->when($request->filled('destination_location_id'), fn ($q) => $q->where('destination_location_id', $data['destination_location_id']));
        $query->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $data['date_from']));
        $query->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $data['date_to']));

        if (($data['delayed'] ?? null) === '1' || ($data['delayed'] ?? null) === 1) {
            $query->whereNotNull('current_eta')->whereColumn('current_eta', '>', 'expected_arrival_at');
        } elseif (($data['delayed'] ?? null) === '0' || ($data['delayed'] ?? null) === 0) {
            $query->where(fn ($q) => $q->whereNull('current_eta')->orWhereColumn('current_eta', '<=', 'expected_arrival_at'));
        }

        $sort = $data['sort'] ?? 'created_at';
        $direction = $data['direction'] ?? 'desc';
        $paginator = $query->orderBy($sort, $direction)->paginate($data['per_page'] ?? 15)->withQueryString();

        $paginator->getCollection()->transform(function (Shipment $shipment) use ($etaService) {
            $shipment->setAttribute('eta_assessment', $etaService->assess($shipment));
            $shipment->setAttribute('progress_percentage', $etaService->progressPercentage($shipment));
            if (request()->user()?->isCustomer()) $shipment->makeHidden(['notes', 'created_by']);
            return $shipment;
        });

        return response()->json($paginator);
    }

    public function store(Request $request, AuditService $audit, NotificationService $notifications)
    {
        $data = $request->validate([
            'shipment_number' => ['nullable', 'string', 'max:50', 'unique:shipments,shipment_number'],
            'customer_reference' => ['nullable', 'string', 'max:100'],
            'customer_id' => ['required', Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', User::ROLE_CUSTOMER)->where('is_active', true))],
            'carrier_id' => ['required', Rule::exists('carriers', 'id')->where('is_active', true)],
            'origin_location_id' => ['required', 'exists:locations,id', 'different:destination_location_id'],
            'destination_location_id' => ['required', 'exists:locations,id'],
            'cargo_description' => ['nullable', 'string', 'max:3000'],
            'package_count' => ['required', 'integer', 'min:1'],
            'total_weight' => ['nullable', 'numeric', 'min:0.001'],
            'weight_unit' => ['nullable', 'string', 'max:10'],
            'expected_arrival_at' => ['required', 'date', 'after:now'],
            'current_eta' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'items' => ['nullable', 'array', 'max:50'],
            'items.*.item_name' => ['required_with:items', 'string', 'max:255'],
            'items.*.sku' => ['nullable', 'string', 'max:80'],
            'items.*.description' => ['nullable', 'string', 'max:1000'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.unit_weight' => ['nullable', 'numeric', 'min:0'],
            'items.*.weight_unit' => ['nullable', 'string', 'max:10'],
        ]);

        $shipment = DB::transaction(function () use ($data, $request, $audit, $notifications) {
            $items = $data['items'] ?? [];
            unset($data['items']);
            $data['shipment_number'] = $data['shipment_number'] ?? 'CT-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
            $data['created_by'] = $request->user()->id;
            $data['status'] = 'CREATED';
            $shipment = Shipment::create($data);

            foreach ($items as $item) {
                $shipment->items()->create($item);
            }

            TrackingEvent::create([
                'shipment_id' => $shipment->id,
                'created_by' => $request->user()->id,
                'event_type' => 'SHIPMENT_CREATED',
                'status' => 'CREATED',
                'description' => 'Shipment berhasil dibuat dan menunggu proses booking.',
                'event_time' => now(),
            ]);

            $audit->record($request->user(), 'SHIPMENT_CREATED', 'Shipment', $shipment->id,
                'Shipment '.$shipment->shipment_number.' dibuat.');
            $notifications->send($shipment->customer, 'SHIPMENT_CREATED', 'Pengiriman baru dibuat',
                'Pengiriman '.$shipment->shipment_number.' telah dibuat.', $shipment);

            return $shipment;
        });

        return response()->json(['message' => 'Shipment berhasil dibuat.', 'data' => $shipment->load(['customer', 'carrier', 'origin', 'destination', 'items'])], 201);
    }

    public function show(Request $request, Shipment $shipment, EtaService $etaService)
    {
        $this->authorizeView($request, $shipment);
        $shipment->load([
            'customer:id,name,email', 'creator:id,name', 'carrier', 'origin', 'destination',
            'items', 'trackingEvents.location', 'trackingEvents.creator:id,name',
            'exceptions.reporter:id,name',
        ]);
        $shipment->setAttribute('eta_assessment', $etaService->assess($shipment));
        $shipment->setAttribute('progress_percentage', $etaService->progressPercentage($shipment));
        if ($request->user()->isCustomer()) $shipment->makeHidden(['notes', 'created_by']);
        return response()->json(['data' => $shipment]);
    }

    public function update(Request $request, Shipment $shipment, AuditService $audit)
    {
        abort_if($shipment->status === 'DELIVERED', 422, 'Shipment yang sudah DELIVERED tidak dapat diubah.');
        $data = $request->validate([
            'customer_reference' => ['sometimes', 'nullable', 'string', 'max:100'],
            'carrier_id' => ['sometimes', 'exists:carriers,id'],
            'origin_location_id' => ['sometimes', 'exists:locations,id'],
            'destination_location_id' => ['sometimes', 'exists:locations,id'],
            'cargo_description' => ['sometimes', 'nullable', 'string', 'max:3000'],
            'package_count' => ['sometimes', 'integer', 'min:1'],
            'total_weight' => ['sometimes', 'nullable', 'numeric', 'min:0.001'],
            'weight_unit' => ['sometimes', 'string', 'max:10'],
            'expected_arrival_at' => ['sometimes', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:3000'],
        ]);
        $origin = $data['origin_location_id'] ?? $shipment->origin_location_id;
        $destination = $data['destination_location_id'] ?? $shipment->destination_location_id;
        if ((string) $origin === (string) $destination) {
            throw ValidationException::withMessages(['destination_location_id' => 'Lokasi asal dan tujuan harus berbeda.']);
        }
        $old = $shipment->only(array_keys($data));
        $shipment->update($data);
        $audit->record($request->user(), 'SHIPMENT_UPDATED', 'Shipment', $shipment->id, 'Detail shipment diperbarui.', $old, $data);
        return response()->json(['message' => 'Shipment diperbarui.', 'data' => $shipment->fresh()]);
    }

    public function destroy(Request $request, Shipment $shipment, AuditService $audit)
    {
        abort_if($shipment->status !== 'CREATED', 422, 'Hanya shipment berstatus CREATED yang boleh dihapus.');
        $audit->record($request->user(), 'SHIPMENT_DELETED', 'Shipment', $shipment->id, 'Shipment '.$shipment->shipment_number.' dihapus.');
        $shipment->delete();
        return response()->json(['message' => 'Shipment dihapus.']);
    }

    public function transition(Request $request, Shipment $shipment, ShipmentLifecycleService $service)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(Shipment::STATUSES)],
            'description' => ['nullable', 'string', 'max:1500'],
        ]);
        $updated = $service->transition($shipment, $data['status'], $request->user(), $data['description'] ?? null);
        return response()->json(['message' => 'Status shipment berhasil diperbarui.', 'data' => $updated]);
    }

    public function updateEta(Request $request, Shipment $shipment, AuditService $audit, NotificationService $notifications)
    {
        abort_if($shipment->status === 'DELIVERED', 422, 'ETA tidak dapat diubah setelah shipment DELIVERED.');
        $data = $request->validate(['current_eta' => ['required', 'date', 'after:now'], 'reason' => ['nullable', 'string', 'max:1000']]);
        $oldEta = $shipment->current_eta?->toDateTimeString();
        $shipment->update(['current_eta' => $data['current_eta']]);
        $audit->record($request->user(), 'SHIPMENT_ETA_UPDATED', 'Shipment', $shipment->id,
            'Current ETA diperbarui. '.($data['reason'] ?? ''), ['current_eta' => $oldEta], ['current_eta' => $shipment->current_eta?->toDateTimeString()]);
        $notifications->send($shipment->customer, 'ETA_UPDATED', 'Estimasi tiba diperbarui',
            'Estimasi tiba '.$shipment->shipment_number.' telah diperbarui.', $shipment,
            ['current_eta' => $shipment->current_eta?->toIso8601String()]);
        return response()->json(['data' => $shipment->fresh(), 'eta_assessment' => app(EtaService::class)->assess($shipment->fresh())]);
    }

    private function authorizeView(Request $request, Shipment $shipment): void
    {
        if ($request->user()->isCustomer() && (int) $shipment->customer_id !== (int) $request->user()->id) {
            abort(404);
        }
    }
}
