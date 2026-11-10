<?php

namespace App\Services;

use App\Models\Location;
use App\Models\Shipment;
use App\Models\TrackingEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TrackingService
{
    /** Add a location-only event. Status changes must use ShipmentLifecycleService. */
    public function addLocationUpdate(Shipment $shipment, array $data, User $actor): TrackingEvent
    {
        return DB::transaction(function () use ($shipment, $data, $actor) {
            $locked = Shipment::query()->lockForUpdate()->findOrFail($shipment->id);
            if ($locked->status === 'DELIVERED') {
                throw ValidationException::withMessages(['shipment' => 'Shipment DELIVERED tidak menerima tracking event baru.']);
            }

            $eventTime = Carbon::parse($data['event_time'] ?? now());
            if ($eventTime->isFuture()) {
                throw ValidationException::withMessages(['event_time' => 'Waktu event tidak boleh di masa depan.']);
            }

            $latest = $locked->trackingEvents()->latest('event_time')->latest('id')->first();
            if ($latest && $eventTime->lt($latest->event_time)) {
                throw ValidationException::withMessages(['event_time' => 'Waktu event tidak boleh lebih awal dari event tracking terakhir.']);
            }

            if (isset($data['location_id'])) {
                $location = Location::findOrFail($data['location_id']);
                $data['latitude'] ??= $location->latitude;
                $data['longitude'] ??= $location->longitude;
            }

            if ((isset($data['latitude']) xor isset($data['longitude']))) {
                throw ValidationException::withMessages(['coordinates' => 'Latitude dan longitude harus diisi berpasangan.']);
            }

            $event = TrackingEvent::create([
                'shipment_id' => $locked->id,
                'location_id' => $data['location_id'] ?? null,
                'created_by' => $actor->id,
                'event_type' => TrackingEvent::TYPE_LOCATION,
                'status' => $locked->status,
                'description' => $data['description'],
                'event_time' => $eventTime,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'metadata' => $data['metadata'] ?? null,
            ]);

            app(AuditService::class)->record($actor, 'TRACKING_EVENT_CREATED', 'TrackingEvent', $event->id,
                'Lokasi tracking ditambahkan untuk '.$locked->shipment_number);

            app(NotificationService::class)->send($locked->customer, 'TRACKING_UPDATE', 'Lokasi pengiriman diperbarui',
                'Informasi lokasi '.$locked->shipment_number.' diperbarui.', $locked,
                ['tracking_event_id' => $event->id, 'status' => $locked->status]);

            return $event->load(['location', 'creator:id,name']);
        });
    }
}
