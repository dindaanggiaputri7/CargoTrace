<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->nullable()->unique();
            $table->string('name');
            $table->string('type', 30)->default('OTHER');
            $table->string('city')->nullable();
            $table->string('country');
            $table->char('country_code', 2)->nullable();
            $table->text('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();

            $table->index(['city', 'country']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
