<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Services\TrackingService;
use Illuminate\Http\Request;

class TrackingEventController extends Controller
{
    public function index(Request $request, Shipment $shipment)
    {
        if ($request->user()->isCustomer() && (int) $shipment->customer_id !== (int) $request->user()->id) abort(404);
        $data = $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $events = $shipment->trackingEvents()->with(['location', 'creator:id,name'])
            ->orderBy('event_time')->orderBy('id')->paginate($data['per_page'] ?? 30)->withQueryString();
        return response()->json($events);
    }

    public function store(Request $request, Shipment $shipment, TrackingService $service)
    {
        $data = $request->validate([
            'description' => ['required', 'string', 'max:2000'],
            'event_time' => ['required', 'date', 'before_or_equal:now'],
            'location_id' => ['nullable', 'exists:locations,id'],
            'location_name' => ['nullable', 'string', 'max:255'],
            'location_address' => ['nullable', 'string', 'max:1000'],
            'location_country' => ['nullable', 'string', 'max:120'],
            'location_region' => ['nullable', 'string', 'max:120'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'metadata' => ['nullable', 'array'],
        ]);
        if (!empty($data['location_id']) && !empty($data['location_name'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'location_name' => 'Pilih lokasi tersimpan atau lokasi hasil pencarian, jangan keduanya.',
            ]);
        }
        if (!empty($data['location_name']) && (!array_key_exists('latitude', $data) || !array_key_exists('longitude', $data) || $data['latitude'] === null || $data['longitude'] === null)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'location_name' => 'Pilih hasil pencarian lokasi agar koordinat checkpoint tersimpan.',
            ]);
        }
        $event = $service->addLocationUpdate($shipment, $data, $request->user());
        return response()->json(['message' => 'Tracking event ditambahkan.', 'data' => $event], 201);
    }
}
