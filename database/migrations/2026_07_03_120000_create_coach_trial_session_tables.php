<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trial Session feature (2026-07-03) — per-coach "Book Your Trial Session" popup
 * with a dedicated, self-contained payment flow (kept OUT of the course-order
 * pipeline so a guest visitor can pay without a login and without touching
 * enrollments/batches/wallet). Four tables, all strictly coach-scoped:
 *
 *   coach_trial_settings  — one row per coach (popup config + price + gateway)
 *   coach_trial_slots     — coach-managed time-slot list (CRUD)
 *   coach_trial_enquiries — the visitor lead (all form fields + IP/UA audit)
 *   coach_trial_payments  — pending→paid record, amount set server-side only
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coach_trial_settings')) {
            Schema::create('coach_trial_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('coach_id');

                // Feature flag — when false the popup NEVER renders.
                $table->boolean('is_enabled')->default(false);

                // Display copy (coach-editable; sensible defaults applied in model).
                $table->string('title', 150)->nullable();
                $table->string('subtitle', 255)->nullable();
                $table->text('success_message')->nullable();

                // Pricing — server-authoritative. price 0 (or require_payment=false)
                // means the trial is a free lead (no gateway redirect).
                $table->decimal('price', 10, 2)->default(0);
                $table->string('currency', 8)->default('INR');
                $table->string('currency_icon', 8)->default('₹');
                $table->boolean('require_payment')->default(true);

                // Which gateway to charge on (nullable = platform default). v1 = razorpay.
                $table->string('payment_gateway', 40)->nullable()->default('razorpay');

                // Auto-show behaviour.
                $table->boolean('auto_show')->default(true);
                $table->unsignedSmallInteger('show_delay_seconds')->default(2);
                // session | daily | once_30d | always
                $table->string('show_frequency', 20)->default('session');

                $table->timestamps();

                $table->unique('coach_id');
                $table->foreign('coach_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('coach_trial_slots')) {
            Schema::create('coach_trial_slots', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('coach_id');
                // Free-form label matching the coach's mental model, e.g.
                // "05:00 AM - Mansi Rawat". Keeps slot management simple + white-label.
                $table->string('label', 190);
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['coach_id', 'is_active']);
                $table->foreign('coach_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('coach_trial_enquiries')) {
            Schema::create('coach_trial_enquiries', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('coach_id');
                // The coach landing-page/site id if resolvable (audit + super-admin).
                $table->unsignedBigInteger('website_id')->nullable();

                // Personal details.
                $table->string('name', 150);
                $table->string('email', 190);
                $table->string('mobile', 40);
                $table->string('gender', 20)->nullable();
                $table->string('height', 20)->nullable();
                $table->string('weight', 20)->nullable();

                // Plan selection.
                $table->string('plan_type', 20)->nullable();     // online | offline
                $table->string('course_type', 20)->nullable();   // individual | couple
                $table->unsignedBigInteger('slot_id')->nullable();
                $table->string('time_slot', 190)->nullable();     // label snapshot (durable)

                // Requirement.
                $table->string('reason', 30)->nullable();         // fitness | problem
                $table->text('problem_description')->nullable();

                // Price snapshot at submission time (audit; never trusted from client).
                $table->decimal('price', 10, 2)->default(0);
                $table->string('currency', 8)->default('INR');

                // Lifecycle.
                $table->string('status', 20)->default('pending');        // pending|contacted|closed|cancelled
                $table->string('payment_status', 20)->default('unpaid'); // unpaid|paid|failed|free

                // Audit.
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->string('source_page', 190)->nullable();
                $table->json('meta')->nullable();

                $table->timestamps();

                $table->index(['coach_id', 'status']);
                $table->index(['coach_id', 'payment_status']);
                $table->index(['coach_id', 'created_at']);
                $table->foreign('coach_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('coach_trial_payments')) {
            Schema::create('coach_trial_payments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('coach_id');
                $table->unsignedBigInteger('enquiry_id');

                $table->string('gateway', 40);
                $table->string('gateway_order_id', 120)->nullable(); // e.g. razorpay order_xxx
                $table->string('transaction_id', 120)->nullable();   // e.g. razorpay pay_xxx
                $table->decimal('amount', 10, 2);
                $table->string('currency', 8)->default('INR');
                $table->string('status', 20)->default('pending');    // pending|paid|failed|refunded

                // Gateway provenance (which coach's creds were used) — mirrors orders table.
                $table->string('gateway_owner_type', 32)->nullable();
                $table->unsignedBigInteger('gateway_config_id')->nullable();

                $table->text('payment_details')->nullable();          // JSON blob
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();

                $table->index(['coach_id', 'status']);
                $table->index('gateway_order_id');
                $table->foreign('coach_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('enquiry_id')->references('id')->on('coach_trial_enquiries')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_trial_payments');
        Schema::dropIfExists('coach_trial_enquiries');
        Schema::dropIfExists('coach_trial_slots');
        Schema::dropIfExists('coach_trial_settings');
    }
};
