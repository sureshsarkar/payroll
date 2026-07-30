<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            // Which role this plan is for. 'all' = available to both. We use enum values
            // matching users.role plus a catch-all so the same plan can be sold to
            // either a student or a coach.
            $table->enum('role', ['student', 'instructor', 'all'])->default('all');
            $table->decimal('price', 10, 2)->default(0);
            $table->unsignedInteger('duration_days')->default(30); // 30 / 90 / 365 / 0=lifetime
            $table->json('features')->nullable(); // free-form bullet list shown on plan card
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['role', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_plans');
    }
};
