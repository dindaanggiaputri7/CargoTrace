<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\TrackingEvent;
use App\Models\User;
use App\Services\AuditService;
use App\Services\EtaService;
use App\Services\NotificationService;
use App\Services\ShipmentLifecycleService;
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
            'delayed' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(['created_at', 'expected_departure_at', 'expected_arrival_at', 'shipment_number', 'status'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Shipment::query()->with([
            'customer:id,name,email', 'carrier:id,name,code',
            'origin:id,name,city,region,country,latitude,longitude',
            'destination:id,name,city,region,country,latitude,longitude',
            'latestTrackingEvent.location',
        ]);
        if ($request->user()->isCustomer()) $query->where('customer_id', $request->user()->id);

        $query->when(!empty($data['search']), function ($q) use ($data) {
            $term = '%'.$data['search'].'%';
            $q->where(fn ($s) => $s->where('shipment_number', 'like', $term)
                ->orWhere('customer_reference', 'like', $term)->orWhere('cargo_type', 'like', $term)
                ->orWhere('cargo_description', 'like', $term));
        });
        $query->when(!empty($data['status']), fn ($q) => $q->where('status', $data['status']));
        $query->when(!empty($data['carrier_id']), fn ($q) => $q->where('carrier_id', $data['carrier_id']));
        $query->when(!empty($data['origin_location_id']), fn ($q) => $q->where('origin_location_id', $data['origin_location_id']));
        $query->when(!empty($data['destination_location_id']), fn ($q) => $q->where('destination_location_id', $data['destination_location_id']));
        $query->when(!empty($data['date_from']), fn ($q) => $q->whereDate('expected_departure_at', '>=', $data['date_from']));
        $query->when(!empty($data['date_to']), fn ($q) => $q->whereDate('expected_departure_at', '<=', $data['date_to']));
        if (array_key_exists('delayed', $data)) {
            $data['delayed'] ? $query->delayed() : $query->where(fn ($q) => $q->where('status', 'DELIVERED')
                ->orWhereRaw('COALESCE(current_eta, expected_arrival_at) <= expected_arrival_at'));
        }

        $paginator = $query->orderBy($data['sort'] ?? 'created_at', $data['direction'] ?? 'desc')
            ->paginate($data['per_page'] ?? 15)->withQueryString();
        $paginator->getCollection()->transform(function (Shipment $shipment) use ($etaService, $request) {
            $shipment->setAttribute('eta_assessment', $etaService->assess($shipment));
            $shipment->setAttribute('progress_percentage', $etaService->progressPercentage($shipment));
            if ($request->user()->isCustomer()) $shipment->makeHidden(['notes', 'created_by']);
            return $shipment;
        });

        return response()->json($paginator);
    }

    public function store(Request $request, AuditService $audit, NotificationService $notifications)
    {
        $data = $request->validate($this->createRules());
        if ((int) $data['origin_location_id'] === (int) $data['destination_location_id']) {
            throw ValidationException::withMessages(['destination_location_id' => ['Lokasi asal dan tujuan harus berbeda.']]);
        }

        $shipment = DB::transaction(function () use ($data, $request, $audit, $notifications) {
            $items = $data['items'] ?? [];
            unset($data['items']);
            $data['shipment_number'] = $data['shipment_number'] ?? $this->generateShipmentNumber();
            $data['created_by'] = $request->user()->id;
            $data['status'] = 'CREATED';
            $shipment = Shipment::create($data);
            foreach ($items as $item) $shipment->items()->create($item);

            TrackingEvent::create([
                'shipment_id' => $shipment->id,
                'created_by' => $request->user()->id,
                'event_type' => TrackingEvent::TYPE_STATUS,
                'status' => 'CREATED',
                'description' => 'Shipment berhasil dibuat dan menunggu proses booking.',
                'event_time' => now(),
            ]);
            $audit->record($request->user(), 'SHIPMENT_CREATED', 'Shipment', $shipment->id,
                'Shipment '.$shipment->shipment_number.' dibuat.', null, $shipment->only(['shipment_number', 'status', 'customer_id', 'carrier_id']));
            $notifications->send($shipment->customer, 'SHIPMENT_CREATED', 'Pengiriman baru dibuat',
                'Pengiriman '.$shipment->shipment_number.' telah dibuat.', $shipment);
            return $shipment;
        });

        return response()->json(['message' => 'Shipment berhasil dibuat.', 'data' => $shipment->load([
            'customer:id,name,email', 'carrier', 'origin', 'destination', 'items', 'trackingEvents',
        ])], 201);
    }

    public function show(Request $request, Shipment $shipment, EtaService $etaService)
    {
        $this->authorizeView($request, $shipment);
        $shipment->load([
            'customer:id,name,email', 'creator:id,name', 'carrier', 'origin', 'destination', 'items',
            'trackingEvents.location', 'trackingEvents.creator:id,name',
            'exceptions.reporter:id,name', 'exceptions.resolver:id,name',
        ]);
        $shipment->setAttribute('eta_assessment', $etaService->assess($shipment));
        $shipment->setAttribute('progress_percentage', $etaService->progressPercentage($shipment));
        $latest = $shipment->trackingEvents->sortBy([['event_time', 'desc'], ['id', 'desc']])->first();
        $shipment->setAttribute('current_location', $latest?->location ?? ($latest && $latest->latitude !== null ? [
            'latitude' => $latest->latitude, 'longitude' => $latest->longitude,
        ] : null));
        if ($request->user()->isCustomer()) $shipment->makeHidden(['notes', 'created_by']);
        return response()->json(['data' => $shipment]);
    }

    public function update(Request $request, Shipment $shipment, AuditService $audit)
    {
        if ($shipment->status === 'DELIVERED') {
            throw ValidationException::withMessages(['shipment' => ['Shipment DELIVERED tidak dapat diubah melalui alur normal.']]);
        }
        $data = $request->validate($this->updateRules());
        $departure = isset($data['expected_departure_at']) ? Carbon::parse($data['expected_departure_at']) : $shipment->expected_departure_at;
        $arrival = isset($data['expected_arrival_at']) ? Carbon::parse($data['expected_arrival_at']) : $shipment->expected_arrival_at;
        if ($departure && $arrival && $arrival->getTimestamp() < $departure->getTimestamp()) {
            throw ValidationException::withMessages(['expected_arrival_at' => ['Expected arrival harus sama atau setelah expected departure.']]);
        }
        $newOrigin = $data['origin_location_id'] ?? $shipment->origin_location_id;
        $newDestination = $data['destination_location_id'] ?? $shipment->destination_location_id;
        if ((int) $newOrigin === (int) $newDestination) {
            throw ValidationException::withMessages(['destination_location_id' => ['Lokasi asal dan tujuan harus berbeda.']]);
        }
        $old = $shipment->only(array_keys($data));
        $shipment->update($data);
        $audit->record($request->user(), 'SHIPMENT_UPDATED', 'Shipment', $shipment->id, 'Detail shipment diperbarui.', $old, $shipment->fresh()->only(array_keys($data)));
        return response()->json(['message' => 'Shipment diperbarui.', 'data' => $shipment->fresh(['customer', 'carrier', 'origin', 'destination', 'items'])]);
    }

    /** Soft archive only: tracking, exception and audit history remain retained. */
    public function destroy(Request $request, Shipment $shipment, AuditService $audit)
    {
        if ($shipment->status !== 'CREATED') {
            throw ValidationException::withMessages(['shipment' => ['Hanya shipment CREATED yang boleh diarsipkan.']]);
        }
        $audit->record($request->user(), 'SHIPMENT_ARCHIVED', 'Shipment', $shipment->id,
            'Shipment '.$shipment->shipment_number.' diarsipkan; riwayat tetap dipertahankan.');
        $shipment->delete();
        return response()->json(['message' => 'Shipment berhasil diarsipkan. Riwayat historis tetap disimpan.']);
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

    public function updateEta(Request $request, Shipment $shipment, AuditService $audit, NotificationService $notifications, EtaService $etaService)
    {
        if ($shipment->status === 'DELIVERED') {
            throw ValidationException::withMessages(['shipment' => ['ETA tidak dapat diubah setelah shipment DELIVERED.']]);
        }
        $data = $request->validate([
            'current_eta' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);
        if ($shipment->expected_departure_at && Carbon::parse($data['current_eta'])->getTimestamp() < $shipment->expected_departure_at->getTimestamp()) {
            throw ValidationException::withMessages(['current_eta' => ['Current ETA tidak boleh lebih awal dari expected departure.']]);
        }
        $old = ['current_eta' => $shipment->current_eta?->toIso8601String()];
        $shipment->update(['current_eta' => $data['current_eta']]);
        $fresh = $shipment->fresh();
        $assessment = $etaService->assess($fresh);
        $audit->record($request->user(), 'SHIPMENT_ETA_UPDATED', 'Shipment', $shipment->id,
            'Current ETA diperbarui. '.($data['reason'] ?? ''), $old, [
                'current_eta' => $fresh->current_eta?->toIso8601String(),
                'delay_severity' => $assessment['delay_severity'],
                'delay_seconds' => $assessment['delay_seconds'],
            ]);
        $notifications->send($fresh->customer, 'ETA_UPDATED', 'Estimasi tiba diperbarui',
            'Estimasi tiba '.$fresh->shipment_number.' telah diperbarui.', $fresh, [
                'current_eta' => $fresh->current_eta?->toIso8601String(),
                'delay_severity' => $assessment['delay_severity'],
                'reason' => $data['reason'] ?? null,
            ]);
        return response()->json(['data' => $fresh, 'eta_assessment' => $assessment]);
    }

    private function authorizeView(Request $request, Shipment $shipment): void
    {
        if ($request->user()->isCustomer() && (int) $shipment->customer_id !== (int) $request->user()->id) abort(404);
    }

    private function generateShipmentNumber(): string
    {
        do {
            $number = 'CT-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
        } while (Shipment::withTrashed()->where('shipment_number', $number)->exists());
        return $number;
    }

    private function createRules(): array
    {
        return [
            'shipment_number' => ['nullable', 'string', 'max:50', 'unique:shipments,shipment_number'],
            'customer_reference' => ['nullable', 'string', 'max:100'],
            'customer_id' => ['required', Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', User::ROLE_CUSTOMER)->where('is_active', true))],
            'carrier_id' => ['required', Rule::exists('carriers', 'id')->where(fn ($q) => $q->where('is_active', true))],
            'origin_location_id' => ['required', 'exists:locations,id', 'different:destination_location_id'],
            'destination_location_id' => ['required', 'exists:locations,id'],
            'cargo_type' => ['required', 'string', 'max:120'],
            'cargo_description' => ['nullable', 'string', 'max:3000'],
            'package_count' => ['required', 'integer', 'min:1'],
            'total_weight' => ['required', 'numeric', 'gt:0'],
            'weight_unit' => ['required', 'string', 'max:10'],
            'total_volume' => ['required', 'numeric', 'gt:0'],
            'volume_unit' => ['required', 'string', 'max:10'],
            'expected_departure_at' => ['required', 'date'],
            'expected_arrival_at' => ['required', 'date', 'after_or_equal:expected_departure_at'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'items' => ['nullable', 'array', 'max:50'],
            'items.*.item_name' => ['required_with:items', 'string', 'max:255'],
            'items.*.sku' => ['nullable', 'string', 'max:80'],
            'items.*.description' => ['nullable', 'string', 'max:1000'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.unit_weight' => ['nullable', 'numeric', 'min:0'],
            'items.*.weight_unit' => ['nullable', 'string', 'max:10'],
            'items.*.unit_volume' => ['nullable', 'numeric', 'min:0'],
            'items.*.volume_unit' => ['nullable', 'string', 'max:10'],
        ];
    }

    private function updateRules(): array
    {
        return [
            'customer_reference' => ['sometimes', 'nullable', 'string', 'max:100'],
            'carrier_id' => ['sometimes', 'exists:carriers,id'],
            'origin_location_id' => ['sometimes', 'exists:locations,id'],
            'destination_location_id' => ['sometimes', 'exists:locations,id'],
            'cargo_type' => ['sometimes', 'string', 'max:120'],
            'cargo_description' => ['sometimes', 'nullable', 'string', 'max:3000'],
            'package_count' => ['sometimes', 'integer', 'min:1'],
            'total_weight' => ['sometimes', 'numeric', 'gt:0'],
            'weight_unit' => ['sometimes', 'string', 'max:10'],
            'total_volume' => ['sometimes', 'numeric', 'gt:0'],
            'volume_unit' => ['sometimes', 'string', 'max:10'],
            'expected_departure_at' => ['sometimes', 'date'],
            'expected_arrival_at' => ['sometimes', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:3000'],
        ];
    }
}
