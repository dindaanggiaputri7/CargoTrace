<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tracking_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('provider_type', 30);
            $table->string('driver', 40)->default('generic_webhook');
            $table->string('base_url', 500)->nullable();
            $table->text('credential_encrypted')->nullable();
            $table->string('webhook_secret_hash', 255)->nullable();
            $table->boolean('is_active')->default(false)->index();
            $table->unsignedInteger('poll_interval_seconds')->default(300);
            $table->unsignedInteger('stale_after_minutes')->default(30);
            $table->json('settings')->nullable();
            $table->dateTime('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('transport_assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_code', 60)->unique();
            $table->string('name');
            $table->string('asset_type', 30); // VESSEL, TRUCK, AIRCRAFT, TRAIN
            $table->string('identifier_type', 20)->nullable(); // IMO, MMSI, REGISTRATION, ICAO, DEVICE_ID
            $table->string('identifier_value', 100)->nullable()->index();
            $table->foreignId('carrier_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('flag_country', 100)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('transport_trips', function (Blueprint $table) {
            $table->id();
            $table->string('trip_number', 60)->unique();
            $table->foreignId('carrier_id')->constrained()->restrictOnDelete();
            $table->foreignId('transport_asset_id')->nullable()->constrained('transport_assets')->nullOnDelete();
            $table->string('transport_mode', 20);
            $table->foreignId('origin_location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('destination_location_id')->constrained('locations')->restrictOnDelete();
            $table->dateTime('scheduled_departure_at')->nullable();
            $table->dateTime('scheduled_arrival_at')->nullable();
            $table->dateTime('actual_departure_at')->nullable();
            $table->dateTime('actual_arrival_at')->nullable();
            $table->string('status', 20)->default('PLANNED')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['carrier_id', 'status']);
        });

        Schema::create('shipment_trip_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->restrictOnDelete();
            $table->foreignId('transport_trip_id')->constrained('transport_trips')->restrictOnDelete();
            $table->unsignedSmallInteger('leg_order');
            $table->dateTime('assigned_at')->useCurrent();
            $table->dateTime('unassigned_at')->nullable();
            $table->string('status', 20)->default('ASSIGNED');
            $table->timestamps();
            $table->unique(['shipment_id', 'leg_order']);
            $table->unique(['shipment_id', 'transport_trip_id']);
            $table->index(['transport_trip_id', 'status']);
        });

        Schema::create('trip_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transport_trip_id')->constrained('transport_trips')->restrictOnDelete();
            $table->foreignId('tracking_provider_id')->nullable()->constrained('tracking_providers')->nullOnDelete();
            $table->string('provider_event_id', 120)->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('speed_knots', 8, 2)->nullable();
            $table->decimal('heading', 6, 2)->nullable();
            $table->dateTime('observed_at');
            $table->dateTime('received_at');
            $table->string('source', 20)->default('MANUAL'); // MANUAL, PROVIDER, DEMO
            $table->string('quality', 20)->default('UNVERIFIED'); // VERIFIED, UNVERIFIED, STALE
            $table->json('raw_payload')->nullable();
            $table->timestamps();
            $table->unique(['tracking_provider_id', 'provider_event_id'], 'trip_position_provider_event_unique');
            $table->index(['transport_trip_id', 'observed_at']);
        });

        Schema::create('external_provider_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracking_provider_id')->constrained('tracking_providers')->restrictOnDelete();
            $table->string('provider_event_id', 120);
            $table->foreignId('shipment_id')->constrained('shipments')->restrictOnDelete();
            $table->string('event_category', 30);
            $table->string('event_type', 60);
            $table->text('description');
            $table->dateTime('observed_at');
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->unique(['tracking_provider_id', 'provider_event_id'], 'external_provider_event_unique');
            $table->index(['shipment_id', 'observed_at']);
        });

        Schema::create('tracking_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracking_provider_id')->nullable()->constrained('tracking_providers')->nullOnDelete();
            $table->foreignId('transport_trip_id')->nullable()->constrained('transport_trips')->nullOnDelete();
            $table->string('sync_type', 30)->default('POSITION');
            $table->string('status', 20)->index(); // SUCCESS, FAILED, PARTIAL
            $table->unsignedInteger('records_received')->default(0);
            $table->unsignedInteger('records_created')->default(0);
            $table->string('error_code', 80)->nullable();
            $table->text('message')->nullable();
            $table->dateTime('started_at');
            $table->dateTime('finished_at')->nullable();
            $table->timestamps();
            $table->index(['tracking_provider_id', 'created_at']);
        });
    }

    public function upAfterProviders(): void {}
    public function down(): void
    {
        Schema::dropIfExists('tracking_sync_logs');
        Schema::dropIfExists('external_provider_events');
        Schema::dropIfExists('trip_positions');
        Schema::dropIfExists('shipment_trip_assignments');
        Schema::dropIfExists('transport_trips');
        Schema::dropIfExists('tracking_providers');
        Schema::dropIfExists('transport_assets');
    }
};