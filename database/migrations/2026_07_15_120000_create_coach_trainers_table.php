<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-07-15 — Trainer entity (Phase 1 of the "Book Personal Class Session"
 * feature). A per-coach, tenant-scoped trainer profile with a slug for the
 * public Trainer Detail Page. Distinct from the freeform `instructor` string on
 * a schedule card and from the platform marketplace instructor (a User).
 * Idempotent + guarded so it is safe to re-run on prod (no auto-migrate).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('coach_trainers')) {
            return;
        }

        Schema::create('coach_trainers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coach_id');
            $table->unsignedBigInteger('website_id')->nullable();  // CoachLandingPage id
            $table->string('name', 150);
            $table->string('slug', 190);
            $table->string('photo', 255)->nullable();
            $table->string('specialisation', 190)->nullable();
            $table->string('experience', 60)->nullable();          // e.g. "12 yrs"
            $table->text('bio')->nullable();
            $table->json('tags')->nullable();                      // specialisation chips
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['coach_id', 'slug'], 'coach_trainer_slug_unique');
            $table->index(['coach_id', 'is_active']);
            $table->foreign('coach_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_trainers');
    }
};
