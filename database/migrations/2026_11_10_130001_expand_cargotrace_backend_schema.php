<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Additive migration: safe for an already-migrated CargoTrace database. */
    public function up(): void
    {
        Schema::table('carriers', function (Blueprint $table) {
            $table->string('contact_name')->nullable()->after('name');
            $table->string('transport_mode', 40)->nullable()->after('contact_name');
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->string('region')->nullable()->after('city');
            $table->string('timezone', 80)->nullable()->after('country_code');
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->string('cargo_type', 120)->nullable()->after('destination_location_id');
            $table->decimal('total_volume', 12, 3)->nullable()->after('weight_unit');
            $table->string('volume_unit', 10)->default('CBM')->after('total_volume');
            $table->dateTime('expected_departure_at')->nullable()->after('volume_unit');
            $table->dateTime('in_transit_at')->nullable()->after('picked_up_at');
            $table->softDeletes();
            $table->index('expected_departure_at');
        });

        Schema::table('shipment_items', function (Blueprint $table) {
            $table->decimal('unit_volume', 12, 3)->nullable()->after('unit_weight');
            $table->string('volume_unit', 10)->default('CBM')->after('unit_volume');
        });

        Schema::table('shipment_exceptions', function (Blueprint $table) {
            $table->foreignId('resolved_by')->nullable()->after('reported_by')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shipment_exceptions', function (Blueprint $table) {
            $table->dropForeign(['resolved_by']);
            $table->dropColumn('resolved_by');
        });

        Schema::table('shipment_items', function (Blueprint $table) {
            $table->dropColumn(['unit_volume', 'volume_unit']);
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->dropIndex(['expected_departure_at']);
            $table->dropSoftDeletes();
            $table->dropColumn(['cargo_type', 'total_volume', 'volume_unit', 'expected_departure_at', 'in_transit_at']);
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn(['region', 'timezone']);
        });

        Schema::table('carriers', function (Blueprint $table) {
            $table->dropColumn(['contact_name', 'transport_mode']);
        });
    }
};
