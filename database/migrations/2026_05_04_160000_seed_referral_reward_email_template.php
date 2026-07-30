<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('email_templates')->updateOrInsert(
            ['name' => 'notif_referral_reward'],
            [
                'subject' => 'You earned a referral reward — {{reward_amount}}',
                'message' => '<p>Hi {{user_name}},</p>
<p>Great news! <strong>{{referred_name}}</strong> just activated their membership using your referral code.</p>
<p>You have earned <strong>{{reward_amount}}</strong> in your referral wallet — apply it to your next membership purchase or renewal at checkout.</p>
<p>Keep sharing your link to earn more rewards.</p>',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('email_templates')->where('name', 'notif_referral_reward')->delete();
    }
};
