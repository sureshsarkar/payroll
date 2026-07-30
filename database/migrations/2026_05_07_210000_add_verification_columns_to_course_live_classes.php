<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-class verification: track each live class's "is the meeting still
 * reachable on Zoom's side?" state so the admin dashboard + instructor
 * sidebar can flag broken meetings BEFORE the start time, not after a
 * student tries to join.
 *
 * Why: on 2026-05-07 we discovered an entire generation of stale
 * `course_live_classes` rows pointed at meetings Zoom had auto-deleted
 * after 30 days of inactivity. The new live-class:verify command runs
 * every 5 minutes against rows scheduled in the next 30 minutes and
 * writes the result here.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('course_live_classes')) {
            return;
        }

        Schema::table('course_live_classes', function (Blueprint $table) {
            // pending | ok | missing | error | skipped
            $table->string('verification_status', 16)->default('pending')->after('join_url');
            $table->string('verification_message', 255)->nullable()->after('verification_status');
            $table->timestamp('last_verified_at')->nullable()->after('verification_message');
            // The cron query is "rows starting in the next 30 min where
            // verification is stale" — index covers it.
            $table->index(['verification_status', 'start_time'], 'idx_clc_verify');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('course_live_classes')) {
            return;
        }

        Schema::table('course_live_classes', function (Blueprint $table) {
            $table->dropIndex('idx_clc_verify');
            $table->dropColumn(['verification_status', 'verification_message', 'last_verified_at']);
        });
    }
};
