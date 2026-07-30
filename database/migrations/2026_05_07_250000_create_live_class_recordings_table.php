<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cache of Zoom Cloud Recording metadata, surfaced as catch-up content
 * for students who couldn't attend a live class.
 *
 * Why a separate table rather than denormalising onto course_live_classes:
 *   - One meeting can produce multiple recording files (cloud, audio,
 *     chat transcript) with separate URLs + sizes.
 *   - Recordings arrive *after* the meeting ends; we don't want a stale
 *     "no recording yet" mistakenly stored on the parent class row.
 *   - Zoom recording urls expire (signed cloudfront URLs). One row per
 *     recording_id makes it easy to refresh in place.
 *
 * Auth model: read access mirrors the live class — students enrolled in
 * the course OR the course instructor. The play URL must NEVER be served
 * to a non-enrolled user, so the lesson player route hides the recording
 * link for non-enrollees.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('live_class_recordings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_live_class_id')
                ->constrained('course_live_classes')
                ->cascadeOnDelete();
            // Zoom's per-file id (uuid-ish). Combined with course_live_class_id
            // it must be unique — Zoom may return the same file twice if we
            // poll at the right moment, and we don't want duplicate cards.
            $table->string('zoom_recording_id', 64);
            // Zoom's high-level type — shared_screen_with_speaker_view,
            // active_speaker, audio_only, chat_file, etc.
            $table->string('file_type', 32);
            // mp4 / m4a / vtt / txt
            $table->string('file_extension', 16)->nullable();
            // Signed cloudfront URL with `?access_token=…` Zoom returns.
            // We DO NOT proxy through our origin — file is served directly
            // by Zoom; we just route auth-gated viewers to it.
            $table->text('play_url')->nullable();
            $table->text('download_url')->nullable();
            // Bytes — for the UI ("Recording · 124 MB").
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamp('recording_start')->nullable();
            $table->timestamp('recording_end')->nullable();
            $table->timestamps();

            $table->unique(['course_live_class_id', 'zoom_recording_id'], 'uq_lcr_meeting_file');
            $table->index('course_live_class_id', 'idx_lcr_class');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_class_recordings');
    }
};
