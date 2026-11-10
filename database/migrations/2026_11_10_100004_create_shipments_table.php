<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('shipment_number', 50)->unique();
            $table->string('customer_reference', 100)->nullable()->index();

            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('carrier_id')->constrained('carriers')->restrictOnDelete();
            $table->foreignId('origin_location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('destination_location_id')->constrained('locations')->restrictOnDelete();

            $table->text('cargo_description')->nullable();
            $table->unsignedInteger('package_count')->default(1);
            $table->decimal('total_weight', 12, 3)->nullable();
            $table->string('weight_unit', 10)->default('KG');

            // Lifecycle: CREATED, BOOKED, PICKED_UP, IN_TRANSIT, DELIVERED.
            $table->string('status', 30)->default('CREATED')->index();

            // Keep original ETA separate from the operator-updated current ETA.
            $table->dateTime('expected_arrival_at');
            $table->dateTime('current_eta')->nullable();
            $table->dateTime('booked_at')->nullable();
            $table->dateTime('picked_up_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index(['carrier_id', 'status']);
            $table->index(['status', 'current_eta']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
