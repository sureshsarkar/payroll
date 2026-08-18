<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-tenant conversion — Phase 1 (additive).
 *
 * A "Company" is the tenant boundary for the HR/Attendance/Leave/Payroll
 * domain. One HR (users.role='instructor') can own several companies; every
 * HR-domain record belongs to exactly one company. Platform-level table, so it
 * lives in the root migrations set (not a module) and always migrates.
 *
 * `owner_user_id` records who registered it, but all access checks go through
 * the `company_user` pivot — keeping the door open for multiple HR staff later.
 * Leaves a clean extension point for a future `subscription_id` (out of scope).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique()->comment('URL/reference handle, not a subdomain');
            $table->string('code', 60)->nullable()->unique()->comment('internal reference code');
            $table->unsignedBigInteger('owner_user_id')->comment('users.id of the HR who registered it');
            $table->string('industry')->nullable();
            $table->string('timezone', 64)->default('Asia/Kolkata');
            $table->string('status', 20)->default('active')->comment('active|suspended');

            // Basic company profile (all optional)
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('postal_code', 20)->nullable();

            $table->timestamps();

            $table->index('owner_user_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
