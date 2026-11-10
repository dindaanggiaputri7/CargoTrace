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

        return DB::transaction(function () use ($shipment, $newStatus, $actor, $description) {
            $locked = Shipment::query()->lockForUpdate()->findOrFail($shipment->id);
            $expectedNext = self::NEXT[$locked->status] ?? null;

            if ($expectedNext !== $newStatus) {
                throw ValidationException::withMessages([
                    'status' => ['Transisi tidak valid. Status '.$locked->status.' hanya dapat dilanjutkan ke '.($expectedNext ?? 'tidak ada status berikutnya').'.'],
                ]);
            }

            $oldStatus = $locked->status;
            $now = now();
            $updates = ['status' => $newStatus];
            match ($newStatus) {
                'BOOKED' => $updates['booked_at'] = $now,
                'PICKED_UP' => $updates['picked_up_at'] = $now,
                'IN_TRANSIT' => $updates['in_transit_at'] = $now,
                'DELIVERED' => $updates['delivered_at'] = $now,
                default => null,
            };
            $locked->update($updates);

            TrackingEvent::create([
                'shipment_id' => $locked->id,
                'created_by' => $actor->id,
                'event_type' => TrackingEvent::TYPE_STATUS,
                'status' => $newStatus,
                'description' => $description ?: 'Status pengiriman berubah dari '.$oldStatus.' menjadi '.$newStatus.'.',
                'event_time' => $now,
            ]);

            app(AuditService::class)->record($actor, 'SHIPMENT_STATUS_CHANGED', 'Shipment', $locked->id,
                'Status shipment '.$locked->shipment_number.' berubah.', ['status' => $oldStatus], ['status' => $newStatus]);

            app(NotificationService::class)->send($locked->customer, 'STATUS_UPDATE', 'Status pengiriman diperbarui',
                'Status '.$locked->shipment_number.' sekarang '.$newStatus.'.', $locked, ['status' => $newStatus]);

            return $locked->fresh(['customer', 'carrier', 'origin', 'destination']);
        });
    }
}
