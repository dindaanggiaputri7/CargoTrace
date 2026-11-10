<?php

namespace Database\Seeders;

use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\TrackingEvent;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class CargoTraceDemoSeeder extends Seeder
{
    public function run(): void
    {
        $password = 'CargoTraceDemo!2026';

        $admin = User::updateOrCreate(['email' => 'admin@cargotrace.test'], [
            'name' => 'CargoTrace Administrator', 'password' => $password,
            'role' => User::ROLE_ADMIN, 'is_active' => true,
        ]);
        $operator = User::updateOrCreate(['email' => 'operator@cargotrace.test'], [
            'name' => 'CargoTrace Logistics Operator', 'password' => $password,
            'role' => User::ROLE_OPERATOR, 'is_active' => true,
        ]);
        $aster = User::updateOrCreate(['email' => 'customer@cargotrace.test'], [
            'name' => 'Aster Retail', 'password' => $password,
            'role' => User::ROLE_CUSTOMER, 'is_active' => true,
        ]);
        $northstar = User::updateOrCreate(['email' => 'northstar@cargotrace.test'], [
            'name' => 'Northstar Labs', 'password' => $password,
            'role' => User::ROLE_CUSTOMER, 'is_active' => true,
        ]);
        $vertex = User::updateOrCreate(['email' => 'vertex@cargotrace.test'], [
            'name' => 'Vertex Motors', 'password' => $password,
            'role' => User::ROLE_CUSTOMER, 'is_active' => true,
        ]);

        $carriers = collect([
            ['code' => 'MAERSK', 'name' => 'Maersk', 'transport_mode' => 'Ocean', 'website' => 'https://www.maersk.com'],
            ['code' => 'MSC', 'name' => 'MSC', 'transport_mode' => 'Ocean', 'website' => 'https://www.msc.com'],
            ['code' => 'DHL', 'name' => 'DHL Freight', 'transport_mode' => 'Road', 'website' => 'https://www.dhl.com'],
            ['code' => 'HAPAG', 'name' => 'Hapag-Lloyd', 'transport_mode' => 'Ocean', 'website' => 'https://www.hapag-lloyd.com'],
        ])->mapWithKeys(fn ($row) => [$row['code'] => Carrier::updateOrCreate(['code' => $row['code']], $row + ['is_active' => true])]);

        $locations = collect([
            ['code' => 'DEHAM', 'name' => 'Hamburg', 'city' => 'Hamburg', 'country' => 'Germany', 'country_code' => 'DE', 'type' => 'PORT', 'latitude' => 53.5511, 'longitude' => 9.9937],
            ['code' => 'USCHI', 'name' => 'Chicago', 'city' => 'Chicago', 'country' => 'United States', 'country_code' => 'US', 'type' => 'WAREHOUSE', 'latitude' => 41.8781, 'longitude' => -87.6298],
            ['code' => 'FRLYS', 'name' => 'Lyon', 'city' => 'Lyon', 'country' => 'France', 'country_code' => 'FR', 'type' => 'WAREHOUSE', 'latitude' => 45.7640, 'longitude' => 4.8357],
            ['code' => 'DEBER', 'name' => 'Berlin', 'city' => 'Berlin', 'country' => 'Germany', 'country_code' => 'DE', 'type' => 'WAREHOUSE', 'latitude' => 52.5200, 'longitude' => 13.4050],
            ['code' => 'CNSHA', 'name' => 'Shanghai', 'city' => 'Shanghai', 'country' => 'China', 'country_code' => 'CN', 'type' => 'PORT', 'latitude' => 31.2304, 'longitude' => 121.4737],
            ['code' => 'USLAX', 'name' => 'Los Angeles', 'city' => 'Los Angeles', 'country' => 'United States', 'country_code' => 'US', 'type' => 'PORT', 'latitude' => 34.0522, 'longitude' => -118.2437],
            ['code' => 'SGSIN', 'name' => 'Singapore', 'city' => 'Singapore', 'country' => 'Singapore', 'country_code' => 'SG', 'type' => 'PORT', 'latitude' => 1.2644, 'longitude' => 103.8220],
            ['code' => 'NLRTM', 'name' => 'Rotterdam', 'city' => 'Rotterdam', 'country' => 'Netherlands', 'country_code' => 'NL', 'type' => 'PORT', 'latitude' => 51.9244, 'longitude' => 4.4777],
            ['code' => 'BEANR', 'name' => 'Antwerp', 'city' => 'Antwerp', 'country' => 'Belgium', 'country_code' => 'BE', 'type' => 'PORT', 'latitude' => 51.2194, 'longitude' => 4.4025],
            ['code' => 'IDJKT', 'name' => 'Jakarta International Port', 'city' => 'Jakarta', 'country' => 'Indonesia', 'country_code' => 'ID', 'type' => 'PORT', 'latitude' => -6.1040, 'longitude' => 106.8830],
        ])->mapWithKeys(fn ($row) => [$row['code'] => Location::updateOrCreate(['code' => $row['code']], $row)]);

        // Stable synthetic snapshot for dashboard/demo. Re-running the seeder does not duplicate shipments.
        $specs = [
            ['CT-1048', $aster, 'MAERSK', 'DEHAM', 'USCHI', 'IN_TRANSIT', 24, 'General merchandise'],
            ['CT-1049', $aster, 'MAERSK', 'IDJKT', 'SGSIN', 'BOOKED', 0, 'Consumer electronics'],
            ['CT-1050', $aster, 'DHL', 'FRLYS', 'DEBER', 'PICKED_UP', 0, 'Retail supplies'],
            ['CT-1051', $aster, 'MSC', 'SGSIN', 'NLRTM', 'DELIVERED', 0, 'Apparel'],
            ['CT-1046', $northstar, 'MSC', 'CNSHA', 'USLAX', 'IN_TRANSIT', 12, 'Automotive components'],
            ['CT-1045', $northstar, 'MSC', 'SGSIN', 'NLRTM', 'IN_TRANSIT', 0, 'Laboratory equipment'],
            ['CT-1044', $northstar, 'HAPAG', 'DEHAM', 'BEANR', 'BOOKED', 0, 'Industrial materials'],
            ['CT-1043', $northstar, 'MAERSK', 'NLRTM', 'DEBER', 'DELIVERED', 0, 'Research supplies'],
            ['CT-1042', $vertex, 'DHL', 'CNSHA', 'USLAX', 'IN_TRANSIT', 30, 'Vehicle parts'],
            ['CT-1041', $vertex, 'DHL', 'FRLYS', 'DEBER', 'IN_TRANSIT', 0, 'Spare parts'],
            ['CT-1040', $vertex, 'HAPAG', 'SGSIN', 'NLRTM', 'DELIVERED', 0, 'Production tooling'],
            ['CT-1039', $vertex, 'MAERSK', 'IDJKT', 'SGSIN', 'CREATED', 0, 'Packaged goods'],
        ];

        foreach ($specs as [$number, $customer, $carrierCode, $originCode, $destinationCode, $status, $delayHours, $cargoType]) {
            $departure = now()->subDays(5);
            $delivered = $status === 'DELIVERED';
            $expectedArrival = $delivered ? now()->subDay() : now()->addDay();
            $currentEta = $delayHours > 0 ? $expectedArrival->copy()->addHours($delayHours) : null;

            $shipment = Shipment::firstOrCreate(['shipment_number' => $number], [
                'customer_reference' => 'REF-'.$number,
                'customer_id' => $customer->id,
                'created_by' => $operator->id,
                'carrier_id' => $carriers[$carrierCode]->id,
                'origin_location_id' => $locations[$originCode]->id,
                'destination_location_id' => $locations[$destinationCode]->id,
                'cargo_type' => $cargoType,
                'cargo_description' => $cargoType,
                'package_count' => 12,
                'total_weight' => 1250.500,
                'weight_unit' => 'KG',
                'total_volume' => 8.250,
                'volume_unit' => 'CBM',
                'expected_departure_at' => $departure,
                'expected_arrival_at' => $expectedArrival,
                'current_eta' => $currentEta,
                'status' => $status,
                'booked_at' => in_array($status, ['BOOKED', 'PICKED_UP', 'IN_TRANSIT', 'DELIVERED'], true) ? $departure->copy()->addHours(1) : null,
                'picked_up_at' => in_array($status, ['PICKED_UP', 'IN_TRANSIT', 'DELIVERED'], true) ? $departure->copy()->addDay() : null,
                'in_transit_at' => in_array($status, ['IN_TRANSIT', 'DELIVERED'], true) ? $departure->copy()->addDays(2) : null,
                'delivered_at' => $delivered ? now()->subHours(2) : null,
                'notes' => 'Synthetic demonstration record. No real shipment data.',
            ]);

            if (!$shipment->items()->exists()) {
                $shipment->items()->create([
                    'item_name' => $cargoType, 'sku' => 'SKU-'.substr($number, -4),
                    'description' => 'Demo shipment line item.', 'quantity' => 12,
                    'unit_weight' => 104.208, 'weight_unit' => 'KG', 'unit_volume' => 0.687, 'volume_unit' => 'CBM',
                ]);
            }

            if (!$shipment->trackingEvents()->exists()) {
                $timeline = [
                    ['CREATED', $departure->copy()->subHours(2), $locations[$originCode]],
                ];
                if (in_array($status, ['BOOKED', 'PICKED_UP', 'IN_TRANSIT', 'DELIVERED'], true)) $timeline[] = ['BOOKED', $departure->copy()->addHours(1), $locations[$originCode]];
                if (in_array($status, ['PICKED_UP', 'IN_TRANSIT', 'DELIVERED'], true)) $timeline[] = ['PICKED_UP', $departure->copy()->addDay(), $locations[$originCode]];
                if (in_array($status, ['IN_TRANSIT', 'DELIVERED'], true)) $timeline[] = ['IN_TRANSIT', $departure->copy()->addDays(2), $locations[$destinationCode]];
                if ($status === 'DELIVERED') $timeline[] = ['DELIVERED', now()->subHours(2), $locations[$destinationCode]];

                foreach ($timeline as [$eventStatus, $eventTime, $location]) {
                    TrackingEvent::create([
                        'shipment_id' => $shipment->id, 'location_id' => $location->id,
                        'created_by' => $operator->id, 'event_type' => TrackingEvent::TYPE_STATUS,
                        'status' => $eventStatus, 'description' => 'Demo event: '.$eventStatus,
                        'event_time' => $eventTime, 'latitude' => $location->latitude,
                        'longitude' => $location->longitude,
                    ]);
                }
            }
        }

        $exceptions = [
            ['CT-1048', 'PORT_CONGESTION', 'HIGH', 'Port congestion', 'Vessel waiting time at destination port.', 'INVESTIGATING'],
            ['CT-1046', 'CUSTOMS_HOLD', 'MEDIUM', 'Customs hold', 'Shipment is waiting for customs clearance.', 'OPEN'],
            ['CT-1045', 'WEATHER_DELAY', 'LOW', 'Weather disruption', 'Weather conditions affected the route schedule.', 'OPEN'],
        ];
        foreach ($exceptions as [$shipmentNumber, $type, $severity, $title, $description, $status]) {
            $shipment = Shipment::where('shipment_number', $shipmentNumber)->first();
            if (!$shipment) continue;
            ShipmentException::firstOrCreate([
                'shipment_id' => $shipment->id,
                'exception_type' => $type,
            ], [
                'reported_by' => $operator->id,
                'severity' => $severity,
                'status' => $status,
                'title' => $title,
                'description' => $description,
                'detected_at' => now()->subHours(8),
            ]);
        }
    }
}
