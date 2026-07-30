<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the `jitsi_settings` table.
 *
 * Jitsi was retired on 2026-05-07 (see project notes); the application no
 * longer reads or writes this table. Any pre-existing rows are dumped to
 * the log first so an operator can audit before the table goes.
 *
 * What this migration does NOT touch:
 *   - `course_live_classes` rows with `type='jitsi'` are preserved as-is.
 *     They surface to users as HTTP 410 via LearningController::liveSession
 *     and Coach\LiveClassController::coachLiveSession. Drop or convert
 *     those rows in a separate, decision-driven migration.
 *   - The `course_live_classes.type` enum is left wider than strictly
 *     necessary so legacy rows still load. Narrowing to ('zoom') only is
 *     a follow-up once the legacy rows are resolved.
 *
 * Down() recreates the table at its post-2026-05-06-encrypt schema. Row
 * data is not restored.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('jitsi_settings')) {
            return;
        }

        // Dump existing rows for the audit trail (no plaintext: api_key
        // is encrypted at rest after the 2026_05_06_130000 migration).
        $rows = DB::table('jitsi_settings')->get(['id', 'instructor_id', 'app_id', 'created_at']);
        foreach ($rows as $r) {
            \Log::info('drop_jitsi_settings: removing row', [
                'id'            => $r->id,
                'instructor_id' => $r->instructor_id,
                'app_id'        => $r->app_id,
                'created_at'    => $r->created_at,
            ]);
        }

        Schema::drop('jitsi_settings');
    }

    public function down(): void
    {
        if (Schema::hasTable('jitsi_settings')) {
            return;
        }

        Schema::create('jitsi_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instructor_id')->constrained('users')->cascadeOnDelete();
            $table->string('app_id')->nullable();
            $table->text('api_key')->nullable();
            $table->timestamps();
        });
    }
};
