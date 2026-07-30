<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit 2026-05-18 phase 5 — per-batch attendance threshold override.
 *
 * Adds course_batches.attendance_min_percent (nullable). When set, the
 * verifier uses it instead of the global setting; when NULL the global
 * default applies. Lets coaches tighten or relax verification per
 * batch (e.g. an exam-prep batch wants 80%; a casual workshop 25%).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('course_batches')) return;

        Schema::table('course_batches', function (Blueprint $table) {
            if (!Schema::hasColumn('course_batches', 'attendance_min_percent')) {
                $table->unsignedTinyInteger('attendance_min_percent')->nullable()->after('capacity');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('course_batches')) return;
        Schema::table('course_batches', function (Blueprint $table) {
            if (Schema::hasColumn('course_batches', 'attendance_min_percent')) {
                $table->dropColumn('attendance_min_percent');
            }
        });
    }
};
