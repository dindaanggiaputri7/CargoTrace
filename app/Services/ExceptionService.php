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
                ['exception_id' => $exception->id, 'severity' => $exception->severity, 'status' => 'OPEN']);

            return $exception->load(['reporter:id,name', 'resolver:id,name']);
        });
    }

    public function updateStatus(
        ShipmentException $exception,
        string $newStatus,
        ?string $resolutionNotes,
        User $actor
    ): ShipmentException {
        $newStatus = strtoupper($newStatus);

        return DB::transaction(function () use ($exception, $newStatus, $resolutionNotes, $actor) {
            $locked = ShipmentException::query()->lockForUpdate()->findOrFail($exception->id);
            $allowed = ['OPEN' => 'INVESTIGATING', 'INVESTIGATING' => 'RESOLVED'];
            if (($allowed[$locked->status] ?? null) !== $newStatus) {
                throw ValidationException::withMessages(['status' => ['Alur exception hanya OPEN → INVESTIGATING → RESOLVED.']]);
            }
            if ($newStatus === 'RESOLVED' && blank($resolutionNotes)) {
                throw ValidationException::withMessages(['resolution_notes' => ['Catatan penyelesaian wajib diisi saat exception diselesaikan.']]);
            }

            $old = ['status' => $locked->status, 'resolved_at' => $locked->resolved_at?->toIso8601String()];
            $locked->update([
                'status' => $newStatus,
                'resolved_at' => $newStatus === 'RESOLVED' ? now() : null,
                'resolved_by' => $newStatus === 'RESOLVED' ? $actor->id : null,
                'resolution_notes' => $resolutionNotes ?? $locked->resolution_notes,
            ]);

            app(AuditService::class)->record($actor, 'EXCEPTION_STATUS_CHANGED', 'ShipmentException', $locked->id,
                'Status exception berubah.', $old, ['status' => $newStatus, 'resolution_notes' => $locked->resolution_notes]);

            $locked->load('shipment.customer');
            app(NotificationService::class)->send($locked->shipment?->customer, 'EXCEPTION_STATUS_UPDATE',
                'Penanganan kendala diperbarui',
                'Kendala '.$locked->exception_type.' pada '.$locked->shipment?->shipment_number.' sekarang '.$newStatus.'.',
                $locked->shipment, ['exception_id' => $locked->id, 'status' => $newStatus]);

            return $locked->fresh(['reporter:id,name', 'resolver:id,name', 'shipment']);
        });
    }
}
