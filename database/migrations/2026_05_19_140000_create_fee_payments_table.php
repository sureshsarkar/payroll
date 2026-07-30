<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fee Management module — Phase 4B (foundation).
 *
 * A FeePayment is ONE student's response to ONE fee_demand.
 * Captures both gateway-mediated payments (Razorpay etc.) and
 * coach-recorded offline payments (cash / cheque / "marked as paid").
 *
 * One demand can have many payments (partial payments, retries).
 * One student per demand is the typical case but we don't enforce
 * a unique constraint — we want to allow retries that previously
 * failed without manual cleanup.
 *
 * Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fee_payments')) {
            return;
        }

        Schema::create('fee_payments', function (Blueprint $table) {
            $table->id();

            // Which demand this payment belongs to.
            $table->unsignedBigInteger('fee_demand_id');
            $table->foreign('fee_demand_id')->references('id')->on('fee_demands')
                  ->onDelete('cascade');

            // Which student paid.
            $table->unsignedBigInteger('student_id');
            $table->foreign('student_id')->references('id')->on('users')
                  ->onDelete('cascade');

            // Receipt number — human-readable, unique. Format example:
            //   RCP-202605-7SP4CHGX
            $table->string('receipt_no', 64)->unique();

            // Amount actually received (can be < demand amount for
            // partial payments).
            $table->decimal('amount', 12, 2);

            // Payment channel.
            $table->enum('gateway', ['razorpay', 'stripe', 'manual', 'cash', 'cheque', 'bank_transfer', 'other'])
                  ->default('manual');

            // Gateway-side transaction id, if any. Empty for manual /
            // cash records.
            $table->string('gateway_txn_id', 128)->nullable();

            // Lifecycle:
            //   initiated → gateway flow started but not confirmed yet
            //   paid      → received, money on account
            //   failed    → gateway returned an error
            //   refunded  → coach refunded after success
            $table->enum('status', ['initiated', 'paid', 'failed', 'refunded'])
                  ->default('paid');

            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();

            // Coach-supplied note (e.g. cheque number, refund reason).
            $table->string('note', 500)->nullable();

            // Who recorded the payment (the coach, the student via
            // self-checkout, or a staff member).
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->foreign('recorded_by')->references('id')->on('users')
                  ->onDelete('set null');

            $table->timestamps();

            // Common access patterns:
            //   - list payments for a demand (coach view)
            //   - list payments for a student (student-side history)
            //   - filter dashboard by status
            $table->index(['fee_demand_id', 'status']);
            $table->index(['student_id', 'status', 'paid_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_payments');
    }
};
