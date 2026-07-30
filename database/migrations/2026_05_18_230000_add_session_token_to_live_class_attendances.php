<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit 2026-05-18 Req 1 — single-login enforcement on live classes.
 *
 * Adds live_class_attendances.session_token (nullable string, indexed).
 *
 * When a student joins a live class via the SDK launcher, the join
 * endpoint stamps the current Laravel session id (or a derived
 * fingerprint) on the OPEN row. A second join from another browser /
 * device produces a DIFFERENT session id; the controller refuses with
 * 409 Conflict, telling the user "already joined from another device".
 *
 * Nullable for backwards compatibility — rows created before this
 * migration ran simply skip the check.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('live_class_attendances')) return;

        Schema::table('live_class_attendances', function (Blueprint $table) {
            if (!Schema::hasColumn('live_class_attendances', 'session_token')) {
                $table->string('session_token', 64)->nullable()->after('user_agent');
                $table->index('session_token', 'lca_session_token_idx');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('live_class_attendances')) return;
        Schema::table('live_class_attendances', function (Blueprint $table) {
            if (Schema::hasColumn('live_class_attendances', 'session_token')) {
                $table->dropIndex('lca_session_token_idx');
                $table->dropColumn('session_token');
            }
        });
    }
};
