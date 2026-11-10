<?php

namespace Database\Seeders;

use App\Models\Carrier;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CargoTraceDemoSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(['email' => 'admin@cargotrace.test'], [
            'name' => 'CargoTrace Administrator',
            'password' => Hash::make('CargoTraceDemo!2026'),
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        User::updateOrCreate(['email' => 'operator@cargotrace.test'], [
            'name' => 'CargoTrace Logistics Operator',
            'password' => Hash::make('CargoTraceDemo!2026'),
            'role' => User::ROLE_OPERATOR,
            'is_active' => true,
        ]);

        User::updateOrCreate(['email' => 'customer@cargotrace.test'], [
            'name' => 'Aster Retail',
            'password' => Hash::make('CargoTraceDemo!2026'),
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
        ]);

        Carrier::updateOrCreate(['code' => 'DHL'], [
            'name' => 'DHL Global Forwarding',
            'website' => 'https://www.dhl.com',
            'support_email' => null,
            'support_phone' => null,
            'is_active' => true,
        ]);

        Carrier::updateOrCreate(['code' => 'MAERSK'], [
            'name' => 'Maersk',
            'website' => 'https://www.maersk.com',
            'support_email' => null,
            'support_phone' => null,
            'is_active' => true,
        ]);

        Location::updateOrCreate(['code' => 'ID-JKT'], [
            'name' => 'Jakarta International Port', 'type' => 'PORT', 'city' => 'Jakarta',
            'country' => 'Indonesia', 'country_code' => 'ID',
            'latitude' => -6.104, 'longitude' => 106.883,
        ]);
        Location::updateOrCreate(['code' => 'SG-SIN'], [
            'name' => 'Port of Singapore', 'type' => 'PORT', 'city' => 'Singapore',
            'country' => 'Singapore', 'country_code' => 'SG',
            'latitude' => 1.264, 'longitude' => 103.840,
        ]);
    }
}
