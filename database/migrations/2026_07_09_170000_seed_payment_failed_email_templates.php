<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-07-09 (Email/Notification audit — Phase 3.1).
 *
 * Default (platform) email bodies for the two new payment-failure notifications.
 * Rendered through the branded `emails.notification` wrapper; coaches may later
 * override via coach_email_templates. Idempotent — skips if the row exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_templates')) {
            return;
        }

        $rows = [
            [
                'name'    => 'notif_payment_failed_student',
                'subject' => 'Your payment didn\'t go through — order #{{order_id}}',
                'message' => <<<HTML
<p>Hi {{user_name}},</p>
<p>Unfortunately your payment of <strong>{{amount}}</strong> for order <strong>#{{order_id}}</strong> was not completed, so your order is still pending.</p>
<p>No money has been taken. You can try the payment again whenever you're ready.</p>
HTML,
            ],
            [
                'name'    => 'notif_payment_failed_coach',
                'subject' => 'A checkout didn\'t complete — order #{{order_id}}',
                'message' => <<<HTML
<p>Hi {{coach_name}},</p>
<p>A payment for order <strong>#{{order_id}}</strong> from <strong>{{student_name}}</strong> did not complete.</p>
<table cellpadding="0" cellspacing="0" style="margin:12px 0;">
  <tr><td style="padding:6px 10px;color:#6b7280;">Amount</td><td style="padding:6px 10px;font-weight:600;">{{amount}}</td></tr>
  <tr><td style="padding:6px 10px;color:#6b7280;">Order</td><td style="padding:6px 10px;font-weight:600;">#{{order_id}}</td></tr>
</table>
<p>The customer has been invited to retry. You can follow up from your orders if you'd like.</p>
HTML,
            ],
        ];

        foreach ($rows as $row) {
            if (! DB::table('email_templates')->where('name', $row['name'])->exists()) {
                DB::table('email_templates')->insert($row + ['created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('email_templates')) {
            DB::table('email_templates')->whereIn('name', ['notif_payment_failed_student', 'notif_payment_failed_coach'])->delete();
        }
    }
};
