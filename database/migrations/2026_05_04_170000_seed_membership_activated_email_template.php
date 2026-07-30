<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('email_templates')->updateOrInsert(
            ['name' => 'notif_membership_activated'],
            [
                'subject' => 'Your {{plan_name}} is now active',
                'message' => '<p>Hi {{user_name}},</p>
<p>Welcome aboard! Your <strong>{{plan_name}}</strong> membership is now active.</p>
<p><strong>Active until:</strong> {{expires_at}}</p>
<p><strong>Cash paid:</strong> {{cash_paid}}<br>
<strong>Wallet credit used:</strong> {{wallet_used}}</p>
<p>You can manage your membership anytime from the membership page in your dashboard.</p>',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('email_templates')->where('name', 'notif_membership_activated')->delete();
    }
};
