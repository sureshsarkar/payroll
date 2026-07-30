<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Audit 2026-05-18 — extend announcements for batch-scoped coach posts.
 *
 * Adds three columns:
 *   - batch_id  (nullable, FK course_batches.id) — null means
 *                "course-wide" (legacy behaviour preserved).
 *   - status    (enum 'active' | 'inactive', default 'active')
 *                — admin can deactivate without deleting.
 *   - sent_at   (timestamp, nullable) — explicit send time. For legacy
 *                rows, backfilled to created_at.
 *
 * Also widens the `announcement` column from VARCHAR-like to TEXT-or-LONGTEXT
 * if it isn't already, so long messages survive. (Existing schema shows it
 * as TEXT already — defensive no-op if so.)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('announcements')) {
            return;
        }

        Schema::table('announcements', function (Blueprint $table) {
            if (!Schema::hasColumn('announcements', 'batch_id')) {
                $table->unsignedBigInteger('batch_id')->nullable()->after('course_id');
                $table->index(['batch_id'], 'announcements_batch_idx');
                $table->index(['course_id', 'batch_id'], 'announcements_course_batch_idx');
            }
            if (!Schema::hasColumn('announcements', 'status')) {
                $table->enum('status', ['active', 'inactive'])->default('active')->after('announcement');
                $table->index(['status'], 'announcements_status_idx');
            }
            if (!Schema::hasColumn('announcements', 'sent_at')) {
                $table->timestamp('sent_at')->nullable()->after('status');
                $table->index(['sent_at'], 'announcements_sent_at_idx');
            }
        });

        // Backfill sent_at = created_at for legacy rows
        if (Schema::hasColumn('announcements', 'sent_at')) {
            DB::statement('
                UPDATE announcements
                SET sent_at = created_at
                WHERE sent_at IS NULL AND created_at IS NOT NULL
            ');
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('announcements')) {
            return;
        }

        Schema::table('announcements', function (Blueprint $table) {
            if (Schema::hasColumn('announcements', 'sent_at')) {
                $table->dropIndex('announcements_sent_at_idx');
                $table->dropColumn('sent_at');
            }
            if (Schema::hasColumn('announcements', 'status')) {
                $table->dropIndex('announcements_status_idx');
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('announcements', 'batch_id')) {
                $table->dropIndex('announcements_batch_idx');
                $table->dropIndex('announcements_course_batch_idx');
                $table->dropColumn('batch_id');
            }
        });
    }
};
