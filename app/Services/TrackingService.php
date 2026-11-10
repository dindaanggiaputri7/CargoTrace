<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\TrackingEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class TrackingService
{
    public function add(Shipment $shipment, array $data, ?User $actor): TrackingEvent
    {
        $eventTime = Carbon::parse($data['event_time'] ?? now());
        $latestEvent = $shipment->trackingEvents()->latest('event_time')->first();

        if ($latestEvent && $eventTime->lt($latestEvent->event_time)) {
            throw ValidationException::withMessages([
                'event_time' => 'Waktu event tidak boleh lebih awal dari event tracking terakhir.',
            ]);
        }

        $event = TrackingEvent::create([
            'shipment_id' => $shipment->id,
            'location_id' => $data['location_id'] ?? null,
            'created_by' => $actor?->id,
            'event_type' => $data['event_type'],
            'status' => $data['status'] ?? null,
            'description' => $data['description'],
            'event_time' => $eventTime,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'metadata' => $data['metadata'] ?? null,
        ]);

        app(AuditService::class)->record($actor, 'TRACKING_EVENT_CREATED', 'TrackingEvent', $event->id,
            'Tracking event ditambahkan untuk '.$shipment->shipment_number);

        return $event->load(['location', 'creator:id,name']);
    }
}
