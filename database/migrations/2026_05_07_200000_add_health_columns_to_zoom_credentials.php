<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Track Zoom OAuth health on `zoom_credentials` so the daily
 * zoom:health-check command can record a result for each instructor.
 *
 * Why this exists: on 2026-05-07 we discovered instructor 1079's Zoom
 * refresh token had been dead since 2026-04-07 — a full month of
 * silently-broken live classes that no one noticed until a student
 * complained. These columns let an admin dashboard surface "X tokens
 * dead, Y expiring this week, Z healthy" at a glance and let us send
 * targeted reconnect emails before students hit a stuck join.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('zoom_credentials')) {
            return;
        }

        Schema::table('zoom_credentials', function (Blueprint $table) {
            // ok | expiring | dead | unknown
            $table->string('health_status', 16)->default('unknown')->after('zoom_token_expires_at');
            // last raw response from Zoom — short message so the admin row
            // can show exactly why a token failed (e.g. "invalid_grant")
            $table->string('health_message', 255)->nullable()->after('health_status');
            $table->timestamp('last_health_check_at')->nullable()->after('health_message');
            $table->index('health_status');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('zoom_credentials')) {
            return;
        }

        Schema::table('zoom_credentials', function (Blueprint $table) {
            $table->dropIndex(['health_status']);
            $table->dropColumn(['health_status', 'health_message', 'last_health_check_at']);
        });
    }
};
