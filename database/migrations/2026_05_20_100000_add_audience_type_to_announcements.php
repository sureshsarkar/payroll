<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Announcement module upgrade (2026-05-20).
 *
 * Widens the announcement audience model so admins can publish
 * platform-wide notices (audience_type='all_students'), while
 * preserving the existing coach-driven batch-scoped flow
 * (audience_type='batch_specific', which is the default for the
 * 200+ legacy rows already on the table).
 *
 * Changes:
 *   1. ADD audience_type ENUM('all_students','batch_specific')
 *        DEFAULT 'batch_specific'        — defaults match legacy semantics
 *   2. ADD sender_role ENUM('admin','instructor')
 *        DEFAULT 'instructor'            — flags who created the row
 *   3. MODIFY course_id BIGINT UNSIGNED NULL
 *        (was NOT NULL)                  — needed for all_students rows
 *        which by definition aren't tied to a course
 *
 * Idempotent: re-runs detect existing columns / nullable state and
 * skip those steps.
 *
 * down() is intentionally a NO-OP for the column drops. We can't tell
 * which rows used the new audience_type='all_students' without an audit
 * column, and reverting course_id to NOT NULL would corrupt any row
 * that left it NULL. Schema-rollback hygiene only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('announcements')) {
            return;
        }

        Schema::table('announcements', function (Blueprint $table) {
            if (!Schema::hasColumn('announcements', 'audience_type')) {
                $table->enum('audience_type', ['all_students', 'batch_specific'])
                      ->default('batch_specific')
                      ->after('batch_id');
            }
            if (!Schema::hasColumn('announcements', 'sender_role')) {
                $table->enum('sender_role', ['admin', 'instructor'])
                      ->default('instructor')
                      ->after('instructor_id');
            }
        });

        // course_id was NOT NULL — relax it. Use raw ALTER because Laravel's
        // ->change() requires doctrine/dbal at the wrong version for enums.
        $col = collect(DB::select(
            'SHOW COLUMNS FROM announcements WHERE Field = ?', ['course_id']
        ))->first();
        if ($col && $col->Null === 'NO') {
            DB::statement('ALTER TABLE `announcements` MODIFY COLUMN `course_id` BIGINT UNSIGNED NULL');
        }

        // Backfill explicit values so future queries can rely on the
        // columns even if the DEFAULT clause changes later.
        DB::table('announcements')
            ->whereNull('audience_type')
            ->update(['audience_type' => 'batch_specific']);
        DB::table('announcements')
            ->whereNull('sender_role')
            ->update(['sender_role' => 'instructor']);
    }

    public function down(): void
    {
        // Intentionally a no-op. See class docstring for rationale.
    }
};
