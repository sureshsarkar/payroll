<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add `attendance_threshold_percent` to `courses`.
 *
 * The minimum percentage of live classes a student must attend before the
 * instructor flags them as "at risk". Default 75% — industry-standard for
 * online cohort courses. Per-course override so courses with optional live
 * sessions can set it to 0 (everyone passes) and high-rigor cohorts can
 * raise it to 90.
 *
 * Stored as TINYINT UNSIGNED — values 0–100 are valid; anything outside
 * that range is a bug in the form validation, not a legitimate use case.
 *
 * Idempotent: skip if column already exists.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('courses', 'attendance_threshold_percent')) {
            return;
        }
        Schema::table('courses', function (Blueprint $table) {
            $table->unsignedTinyInteger('attendance_threshold_percent')
                ->default(75)
                ->after('certificate');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('courses', 'attendance_threshold_percent')) {
            return;
        }
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('attendance_threshold_percent');
        });
    }
};
