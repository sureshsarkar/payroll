<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Offline Payment — Phase 1 (2026-07-11).
 *
 * ONE canonical, tenant-scoped ledger for every payment a coach records
 * manually (cash / bank transfer / UPI / cheque / other) WITHOUT the online
 * gateway. It is polymorphic over the thing being paid for:
 *   source_type = 'order' (course purchase) | 'fee' (fee demand) | 'trial'
 * and links to the domain row it created/settled (order_id / fee_payment_id).
 *
 * White-label / multi-tenant: EVERY row carries coach_id and every read is
 * scoped by it — a coach only ever sees their own offline payments, students,
 * receipts and proofs. Nothing here is specific to any one coach.
 *
 * Idempotent (hasTable/hasColumn guards) — production does NOT auto-migrate.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('offline_payments')) {
            Schema::create('offline_payments', function (Blueprint $table) {
                $table->id();

                // Tenant + parties
                $table->unsignedBigInteger('coach_id')->index();      // the tenant
                $table->unsignedBigInteger('student_id')->index();

                // What it pays for (polymorphic)
                $table->string('source_type', 20)->default('order');  // order | fee | trial
                $table->unsignedBigInteger('source_id')->nullable();   // FeeDemand id / trial id
                $table->unsignedBigInteger('course_id')->nullable();
                $table->unsignedBigInteger('batch_id')->nullable();

                // Money
                $table->decimal('amount', 12, 2)->default(0);
                $table->string('currency', 8)->nullable();
                $table->string('method', 24)->default('cash');        // cash|bank_transfer|upi|cheque|other
                $table->string('reference_no', 128)->nullable();      // UTR / cheque no / UPI ref
                $table->dateTime('paid_at')->nullable();              // when the money was received (back-datable)

                // Proof + context
                $table->string('proof_path', 512)->nullable();        // private disk path
                $table->string('proof_name', 255)->nullable();        // original filename
                $table->text('notes')->nullable();

                // Status + approval workflow
                $table->string('status', 16)->default('paid');        // paid|partial|pending
                $table->string('approval_status', 20)->default('auto_approved'); // auto_approved|pending|approved|rejected

                // Actors (audit-at-a-glance; full trail in activity_logs)
                $table->unsignedBigInteger('recorded_by');
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->dateTime('approved_at')->nullable();
                $table->unsignedBigInteger('cancelled_by')->nullable();
                $table->dateTime('cancelled_at')->nullable();
                $table->string('cancel_reason', 255)->nullable();

                // Links to the domain rows this settled
                $table->string('receipt_no', 40)->nullable();
                $table->unsignedBigInteger('order_id')->nullable();
                $table->unsignedBigInteger('fee_payment_id')->nullable();

                $table->timestamps();

                $table->index(['coach_id', 'status']);
                $table->index(['coach_id', 'approval_status']);
                $table->index(['source_type', 'source_id']);
            });
        }

        // Per-coach toggle: do offline payments need approval before they take
        // effect? Default OFF (auto-approve) so existing behaviour is unchanged.
        if (Schema::hasTable('coach_brand_settings')
            && ! Schema::hasColumn('coach_brand_settings', 'offline_payment_needs_approval')) {
            Schema::table('coach_brand_settings', function (Blueprint $table) {
                $table->boolean('offline_payment_needs_approval')->default(false);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_payments');
        if (Schema::hasTable('coach_brand_settings')
            && Schema::hasColumn('coach_brand_settings', 'offline_payment_needs_approval')) {
            Schema::table('coach_brand_settings', function (Blueprint $table) {
                $table->dropColumn('offline_payment_needs_approval');
            });
        }
    }
};
