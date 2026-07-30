<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-06-22 — tracks the devices/locations a user signs in from, so the
 * platform can email a security alert the first time a sign-in comes from a
 * NEW device (login-alert feature). The very first device per user is recorded
 * silently as the baseline (no alert).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_login_devices')) {
            return;
        }
        Schema::create('user_login_devices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('fingerprint', 64);
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_login_devices');
    }
};
