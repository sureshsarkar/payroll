<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Narrow `course_live_classes.type` from enum('zoom','jitsi') to enum('zoom').
 *
 * Safe-guard: aborts if any rows still hold a non-zoom value. Run the
 * 2026_05_07_180100 migration first to clear them.
 *
 * Down() restores the original enum so legacy fixtures and tests can
 * round-trip.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('course_live_classes')) {
            return;
        }

        $bad = DB::table('course_live_classes')->where('type', '!=', 'zoom')->count();
        if ($bad > 0) {
            throw new \RuntimeException(
                "Refusing to narrow enum: {$bad} course_live_classes row(s) still hold a non-zoom type. "
                . "Run 2026_05_07_180100_remove_legacy_jitsi_live_class_rows first."
            );
        }

        DB::statement("ALTER TABLE course_live_classes MODIFY COLUMN type ENUM('zoom') NOT NULL DEFAULT 'zoom'");
    }

    public function down(): void
    {
        if (!Schema::hasTable('course_live_classes')) {
            return;
        }

        DB::statement("ALTER TABLE course_live_classes MODIFY COLUMN type ENUM('zoom','jitsi') NOT NULL DEFAULT 'zoom'");
    }
};
