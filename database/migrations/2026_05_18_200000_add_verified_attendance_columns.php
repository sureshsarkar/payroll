<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Audit 2026-05-18 phase 4 — verified-attendance state model.
 *
 * Previously: any row in live_class_attendances counted as "attended".
 * That was wrong — joining for 30 seconds shouldn't count the same as
 * sitting through the whole class.
 *
 * New model:
 *   - live_class_attendances.attendance_verified  (bool, default 0)
 *     Set to 1 only after the class ends AND the student's total
 *     duration_seconds across all sessions of that class meets the
 *     threshold. Manual marks (is_manual=1) auto-verify.
 *   - live_class_attendances.verified_at          (timestamp, nullable)
 *     When the verifier marked the row.
 *   - course_live_classes.expected_duration_minutes (int default 60)
 *     How long the class is supposed to run. Used by the threshold.
 *   - course_live_classes.ended_at                (timestamp, nullable)
 *     Set by the verifier when it has finished processing this class.
 *     "Class is finalised" — won't be re-processed.
 *
 * Backfill:
 *   - For existing rows with is_manual=1 → attendance_verified=1
 *     (coach decision is authoritative).
 *   - For automatic rows with duration_seconds NULL → leave at 0;
 *     the next verifier sweep handles them.
 *   - For existing classes (no end timestamp), the verifier's "ended_at
 *     IS NULL AND start_time was long ago" sweep will pick them up.
 *
 * Settings seed:
 *   - attendance_min_percent = '50' added to settings table if missing.
 *     Coach can override per-batch in a later phase; for now global.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('live_class_attendances')) {
            Schema::table('live_class_attendances', function (Blueprint $table) {
                if (!Schema::hasColumn('live_class_attendances', 'attendance_verified')) {
                    $table->boolean('attendance_verified')->default(false)->after('is_manual');
                    $table->index('attendance_verified', 'lca_verified_idx');
                }
                if (!Schema::hasColumn('live_class_attendances', 'verified_at')) {
                    $table->timestamp('verified_at')->nullable()->after('attendance_verified');
                }
            });
        }

        if (Schema::hasTable('course_live_classes')) {
            Schema::table('course_live_classes', function (Blueprint $table) {
                if (!Schema::hasColumn('course_live_classes', 'expected_duration_minutes')) {
                    $table->integer('expected_duration_minutes')->default(60)->after('start_time');
                }
                if (!Schema::hasColumn('course_live_classes', 'ended_at')) {
                    $table->timestamp('ended_at')->nullable()->after('expected_duration_minutes');
                    $table->index('ended_at', 'clc_ended_idx');
                }
            });
        }

        // Backfill: manual rows are trusted — auto-verify.
        DB::statement('
            UPDATE live_class_attendances
            SET attendance_verified = 1,
                verified_at = COALESCE(verified_at, updated_at, NOW())
            WHERE is_manual = 1 AND attendance_verified = 0
        ');

        // Seed default minimum-percent setting (idempotent).
        if (Schema::hasTable('settings')) {
            $exists = DB::table('settings')->where('key', 'attendance_min_percent')->exists();
            if (!$exists) {
                DB::table('settings')->insert([
                    'key'        => 'attendance_min_percent',
                    'value'      => '50',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('live_class_attendances')) {
            Schema::table('live_class_attendances', function (Blueprint $table) {
                if (Schema::hasColumn('live_class_attendances', 'verified_at')) {
                    $table->dropColumn('verified_at');
                }
                if (Schema::hasColumn('live_class_attendances', 'attendance_verified')) {
                    $table->dropIndex('lca_verified_idx');
                    $table->dropColumn('attendance_verified');
                }
            });
        }

        if (Schema::hasTable('course_live_classes')) {
            Schema::table('course_live_classes', function (Blueprint $table) {
                if (Schema::hasColumn('course_live_classes', 'ended_at')) {
                    $table->dropIndex('clc_ended_idx');
                    $table->dropColumn('ended_at');
                }
                if (Schema::hasColumn('course_live_classes', 'expected_duration_minutes')) {
                    $table->dropColumn('expected_duration_minutes');
                }
            });
        }

        DB::table('settings')->where('key', 'attendance_min_percent')->delete();
    }
};
