<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('transport_trips')) {
            return;
        }

        $hasLegacyMode = Schema::hasColumn('transport_trips', 'mode');
        $hasTransportMode = Schema::hasColumn('transport_trips', 'transport_mode');

        if ($hasLegacyMode && $hasTransportMode) {
            DB::table('transport_trips')
                ->whereNull('transport_mode')
                ->update(['transport_mode' => DB::raw('mode')]);

            Schema::table('transport_trips', function (Blueprint $table): void {
                $table->dropColumn('mode');
            });

            Schema::table('transport_trips', function (Blueprint $table): void {
                $table->string('transport_mode', 20)->nullable(false)->change();
            });

            return;
        }

        if ($hasLegacyMode && ! $hasTransportMode) {
            Schema::table('transport_trips', function (Blueprint $table): void {
                $table->renameColumn('mode', 'transport_mode');
            });
        }
    }

    public function down(): void
    {
        if (
            ! Schema::hasTable('transport_trips') ||
            Schema::hasColumn('transport_trips', 'mode') ||
            ! Schema::hasColumn('transport_trips', 'planned_departure')
        ) {
            return;
        }

        Schema::table('transport_trips', function (Blueprint $table): void {
            $table->string('mode', 20)->nullable();
        });

        DB::table('transport_trips')->update(['mode' => DB::raw('transport_mode')]);

        Schema::table('transport_trips', function (Blueprint $table): void {
            $table->string('mode', 20)->nullable(false)->change();
            $table->index('mode');
        });
    }
};