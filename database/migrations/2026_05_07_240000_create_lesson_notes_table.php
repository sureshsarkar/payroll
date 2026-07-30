<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user, per-lesson personal notes — server-side mirror of the
 * localStorage notes drawer added in the Component View migration on
 * 2026-05-07. Lets a student start typing on their laptop and pick up
 * mid-sentence on their phone.
 *
 * Privacy: rows belong to a single user_id and are never surfaced to
 * the instructor or other students. The retrieve/save endpoint enforces
 * `where user_id = $auth->id` so a leaky controller can't leak notes
 * across users.
 *
 * Update strategy: one row per (user, lesson) — UPSERT on save. We
 * don't keep history; the localStorage copy on the client provides
 * eventual-consistency redundancy if the server save fails.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('lesson_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('lesson_id')
                ->constrained('course_chapter_lessons')
                ->cascadeOnDelete();
            // mediumtext gives ~16MB headroom — overkill but harmless,
            // and avoids the 64KB cliff of TEXT for a verbose note-taker.
            $table->mediumText('body')->nullable();
            $table->timestamps();

            // Composite unique — one note row per (user, lesson). The
            // upsert in the controller relies on this.
            $table->unique(['user_id', 'lesson_id'], 'uq_lesson_notes_user_lesson');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_notes');
    }
};
