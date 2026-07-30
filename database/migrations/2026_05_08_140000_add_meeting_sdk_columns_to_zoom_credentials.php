<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add separate Meeting SDK credential columns to `zoom_credentials`.
 *
 * Why: an OAuth-only Zoom app's client_id/client_secret cannot sign a valid
 * Meeting SDK JWT — Zoom rejects the join with "Signature is invalid" even
 * though the SAME pair successfully creates meetings via the REST API. Some
 * instructors will have a separate Meeting SDK app whose Key/Secret differ
 * from their OAuth credentials, so we need a place to store them.
 *
 * Both columns are nullable so existing rows keep working until the
 * instructor fills them in via the Zoom settings UI. The signature
 * controller will return a 503 with a clear "configure SDK Key" message
 * when sdk_key/sdk_secret are missing.
 *
 * Idempotent.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('zoom_credentials')) {
            return;
        }

        Schema::table('zoom_credentials', function (Blueprint $table) {
            if (!Schema::hasColumn('zoom_credentials', 'sdk_key')) {
                $table->string('sdk_key')->nullable()->after('client_secret');
            }
            if (!Schema::hasColumn('zoom_credentials', 'sdk_secret')) {
                // text() because the encrypted-cast envelope is much longer
                // than the raw 32-char Zoom SDK secret.
                $table->text('sdk_secret')->nullable()->after('sdk_key');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('zoom_credentials')) {
            return;
        }

        Schema::table('zoom_credentials', function (Blueprint $table) {
            foreach (['sdk_key', 'sdk_secret'] as $col) {
                if (Schema::hasColumn('zoom_credentials', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
