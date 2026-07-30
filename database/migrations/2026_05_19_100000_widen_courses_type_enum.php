<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SRS-CLC-001 §4.1 — Course Type dropdown on Create Course page.
 *
 * The original `courses.type` column is ENUM('course','webinar') and
 * 100% of existing rows are 'course' (211 of 211 at audit time).
 *
 * SRS asks for three meaningful options the coach picks at creation:
 *   - live      : real-time only, no recorded library
 *   - recorded  : pre-recorded library only
 *   - hybrid    : both — live sessions + recorded materials
 *
 * Approach: WIDEN the enum to keep existing rows valid AND accept the
 * new values. No data migration; existing rows stay 'course'. Form
 * starts writing the new values for newly-created courses.
 *
 * Idempotent — re-run is a no-op (we detect the existing enum shape).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('courses')) {
            return;
        }

        $col = collect(DB::select('SHOW COLUMNS FROM courses WHERE Field = ?', ['type']))->first();
        if (!$col) {
            return;
        }

        // Already widened? short-circuit.
        if (str_contains((string) $col->Type, "'live'") &&
            str_contains((string) $col->Type, "'recorded'") &&
            str_contains((string) $col->Type, "'hybrid'")) {
            return;
        }

        DB::statement(
            "ALTER TABLE `courses` MODIFY COLUMN `type` " .
            "ENUM('course','webinar','live','recorded','hybrid') " .
            "NOT NULL DEFAULT 'course'"
        );
    }

    public function down(): void
    {
        if (!Schema::hasTable('courses')) {
            return;
        }

        // Refuse to shrink the enum if any row uses one of the new
        // values — destructive narrowing would corrupt data.
        $newValueCount = (int) DB::table('courses')
            ->whereIn('type', ['live', 'recorded', 'hybrid'])
            ->count();

        if ($newValueCount > 0) {
            // Soft-degrade: leave the enum widened. The down() exists
            // for schema-rollback hygiene only.
            return;
        }

        DB::statement(
            "ALTER TABLE `courses` MODIFY COLUMN `type` " .
            "ENUM('course','webinar') NOT NULL DEFAULT 'course'"
        );
    }
};
