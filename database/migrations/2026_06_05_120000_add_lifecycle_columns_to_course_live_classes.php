<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-06-05 — Live-class lifecycle: two minimal, nullable signals so the
 * student "wait for the coach" gate and the status state machine work without
 * a single global flag (status stays strictly per-class):
 *
 *   - coach_joined_at : stamped the first time the HOST (coach / assigned
 *     teacher) actually joins the meeting. Drives "coach has joined → students
 *     may enter" and doubles as actual_started_at. Sticky for the session.
 *   - cancelled_at    : set when a live class is cancelled, so the lifecycle
 *     status can report "cancelled" distinctly from "completed".
 *
 * Everything else in the lifecycle (Scheduled / Waiting / Ready / Live /
 * Completed) is DERIVED from existing columns + attendance — no denormalised
 * running flags to keep in sync.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_live_classes', function (Blueprint $table) {
            if (! Schema::hasColumn('course_live_classes', 'coach_joined_at')) {
                $table->timestamp('coach_joined_at')->nullable()->after('ended_at');
            }
            if (! Schema::hasColumn('course_live_classes', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('coach_joined_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('course_live_classes', function (Blueprint $table) {
            foreach (['cancelled_at', 'coach_joined_at'] as $col) {
                if (Schema::hasColumn('course_live_classes', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
