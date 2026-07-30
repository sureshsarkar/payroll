<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Delete legacy `course_live_classes` rows that point at the retired
 * Jitsi provider.
 *
 * Each row is dumped to the log first so an operator can see what was
 * removed. Lessons referenced by these rows are NOT deleted — only the
 * live-class metadata is dropped, since the meeting itself is unreachable
 * (Jitsi was retired 2026-05-07).
 *
 * Run order:
 *   2026_05_07_180000_drop_jitsi_settings_table       (drops credentials)
 *   2026_05_07_180100_remove_legacy_jitsi_live_class_rows  (this file)
 *   2026_05_07_180200_narrow_course_live_classes_type_enum (enum cleanup)
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('course_live_classes')) {
            return;
        }

        $rows = DB::table('course_live_classes')->where('type', 'jitsi')->get();
        foreach ($rows as $r) {
            \Log::info('remove_legacy_jitsi_live_class_rows: deleting row', [
                'id'         => $r->id,
                'lesson_id'  => $r->lesson_id ?? null,
                'meeting_id' => $r->meeting_id ?? null,
                'start_time' => $r->start_time ?? null,
            ]);
        }

        DB::table('course_live_classes')->where('type', 'jitsi')->delete();
    }

    public function down(): void
    {
        // Row data is not restored on rollback — the meeting state is
        // unrecoverable (no Jitsi backend). Leave the rollback as a no-op.
    }
};
