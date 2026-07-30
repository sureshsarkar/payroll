<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Live-class attendance log. Each row is one (user, live class, session)
 * — `joined_at` is mandatory, `left_at` and `duration_seconds` are
 * filled in when the user closes the meeting tab or hits Leave.
 *
 * The launcher (resources/views/frontend/student-dashboard/live/zoom.blade.php)
 * posts to /api/live-class/{id}/attendance with {event: 'join'|'leave'}
 * on Component View `connection-change` events. The endpoint requires
 * enrollment (or instructor ownership) and writes here.
 *
 * Why a row per session rather than one row total: a student may
 * disconnect (network drop, tab close, browser crash) and rejoin —
 * each rejoin is a new session row, and the instructor can see total
 * duration by summing duration_seconds.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('live_class_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_live_class_id')
                ->constrained('course_live_classes')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            // host | attendee — decided server-side based on the course's
            // instructor_id at join time, so a coach who re-enters as
            // attendee can't quietly reclassify themselves.
            $table->string('role', 16);
            $table->timestamp('joined_at');
            $table->timestamp('left_at')->nullable();
            // Filled on leave; lets the instructor view sum duration without
            // post-processing on read. Capped at 24h to defend against rows
            // with a missed `left_at` (browser crash → next join writes a
            // huge synthetic duration if we just diffed live).
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('client_ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();

            // The instructor view filters by live-class. Index covers it.
            $table->index(['course_live_class_id', 'joined_at'], 'idx_lca_class_joined');
            // The "is this user currently in the meeting" lookup that
            // /attendance leave handler uses — find the latest open row
            // for this (class, user).
            $table->index(['course_live_class_id', 'user_id', 'left_at'], 'idx_lca_open');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_class_attendances');
    }
};
