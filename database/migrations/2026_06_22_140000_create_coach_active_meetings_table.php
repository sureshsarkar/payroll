<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-06-22 — enforce "one coach = one active live meeting at a time".
 *
 * One row per coach while they have a live meeting running. The UNIQUE(coach_id)
 * constraint is the DB-level race guard: two simultaneous "go live" requests for
 * the same coach can't both insert — the second hits the unique violation and is
 * blocked. Tenant-safe by construction (keyed on coach_id; Coach A's row can
 * never affect Coach B). expires_at is the stuck-session fallback so an
 * abandoned meeting never permanently blocks the coach.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('coach_active_meetings')) {
            return;
        }
        Schema::create('coach_active_meetings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coach_id')->unique(); // ONE active meeting per coach
            $table->unsignedBigInteger('course_live_class_id')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_active_meetings');
    }
};
