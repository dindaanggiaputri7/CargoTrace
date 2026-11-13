<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('tracking_providers')) {
            return;
        }

        $hasProviderName = Schema::hasColumn('tracking_providers', 'provider_name');
        $hasName = Schema::hasColumn('tracking_providers', 'name');
        $hasConfigJson = Schema::hasColumn('tracking_providers', 'config_json');
        $hasSettings = Schema::hasColumn('tracking_providers', 'settings');
        $hasLastCheckedAt = Schema::hasColumn('tracking_providers', 'last_checked_at');
        $hasLastSyncedAt = Schema::hasColumn('tracking_providers', 'last_synced_at');

        Schema::table('tracking_providers', function (Blueprint $table) use (
            $hasProviderName,
            $hasName,
            $hasConfigJson,
            $hasSettings,
            $hasLastCheckedAt,
            $hasLastSyncedAt
        ): void {
            if (! $hasName && $hasProviderName) {
                $table->renameColumn('provider_name', 'name');
            }

            if (! Schema::hasColumn('tracking_providers', 'driver')) {
                $table->string('driver', 40)->default('generic_webhook');
            }

            if (! Schema::hasColumn('tracking_providers', 'webhook_secret_hash')) {
                $table->string('webhook_secret_hash', 255)->nullable();
            }

            if (! Schema::hasColumn('tracking_providers', 'poll_interval_seconds')) {
                $table->unsignedInteger('poll_interval_seconds')->default(300);
            }

            if (! Schema::hasColumn('tracking_providers', 'stale_after_minutes')) {
                $table->unsignedInteger('stale_after_minutes')->default(30);
            }

            if (! $hasSettings && $hasConfigJson) {
                $table->renameColumn('config_json', 'settings');
            } elseif (! $hasSettings) {
                $table->json('settings')->nullable();
            }

            if (! $hasLastSyncedAt && $hasLastCheckedAt) {
                $table->renameColumn('last_checked_at', 'last_synced_at');
            } elseif (! $hasLastSyncedAt) {
                $table->dateTime('last_synced_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tracking_providers')) {
            return;
        }

        $hasName = Schema::hasColumn('tracking_providers', 'name');
        $hasProviderName = Schema::hasColumn('tracking_providers', 'provider_name');
        $hasSettings = Schema::hasColumn('tracking_providers', 'settings');
        $hasConfigJson = Schema::hasColumn('tracking_providers', 'config_json');
        $hasLastSyncedAt = Schema::hasColumn('tracking_providers', 'last_synced_at');
        $hasLastCheckedAt = Schema::hasColumn('tracking_providers', 'last_checked_at');

        Schema::table('tracking_providers', function (Blueprint $table) use (
            $hasName,
            $hasProviderName,
            $hasSettings,
            $hasConfigJson,
            $hasLastSyncedAt,
            $hasLastCheckedAt
        ): void {
            if ($hasName && ! $hasProviderName) {
                $table->renameColumn('name', 'provider_name');
            }

            if ($hasSettings && ! $hasConfigJson) {
                $table->renameColumn('settings', 'config_json');
            }

            if ($hasLastSyncedAt && ! $hasLastCheckedAt) {
                $table->renameColumn('last_synced_at', 'last_checked_at');
            }

            $columns = array_values(array_filter([
                Schema::hasColumn('tracking_providers', 'driver') ? 'driver' : null,
                Schema::hasColumn('tracking_providers', 'webhook_secret_hash') ? 'webhook_secret_hash' : null,
                Schema::hasColumn('tracking_providers', 'poll_interval_seconds') ? 'poll_interval_seconds' : null,
                Schema::hasColumn('tracking_providers', 'stale_after_minutes') ? 'stale_after_minutes' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};