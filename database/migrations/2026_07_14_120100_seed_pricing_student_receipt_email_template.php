<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed the coach-editable "paid receipt" email template sent to the visitor
 * after a Pricing & Plans booking payment succeeds (2026-07-14). `notif_`-
 * prefixed so coaches can override it per-site. Idempotent (updateOrInsert);
 * a missing template degrades gracefully to the bell title/body.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_templates')) {
            return;
        }

        $student = <<<HTML
<p>Hi {{student_name}},</p>
<p>Thank you — your payment to <strong>{{coach_name}}</strong> was received. Here is your receipt:</p>
<table style="border-collapse:collapse;font-size:14px;">
  <tr><td style="padding:4px 12px 4px 0;color:#64748b;">Plan</td><td style="padding:4px 0;">{{category}} &middot; {{course_type}} &middot; {{time_period}}</td></tr>
  <tr><td style="padding:4px 12px 4px 0;color:#64748b;">Amount paid</td><td style="padding:4px 0;"><strong>{{amount}}</strong></td></tr>
  <tr><td style="padding:4px 12px 4px 0;color:#64748b;">Payment method</td><td style="padding:4px 0;">{{gateway}}</td></tr>
  <tr><td style="padding:4px 12px 4px 0;color:#64748b;">Transaction ID</td><td style="padding:4px 0;">{{transaction_id}}</td></tr>
  <tr><td style="padding:4px 12px 4px 0;color:#64748b;">Paid on</td><td style="padding:4px 0;">{{paid_at}}</td></tr>
</table>
<p>We will be in touch shortly to confirm your booking. Please keep this receipt for your records.</p>
HTML;

        DB::table('email_templates')->updateOrInsert(
            ['name' => 'notif_pricing_booking_receipt_to_student'],
            [
                'subject'    => 'Payment receipt — {{amount}} to {{coach_name}}',
                'message'    => $student,
                'updated_at' => now(),
                'created_at' => DB::raw('COALESCE(created_at, NOW())'),
            ]
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('email_templates')) {
            DB::table('email_templates')->where('name', 'notif_pricing_booking_receipt_to_student')->delete();
        }
    }
};
