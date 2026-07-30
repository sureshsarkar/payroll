<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the "Coach Free Trial" membership plan.
 *
 * The trial mechanism reuses the regular MembershipPlan + UserMembership
 * tables — a $0 plan with a fixed slug 'coach-free-trial' that auto-grants
 * to new coach registrations. Admin can edit the plan (e.g. change the
 * duration) like any other plan, or set status=inactive to disable trials
 * entirely without code changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('membership_plans')->updateOrInsert(
            ['slug' => 'coach-free-trial'],
            [
                'name'          => 'Coach Free Trial',
                'role'          => 'instructor',
                'price'         => 0,
                'duration_days' => 15,
                'features'      => json_encode([
                    'Create courses',
                    'Schedule live classes',
                    'Invite staff',
                    'Full access to coach features for 15 days',
                ]),
                'status'        => 'active',
                'sort_order'    => -1, // surface at the top of any list
                'updated_at'    => now(),
                'created_at'    => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('membership_plans')->where('slug', 'coach-free-trial')->delete();
    }
};
