<?php

namespace Tests\Feature;

use App\Models\BookingPayment;
use App\Models\BookingRequest;
use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingWorkflowTest extends TestCase
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

    private function masterData(): array
    {
        $carrier = Carrier::firstOrCreate(['code' => 'BKG'], ['name' => 'Booking Carrier', 'is_active' => true]);
        $origin = Location::firstOrCreate(['code' => 'BKG-ORG'], ['name' => 'Jakarta Port', 'city' => 'Jakarta', 'country' => 'Indonesia', 'type' => 'PORT']);
        $destination = Location::firstOrCreate(['code' => 'BKG-DST'], ['name' => 'Singapore Port', 'city' => 'Singapore', 'country' => 'Singapore', 'type' => 'PORT']);
        return [$carrier, $origin, $destination];
    }

    private function bookingPayload(array $locations): array
    {
        return [
            'origin_location_id' => $locations[0]->id,
            'destination_location_id' => $locations[1]->id,
            'desired_departure_at' => now()->addDays(2)->toISOString(),
            'desired_arrival_at' => now()->addDays(6)->toISOString(),
            'cargo_type' => 'Apparel',
            'cargo_description' => 'Cartons of clothing',
            'package_count' => 10,
            'total_weight' => 50,
            'weight_unit' => 'KG',
            'total_volume' => 2.5,
            'volume_unit' => 'CBM',
            'customer_notes' => 'Handle with care',
            'items' => [
                ['item_name' => 'Clothing carton', 'quantity' => 10, 'unit_weight' => 5, 'weight_unit' => 'KG'],
            ],
        ];
    }

    public function test_customer_can_submit_booking_request_and_operator_can_review_it(): void
    {
        $customer = $this->user(User::ROLE_CUSTOMER);
        $operator = $this->user(User::ROLE_OPERATOR);
        [, $origin, $destination] = $this->masterData();

        Sanctum::actingAs($customer);
        $created = $this->postJson('/api/booking-requests', $this->bookingPayload([$origin, $destination]))
            ->assertCreated()
            ->assertJsonPath('data.status', 'PENDING_REVIEW');
        $bookingId = $created->json('data.id');
        $this->assertDatabaseHas('booking_requests', ['id' => $bookingId, 'customer_id' => $customer->id]);
        $this->assertDatabaseHas('booking_request_items', ['booking_request_id' => $bookingId, 'item_name' => 'Clothing carton']);

        Sanctum::actingAs($operator);
        $this->getJson('/api/booking-requests')->assertOk()->assertJsonFragment(['request_number' => $created->json('data.request_number')]);
        $this->postJson('/api/booking-requests/'.$bookingId.'/review', ['decision' => 'approve'])
            ->assertOk()->assertJsonPath('data.status', 'APPROVED');
    }

    public function test_customer_cannot_view_another_customers_booking_request(): void
    {
        $customerA = $this->user(User::ROLE_CUSTOMER);
        $customerB = $this->user(User::ROLE_CUSTOMER);
        [, $origin, $destination] = $this->masterData();
        Sanctum::actingAs($customerA);
        $created = $this->postJson('/api/booking-requests', $this->bookingPayload([$origin, $destination]))->assertCreated();

        Sanctum::actingAs($customerB);
        $this->getJson('/api/booking-requests/'.$created->json('data.id'))->assertNotFound();
    }

    public function test_booking_cannot_be_converted_until_quote_is_accepted_and_payment_is_paid(): void
    {
        config(['cargotrace.allow_demo_payments' => true]);
        $customer = $this->user(User::ROLE_CUSTOMER);
        $operator = $this->user(User::ROLE_OPERATOR);
        [$carrier, $origin, $destination] = $this->masterData();

        Sanctum::actingAs($customer);
        $created = $this->postJson('/api/booking-requests', $this->bookingPayload([$origin, $destination]))->assertCreated();
        $bookingId = $created->json('data.id');

        Sanctum::actingAs($operator);
        $this->postJson('/api/booking-requests/'.$bookingId.'/review', ['decision' => 'approve'])->assertOk();
        $this->postJson('/api/booking-requests/'.$bookingId.'/quotation', [
            'carrier_id' => $carrier->id,
            'amount' => 2500000,
            'currency' => 'IDR',
            'expected_departure_at' => now()->addDays(2)->toISOString(),
            'expected_arrival_at' => now()->addDays(6)->toISOString(),
            'valid_until' => now()->addDays(3)->toISOString(),
            'terms' => 'Demo quotation terms.',
        ])->assertCreated()->assertJsonPath('data.status', 'SENT');

        $this->postJson('/api/booking-requests/'.$bookingId.'/convert')->assertUnprocessable();

        Sanctum::actingAs($customer);
        $this->postJson('/api/booking-requests/'.$bookingId.'/quotation/accept')->assertOk()->assertJsonPath('data.status', 'QUOTE_ACCEPTED');
        $payment = $this->postJson('/api/booking-requests/'.$bookingId.'/payments')->assertCreated()->assertJsonPath('demo_mode', true);
        $paymentId = $payment->json('data.id');
        $this->assertDatabaseHas('booking_payments', ['id' => $paymentId, 'status' => 'PENDING', 'provider' => 'mock']);
        $this->postJson('/api/booking-payments/'.$paymentId.'/demo-confirm')->assertOk();
        $this->assertDatabaseHas('booking_requests', ['id' => $bookingId, 'status' => 'READY_FOR_CONVERSION']);

        Sanctum::actingAs($operator);
        $converted = $this->postJson('/api/booking-requests/'.$bookingId.'/convert')->assertCreated()->assertJsonPath('data.status', 'CREATED');
        $shipmentId = $converted->json('data.id');
        $this->assertDatabaseHas('booking_requests', ['id' => $bookingId, 'status' => 'CONVERTED', 'shipment_id' => $shipmentId]);
        $this->assertDatabaseHas('shipments', ['id' => $shipmentId, 'customer_id' => $customer->id, 'carrier_id' => $carrier->id]);
        $this->assertDatabaseHas('tracking_events', ['shipment_id' => $shipmentId, 'status' => 'CREATED']);

        // Retrying conversion is idempotent and must not create a second shipment.
        $this->postJson('/api/booking-requests/'.$bookingId.'/convert')->assertOk()->assertJsonPath('already_converted', true);
        $this->assertSame(1, Shipment::where('customer_reference', $created->json('data.request_number'))->count());
    }

    public function test_operator_cannot_use_demo_payment_confirmation(): void
    {
        $operator = $this->user(User::ROLE_OPERATOR);
        $payment = new BookingPayment(['provider' => 'mock', 'status' => 'PENDING']);
        $payment->id = 999;
        Sanctum::actingAs($operator);
        $this->postJson('/api/booking-payments/999/demo-confirm')->assertForbidden();
    }
}
