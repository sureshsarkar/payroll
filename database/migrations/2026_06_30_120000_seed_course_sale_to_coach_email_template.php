<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed the default platform email template for the coach course-sale
 * notification (doc item 5, 2026-06-30). Any `notif_*` row automatically shows
 * up in Email Settings → Notification templates and is per-coach editable via
 * coach_email_templates (CoachEmailTemplate::override). Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_templates')) {
            return;
        }
        if (DB::table('email_templates')->where('name', 'notif_course_sale_to_coach')->exists()) {
            return;
        }

        $message = <<<HTML
<p>Hi {{coach_name}},</p>
<p>Good news — you just made a sale! A student has purchased one of your courses.</p>
<table style="border-collapse:collapse;width:100%;max-width:520px;margin:14px 0;font-size:14px;">
  <tr><td style="padding:6px 10px;color:#6b7280;">Student</td><td style="padding:6px 10px;font-weight:600;">{{student_name}}</td></tr>
  <tr><td style="padding:6px 10px;color:#6b7280;">Course</td><td style="padding:6px 10px;font-weight:600;">{{course_title}}</td></tr>
  <tr><td style="padding:6px 10px;color:#6b7280;">Order ID</td><td style="padding:6px 10px;">{{order_id}}</td></tr>
  <tr><td style="padding:6px 10px;color:#6b7280;">Date &amp; time</td><td style="padding:6px 10px;">{{purchased_at}}</td></tr>
  <tr><td style="padding:6px 10px;color:#6b7280;">Amount paid</td><td style="padding:6px 10px;font-weight:600;">{{amount}}</td></tr>
  <tr><td style="padding:6px 10px;color:#6b7280;">Payment status</td><td style="padding:6px 10px;">{{payment_status}}</td></tr>
</table>
<p>You can <a href="{{order_url}}">view the order</a> or <a href="{{student_url}}">see the enrolled student</a> in your coach panel.</p>
<p>Keep up the great work!</p>
HTML;

        DB::table('email_templates')->insert([
            'name'       => 'notif_course_sale_to_coach',
            'subject'    => 'New course sale: {{course_title}}',
            'message'    => $message,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('email_templates')) {
            DB::table('email_templates')->where('name', 'notif_course_sale_to_coach')->delete();
        }
    }
};
