<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('email_templates')->updateOrInsert(
            ['name' => 'notif_trial_expiring'],
            [
                'subject' => 'Your free trial ends in {{days_left}} day(s)',
                'message' => '<p>Hi {{user_name}},</p>
<p>Your <strong>Coach Free Trial</strong> ends on <strong>{{expires_at}}</strong> ({{days_left}} day(s) from now).</p>
<p>To keep creating courses, scheduling live classes, and using the rest of the coach toolkit, pick a plan from your membership page.</p>
<p>If you applied a referral code at signup, you may have wallet credit you can use at checkout.</p>',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('email_templates')->where('name', 'notif_trial_expiring')->delete();
    }
};
