<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-06-13 — tax fields on courses + orders + order_items (Phase 1).
 *
 *  • courses.tax_rate_id — optional per-course rate; NULL → use the coach's
 *    default rate (or no tax if the coach hasn't enabled tax).
 *  • orders.*           — the tax SNAPSHOT captured at checkout (rate, amount,
 *    taxable base, mode, label, registration) so the invoice + reports are
 *    reproducible even if the coach later changes their rates.
 *  • order_items.tax_*  — per-line tax so multi-coach / multi-rate carts are
 *    itemised correctly.
 *
 * All columns are nullable / default 0 → existing rows + the no-tax path are
 * unchanged. Guarded (hasColumn) so it is safe to (re)run on prod.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            if (! Schema::hasColumn('courses', 'tax_rate_id')) {
                $table->unsignedBigInteger('tax_rate_id')->nullable()->after('discount');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'tax_amount')) {
                $table->decimal('tax_amount', 12, 2)->default(0)->after('payable_amount');
            }
            if (! Schema::hasColumn('orders', 'taxable_amount')) {
                $table->decimal('taxable_amount', 12, 2)->nullable()->after('tax_amount');
            }
            if (! Schema::hasColumn('orders', 'tax_rate_applied')) {
                $table->decimal('tax_rate_applied', 6, 3)->nullable()->after('taxable_amount');
            }
            if (! Schema::hasColumn('orders', 'tax_mode')) {
                $table->string('tax_mode')->nullable()->after('tax_rate_applied'); // exclusive|inclusive
            }
            if (! Schema::hasColumn('orders', 'tax_label')) {
                $table->string('tax_label')->nullable()->after('tax_mode'); // e.g. "GST 18%"
            }
            if (! Schema::hasColumn('orders', 'tax_registration')) {
                $table->string('tax_registration')->nullable()->after('tax_label'); // coach GSTIN snapshot
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'tax_amount')) {
                $table->decimal('tax_amount', 12, 2)->default(0)->after('price');
            }
            if (! Schema::hasColumn('order_items', 'tax_rate_applied')) {
                $table->decimal('tax_rate_applied', 6, 3)->nullable()->after('tax_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            foreach (['tax_amount', 'tax_rate_applied'] as $c) {
                if (Schema::hasColumn('order_items', $c)) $table->dropColumn($c);
            }
        });
        Schema::table('orders', function (Blueprint $table) {
            foreach (['tax_amount', 'taxable_amount', 'tax_rate_applied', 'tax_mode', 'tax_label', 'tax_registration'] as $c) {
                if (Schema::hasColumn('orders', $c)) $table->dropColumn($c);
            }
        });
        Schema::table('courses', function (Blueprint $table) {
            if (Schema::hasColumn('courses', 'tax_rate_id')) $table->dropColumn('tax_rate_id');
        });
    }
};
