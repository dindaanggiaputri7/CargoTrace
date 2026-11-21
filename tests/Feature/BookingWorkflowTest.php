<?php

namespace Tests\Feature;

use App\Models\BookingPayment;
use App\Models\BookingRequest;
use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_midtrans_environment_mismatch_is_refused_before_gateway_call(): void
    {
        Http::fake();
        config([
            'services.midtrans.server_key' => 'Mid-server-production-key',
            'services.midtrans.is_production' => false,
            'services.midtrans.snap_url' => 'https://app.sandbox.midtrans.com/snap/v1/transactions',
        ]);
        $customer = $this->user(User::ROLE_CUSTOMER);
        [$carrier, $origin, $destination] = $this->masterData();

        Sanctum::actingAs($customer);
        $created = $this->postJson('/api/booking-requests', $this->bookingPayload([$origin, $destination]))->assertCreated();
        $bookingId = $created->json('data.id');

        $operator = $this->user(User::ROLE_OPERATOR);
        Sanctum::actingAs($operator);
        $this->postJson('/api/booking-requests/'.$bookingId.'/review', ['decision' => 'approve'])->assertOk();
        $this->postJson('/api/booking-requests/'.$bookingId.'/quotation', [
            'carrier_id' => $carrier->id,
            'amount' => 2500000,
            'currency' => 'IDR',
            'expected_departure_at' => now()->addDays(2)->toISOString(),
            'expected_arrival_at' => now()->addDays(6)->toISOString(),
        ])->assertCreated();

        Sanctum::actingAs($customer);
        $this->postJson('/api/booking-requests/'.$bookingId.'/quotation/accept')->assertOk();
        $this->postJson('/api/booking-requests/'.$bookingId.'/payments')
            ->assertStatus(503)
            ->assertJsonPath('code', 'PAYMENT_GATEWAY_ENVIRONMENT_MISMATCH');
        Http::assertNothingSent();
    }

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

    public function test_payment_intent_is_refused_without_midtrans_and_cannot_be_marked_paid_by_demo(): void
    {
        config(['services.midtrans.server_key' => null]);
        $customer = $this->user(User::ROLE_CUSTOMER);
        $operator = $this->user(User::ROLE_OPERATOR);
        [$carrier, $origin, $destination] = $this->masterData();

        Sanctum::actingAs($customer);
        $created = $this->postJson('/api/booking-requests', $this->bookingPayload([$origin, $destination]))->assertCreated();
        $bookingId = $created->json('data.id');
        $this->assertNotNull($created->json('data.customer_record_id'));

        Sanctum::actingAs($operator);
        $this->postJson('/api/booking-requests/'.$bookingId.'/review', ['decision' => 'approve'])->assertOk();
        $this->postJson('/api/booking-requests/'.$bookingId.'/quotation', [
            'carrier_id' => $carrier->id,
            'amount' => 2500000,
            'currency' => 'IDR',
            'expected_departure_at' => now()->addDays(2)->toISOString(),
            'expected_arrival_at' => now()->addDays(6)->toISOString(),
            'valid_until' => now()->addDays(3)->toISOString(),
            'terms' => 'Sandbox quotation terms.',
        ])->assertCreated();

        Sanctum::actingAs($customer);
        $this->postJson('/api/booking-requests/'.$bookingId.'/quotation/accept')->assertOk()->assertJsonPath('data.status', 'QUOTE_ACCEPTED');
        $this->postJson('/api/booking-requests/'.$bookingId.'/payments')->assertStatus(503)
            ->assertJsonPath('code', 'PAYMENT_PROVIDER_NOT_CONFIGURED');
        $this->assertDatabaseHas('booking_requests', ['id' => $bookingId, 'status' => 'QUOTE_ACCEPTED']);
        $this->assertDatabaseMissing('booking_payments', ['booking_request_id' => $bookingId, 'status' => 'PAID']);
        $this->postJson('/api/booking-payments/999/demo-confirm')->assertNotFound();

        Sanctum::actingAs($operator);
        $this->postJson('/api/booking-requests/'.$bookingId.'/convert')->assertUnprocessable();
    }

    public function test_verified_midtrans_notification_allows_booking_conversion(): void
    {
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
            'terms' => 'Sandbox quotation terms.',
        ])->assertCreated();

        Sanctum::actingAs($customer);
        $this->postJson('/api/booking-requests/'.$bookingId.'/quotation/accept')->assertOk();
        $booking = BookingRequest::findOrFail($bookingId);
        $booking->update(['status' => 'PENDING_PAYMENT']);
        $serverKey = 'test-midtrans-server-key';
        $signatureSecretKey = 'test-midtrans-signature-secret';

        config([
            'services.midtrans.server_key' => $serverKey,
            'services.midtrans.signature_secret_key' => $signatureSecretKey,

        ]);
        config(['services.midtrans.server_key' => $serverKey]);
        $orderId = 'CTPAY-TEST-'.uniqid();
        $payment = BookingPayment::create([
            'booking_request_id' => $bookingId,
            'quotation_id' => $booking->latestQuotation->id,
            'customer_id' => $customer->id,
            'amount' => 2500000,
            'currency' => 'IDR',
            'provider' => 'midtrans',
            'payment_method' => 'MIDTRANS_SNAP',
            'provider_reference' => $orderId,
            'status' => 'PENDING',
            'expires_at' => now()->addHours(24),
            'provider_data' => ['redirect_url' => 'https://example.test/checkout'],
        ]);
        $statusCode = '200';
        $grossAmount = '2500000.00';
        $signature = hash('sha512', $orderId.$statusCode.$grossAmount.$signatureSecretKey);

        $this->postJson('/api/integrations/payments/midtrans/notification', [
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => $signature,
            'transaction_status' => 'settlement',
            'fraud_status' => 'accept',
        ])->assertOk();
        $this->assertDatabaseHas('booking_payments', ['id' => $payment->id, 'status' => 'PAID']);
        $this->assertDatabaseHas('booking_requests', ['id' => $bookingId, 'status' => 'READY_FOR_CONVERSION']);

        Sanctum::actingAs($operator);
        $converted = $this->postJson('/api/booking-requests/'.$bookingId.'/convert')->assertCreated()->assertJsonPath('data.status', 'CREATED');
        $shipmentId = $converted->json('data.id');
        $this->assertDatabaseHas('booking_requests', ['id' => $bookingId, 'status' => 'CONVERTED', 'shipment_id' => $shipmentId]);
        $this->assertDatabaseHas('shipments', [
            'id' => $shipmentId,
            'customer_id' => $customer->id,
            'customer_record_id' => $booking->customer_record_id,
            'source_type' => 'BOOKING',
            'carrier_id' => $carrier->id,
        ]);
        $this->assertDatabaseHas('tracking_events', ['shipment_id' => $shipmentId, 'status' => 'CREATED']);

        // Retrying conversion is idempotent and must not create a second shipment.
        $this->postJson('/api/booking-requests/'.$bookingId.'/convert')->assertOk()->assertJsonPath('already_converted', true);
        $this->assertSame(1, Shipment::where('customer_reference', $created->json('data.request_number'))->count());
    }

    public function test_late_pending_midtrans_notification_does_not_revive_expired_payment(): void
    {
        $customer = $this->user(User::ROLE_CUSTOMER);
        $operator = $this->user(User::ROLE_OPERATOR);
        [$carrier, $origin, $destination] = $this->masterData();
        Sanctum::actingAs($customer);
        $created = $this->postJson('/api/booking-requests', $this->bookingPayload([$origin, $destination]))->assertCreated();
        $booking = BookingRequest::findOrFail($created->json('data.id'));
        Sanctum::actingAs($operator);
        $this->postJson('/api/booking-requests/'.$booking->id.'/review', ['decision' => 'approve'])->assertOk();
        $this->postJson('/api/booking-requests/'.$booking->id.'/quotation', [
            'carrier_id' => $carrier->id,
            'amount' => 5000,
            'currency' => 'IDR',
            'expected_departure_at' => now()->addDays(2)->toISOString(),
            'expected_arrival_at' => now()->addDays(6)->toISOString(),
        ])->assertCreated();
        $quotation = $booking->fresh()->latestQuotation;
        $payment = BookingPayment::create([
            'booking_request_id' => $booking->id, 'quotation_id' => $quotation->id,
            'customer_id' => $customer->id, 'amount' => 5000, 'currency' => 'IDR',
            'provider' => 'midtrans', 'payment_method' => 'MIDTRANS_SNAP',
            'provider_reference' => 'CTPAY-EXPIRED-'.uniqid(), 'status' => 'EXPIRED',
            'expires_at' => now()->subMinute(),
        ]);
        $signatureSecretKey = 'test-midtrans-signature-secret';

        config([
            'services.midtrans.signature_secret_key' => $signatureSecretKey,
        ]);
        $orderId = $payment->provider_reference;
        $grossAmount = '5000.00';
        $this->postJson('/api/integrations/payments/midtrans/notification', [
            'order_id' => $orderId, 'status_code' => '201', 'gross_amount' => $grossAmount,
            'signature_key' => hash('sha512', $orderId.'201'.$grossAmount.$signatureSecretKey),
            'transaction_status' => 'pending',
        ])->assertOk();
        $this->assertDatabaseHas('booking_payments', ['id' => $payment->id, 'status' => 'EXPIRED']);
    }

}
