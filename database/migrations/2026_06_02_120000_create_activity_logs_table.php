<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enterprise H-A — Activity / Audit log.
 *
 * One row per audited action (login, CRUD, status/payment/commission/role
 * changes, exports). Additive table only — no change to existing tables, so
 * it is safe and fully reversible (down drops just this table).
 *
 * Actor is intentionally NOT a foreign key: the actor may be an Admin (admin
 * guard) OR a User (web guard), and we denormalize actor_name/role so the log
 * remains meaningful even if the account is later deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('activity_logs')) {
            return;
        }
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->string('actor_type', 32)->nullable();   // admin | user | system
            $table->string('actor_name')->nullable();        // denormalized for display
            $table->string('actor_role', 64)->nullable();
            $table->string('action', 64)->index();           // login, logout, login_failed, created, updated, deleted, status_changed, payment_status_changed, exported, ...
            $table->string('module', 64)->nullable()->index();
            $table->string('subject_type')->nullable();      // affected model class
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('description', 500)->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['actor_type', 'actor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
