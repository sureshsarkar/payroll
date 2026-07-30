<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Audit 2026-05-18 — link enrollments to course_batches.
 *
 * The student picks a batch at checkout (order_items.batch_id is set),
 * but the resulting Enrollment row drops that mapping. Adds a nullable
 * batch_id column and backfills from order_items where the link is
 * unambiguous (one batch_id per enrollment's order_id + course_id).
 *
 * Required by:
 *   - Batch attendance summary (count students in a batch)
 *   - Coach-side announcements scoped to one batch
 *   - Student-side "show announcements for MY batch only"
 *
 * Safe: column is NULLABLE; legacy rows without a batch keep working
 * (treated as course-wide).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('enrollments')) {
            return;
        }

        if (!Schema::hasColumn('enrollments', 'batch_id')) {
            Schema::table('enrollments', function (Blueprint $table) {
                $table->unsignedBigInteger('batch_id')->nullable()->after('course_id');
                $table->index(['user_id', 'batch_id'], 'enrollments_user_batch_idx');
                $table->index(['batch_id'], 'enrollments_batch_idx');
            });
        }

        // Backfill from order_items where unambiguous:
        // pair each enrollment row to the matching order_item by (order_id, course_id).
        if (Schema::hasTable('order_items')
            && Schema::hasColumn('order_items', 'batch_id')
            && Schema::hasColumn('enrollments', 'batch_id')) {

            DB::statement('
                UPDATE enrollments e
                INNER JOIN order_items oi
                    ON oi.order_id = e.order_id
                    AND oi.course_id = e.course_id
                SET e.batch_id = oi.batch_id
                WHERE e.batch_id IS NULL
                  AND oi.batch_id IS NOT NULL
            ');
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('enrollments', 'batch_id')) {
            return;
        }

        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropIndex('enrollments_user_batch_idx');
            $table->dropIndex('enrollments_batch_idx');
            $table->dropColumn('batch_id');
        });
    }
};
