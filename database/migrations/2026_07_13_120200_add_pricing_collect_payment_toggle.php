<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-07-13 — per-coach opt-in for collecting payment on plan bookings.
 *
 * Default FALSE so every existing coach's pricing-plans form stays a pure lead
 * form (zero behaviour change). When a coach turns this on AND the selected
 * plan resolves to a numeric price > 0, the booking flow redirects to the
 * gateway; otherwise it stays lead-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coach_brand_settings')) {
            return;
        }
        if (! Schema::hasColumn('coach_brand_settings', 'pricing_plan_collect_payment')) {
            Schema::table('coach_brand_settings', function (Blueprint $table) {
                $table->boolean('pricing_plan_collect_payment')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('coach_brand_settings') && Schema::hasColumn('coach_brand_settings', 'pricing_plan_collect_payment')) {
            Schema::table('coach_brand_settings', function (Blueprint $table) {
                $table->dropColumn('pricing_plan_collect_payment');
            });
        }
    }
};
