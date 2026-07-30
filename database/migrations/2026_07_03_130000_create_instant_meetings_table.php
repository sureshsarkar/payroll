<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1:1 Instant Meeting (2026-07-03) — a coach starts a private Zoom room with ONE
 * student, separate from batch/group live classes. Its own table so it never
 * touches the course/lesson/enrollment-bound `course_live_classes` engine.
 *
 * The one-active-meeting mutex (`coach_active_meetings`, UNIQUE(coach_id)) is
 * SHARED across both systems by adding a nullable `instant_meeting_id` column,
 * so a coach can't run a 1:1 and a batch class at the same time (and vice-versa).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('instant_meetings')) {
            Schema::create('instant_meetings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('coach_id');
                $table->unsignedBigInteger('student_id');

                $table->string('topic', 190)->nullable();
                // consultation | doubt | training | general
                $table->string('purpose', 30)->nullable();

                // Zoom meeting details (populated after the REST create).
                $table->string('meeting_id', 64)->nullable();
                $table->string('password', 64)->nullable();
                $table->string('join_url', 512)->nullable();

                // active | ended | cancelled
                $table->string('status', 16)->default('active');
                $table->unsignedInteger('expected_duration_minutes')->default(30);

                $table->timestamp('coach_joined_at')->nullable();
                $table->timestamp('student_joined_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->timestamp('expires_at')->nullable();   // stuck-session TTL fallback

                $table->string('created_ip', 45)->nullable();
                $table->timestamps();

                $table->index(['coach_id', 'status']);
                $table->index(['student_id', 'status']);
                $table->foreign('coach_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('student_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        // Share the one-active-meeting mutex with the instant-meeting flow.
        if (Schema::hasTable('coach_active_meetings') && ! Schema::hasColumn('coach_active_meetings', 'instant_meeting_id')) {
            Schema::table('coach_active_meetings', function (Blueprint $table) {
                $table->unsignedBigInteger('instant_meeting_id')->nullable()->after('course_live_class_id')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('coach_active_meetings') && Schema::hasColumn('coach_active_meetings', 'instant_meeting_id')) {
            Schema::table('coach_active_meetings', function (Blueprint $table) {
                $table->dropColumn('instant_meeting_id');
            });
        }
        Schema::dropIfExists('instant_meetings');
    }
};
