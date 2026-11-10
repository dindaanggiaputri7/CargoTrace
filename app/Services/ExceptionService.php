<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExceptionService
{
    public function create(Shipment $shipment, array $data, User $actor): ShipmentException
    {
        return DB::transaction(function () use ($shipment, $data, $actor) {
            $exception = ShipmentException::create([
                'shipment_id' => $shipment->id,
                'reported_by' => $actor->id,
                'exception_type' => $data['exception_type'],
                'severity' => $data['severity'],
                'status' => 'OPEN',
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'detected_at' => $data['detected_at'] ?? now(),
            ]);

            app(AuditService::class)->record($actor, 'EXCEPTION_CREATED', 'ShipmentException', $exception->id,
                'Exception '.$exception->exception_type.' dibuat untuk '.$shipment->shipment_number);
            app(NotificationService::class)->send($shipment->customer, 'SHIPMENT_EXCEPTION', 'Kendala pengiriman dilaporkan',
                'Ada kendala '.$exception->exception_type.' pada '.$shipment->shipment_number.'.', $shipment,
                ['exception_id' => $exception->id, 'severity' => $exception->severity]);

            return $exception;
        });
    }

    public function updateStatus(ShipmentException $exception, string $newStatus, ?string $resolutionNotes, User $actor): ShipmentException
    {
        $newStatus = strtoupper($newStatus);
        $allowed = ['OPEN' => 'INVESTIGATING', 'INVESTIGATING' => 'RESOLVED'];
        if (($allowed[$exception->status] ?? null) !== $newStatus) {
            throw ValidationException::withMessages(['status' => 'Alur exception hanya OPEN → INVESTIGATING → RESOLVED.']);
        }

        $oldStatus = $exception->status;
        $exception->update([
            'status' => $newStatus,
            'resolved_at' => $newStatus === 'RESOLVED' ? now() : null,
            'resolution_notes' => $resolutionNotes ?? $exception->resolution_notes,
        ]);

        app(AuditService::class)->record($actor, 'EXCEPTION_STATUS_CHANGED', 'ShipmentException', $exception->id,
            'Status exception berubah.', ['status' => $oldStatus], ['status' => $newStatus]);

        return $exception->fresh();
    }
}
