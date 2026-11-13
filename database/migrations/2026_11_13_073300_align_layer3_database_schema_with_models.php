<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | TRANSPORT ASSETS
        |--------------------------------------------------------------------------
        */
        Schema::table('transport_assets', function (Blueprint $table) {
            if (!Schema::hasColumn('transport_assets', 'name')) {
                $table->string('name')->nullable();
            }

            if (!Schema::hasColumn('transport_assets', 'identifier_type')) {
                $table->string('identifier_type', 20)->nullable();
            }

            if (!Schema::hasColumn('transport_assets', 'identifier_value')) {
                $table->string('identifier_value', 100)->nullable()->index();
            }

            if (!Schema::hasColumn('transport_assets', 'flag_country')) {
                $table->string('flag_country', 100)->nullable();
            }

            if (!Schema::hasColumn('transport_assets', 'metadata')) {
                $table->json('metadata')->nullable();
            }
        });

        if (Schema::hasColumn('transport_assets', 'display_name')) {
            DB::table('transport_assets')
                ->whereNull('name')
                ->update(['name' => DB::raw('display_name')]);
        }

        if (
            Schema::hasColumn('transport_assets', 'external_identifier') &&
            Schema::hasColumn('transport_assets', 'registration_code')
        ) {
            DB::table('transport_assets')
                ->whereNull('identifier_value')
                ->update([
                    'identifier_value' => DB::raw(
                        'COALESCE(external_identifier, registration_code)'
                    )
                ]);

            DB::table('transport_assets')
                ->whereNull('identifier_type')
                ->whereNotNull('registration_code')
                ->update(['identifier_type' => 'REGISTRATION']);
        }

        /*
        |--------------------------------------------------------------------------
        | TRANSPORT TRIPS
        |--------------------------------------------------------------------------
        */
        Schema::table('transport_trips', function (Blueprint $table) {
            if (!Schema::hasColumn('transport_trips', 'transport_mode')) {
                $table->string('transport_mode', 20)->nullable();
            }

            if (!Schema::hasColumn('transport_trips', 'scheduled_departure_at')) {
                $table->dateTime('scheduled_departure_at')->nullable();
            }

            if (!Schema::hasColumn('transport_trips', 'scheduled_arrival_at')) {
                $table->dateTime('scheduled_arrival_at')->nullable();
            }

            if (!Schema::hasColumn('transport_trips', 'notes')) {
                $table->text('notes')->nullable();
            }

            if (!Schema::hasColumn('transport_trips', 'created_by')) {
                $table->foreignId('created_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });

        if (Schema::hasColumn('transport_trips', 'mode')) {
            DB::table('transport_trips')
                ->whereNull('transport_mode')
                ->update(['transport_mode' => DB::raw('mode')]);
        }

        if (Schema::hasColumn('transport_trips', 'planned_departure')) {
            DB::table('transport_trips')
                ->whereNull('scheduled_departure_at')
                ->update([
                    'scheduled_departure_at' => DB::raw('planned_departure')
                ]);
        }

        if (Schema::hasColumn('transport_trips', 'planned_arrival')) {
            DB::table('transport_trips')
                ->whereNull('scheduled_arrival_at')
                ->update([
                    'scheduled_arrival_at' => DB::raw('planned_arrival')
                ]);
        }

        /*
        |--------------------------------------------------------------------------
        | TRIP POSITIONS
        |--------------------------------------------------------------------------
        */
        Schema::table('trip_positions', function (Blueprint $table) {
            if (!Schema::hasColumn('trip_positions', 'tracking_provider_id')) {
                $table->foreignId('tracking_provider_id')
                    ->nullable()
                    ->constrained('tracking_providers')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('trip_positions', 'provider_event_id')) {
                $table->string('provider_event_id', 120)->nullable();
            }

            if (!Schema::hasColumn('trip_positions', 'speed_knots')) {
                $table->decimal('speed_knots', 8, 2)->nullable();
            }

            if (!Schema::hasColumn('trip_positions', 'heading')) {
                $table->decimal('heading', 6, 2)->nullable();
            }

            if (!Schema::hasColumn('trip_positions', 'source')) {
                $table->string('source', 20)->default('MANUAL');
            }

            if (!Schema::hasColumn('trip_positions', 'quality')) {
                $table->string('quality', 20)->default('UNVERIFIED');
            }

            if (!Schema::hasColumn('trip_positions', 'raw_payload')) {
                $table->json('raw_payload')->nullable();
            }
        });

        if (Schema::hasColumn('trip_positions', 'provider_id')) {
            DB::table('trip_positions')
                ->whereNull('tracking_provider_id')
                ->update([
                    'tracking_provider_id' => DB::raw('provider_id')
                ]);
        }

        if (Schema::hasColumn('trip_positions', 'external_event_id')) {
            DB::table('trip_positions')
                ->whereNull('provider_event_id')
                ->update([
                    'provider_event_id' => DB::raw('external_event_id')
                ]);
        }

        if (Schema::hasColumn('trip_positions', 'source_type')) {
            DB::table('trip_positions')
                ->whereNull('source')
                ->update(['source' => DB::raw('source_type')]);
        }

        /*
        |--------------------------------------------------------------------------
        | TRACKING SYNC LOGS
        |--------------------------------------------------------------------------
        */
        Schema::table('tracking_sync_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('tracking_sync_logs', 'tracking_provider_id')) {
                $table->foreignId('tracking_provider_id')
                    ->nullable()
                    ->constrained('tracking_providers')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('tracking_sync_logs', 'sync_type')) {
                $table->string('sync_type', 30)->default('POSITION');
            }

            if (!Schema::hasColumn('tracking_sync_logs', 'records_created')) {
                $table->unsignedInteger('records_created')->default(0);
            }

            if (!Schema::hasColumn('tracking_sync_logs', 'error_code')) {
                $table->string('error_code', 80)->nullable();
            }

            if (!Schema::hasColumn('tracking_sync_logs', 'message')) {
                $table->text('message')->nullable();
            }
        });

        if (Schema::hasColumn('tracking_sync_logs', 'provider_id')) {
            DB::table('tracking_sync_logs')
                ->whereNull('tracking_provider_id')
                ->update([
                    'tracking_provider_id' => DB::raw('provider_id')
                ]);
        }

        if (Schema::hasColumn('tracking_sync_logs', 'records_saved')) {
            DB::table('tracking_sync_logs')
                ->where('records_created', 0)
                ->update([
                    'records_created' => DB::raw('records_saved')
                ]);
        }

        if (Schema::hasColumn('tracking_sync_logs', 'error_message_safe')) {
            DB::table('tracking_sync_logs')
                ->whereNull('message')
                ->update([
                    'message' => DB::raw('error_message_safe')
                ]);
        }

        /*
        |--------------------------------------------------------------------------
        | EXTERNAL PROVIDER EVENTS
        |--------------------------------------------------------------------------
        */
        if (!Schema::hasTable('external_provider_events')) {
            Schema::create('external_provider_events', function (Blueprint $table) {
                $table->id();

                $table->foreignId('tracking_provider_id')
                    ->constrained('tracking_providers')
                    ->restrictOnDelete();

                $table->string('provider_event_id', 120);

                $table->foreignId('shipment_id')
                    ->constrained('shipments')
                    ->restrictOnDelete();

                $table->string('event_category', 30);
                $table->string('event_type', 60);
                $table->text('description');
                $table->dateTime('observed_at');
                $table->json('payload')->nullable();

                $table->timestamps();

                $table->unique(
                    ['tracking_provider_id', 'provider_event_id'],
                    'external_provider_event_unique'
                );

                $table->index(['shipment_id', 'observed_at']);
            });
        }
    }

    public function down(): void
    {
        // Sengaja tidak menghapus kolom atau tabel.
        // Migrasi ini mempertahankan data dan struktur lama.
    }
};
