<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-06-13 — multi-component tax (Phase 2): a rate may split into named
 * components (e.g. CGST 9% + SGST 9%) shown separately on the invoice for full
 * GST compliance.
 *
 *  • tax_rates.components — JSON [{"name":"CGST","rate":9}, ...]; NULL = single rate.
 *  • orders.tax_components — JSON snapshot of the split actually charged, so the
 *    invoice stays reproducible if the coach later edits the rate.
 *
 * Both nullable → existing rows + single-rate behaviour unchanged. Guarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tax_rates', function (Blueprint $table) {
            if (! Schema::hasColumn('tax_rates', 'components')) {
                $table->json('components')->nullable()->after('rate');
            }
        });
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'tax_components')) {
                $table->json('tax_components')->nullable()->after('tax_label');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tax_rates', function (Blueprint $table) {
            if (Schema::hasColumn('tax_rates', 'components')) $table->dropColumn('components');
        });
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'tax_components')) $table->dropColumn('tax_components');
        });
    }
};
