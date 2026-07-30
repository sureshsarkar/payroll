<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the missing `role_slug` column to `coach_staff_roles`.
 *
 * Backstory: the column has been read/written by CoachStaffRole + the
 * coach-staff-role views since long before the audit, but no migration
 * ever created it — it was added by hand in phpMyAdmin during early
 * development and the schema change never made it into version control.
 * On a fresh DB (CI, new install) the next migration —
 * `2026_05_01_000000_fix_coach_staff_roles_added_by_type` — explodes
 * because it references `->after('role_slug')`. This file backfills
 * that missing step.
 *
 * Idempotent: skips the work if the column is already present, so it's
 * safe on prod databases that have it from the manual ALTER.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('coach_staff_roles', 'role_slug')) {
            Schema::table('coach_staff_roles', function (Blueprint $table) {
                $table->string('role_slug')->nullable()->after('role_name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('coach_staff_roles', 'role_slug')) {
            Schema::table('coach_staff_roles', function (Blueprint $table) {
                $table->dropColumn('role_slug');
            });
        }
    }
};
