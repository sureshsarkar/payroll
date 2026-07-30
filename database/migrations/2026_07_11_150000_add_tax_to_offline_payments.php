<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Offline Payment — tax on receipts (2026-07-11).
 *
 * The coach enters the GROSS amount received; we store the tax-inclusive split
 * (base + tax) using the coach's tax profile, so the receipt + reports can show
 * "of which <GST/VAT> …". NULL tax = no tax profile (backward compatible).
 * Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('offline_payments')) {
            return;
        }
        Schema::table('offline_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('offline_payments', 'base_amount')) {
                $table->decimal('base_amount', 12, 2)->nullable()->after('amount');
            }
            if (! Schema::hasColumn('offline_payments', 'tax_amount')) {
                $table->decimal('tax_amount', 12, 2)->nullable()->after('base_amount');
            }
            if (! Schema::hasColumn('offline_payments', 'tax_rate')) {
                $table->decimal('tax_rate', 6, 2)->nullable()->after('tax_amount');
            }
            if (! Schema::hasColumn('offline_payments', 'tax_label')) {
                $table->string('tax_label', 60)->nullable()->after('tax_rate');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('offline_payments')) {
            return;
        }
        Schema::table('offline_payments', function (Blueprint $table) {
            foreach (['base_amount', 'tax_amount', 'tax_rate', 'tax_label'] as $c) {
                if (Schema::hasColumn('offline_payments', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
