<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-06-29 — Coach-specific payment gateway configuration.
 *
 * Lets eligible Enterprise coaches (self-managed) and the Super-Admin
 * (on behalf of any coach) receive course payments into their OWN merchant
 * accounts. Fully additive + idempotent: when no row exists for a coach the
 * checkout falls back to the existing global Super-Admin gateway, so current
 * behaviour is unchanged.
 *
 * `manager_type` separates the two non-default priority tiers so both can
 * coexist for the same coach+gateway:
 *   'coach' = coach self-managed (Enterprise only)  -> owner_type coach_self_managed
 *   'admin' = Super-Admin configured for this coach  -> owner_type super_admin_for_coach
 * unique(coach_id, gateway, manager_type); the resolver prefers coach > admin > global.
 *
 * `credentials` is an ENCRYPTED JSON blob (encrypted:array cast on the model),
 * so secret keys / API secrets / webhook secrets are encrypted at rest.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('coach_payment_gateways')) {
            return;
        }

        Schema::create('coach_payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coach_id')->index();
            $table->string('gateway', 40);                       // canonical lowercase name e.g. 'razorpay'
            $table->string('manager_type', 10)->default('coach'); // 'coach' | 'admin'
            $table->string('status', 12)->default('inactive');    // 'active' | 'inactive'
            $table->decimal('charge', 8, 2)->default(0);
            $table->unsignedBigInteger('currency_id')->nullable();
            $table->string('image')->nullable();
            $table->text('credentials')->nullable();             // ENCRYPTED JSON (encrypted:array)
            $table->timestamps();

            $table->unique(['coach_id', 'gateway', 'manager_type'], 'coach_gateway_owner_unique');
            $table->foreign('coach_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_payment_gateways');
    }
};
