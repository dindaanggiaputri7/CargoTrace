<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number', 50)->unique();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('origin_location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('destination_location_id')->constrained('locations')->restrictOnDelete();
            $table->dateTime('desired_departure_at')->nullable();
            $table->dateTime('desired_arrival_at')->nullable();
            $table->string('cargo_type', 120);
            $table->text('cargo_description')->nullable();
            $table->unsignedInteger('package_count')->default(1);
            $table->decimal('total_weight', 12, 3)->nullable();
            $table->string('weight_unit', 10)->default('KG');
            $table->decimal('total_volume', 12, 3)->nullable();
            $table->string('volume_unit', 10)->default('CBM');
            $table->text('customer_notes')->nullable();
            $table->string('status', 32)->default('PENDING_REVIEW')->index();
            $table->dateTime('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->text('operator_note')->nullable();
            $table->foreignId('shipment_id')->nullable()->unique()->constrained('shipments')->nullOnDelete();
            $table->timestamps();
            $table->index(['customer_id', 'status']);
            $table->index(['status', 'submitted_at']);
        });

        Schema::create('booking_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_request_id')->constrained('booking_requests')->cascadeOnDelete();
            $table->string('item_name', 255);
            $table->text('description')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_weight', 12, 3)->nullable();
            $table->string('weight_unit', 10)->default('KG');
            $table->decimal('unit_volume', 12, 3)->nullable();
            $table->string('volume_unit', 10)->default('CBM');
            $table->timestamps();
        });

        Schema::create('booking_request_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_request_id')->constrained('booking_requests')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->text('note')->nullable();
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['booking_request_id', 'created_at']);
        });

        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_request_id')->constrained('booking_requests')->cascadeOnDelete();
            $table->unsignedInteger('version_number')->default(1);
            $table->foreignId('carrier_id')->constrained('carriers')->restrictOnDelete();
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('IDR');
            $table->dateTime('expected_departure_at')->nullable();
            $table->dateTime('expected_arrival_at')->nullable();
            $table->dateTime('valid_until')->nullable();
            $table->text('terms')->nullable();
            $table->string('status', 20)->default('SENT')->index();
            $table->dateTime('accepted_at')->nullable();
            $table->timestamps();
            $table->unique(['booking_request_id', 'version_number']);
            $table->index(['booking_request_id', 'status']);
        });

        Schema::create('booking_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_request_id')->constrained('booking_requests')->restrictOnDelete();
            $table->foreignId('quotation_id')->constrained('quotations')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('IDR');
            $table->string('provider', 40)->default('mock');
            $table->string('payment_method', 40)->default('DEMO_CHECKOUT');
            $table->string('provider_reference', 100)->unique();
            $table->string('status', 20)->default('PENDING')->index();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->json('provider_data')->nullable();
            $table->timestamps();
            $table->index(['booking_request_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_payments');
        Schema::dropIfExists('quotations');
        Schema::dropIfExists('booking_request_histories');
        Schema::dropIfExists('booking_request_items');
        Schema::dropIfExists('booking_requests');
    }
};
