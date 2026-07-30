<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seeds three buyable plans so a fresh installation has paid inventory ready
 * the moment a coach's free trial ends. Idempotent (skip-by-slug). Admin can
 * edit/delete/disable any of these freely — they're regular MembershipPlan
 * rows, not hardcoded.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            [
                'slug'  => 'coach-monthly',
                'name'  => 'Coach Monthly',
                'role'  => 'instructor',
                'price' => 29.00,
                'duration_days' => 30,
                'features' => [
                    'All trial features',
                    'Unlimited course creation',
                    'Unlimited live classes',
                    'Coach analytics dashboard',
                    'Coach-staff invites',
                    'Custom course pages',
                    'Email + chat support',
                ],
                'sort_order' => 10,
            ],
            [
                'slug'  => 'coach-yearly',
                'name'  => 'Coach Yearly',
                'role'  => 'instructor',
                'price' => 299.00,
                'duration_days' => 365,
                'features' => [
                    'Everything in Monthly',
                    '2 months free vs. monthly billing',
                    'Priority email support',
                    'Locked-in pricing for the year',
                ],
                'sort_order' => 20,
            ],
            [
                'slug'  => 'coach-lifetime',
                'name'  => 'Coach Lifetime',
                'role'  => 'instructor',
                'price' => 999.00,
                'duration_days' => 0, // 0 = lifetime
                'features' => [
                    'Everything in Yearly',
                    'One-time payment, never expires',
                    'All future feature releases included',
                ],
                'sort_order' => 30,
            ],
        ];

        foreach ($rows as $r) {
            DB::table('membership_plans')->updateOrInsert(
                ['slug' => $r['slug']],
                [
                    'name'          => $r['name'],
                    'role'          => $r['role'],
                    'price'         => $r['price'],
                    'duration_days' => $r['duration_days'],
                    'features'      => json_encode($r['features']),
                    'status'        => 'active',
                    'sort_order'    => $r['sort_order'],
                    'updated_at'    => now(),
                    'created_at'    => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('membership_plans')->whereIn('slug', ['coach-monthly', 'coach-yearly', 'coach-lifetime'])->delete();
    }
};
