<?php

namespace App\Services;

use App\Models\Shipment;

class EtaService
{
    /**
     * Threshold contract: <= 0h ON_TIME, >0-6h LOW, >6-24h MEDIUM, >24h HIGH.
     * The exact +24-hour boundary is MEDIUM.
     */
    public function assess(Shipment $shipment): array
    {
        $expected = $shipment->expected_arrival_at;
        $eta = $shipment->current_eta ?? $expected;
        $seconds = $expected->diffInSeconds($eta, false);
        $delaySeconds = max(0, $seconds);

        $severity = match (true) {
            $seconds <= 0 => 'ON_TIME',
            $seconds <= 6 * 3600 => 'LOW',
            $seconds <= 24 * 3600 => 'MEDIUM',
            default => 'HIGH',
        };

        return [
            'expected_arrival_at' => $expected,
            'current_eta' => $eta,
            'delay_hours' => $delaySeconds > 0 ? (int) ceil($delaySeconds / 3600) : 0,
            'delay_severity' => $severity,
            'is_delayed' => $seconds > 0,
        ];
    }

    public function progressPercentage(Shipment $shipment): int
    {
        $index = array_search($shipment->status, Shipment::STATUSES, true);
        return $index === false ? 0 : (int) round(($index / (count(Shipment::STATUSES) - 1)) * 100);
    }
}
