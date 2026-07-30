<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed the coach-editable submit-time email templates for "Book a Session"
 * (2026-07-14) — sent to coach + student the moment the enquiry is created,
 * carrying the current payment status. Idempotent; missing templates degrade
 * to the bell title/body.
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
<p>You have a new booking enquiry from your website.</p>
<table style="border-collapse:collapse;font-size:14px;">
  <tr><td style="padding:4px 12px 4px 0;color:#64748b;">Name</td><td style="padding:4px 0;">{{student_name}}</td></tr>
  <tr><td style="padding:4px 12px 4px 0;color:#64748b;">Email</td><td style="padding:4px 0;">{{student_email}}</td></tr>
  <tr><td style="padding:4px 12px 4px 0;color:#64748b;">Mobile</td><td style="padding:4px 0;">{{student_mobile}}</td></tr>
  <tr><td style="padding:4px 12px 4px 0;color:#64748b;">Class</td><td style="padding:4px 0;">{{class_name}} &middot; {{slot}} &middot; {{trainer}}</td></tr>
  <tr><td style="padding:4px 12px 4px 0;color:#64748b;">Plan</td><td style="padding:4px 0;">{{plan_type}} &middot; {{course_type}} &middot; {{time_period}}</td></tr>
  <tr><td style="padding:4px 12px 4px 0;color:#64748b;">Amount</td><td style="padding:4px 0;">{{amount}}</td></tr>
  <tr><td style="padding:4px 12px 4px 0;color:#64748b;">Payment status</td><td style="padding:4px 0;"><strong>{{payment_status}}</strong></td></tr>
</table>
<p>You can manage this enquiry from your coach panel.</p>
HTML;

        $student = <<<HTML
<p>Hi {{student_name}},</p>
<p>Thank you — your booking request with <strong>{{coach_name}}</strong> has been received.</p>
<table style="border-collapse:collapse;font-size:14px;">
  <tr><td style="padding:4px 12px 4px 0;color:#64748b;">Class</td><td style="padding:4px 0;">{{class_name}} &middot; {{slot}}</td></tr>
  <tr><td style="padding:4px 12px 4px 0;color:#64748b;">Plan</td><td style="padding:4px 0;">{{plan_type}} &middot; {{course_type}} &middot; {{time_period}}</td></tr>
  <tr><td style="padding:4px 12px 4px 0;color:#64748b;">Amount</td><td style="padding:4px 0;">{{amount}}</td></tr>
  <tr><td style="padding:4px 12px 4px 0;color:#64748b;">Payment status</td><td style="padding:4px 0;"><strong>{{payment_status}}</strong></td></tr>
</table>
<p>If a payment is pending, you can complete it from the booking window. We will be in touch shortly.</p>
HTML;

        foreach ([
            ['name' => 'notif_booking_enquiry_to_coach',   'subject' => 'New booking: {{student_name}} ({{payment_status}})', 'message' => $coach],
            ['name' => 'notif_booking_enquiry_to_student', 'subject' => 'Your booking with {{coach_name}} — {{payment_status}}', 'message' => $student],
        ] as $tpl) {
            DB::table('email_templates')->updateOrInsert(
                ['name' => $tpl['name']],
                ['subject' => $tpl['subject'], 'message' => $tpl['message'], 'updated_at' => now(), 'created_at' => DB::raw('COALESCE(created_at, NOW())')]
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('email_templates')) {
            DB::table('email_templates')->whereIn('name', ['notif_booking_enquiry_to_coach', 'notif_booking_enquiry_to_student'])->delete();
        }
    }
};
