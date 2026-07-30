<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed the coach-editable email template for a PAID Pricing & Plans booking
 * (2026-07-14). `notif_`-prefixed so coaches can override it per-site from the
 * Email Templates settings hub. Idempotent (updateOrInsert). If this template
 * is ever missing the notification degrades gracefully to the bell title/body.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_templates')) {
            return;
        }

        $coach = <<<HTML
<p>Hi {{coach_name}},</p>
<p>You received a new <strong>paid</strong> plan booking from your website.</p>
<table style="border-collapse:collapse;font-size:14px;">
  <tr><td style="padding:4px 12px 4px 0;color:#64748b;">Name</td><td style="padding:4px 0;">{{student_name}}</td></tr>
  <tr><td style="padding:4px 12px 4px 0;color:#64748b;">Email</td><td style="padding:4px 0;">{{student_email}}</td></tr>
  <tr><td style="padding:4px 12px 4px 0;color:#64748b;">Mobile</td><td style="padding:4px 0;">{{student_mobile}}</td></tr>
  <tr><td style="padding:4px 12px 4px 0;color:#64748b;">Plan</td><td style="padding:4px 0;">{{category}} &middot; {{course_type}} &middot; {{time_period}}</td></tr>
  <tr><td style="padding:4px 12px 4px 0;color:#64748b;">Amount paid</td><td style="padding:4px 0;"><strong>{{amount}}</strong></td></tr>
  <tr><td style="padding:4px 12px 4px 0;color:#64748b;">Gateway</td><td style="padding:4px 0;">{{gateway}}</td></tr>
  <tr><td style="padding:4px 12px 4px 0;color:#64748b;">Transaction ID</td><td style="padding:4px 0;">{{transaction_id}}</td></tr>
  <tr><td style="padding:4px 12px 4px 0;color:#64748b;">Paid on</td><td style="padding:4px 0;">{{paid_at}}</td></tr>
</table>
<p>You can view and manage this enquiry from your coach panel.</p>
HTML;

        DB::table('email_templates')->updateOrInsert(
            ['name' => 'notif_pricing_booking_paid_to_coach'],
            [
                'subject'    => 'Payment received: {{student_name}} — {{amount}}',
                'message'    => $coach,
                'updated_at' => now(),
                'created_at' => DB::raw('COALESCE(created_at, NOW())'),
            ]
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('email_templates')) {
            DB::table('email_templates')->where('name', 'notif_pricing_booking_paid_to_coach')->delete();
        }
    }
};
