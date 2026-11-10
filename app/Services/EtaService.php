<?php

namespace App\Services;

use App\Models\Shipment;

class EtaService
{
    /**
     * SRS/PPT threshold: <= 0h ON_TIME; >0-6h LOW; >6-24h MEDIUM; >24h HIGH.
     * Exactly +24 hours remains MEDIUM.
     */
    public function assess(Shipment $shipment): array
    {
        $expected = $shipment->expected_arrival_at;
        $eta = $shipment->current_eta ?? $expected;
        $delaySeconds = max(0, $eta->getTimestamp() - $expected->getTimestamp());
        $severity = match (true) {
            $delaySeconds <= 0 => 'ON_TIME',
            $delaySeconds <= 6 * 3600 => 'LOW',
            $delaySeconds <= 24 * 3600 => 'MEDIUM',
            default => 'HIGH',
        };

        $hours = intdiv($delaySeconds, 3600);
        $minutes = intdiv($delaySeconds % 3600, 60);

        return [
            'expected_arrival_at' => $expected,
            'current_eta' => $eta,
            'delay_seconds' => $delaySeconds,
            'delay_hours' => round($delaySeconds / 3600, 2),
            'delay_minutes' => intdiv($delaySeconds, 60),
            'delay_duration' => sprintf('%dh %02dm', $hours, $minutes),
            'delay_severity' => $severity,
            'is_delayed' => $delaySeconds > 0 && $shipment->status !== 'DELIVERED',
        ];
    }

    public function progressPercentage(Shipment $shipment): int
    {
        $index = array_search($shipment->status, Shipment::STATUSES, true);
        return $index === false ? 0 : (int) round(($index / (count(Shipment::STATUSES) - 1)) * 100);
    }
}
