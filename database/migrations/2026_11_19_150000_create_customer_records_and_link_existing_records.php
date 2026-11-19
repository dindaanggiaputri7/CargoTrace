<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('name', 255);
            $table->string('company_name', 255)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone', 40)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 120)->nullable();
            $table->string('region', 120)->nullable();
            $table->string('country', 120)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['name', 'company_name']);
        });

        // Preserve existing customer accounts and create their linked customer records.
        $customerUsers = DB::table('users')->where('role', 'customer')->get(['id', 'name', 'email', 'created_at', 'updated_at']);
        foreach ($customerUsers as $user) {
            DB::table('customers')->insertOrIgnore([
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at ?? now(),
                'updated_at' => $user->updated_at ?? now(),
            ]);
        }

        Schema::table('shipments', function (Blueprint $table) {
            // customer_id remains the optional login identity for authorization/notifications.
            $table->dropForeign(['customer_id']);
            $table->unsignedBigInteger('customer_id')->nullable()->change();
            $table->foreign('customer_id')->references('id')->on('users')->nullOnDelete();
            $table->foreignId('customer_record_id')->nullable()->after('customer_id')->constrained('customers')->nullOnDelete();
            $table->string('source_type', 16)->default('MANUAL')->after('customer_record_id')->index();
        });

        Schema::table('booking_requests', function (Blueprint $table) {
            $table->foreignId('customer_record_id')->nullable()->after('customer_id')->constrained('customers')->nullOnDelete();
        });

        // Link historic operational records to the new customer directory.
        DB::statement('UPDATE shipments SET customer_record_id = (SELECT customers.id FROM customers WHERE customers.user_id = shipments.customer_id) WHERE customer_record_id IS NULL');
        DB::statement('UPDATE booking_requests SET customer_record_id = (SELECT customers.id FROM customers WHERE customers.user_id = booking_requests.customer_id) WHERE customer_record_id IS NULL');
        DB::statement("UPDATE shipments SET source_type = 'BOOKING' WHERE id IN (SELECT shipment_id FROM booking_requests WHERE shipment_id IS NOT NULL)");
    }

    public function down(): void
    {
        // Keep customer history intact; this migration is intentionally non-destructive.
    }
};
