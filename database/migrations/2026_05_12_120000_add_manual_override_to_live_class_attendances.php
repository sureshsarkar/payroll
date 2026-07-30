<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add manual-override fields to live_class_attendances (2026-05-12).
 *
 *   - is_manual       : flag distinguishing instructor-typed presence
 *                       from launcher-recorded presence. Lets the per-
 *                       lesson page badge manual rows so it's clear
 *                       which were attended vs. marked.
 *   - manual_reason   : why the instructor decided to override
 *                       (e.g. "audio failed on join", "WhatsApp screenshot
 *                       confirms attendance"). Audit trail.
 *   - marked_by       : users.id of the instructor who created the
 *                       override row. Different from user_id (the
 *                       student) and from the launcher-set user_id
 *                       on real attendance rows.
 *
 * All three nullable so existing rows (none of which were manual)
 * stay valid. Idempotent.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('live_class_attendances')) {
            return;
        }
        Schema::table('live_class_attendances', function (Blueprint $table) {
            if (!Schema::hasColumn('live_class_attendances', 'is_manual')) {
                $table->boolean('is_manual')->default(false)->after('user_agent');
            }
            if (!Schema::hasColumn('live_class_attendances', 'manual_reason')) {
                $table->string('manual_reason', 255)->nullable()->after('is_manual');
            }
            if (!Schema::hasColumn('live_class_attendances', 'marked_by')) {
                $table->unsignedBigInteger('marked_by')->nullable()->after('manual_reason');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('live_class_attendances')) {
            return;
        }
        Schema::table('live_class_attendances', function (Blueprint $table) {
            foreach (['is_manual', 'manual_reason', 'marked_by'] as $col) {
                if (Schema::hasColumn('live_class_attendances', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
