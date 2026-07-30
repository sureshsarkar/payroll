<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-07-15 — Trainer session-packages (Phase 1). Each package is scoped to a
 * trainer AND its coach (coach_id denormalised for a cheap tenant gate). Prices
 * are server-authoritative: the booking flow re-reads price/validity from here,
 * never from the client. Idempotent + guarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('trainer_session_packages')) {
            return;
        }

        Schema::create('trainer_session_packages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('trainer_id');
            $table->unsignedBigInteger('coach_id');                // denormalised tenant gate
            $table->string('name', 120);
            $table->unsignedInteger('sessions')->default(1);       // number of sessions
            $table->unsignedInteger('validity_value')->default(30);
            $table->string('validity_unit', 12)->default('days');  // days | weeks | months
            $table->decimal('price', 10, 2)->default(0);
            $table->string('currency', 8)->default('INR');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['trainer_id', 'is_active']);
            $table->index('coach_id');
            $table->foreign('trainer_id')->references('id')->on('coach_trainers')->cascadeOnDelete();
            $table->foreign('coach_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trainer_session_packages');
    }
};
