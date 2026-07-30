<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SRS-CLC-001 Phase 3 follow-up — strict Course Type filtering.
 *
 * The Live Class course dropdown was widened to ONLY show courses
 * whose `type` is 'live' or 'hybrid' (per user feedback on 2026-05-19).
 * Existing rows on the box predate the Course Type field and default
 * to type='course', so without this migration they vanish from the
 * dropdown — a hard regression for any course that's actively
 * running live classes today.
 *
 * Fix: any course that already has at least one course_live_classes
 * row gets type='live'. That captures every course where a live class
 * has ever been scheduled.
 *
 * Idempotent — re-running just re-asserts the same rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('courses') || !Schema::hasTable('course_live_classes')) {
            return;
        }

        // Find every course_id that owns a CourseLiveClass row and is
        // still on the legacy 'course' or 'webinar' enum value.
        $legacyCourseIds = DB::table('courses')
            ->whereIn('type', ['course', 'webinar'])
            ->whereIn('id', function ($sub) {
                $sub->from('course_live_classes')
                    ->select('course_id')
                    ->whereNotNull('course_id');
            })
            ->pluck('id');

        if ($legacyCourseIds->isEmpty()) {
            return;
        }

        DB::table('courses')
            ->whereIn('id', $legacyCourseIds)
            ->update([
                'type'       => 'live',
                'updated_at' => now(),
            ]);

        \Illuminate\Support\Facades\Log::info(
            'Backfilled course type → live for ' . $legacyCourseIds->count() .
            ' legacy courses that had existing live classes.'
        );
    }

    public function down(): void
    {
        // Intentionally a no-op. We can't tell which rows we touched
        // without an audit column; reverting them to 'course' would
        // hide them from the (current) Live Class dropdown and silently
        // break live workflows.
    }
};
