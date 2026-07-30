<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-06-30 — "Live class started" notification log + dedup ledger.
 *
 * Records every class-started reminder so (a) the same student is never emailed
 * twice for the same class start (unique guard), and (b) the coach/admin can see
 * how many were sent vs failed. Separate from live_class_reminders_sent (which is
 * the PRE-start 15-min reminder) — a class can have both.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('live_class_start_notifications')) {
            return;
        }
        Schema::create('live_class_start_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('live_class_id')->index();
            $table->unsignedBigInteger('student_id')->index();
            $table->unsignedBigInteger('coach_id')->nullable()->index();
            $table->string('notification_type', 40)->default('class_started');
            $table->string('email_status', 16)->default('sent'); // sent | failed
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['live_class_id', 'student_id', 'notification_type'], 'lcsn_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_class_start_notifications');
    }
};
