<?php

namespace Database\Seeders;

use App\Models\MembershipPlan;
use Illuminate\Database\Seeder;

/**
 * 2026-06-24 — default Super-Admin coach pricing plans (MBS Guru pricing doc):
 * Starter / Medium / Enterprise. Idempotent (updateOrCreate by slug) so it can
 * be re-run on prod without duplicating; the Super Admin can edit values later.
 */
class CoachPricingPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'slug'  => 'starter',
                'name'  => 'Starter',
                'tier'  => MembershipPlan::TIER_STARTER,
                'role'  => 'instructor',
                'price' => 1499,              // monthly subscription
                'duration_days' => 30,
                'setup_fee' => 9999,
                'setup_fee_custom' => false,
                'platform_commission_rate' => 7,
                'commission_min_rate' => null,
                'commission_max_rate' => null,
                'student_capacity' => 500,
                'payout_required' => true,
                'direct_settlement' => false,
                'status' => 'active',
                'sort_order' => 1,
                'features' => [
                    'Up to 500 students',
                    '₹1,499 / month subscription',
                    '₹9,999 one-time setup fee',
                    '7% platform commission',
                    'Payout request based settlement',
                    'Ideal for individual coaches & small institutes',
                ],
            ],
            [
                'slug'  => 'medium',
                'name'  => 'Medium',
                'tier'  => MembershipPlan::TIER_MEDIUM,
                'role'  => 'instructor',
                'price' => 6999,
                'duration_days' => 30,
                'setup_fee' => 29999,
                'setup_fee_custom' => false,
                'platform_commission_rate' => 3,
                'commission_min_rate' => null,
                'commission_max_rate' => null,
                'student_capacity' => 5000,
                'payout_required' => true,
                'direct_settlement' => false,
                'status' => 'active',
                'sort_order' => 2,
                'features' => [
                    'Up to 5,000 students',
                    '₹6,999 / month subscription',
                    '₹29,999 one-time setup fee',
                    '3% platform commission',
                    'Payout request based settlement',
                    'For growing institutes needing scale',
                ],
            ],
            [
                'slug'  => 'enterprise',
                'name'  => 'Enterprise',
                'tier'  => MembershipPlan::TIER_ENTERPRISE,
                'role'  => 'instructor',
                'price' => 24999,            // ₹24,999/month onwards
                'duration_days' => 30,
                'setup_fee' => 0,
                'setup_fee_custom' => true,  // custom, based on requirement
                'platform_commission_rate' => 1, // within the 0–1% band
                'commission_min_rate' => 0,
                'commission_max_rate' => 1,
                'student_capacity' => null,  // unlimited
                'payout_required' => false,  // no payout request
                'direct_settlement' => true, // settle directly to coach bank
                'status' => 'active',
                'sort_order' => 3,
                'features' => [
                    'Unlimited students',
                    '₹24,999 / month onwards',
                    'Custom one-time setup fee',
                    '0% – 1% platform commission',
                    'Direct settlement to your bank — no payout requests',
                    'For large brands & multi-branch organisations',
                ],
            ],
        ];

        foreach ($plans as $p) {
            MembershipPlan::updateOrCreate(['slug' => $p['slug']], $p);
        }
    }
}
