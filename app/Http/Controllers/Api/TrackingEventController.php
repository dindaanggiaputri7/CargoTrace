<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Shipment;
use App\Services\TrackingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TrackingEventController extends Controller
{
    public function index(Request $request, Shipment $shipment)
    {
        if ($request->user()->isCustomer()) abort_unless((int) $shipment->customer_id === (int) $request->user()->id, 404);
        $events = $shipment->trackingEvents()->with(['location', 'creator:id,name'])->orderBy('event_time')->paginate(30);
        return response()->json($events);
    }

    public function store(Request $request, Shipment $shipment, TrackingService $service)
    {
        abort_if($shipment->status === 'DELIVERED', 422, 'Tracking event baru tidak dapat ditambahkan setelah DELIVERED.');
        $data = $request->validate([
            'event_type' => ['required', 'string', 'max:40'],
            'status' => ['nullable', Rule::in(Shipment::STATUSES)],
            'description' => ['required', 'string', 'max:2000'],
            'event_time' => ['required', 'date', 'before_or_equal:now'],
            'location_id' => ['nullable', 'exists:locations,id'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'metadata' => ['nullable', 'array'],
        ]);

        if (!empty($data['location_id'])) {
            $location = Location::findOrFail($data['location_id']);
            $data['latitude'] ??= $location->latitude;
            $data['longitude'] ??= $location->longitude;
        }
        $event = $service->add($shipment, $data, $request->user());
        return response()->json(['message' => 'Tracking event ditambahkan.', 'data' => $event], 201);
    }
}
