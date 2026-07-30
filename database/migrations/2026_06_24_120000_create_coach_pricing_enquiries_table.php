<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-06-24 — leads captured from the website-builder "Pricing & Plans"
 * section's Book Class modal. One row per submission, scoped to the owning
 * coach. Couple-plan second person + any extra fields live in `details` JSON.
 * Idempotent so it's safe to re-run on prod.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('coach_pricing_enquiries')) {
            return;
        }
        Schema::create('coach_pricing_enquiries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coach_id')->index();   // owning coach
            $table->string('category', 120)->nullable();        // Online / Offline / ...
            $table->string('course_type', 40)->nullable();      // individual | couple
            $table->string('time_period', 80)->nullable();      // "3 Months"
            $table->string('price', 40)->nullable();
            $table->string('name', 150)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('mobile', 40)->nullable();
            $table->string('age', 10)->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('reason', 60)->nullable();
            $table->string('time_slot', 150)->nullable();
            $table->json('details')->nullable();                // problem desc, height, weight, person2 {...}
            $table->string('status', 20)->default('new');       // new | contacted | closed
            $table->timestamps();

            $table->index(['coach_id', 'status']);
            $table->index(['coach_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_pricing_enquiries');
    }
};
