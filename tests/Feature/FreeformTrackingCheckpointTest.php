<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\TrackingEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FreeformTrackingCheckpointTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::create([
            'name' => ucfirst($role).' Test',
            'email' => $role.'-'.uniqid().'@cargotrace.test',
            'password' => 'Password!123',
            'role' => $role,
            'is_active' => true,
        ]);
    }

    public function test_operator_can_search_and_save_a_checkpoint_not_registered_in_admin_locations(): void
    {
        config(['services.cargotrace_geocoding.endpoint' => 'https://nominatim.test']);
        Http::fake([
            'nominatim.test/search*' => Http::response([[
                'display_name' => 'Tanjung Pinang, Kepulauan Riau, Indonesia',
                'lat' => '0.9185500',
                'lon' => '104.4665000',
                'type' => 'city',
                'address' => [
                    'city' => 'Tanjung Pinang',
                    'state' => 'Kepulauan Riau',
                    'country' => 'Indonesia',
                    'country_code' => 'id',
                ],
            ]], 200),
        ]);

        $operator = $this->user('operator');
        $customer = $this->user('customer');
        $carrier = Carrier::create(['code' => 'FREE-'.uniqid(), 'name' => 'Free Checkpoint Carrier', 'is_active' => true]);
        $origin = Location::create(['code' => 'FREE-ORG-'.uniqid(), 'name' => 'Port of Singapore', 'city' => 'Singapore', 'country' => 'Singapore', 'type' => 'PORT', 'latitude' => 1.2644, 'longitude' => 103.8220]);
        $destination = Location::create(['code' => 'FREE-DST-'.uniqid(), 'name' => 'Jakarta International Port', 'city' => 'Jakarta', 'country' => 'Indonesia', 'type' => 'PORT', 'latitude' => -6.1040, 'longitude' => 106.8800]);
        $shipment = Shipment::create([
            'shipment_number' => 'CT-FREE-'.uniqid(),
            'customer_id' => $customer->id,
            'created_by' => $operator->id,
            'carrier_id' => $carrier->id,
            'origin_location_id' => $origin->id,
            'destination_location_id' => $destination->id,
            'cargo_type' => 'General Cargo',
            'package_count' => 1,
            'total_weight' => 100,
            'weight_unit' => 'KG',
            'total_volume' => 1,
            'volume_unit' => 'CBM',
            'expected_departure_at' => now()->subDay(),
            'expected_arrival_at' => now()->addDays(2),
            'status' => 'IN_TRANSIT',
        ]);
        TrackingEvent::create([
            'shipment_id' => $shipment->id,
            'created_by' => $operator->id,
            'event_type' => TrackingEvent::TYPE_STATUS,
            'status' => 'IN_TRANSIT',
            'description' => 'Initial transit event',
            'event_time' => now()->subHour(),
        ]);

        Sanctum::actingAs($operator);
        $this->getJson('/api/tracking/geocode/search?query=Tanjung%20Pinang')
            ->assertOk()
            ->assertJsonPath('data.0.city', 'Tanjung Pinang')
            ->assertJsonPath('data.0.latitude', 0.91855);

        $response = $this->postJson('/api/shipments/'.$shipment->id.'/tracking-events', [
            'description' => 'Carrier confirmed that the vessel reached Tanjung Pinang.',
            'event_time' => now()->toDateTimeString(),
            'location_name' => 'Tanjung Pinang, Kepulauan Riau, Indonesia',
            'location_address' => 'Tanjung Pinang, Kepulauan Riau, Indonesia',
            'location_country' => 'Indonesia',
            'location_region' => 'Kepulauan Riau',
            'latitude' => 0.91855,
            'longitude' => 104.4665,
        ])->assertCreated()->assertJsonPath('data.metadata.location_name', 'Tanjung Pinang, Kepulauan Riau, Indonesia');

        $eventId = $response->json('data.id');
        $event = TrackingEvent::findOrFail($eventId);
        $this->assertNull($event->location_id);
        $this->assertSame(2, Location::count(), 'A free-text checkpoint must not be inserted into Admin Locations.');
        $this->assertSame('GEOCODING', $event->metadata['location_source']);
    }

    public function test_customer_cannot_use_operator_checkpoint_geocoding_endpoint(): void
    {
        Sanctum::actingAs($this->user('customer'));
        $this->getJson('/api/tracking/geocode/search?query=Tanjung%20Pinang')->assertForbidden();
    }
}
