<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\TrackingEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShipmentLifecycleService
{
    private const NEXT = [
        'CREATED' => 'BOOKED',
        'BOOKED' => 'PICKED_UP',
        'PICKED_UP' => 'IN_TRANSIT',
        'IN_TRANSIT' => 'DELIVERED',
    ];

    public function transition(Shipment $shipment, string $newStatus, User $actor, ?string $description = null): Shipment
    {
        $newStatus = strtoupper($newStatus);
        $expectedNext = self::NEXT[$shipment->status] ?? null;

        if ($expectedNext !== $newStatus) {
            throw ValidationException::withMessages([
                'status' => ['Transisi tidak valid. Status '.$shipment->status.' hanya dapat dilanjutkan ke '.($expectedNext ?? 'tidak ada status berikutnya').'.'],
            ]);
        }

        return DB::transaction(function () use ($shipment, $newStatus, $actor, $description) {
            $oldStatus = $shipment->status;
            $now = now();
            $updates = ['status' => $newStatus];

            if ($newStatus === 'BOOKED') $updates['booked_at'] = $now;
            if ($newStatus === 'PICKED_UP') $updates['picked_up_at'] = $now;
            if ($newStatus === 'DELIVERED') $updates['delivered_at'] = $now;

            $shipment->update($updates);

            TrackingEvent::create([
                'shipment_id' => $shipment->id,
                'created_by' => $actor->id,
                'event_type' => 'STATUS_UPDATE',
                'status' => $newStatus,
                'description' => $description ?: 'Status pengiriman berubah dari '.$oldStatus.' menjadi '.$newStatus.'.',
                'event_time' => $now,
            ]);

            app(AuditService::class)->record($actor, 'SHIPMENT_STATUS_CHANGED', 'Shipment', $shipment->id,
                'Status shipment '.$shipment->shipment_number.' berubah.', ['status' => $oldStatus], ['status' => $newStatus]);

            app(NotificationService::class)->send($shipment->customer, 'STATUS_UPDATE', 'Status pengiriman diperbarui',
                'Status '.$shipment->shipment_number.' sekarang '.$newStatus.'.', $shipment, ['status' => $newStatus]);

            return $shipment->fresh();
        });
    }
}
