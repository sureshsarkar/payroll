<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Role Permission Test doc (2026-07-06), issue A — "My Plan & Billing" and
 * Analytics were showing in the staff menu without a permission behind them.
 * Analytics already had an `analytics` slug in the catalog; My Plan did not, so
 * a coach could not gate it. Add the `my-plan` slug (idempotent) so it becomes
 * assignable via Roles and can be menu- + route-gated like every other module.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        foreach ([
            'my-plan' => 'My Plan & Billing',
        ] as $slug => $name) {
            $exists = DB::table('coach_staff_permissions')->where('slug', $slug)->exists();
            if (! $exists) {
                DB::table('coach_staff_permissions')->insert([
                    'name'       => $name,
                    'slug'       => $slug,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Append-only catalog; leave the slug in place on rollback.
    }
};
