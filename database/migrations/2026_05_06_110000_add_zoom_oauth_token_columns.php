<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the OAuth-token columns to `zoom_credentials` that have been read
 * and written by ZoomMeetingTrait + the ZoomCredential model since
 * forever, but were never captured in a migration — they were added
 * manually in someone's dev DB and never made it into version control.
 * On a fresh install the encryption migration that follows
 * (2026_05_06_120000_encrypt_zoom_credentials) crashed when its
 * SELECT reached for these columns.
 *
 * Idempotent: skip per-column when the column already exists, so prod
 * databases that have them from the manual ALTER are unaffected.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('zoom_credentials')) {
            return;
        }

        Schema::table('zoom_credentials', function (Blueprint $table) {
            if (!Schema::hasColumn('zoom_credentials', 'zoom_access_token')) {
                $table->text('zoom_access_token')->nullable();
            }
            if (!Schema::hasColumn('zoom_credentials', 'zoom_refresh_token')) {
                $table->text('zoom_refresh_token')->nullable();
            }
            if (!Schema::hasColumn('zoom_credentials', 'zoom_token_expires_at')) {
                $table->timestamp('zoom_token_expires_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('zoom_credentials')) {
            return;
        }
        Schema::table('zoom_credentials', function (Blueprint $table) {
            foreach (['zoom_access_token', 'zoom_refresh_token', 'zoom_token_expires_at'] as $col) {
                if (Schema::hasColumn('zoom_credentials', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
