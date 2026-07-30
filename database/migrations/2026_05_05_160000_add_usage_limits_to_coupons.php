<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coupon usage tracking.
 *
 * usage_limit  = max total uses of this coupon code (NULL = unlimited)
 * usage_count  = how many times it has been used so far (incremented atomically at checkout)
 * per_user_limit = max uses per single user (NULL = unlimited)
 *
 * Per-user enforcement requires a coupon_uses tracking table — added here as
 * `coupon_uses(coupon_id, user_id, order_id)` with a composite index so the
 * "has this user used this coupon already?" check is fast.
 */
return new class extends Migration {
    public function up(): void
    {
        // F42 (audit 2026-06-26) — guard each column so a re-run on a DB where
        // these were hot-fixed in doesn't fail with "Duplicate column" (1060).
        Schema::table('coupons', function (Blueprint $t) {
            if (! Schema::hasColumn('coupons', 'usage_limit'))    $t->unsignedInteger('usage_limit')->nullable()->after('status');
            if (! Schema::hasColumn('coupons', 'usage_count'))    $t->unsignedInteger('usage_count')->default(0)->after('usage_limit');
            if (! Schema::hasColumn('coupons', 'per_user_limit')) $t->unsignedInteger('per_user_limit')->nullable()->after('usage_count');
        });

        if (! Schema::hasTable('coupon_uses')) {
            Schema::create('coupon_uses', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('coupon_id');
                $t->unsignedBigInteger('user_id');
                $t->unsignedBigInteger('order_id')->nullable();
                $t->timestamps();
                $t->index(['coupon_id', 'user_id'], 'coupon_uses_coupon_user_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_uses');

        Schema::table('coupons', function (Blueprint $t) {
            $t->dropColumn(['usage_limit', 'usage_count', 'per_user_limit']);
        });
    }
};
