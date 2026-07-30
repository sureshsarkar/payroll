<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-07-13 — coach_pricing_payments.
 *
 * One row per payment attempt against a pricing-plan enquiry. Mirrors
 * coach_trial_payments (the proven guest-Razorpay pattern): amount is set
 * server-side only; gateway provenance (owner_type + config_id) is stamped so
 * verification / webhook re-resolves the SAME merchant credentials. Status is
 * never 'paid' without server-side signature or webhook verification.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('coach_pricing_payments')) {
            return;
        }

        Schema::create('coach_pricing_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coach_id');
            $table->unsignedBigInteger('enquiry_id');

            $table->string('gateway', 40);
            $table->string('gateway_order_id', 120)->nullable();  // razorpay order_xxx
            $table->string('transaction_id', 120)->nullable();    // razorpay pay_xxx
            $table->decimal('amount', 10, 2);
            $table->string('currency', 8)->default('INR');
            // pending | paid | failed | cancelled | refunded
            $table->string('status', 20)->default('pending');

            // Gateway provenance (which coach's creds were used) — mirrors orders/trial.
            $table->string('gateway_owner_type', 32)->nullable();
            $table->unsignedBigInteger('gateway_config_id')->nullable();

            $table->text('payment_details')->nullable();          // JSON gateway response
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['coach_id', 'status']);
            $table->index('gateway_order_id');
            $table->index('enquiry_id');
            $table->foreign('coach_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('enquiry_id')->references('id')->on('coach_pricing_enquiries')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_pricing_payments');
    }
};
