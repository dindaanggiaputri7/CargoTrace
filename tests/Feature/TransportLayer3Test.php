<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TransportLayer3Test extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::create(['name'=>ucfirst($role).' Test','email'=>$role.'-'.uniqid().'@cargotrace.test',
            'password'=>'Password!123','role'=>$role,'is_active'=>true]);
    }

    private function master(): array
    {
        $carrier=Carrier::create(['code'=>'L3-'.uniqid(),'name'=>'Layer Three Carrier','is_active'=>true]);
        $origin=Location::create(['code'=>'L3O-'.uniqid(),'name'=>'Port Alpha','city'=>'Jakarta','country'=>'Indonesia','type'=>'PORT']);
        $destination=Location::create(['code'=>'L3D-'.uniqid(),'name'=>'Port Beta','city'=>'Singapore','country'=>'Singapore','type'=>'PORT']);
        return [$carrier,$origin,$destination];
    }

    public function test_admin_operator_can_create_asset_trip_and_customer_sees_only_assigned_journey(): void
    {
        $admin=$this->user('admin'); $operator=$this->user('operator'); $customer=$this->user('customer');
        [$carrier,$origin,$destination]=$this->master();

        Sanctum::actingAs($admin);
        $asset=$this->postJson('/api/transport-assets',[
            'asset_code'=>'VESSEL-'.uniqid(),'name'=>'MV Test Vessel','asset_type'=>'VESSEL',
            'identifier_type'=>'MMSI','identifier_value'=>'525000001','carrier_id'=>$carrier->id,
        ])->assertCreated()->json('data');

        Sanctum::actingAs($operator);
        $trip=$this->postJson('/api/transport-trips',[
            'carrier_id'=>$carrier->id,'transport_asset_id'=>$asset['id'],'transport_mode'=>'SEA',
            'origin_location_id'=>$origin->id,'destination_location_id'=>$destination->id,
            'scheduled_departure_at'=>now()->addDay()->toISOString(),
            'scheduled_arrival_at'=>now()->addDays(3)->toISOString(),
        ])->assertCreated()->json('data');

        $shipment=Shipment::create([
            'shipment_number'=>'CT-L3-'.uniqid(),'customer_reference'=>'L3-TEST','customer_id'=>$customer->id,
            'created_by'=>$operator->id,'carrier_id'=>$carrier->id,'origin_location_id'=>$origin->id,
            'destination_location_id'=>$destination->id,'cargo_type'=>'General Cargo','package_count'=>1,
            'expected_arrival_at'=>now()->addDays(3),'status'=>'IN_TRANSIT',
        ]);
        $this->postJson('/api/transport-trips/'.$trip['id'].'/assignments',[
            'shipment_id'=>$shipment->id,'leg_order'=>1,
        ])->assertCreated();

        $this->postJson('/api/transport-trips/'.$trip['id'].'/positions',[
            'latitude'=>-6.1,'longitude'=>106.8,'observed_at'=>now()->subMinute()->toISOString(),
        ])->assertCreated()->assertJsonPath('data.source','MANUAL');

        Sanctum::actingAs($customer);
        $this->getJson('/api/customer/transport-journeys')->assertOk()
            ->assertJsonFragment(['trip_number'=>$trip['trip_number']]);
    }

    public function test_provider_webhook_is_secret_protected_and_duplicate_events_are_idempotent(): void
    {
        $admin=$this->user('admin'); $operator=$this->user('operator');
        [$carrier,$origin,$destination]=$this->master();
        Sanctum::actingAs($operator);
        $trip=$this->postJson('/api/transport-trips',[
            'carrier_id'=>$carrier->id,'transport_mode'=>'SEA','origin_location_id'=>$origin->id,
            'destination_location_id'=>$destination->id,
        ])->assertCreated()->json('data');

        Sanctum::actingAs($admin);
        $secret='Layer3-Webhook-Secret-2026-ChangeMe';
        $provider=$this->postJson('/api/tracking-providers',[
            'name'=>'Test AIS','provider_type'=>'AIS','webhook_secret'=>$secret,'is_active'=>true,
        ])->assertCreated()->json('data');

        $payload=['trip_number'=>$trip['trip_number'],'event_id'=>'event-001','latitude'=>-6.2,'longitude'=>106.9,'observed_at'=>now()->subMinute()->toISOString()];
        $headers=['X-CargoTrace-Webhook-Secret'=>$secret];
        $this->postJson('/api/integrations/tracking/'.$provider['id'].'/positions',$payload,$headers)
            ->assertCreated();
        $this->postJson('/api/integrations/tracking/'.$provider['id'].'/positions',$payload,$headers)
            ->assertOk()->assertJsonPath('message','Duplicate event ignored.');
        $this->assertDatabaseCount('trip_positions',1);
    }
}
