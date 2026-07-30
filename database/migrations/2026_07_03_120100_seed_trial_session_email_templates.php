<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed the two coach-editable email templates for the Trial Session flow
 * (2026-07-03). `notif_`-prefixed so coaches can override them per-site from the
 * Email Templates settings hub. Idempotent (updateOrInsert).
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
<p>Thank you for booking a trial session with <strong>{{coach_name}}</strong>. Here are your details:</p>
<table style="border-collapse:collapse;font-size:14px;">
  <tr><td style="padding:4px 12px 4px 0;color:#64748b;">Plan</td><td style="padding:4px 0;">{{plan_type}} &middot; {{course_type}}</td></tr>
  <tr><td style="padding:4px 12px 4px 0;color:#64748b;">Time slot</td><td style="padding:4px 0;">{{time_slot}}</td></tr>
  <tr><td style="padding:4px 12px 4px 0;color:#64748b;">Amount</td><td style="padding:4px 0;">{{amount}}</td></tr>
  <tr><td style="padding:4px 12px 4px 0;color:#64748b;">Payment</td><td style="padding:4px 0;">{{payment_status}}</td></tr>
</table>
<p>We will contact you shortly to confirm your session. See you on the mat!</p>
HTML;

        $coach = <<<HTML
<p>Hi {{coach_name}},</p>
<p>You have a new trial-session booking from your website.</p>
<table style="border-collapse:collapse;font-size:14px;">
  <tr><td style="padding:4px 12px 4px 0;color:#64748b;">Name</td><td style="padding:4px 0;">{{student_name}}</td></tr>
  <tr><td style="padding:4px 12px 4px 0;color:#64748b;">Email</td><td style="padding:4px 0;">{{student_email}}</td></tr>
  <tr><td style="padding:4px 12px 4px 0;color:#64748b;">Mobile</td><td style="padding:4px 0;">{{student_mobile}}</td></tr>
  <tr><td style="padding:4px 12px 4px 0;color:#64748b;">Plan</td><td style="padding:4px 0;">{{plan_type}} &middot; {{course_type}}</td></tr>
  <tr><td style="padding:4px 12px 4px 0;color:#64748b;">Time slot</td><td style="padding:4px 0;">{{time_slot}}</td></tr>
  <tr><td style="padding:4px 12px 4px 0;color:#64748b;">Reason</td><td style="padding:4px 0;">{{reason}}</td></tr>
  <tr><td style="padding:4px 12px 4px 0;color:#64748b;">Amount</td><td style="padding:4px 0;">{{amount}}</td></tr>
  <tr><td style="padding:4px 12px 4px 0;color:#64748b;">Payment</td><td style="padding:4px 0;">{{payment_status}}</td></tr>
</table>
<p>You can view and manage this enquiry from your coach panel.</p>
HTML;

        foreach ([
            ['name' => 'notif_trial_booking_to_student', 'subject' => 'Your trial session with {{coach_name}} is booked', 'message' => $student],
            ['name' => 'notif_trial_booking_to_coach',   'subject' => 'New trial booking: {{student_name}}',            'message' => $coach],
        ] as $tpl) {
            DB::table('email_templates')->updateOrInsert(
                ['name' => $tpl['name']],
                [
                    'subject'    => $tpl['subject'],
                    'message'    => $tpl['message'],
                    'updated_at' => now(),
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                ]
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('email_templates')) {
            DB::table('email_templates')->whereIn('name', [
                'notif_trial_booking_to_student',
                'notif_trial_booking_to_coach',
            ])->delete();
        }
    }
};
