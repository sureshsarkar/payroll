<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Widen course_live_classes.type from ENUM('zoom') to ENUM('zoom','external').
 *
 * The mobile "Schedule live class" form lets a coach pick an EXTERNAL class
 * (a manually-supplied join URL, no Zoom meeting). The column had been narrowed
 * to zoom-only (2026_05_07_180200), so inserting 'external' silently stored ''
 * under MySQL's non-strict mode. Widening the enum lets external classes persist
 * their real type. Purely additive — existing 'zoom' rows are untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('course_live_classes', 'type')) {
            DB::statement("ALTER TABLE course_live_classes MODIFY COLUMN type ENUM('zoom','external') NOT NULL DEFAULT 'zoom'");
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('course_live_classes', 'type')) {
            // Any external rows must become zoom before we can narrow back.
            DB::table('course_live_classes')->where('type', 'external')->update(['type' => 'zoom']);
            DB::statement("ALTER TABLE course_live_classes MODIFY COLUMN type ENUM('zoom') NOT NULL DEFAULT 'zoom'");
        }
    }
};
