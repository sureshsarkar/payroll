<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fee Management module — Phase 4B (foundation).
 *
 * A FeeDemand is a coach-issued bill against a batch. Examples:
 *   - "Term 1 fee — Jan 2026"
 *   - "Monthly fee — Feb 2026"
 *   - "Exam fee — JEE Mock 2"
 *
 * Each demand applies to every active student in the batch. Coach
 * collection / refund flows live in fee_payments (sibling table).
 *
 * Idempotent: re-run is a no-op (skips if the table already exists).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fee_demands')) {
            return;
        }

        Schema::create('fee_demands', function (Blueprint $table) {
            $table->id();

            // Who raised the demand. Always points at a coach user
            // (role='instructor') — even when staff create demands
            // we attribute to their parent coach for visibility.
            $table->unsignedBigInteger('coach_id');
            $table->foreign('coach_id')->references('id')->on('users')
                  ->onDelete('cascade');

            // Which batch the demand applies to.
            $table->unsignedBigInteger('batch_id');
            $table->foreign('batch_id')->references('id')->on('course_batches')
                  ->onDelete('cascade');

            // Coach-supplied title — "Term 1 fee — Jan 2026" etc.
            $table->string('title', 255);

            // Currency-naive amount; default decimal precision matches
            // existing orders.paid_amount.
            $table->decimal('amount', 12, 2);

            // When the student is expected to have paid by. Nullable
            // for "pay-as-you-can" demands.
            $table->date('due_date')->nullable();

            // Late fine accrual (₹/day, default 0 = no late fee).
            $table->decimal('late_fine_per_day', 10, 2)->default(0);

            // Coach-supplied free-text notes.
            $table->text('notes')->nullable();

            // Lifecycle: draft (not visible to students) → published
            // (students can see and pay) → closed (demand fully
            // collected or coach manually closed).
            $table->enum('status', ['draft', 'published', 'closed'])
                  ->default('published');

            // Audit trail — which staff actually created it, for the
            // case where a delegated coach-staff (not the coach
            // themselves) raised the demand.
            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')
                  ->onDelete('set null');

            $table->timestamps();

            // Coach + batch are the most common scope; index for
            // ordered list pulls (Coach Fee dashboard).
            $table->index(['coach_id', 'batch_id', 'status']);
            $table->index(['batch_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_demands');
    }
};
