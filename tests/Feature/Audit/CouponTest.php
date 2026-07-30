<?php

namespace Tests\Feature\Audit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Verifies the audit's coupon-usage-limit guarantees:
 *
 * 1. coupons table has the new `usage_limit`, `usage_count`, `per_user_limit` columns.
 * 2. coupon_uses tracking table exists with the right columns + index.
 * 3. The composite index on (coupon_id, user_id) is in place for fast per-user lookup.
 */
class CouponTest extends TestCase
{
    use DatabaseTransactions;

    public function test_coupons_table_has_new_audit_columns(): void
    {
        $columns = Schema::getColumnListing('coupons');
        $this->assertContains('usage_limit', $columns, 'coupons.usage_limit must exist (audit migration 2026_05_05_160000)');
        $this->assertContains('usage_count', $columns, 'coupons.usage_count must exist');
        $this->assertContains('per_user_limit', $columns, 'coupons.per_user_limit must exist');
    }

    public function test_coupon_uses_tracking_table_exists(): void
    {
        $this->assertTrue(
            Schema::hasTable('coupon_uses'),
            'coupon_uses table must exist (audit migration 2026_05_05_160000)'
        );
        $columns = Schema::getColumnListing('coupon_uses');
        foreach (['coupon_id', 'user_id', 'order_id'] as $c) {
            $this->assertContains($c, $columns, "coupon_uses.$c column missing");
        }
    }

    public function test_coupon_uses_has_composite_index_on_coupon_user(): void
    {
        $idx = collect(DB::select("SHOW INDEX FROM coupon_uses WHERE Key_name='coupon_uses_coupon_user_idx'"));
        $this->assertNotEmpty($idx, 'coupon_uses must have composite index on (coupon_id, user_id) for per-user limit lookup speed');
    }

    public function test_usage_count_default_is_zero(): void
    {
        // Schema integrity: existing coupons should default usage_count = 0,
        // not NULL or some other value, so the per-application increment math works.
        $defaults = collect(DB::select("SHOW COLUMNS FROM coupons WHERE Field='usage_count'"))->first();
        $this->assertNotNull($defaults);
        $this->assertSame('0', (string) $defaults->Default, 'usage_count must default to 0');
    }
}
