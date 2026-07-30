<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-07-13 — Pricing & Plans booking → payment.
 *
 * The pricing-plans enquiry was lead-only. When a coach opts in to collect
 * payment (coach_brand_settings.pricing_plan_collect_payment) and the selected
 * plan resolves to a numeric price > 0, the visitor is charged. These columns
 * hold the server-resolved plan amount + the enquiry-level payment snapshot.
 * The authoritative amount is re-derived from the coach's stored section
 * content (never the posted price) — see PricingPaymentService.
 *
 * All additive + guarded so it is safe to re-run on prod (no auto-migrate).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coach_pricing_enquiries')) {
            return;
        }

        Schema::table('coach_pricing_enquiries', function (Blueprint $table) {
            if (! Schema::hasColumn('coach_pricing_enquiries', 'section_id')) {
                // The landing_sections row the plan was booked from (audit + the
                // exact source for server-side amount re-resolution).
                $table->unsignedBigInteger('section_id')->nullable()->after('coach_id');
            }
            if (! Schema::hasColumn('coach_pricing_enquiries', 'website_id')) {
                // CoachLandingPage id (the coach's built site) — matches the trial flow.
                $table->unsignedBigInteger('website_id')->nullable()->after('section_id');
            }
            if (! Schema::hasColumn('coach_pricing_enquiries', 'plan_amount')) {
                // Server-resolved authoritative amount for the selected plan/period.
                $table->decimal('plan_amount', 10, 2)->nullable()->after('price');
            }
            if (! Schema::hasColumn('coach_pricing_enquiries', 'currency')) {
                $table->string('currency', 8)->nullable()->default('INR')->after('plan_amount');
            }
            if (! Schema::hasColumn('coach_pricing_enquiries', 'payment_status')) {
                // unpaid | paid | pending | failed | cancelled  (default: unpaid)
                $table->string('payment_status', 20)->default('unpaid')->after('currency');
            }
            if (! Schema::hasColumn('coach_pricing_enquiries', 'paid_amount')) {
                $table->decimal('paid_amount', 10, 2)->nullable()->after('payment_status');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('coach_pricing_enquiries')) {
            return;
        }
        Schema::table('coach_pricing_enquiries', function (Blueprint $table) {
            foreach (['section_id', 'website_id', 'plan_amount', 'currency', 'payment_status', 'paid_amount'] as $col) {
                if (Schema::hasColumn('coach_pricing_enquiries', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
