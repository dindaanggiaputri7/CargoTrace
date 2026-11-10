<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Carrier;
use App\Models\Location;
use App\Models\Notification;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\TrackingEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CargoTraceApiTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = User::ROLE_CUSTOMER): User
    {
        return User::create([
            'name' => ucfirst($role).' Test',
            'email' => $role.'-'.uniqid().'@cargotrace.test',
            'password' => 'Password!123',
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function masterData(): array
    {
        // Reuse the same test master records when a test creates multiple shipments.
        // Carrier and location codes are unique, so creating them repeatedly causes
        // a UNIQUE constraint violation in tests that create more than one shipment.
        $carrier = Carrier::firstOrCreate(
            ['code' => 'TST'],
            ['name' => 'Test Carrier', 'is_active' => true]
        );
        $origin = Location::firstOrCreate(
            ['code' => 'ORG'],
            ['name' => 'Origin', 'country' => 'Indonesia', 'type' => 'PORT', 'latitude' => -6.1, 'longitude' => 106.8]
        );
        $destination = Location::firstOrCreate(
            ['code' => 'DST'],
            ['name' => 'Destination', 'country' => 'Singapore', 'type' => 'PORT', 'latitude' => 1.2, 'longitude' => 103.8]
        );
        return [$carrier, $origin, $destination];
    }

    private function shipment(User $customer, User $operator, string $status = 'CREATED'): Shipment
    {
        [$carrier, $origin, $destination] = $this->masterData();
        $departure = now()->subDay();
        $shipment = Shipment::create([
            'shipment_number' => 'CT-'.strtoupper(uniqid()),
            'customer_id' => $customer->id,
            'created_by' => $operator->id,
            'carrier_id' => $carrier->id,
            'origin_location_id' => $origin->id,
            'destination_location_id' => $destination->id,
            'cargo_type' => 'General cargo',
            'package_count' => 2,
            'total_weight' => 10,
            'weight_unit' => 'KG',
            'total_volume' => 1,
            'volume_unit' => 'CBM',
            'expected_departure_at' => $departure,
            'expected_arrival_at' => now()->addDays(3),
            'status' => $status,
        ]);
        TrackingEvent::create([
            'shipment_id' => $shipment->id,
            'created_by' => $operator->id,
            'event_type' => TrackingEvent::TYPE_STATUS,
            'status' => $status,
            'description' => 'Initial event',
            'event_time' => now()->subMinutes(30),
        ]);
        return $shipment;
    }

    public function test_public_registration_always_creates_customer_role(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Public User',
            'email' => 'public-user@cargotrace.test',
            'password' => 'Password!123',
            'password_confirmation' => 'Password!123',
            'role' => User::ROLE_ADMIN,
        ]);

        $response->assertCreated()->assertJsonPath('user.role', User::ROLE_CUSTOMER);
        $this->assertDatabaseHas('users', ['email' => 'public-user@cargotrace.test', 'role' => User::ROLE_CUSTOMER]);
    }

    public function test_customer_cannot_create_carrier(): void
    {
        Sanctum::actingAs($this->user());
        $this->postJson('/api/carriers', ['code' => 'X', 'name' => 'Forbidden'])->assertForbidden();
    }

    public function test_customer_can_only_list_and_view_owned_shipments(): void
    {
        $customer = $this->user();
        $otherCustomer = $this->user();
        $operator = $this->user(User::ROLE_OPERATOR);
        $mine = $this->shipment($customer, $operator);
        $theirs = $this->shipment($otherCustomer, $operator);
        Sanctum::actingAs($customer);

        $this->getJson('/api/shipments')->assertOk()->assertJsonFragment(['shipment_number' => $mine->shipment_number])
            ->assertJsonMissing(['shipment_number' => $theirs->shipment_number]);
        $this->getJson('/api/shipments/'.$theirs->id)->assertNotFound();
        $this->getJson('/api/shipments/'.$mine->id)->assertOk();
    }

    public function test_operator_create_shipment_creates_initial_event_and_audit_log(): void
    {
        $operator = $this->user(User::ROLE_OPERATOR);
        $customer = $this->user();
        [$carrier, $origin, $destination] = $this->masterData();
        Sanctum::actingAs($operator);

        $response = $this->postJson('/api/shipments', [
            'customer_id' => $customer->id,
            'carrier_id' => $carrier->id,
            'origin_location_id' => $origin->id,
            'destination_location_id' => $destination->id,
            'cargo_type' => 'General cargo',
            'package_count' => 3,
            'total_weight' => 80.5,
            'weight_unit' => 'KG',
            'total_volume' => 2.4,
            'volume_unit' => 'CBM',
            'expected_departure_at' => now()->addHours(1)->toDateTimeString(),
            'expected_arrival_at' => now()->addDays(4)->toDateTimeString(),
            'items' => [['item_name' => 'Box', 'quantity' => 3]],
        ]);

        $response->assertCreated()->assertJsonPath('data.status', 'CREATED');
        $shipmentId = $response->json('data.id');
        $this->assertDatabaseHas('tracking_events', ['shipment_id' => $shipmentId, 'status' => 'CREATED']);
        $this->assertDatabaseHas('audit_logs', ['entity_type' => 'Shipment', 'entity_id' => $shipmentId, 'action' => 'SHIPMENT_CREATED']);
        $this->assertDatabaseHas('notifications', ['user_id' => $customer->id, 'type' => 'SHIPMENT_CREATED']);
    }

    public function test_lifecycle_rejects_skipped_status_and_records_valid_transition(): void
    {
        $customer = $this->user();
        $operator = $this->user(User::ROLE_OPERATOR);
        $shipment = $this->shipment($customer, $operator);
        Sanctum::actingAs($operator);

        $this->patchJson('/api/shipments/'.$shipment->id.'/status', ['status' => 'IN_TRANSIT'])->assertUnprocessable();
        $this->assertSame('CREATED', $shipment->fresh()->status);

        $this->patchJson('/api/shipments/'.$shipment->id.'/status', ['status' => 'BOOKED'])->assertOk();
        $this->assertSame('BOOKED', $shipment->fresh()->status);
        $this->assertDatabaseHas('tracking_events', ['shipment_id' => $shipment->id, 'status' => 'BOOKED']);
    }

    public function test_tracking_events_are_append_only_and_chronological(): void
    {
        $customer = $this->user();
        $operator = $this->user(User::ROLE_OPERATOR);
        $shipment = $this->shipment($customer, $operator, 'IN_TRANSIT');
        Sanctum::actingAs($operator);

        $this->postJson('/api/shipments/'.$shipment->id.'/tracking-events', [
            'description' => 'Location checkpoint',
            'event_time' => now()->toDateTimeString(),
            'location_id' => $shipment->origin_location_id,
        ])->assertCreated();

        $this->postJson('/api/shipments/'.$shipment->id.'/tracking-events', [
            'description' => 'Backdated checkpoint',
            'event_time' => now()->subDays(2)->toDateTimeString(),
        ])->assertUnprocessable();

        $this->assertSame(2, TrackingEvent::where('shipment_id', $shipment->id)->count());
    }

    public function test_exception_status_transition_notifies_customer_and_requires_resolution_note(): void
    {
        $customer = $this->user();
        $operator = $this->user(User::ROLE_OPERATOR);
        $shipment = $this->shipment($customer, $operator, 'IN_TRANSIT');
        Sanctum::actingAs($operator);

        $created = $this->postJson('/api/shipments/'.$shipment->id.'/exceptions', [
            'exception_type' => 'CUSTOMS_HOLD', 'severity' => 'MEDIUM',
            'title' => 'Customs review', 'description' => 'Waiting for documents.',
        ])->assertCreated();
        $exceptionId = $created->json('data.id');

        $this->patchJson('/api/exceptions/'.$exceptionId.'/status', ['status' => 'INVESTIGATING'])->assertOk();
        $this->patchJson('/api/exceptions/'.$exceptionId.'/status', ['status' => 'RESOLVED'])->assertUnprocessable();
        $this->patchJson('/api/exceptions/'.$exceptionId.'/status', [
            'status' => 'RESOLVED', 'resolution_notes' => 'Customs release document received.',
        ])->assertOk();

        $this->assertDatabaseHas('shipment_exceptions', ['id' => $exceptionId, 'status' => 'RESOLVED', 'resolved_by' => $operator->id]);
        $this->assertSame(3, Notification::where('user_id', $customer->id)->count());
    }

    public function test_archiving_shipment_keeps_tracking_and_audit_history(): void
    {
        $customer = $this->user();
        $operator = $this->user(User::ROLE_OPERATOR);
        $shipment = $this->shipment($customer, $operator, 'CREATED');
        Sanctum::actingAs($operator);

        $this->deleteJson('/api/shipments/'.$shipment->id)->assertOk();
        $this->assertSoftDeleted('shipments', ['id' => $shipment->id]);
        $this->assertDatabaseHas('tracking_events', ['shipment_id' => $shipment->id]);
        $this->assertDatabaseHas('audit_logs', ['entity_type' => 'Shipment', 'entity_id' => $shipment->id, 'action' => 'SHIPMENT_ARCHIVED']);
    }
    public function test_admin_can_read_shipments_but_cannot_change_operational_status(): void
    {
        $customer = $this->user();
        $operator = $this->user(User::ROLE_OPERATOR);
        $admin = $this->user(User::ROLE_ADMIN);
        $shipment = $this->shipment($customer, $operator);
        Sanctum::actingAs($admin);

        $this->getJson('/api/shipments')->assertOk()->assertJsonFragment(['shipment_number' => $shipment->shipment_number]);
        $this->patchJson('/api/shipments/'.$shipment->id.'/status', ['status' => 'BOOKED'])->assertForbidden();
    }

    public function test_delivered_is_terminal_in_normal_lifecycle(): void
    {
        $customer = $this->user();
        $operator = $this->user(User::ROLE_OPERATOR);
        $shipment = $this->shipment($customer, $operator);
        Sanctum::actingAs($operator);

        foreach (['BOOKED', 'PICKED_UP', 'IN_TRANSIT', 'DELIVERED'] as $status) {
            $this->patchJson('/api/shipments/'.$shipment->id.'/status', ['status' => $status])->assertOk();
        }
        $this->patchJson('/api/shipments/'.$shipment->id.'/status', ['status' => 'IN_TRANSIT'])->assertUnprocessable();
        $this->assertSame('DELIVERED', $shipment->fresh()->status);
    }

    public function test_geocoding_endpoint_is_admin_only(): void
    {
        Sanctum::actingAs($this->user());
        $this->getJson('/api/locations/geocode/search?query=Singapore')->assertForbidden();
    }

    public function test_admin_geocoding_uses_http_client_and_returns_coordinates(): void
    {
        Http::fake([
            'https://nominatim.openstreetmap.org/search*' => Http::response([
                ['display_name' => 'Singapore', 'lat' => '1.2644', 'lon' => '103.8220', 'type' => 'port', 'address' => ['country_code' => 'sg']],
            ], 200),
        ]);
        Sanctum::actingAs($this->user(User::ROLE_ADMIN));

        $this->getJson('/api/locations/geocode/search?query=Singapore')
            ->assertOk()
            ->assertJsonPath('data.0.latitude', 1.2644)
            ->assertJsonPath('data.0.longitude', 103.822);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'nominatim.openstreetmap.org/search')
            && $request->hasHeader('User-Agent'));
    }

    public function test_inactive_account_is_denied_login(): void
    {
        $user = $this->user();
        $user->update(['is_active' => false]);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'Password!123',
        ])->assertForbidden();
    }

}
