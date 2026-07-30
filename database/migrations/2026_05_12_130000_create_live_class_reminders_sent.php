<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-class reminder dedup ledger (#8, 2026-05-12).
 *
 * One row per (live class × user) the moment a reminder is dispatched.
 * The cron-driven `live-class:send-reminders` checks this table before
 * sending so a single 30-minute lead-time reminder isn't sent twice
 * if the cron runs multiple times in that window (every 5 min × 6 = 6
 * opportunities to misfire without this guard).
 *
 * Idempotent.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('live_class_reminders_sent')) {
            return;
        }
        Schema::create('live_class_reminders_sent', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_live_class_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('sent_at')->useCurrent();

            // The (class, user) pair must be unique — that's the whole
            // point of this table. Sending twice would be a bug.
            $table->unique(['course_live_class_id', 'user_id'], 'lcrs_class_user_uniq');
            $table->index('sent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_class_reminders_sent');
    }
};
