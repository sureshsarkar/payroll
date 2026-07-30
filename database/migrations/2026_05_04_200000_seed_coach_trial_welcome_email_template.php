<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('email_templates')->updateOrInsert(
            ['name' => 'notif_coach_trial_welcome'],
            [
                'subject' => 'Welcome to coaching — your {{trial_days}}-day free trial is live',
                'message' => '<p>Hi {{user_name}},</p>
<p>Your <strong>{{trial_days}}-day Coach Free Trial</strong> is now active. You have full access to:</p>
<ul>
  <li>Course creation + chapter management</li>
  <li>Live class scheduling (Zoom / YouTube)</li>
  <li>Coach analytics + sales tracking</li>
  <li>Coach-staff invites + permissions</li>
</ul>
<p>Your trial expires on <strong>{{expires_at}}</strong>. We will remind you 3 days before so nothing surprises you.</p>
<p>Tip: share your referral link with friends — when they activate a paid plan you earn wallet credit you can apply at your own checkout.</p>',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('email_templates')->where('name', 'notif_coach_trial_welcome')->delete();
    }
};
