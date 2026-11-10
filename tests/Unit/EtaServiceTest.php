<?php

namespace Tests\Unit;

use App\Models\Shipment;
use App\Services\EtaService;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EtaServiceTest extends TestCase
{
    public static function etaCases(): array
    {
        return [
            'early arrival' => [-1, 'ON_TIME'],
            'exact arrival' => [0, 'ON_TIME'],
            'six hours' => [6, 'LOW'],
            'six hours plus one minute' => [6.0167, 'MEDIUM'],
            'exactly twenty four hours' => [24, 'MEDIUM'],
            'over twenty four hours' => [24.0167, 'HIGH'],
        ];
    }

    #[DataProvider('etaCases')]
    public function test_eta_thresholds_follow_the_cargotrace_contract(float $hours, string $expectedSeverity): void
    {
        $expected = Carbon::parse('2026-11-10 12:00:00');
        $eta = $expected->copy()->addSeconds((int) round($hours * 3600));
        $shipment = new Shipment([
            'status' => 'IN_TRANSIT',
            'expected_arrival_at' => $expected,
            'current_eta' => $eta,
        ]);

        $assessment = app(EtaService::class)->assess($shipment);

        $this->assertSame($expectedSeverity, $assessment['delay_severity']);
        $this->assertSame($hours > 0, $assessment['is_delayed']);
    }

    public function test_delivered_shipment_is_not_reported_as_active_delay(): void
    {
        $expected = Carbon::parse('2026-11-10 12:00:00');
        $shipment = new Shipment([
            'status' => 'DELIVERED',
            'expected_arrival_at' => $expected,
            'current_eta' => $expected->copy()->addDay(),
        ]);

        $this->assertFalse(app(EtaService::class)->assess($shipment)['is_delayed']);
    }
}
