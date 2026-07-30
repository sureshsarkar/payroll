<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-06-16 — repair migration for the coach-coupon feature.
 *
 * The coach coupon panel (CoachCouponController) + the per-coach coupon scope
 * read/write `coupons.coach_id` and the usage-limit columns. On any environment
 * that did not run the earlier coupon migrations (e.g. `2026_06_16_200000_add_
 * coach_id_to_coupons` or `2026_05_05_160000_add_usage_limits_to_coupons`), those
 * columns are MISSING — so `Coupon::where('coach_id', ...)` / saving a coupon
 * throws "Unknown column" → HTTP 500 on the coupon page.
 *
 * This guard ensures every needed column exists. Fully idempotent: each column
 * is added only if absent, so it is safe on environments that already have them.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coupons')) {
            return;
        }

        Schema::table('coupons', function (Blueprint $table) {
            if (! Schema::hasColumn('coupons', 'coach_id')) {
                $table->unsignedBigInteger('coach_id')->nullable()->after('author_id');
                $table->index('coach_id', 'coupons_coach_id_idx');
            }
            if (! Schema::hasColumn('coupons', 'min_price')) {
                $table->decimal('min_price', 10, 2)->default(0)->after('offer_percentage');
            }
            if (! Schema::hasColumn('coupons', 'usage_limit')) {
                $table->unsignedInteger('usage_limit')->nullable()->after('status');
            }
            if (! Schema::hasColumn('coupons', 'usage_count')) {
                $table->unsignedInteger('usage_count')->default(0)->after('usage_limit');
            }
            if (! Schema::hasColumn('coupons', 'per_user_limit')) {
                $table->unsignedInteger('per_user_limit')->nullable()->after('usage_count');
            }
        });

        // FK on coach_id → users (guarded; SET NULL so deleting a coach degrades
        // their coupon to an orphan rather than cascading away financial config).
        try {
            Schema::table('coupons', function (Blueprint $table) {
                $table->foreign('coach_id', 'coupons_coach_id_fk')
                    ->references('id')->on('users')->onDelete('set null');
            });
        } catch (\Throwable $e) {
            // constraint already present / unsupported engine — safe to ignore
        }
    }

    public function down(): void
    {
        // Non-destructive: leave the columns in place (other code now depends on
        // them). Dropping them would re-break the coupon feature.
    }
};
