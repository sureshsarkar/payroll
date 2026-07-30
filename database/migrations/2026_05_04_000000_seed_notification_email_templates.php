<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seeds editable email-template rows for every notification class added in
 * the comprehensive notification suite. Idempotent — uses upsert by `name`.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $templates = [
            [
                'name'    => 'notif_course_approved',
                'subject' => 'Your course "{{course_title}}" has been approved',
                'message' => '<p>Hi {{user_name}},</p>
<p>Great news — your course <strong>{{course_title}}</strong> has been approved by our team and is now live and visible to students.</p>
<p>You can view it from your dashboard.</p>
<p>Thanks for teaching with us!</p>',
            ],
            [
                'name'    => 'notif_course_rejected',
                'subject' => 'Update on your course "{{course_title}}"',
                'message' => '<p>Hi {{user_name}},</p>
<p>Your course <strong>{{course_title}}</strong> has been marked as <strong>{{status}}</strong> by our review team.</p>
<p>Please open the course from your dashboard to see the feedback and resubmit when ready.</p>',
            ],
            [
                'name'    => 'notif_new_enrollment',
                'subject' => 'New student enrolled in {{course_title}}',
                'message' => '<p>Hi {{coach_name}},</p>
<p><strong>{{student_name}}</strong> just enrolled in your course <strong>{{course_title}}</strong>.</p>
<p>You can see your full sales list from your coach dashboard.</p>',
            ],
            [
                'name'    => 'notif_course_completed',
                'subject' => 'Course completed: {{course_title}}',
                'message' => '<p>Hi {{user_name}},</p>
<p>Congratulations on finishing <strong>{{course_title}}</strong>! 🎉</p>
<p>Your certificate is ready to download from your enrolled courses page.</p>',
            ],
            [
                'name'    => 'notif_quiz_result',
                'subject' => '{{quiz_title}} — your result',
                'message' => '<p>Hi {{user_name}},</p>
<p>Your attempt on <strong>{{quiz_title}}</strong> has been graded.</p>
<p><strong>Score:</strong> {{score}} / {{total}}<br>
<strong>Status:</strong> {{status}}</p>
<p>Open the result page for the full breakdown.</p>',
            ],
            [
                'name'    => 'notif_live_class_scheduled',
                'subject' => 'New Live Class Scheduled: {{lesson_title}}',
                'message' => '<p>Dear {{user_name}},</p>
<p>A new live class has been scheduled for your enrolled batch.</p>
<p><strong>Course:</strong> {{course_title}}<br>
<strong>Batch:</strong> {{batch_name}}<br>
<strong>Live Class:</strong> {{lesson_title}}<br>
<strong>Date &amp; Time:</strong> {{start_time}}<br>
<strong>Coach:</strong> {{coach_name}}</p>
<p>Please join the class on time from your student panel using the button below.</p>',
            ],
            [
                'name'    => 'notif_live_class_starting_soon',
                'subject' => 'Live class starting in {{minutes}} min — {{course_title}}',
                'message' => '<p>Hi {{user_name}},</p>
<p>Your live class for <strong>{{course_title}}</strong> starts in <strong>{{minutes}} minutes</strong>.</p>
<p>Click the button below to join.</p>',
            ],
            [
                'name'    => 'notif_refund_approved',
                'subject' => 'Refund approved — order #{{order_id}}',
                'message' => '<p>Hi {{user_name}},</p>
<p>Your refund of <strong>{{refund_amount}}</strong> for order <strong>#{{order_id}}</strong> has been approved and is now being processed.</p>
<p>You should see the funds back in your account within a few business days.</p>',
            ],
            [
                'name'    => 'notif_refund_rejected',
                'subject' => 'Refund request not approved — order #{{order_id}}',
                'message' => '<p>Hi {{user_name}},</p>
<p>We were unable to approve your refund request for order <strong>#{{order_id}}</strong>.</p>
<p><strong>Reason:</strong> {{reason}}</p>
<p>{{description}}</p>
<p>If you have questions please reply to this email.</p>',
            ],
            [
                'name'    => 'notif_new_refund_request',
                'subject' => 'New refund request from {{student_name}}',
                'message' => '<p>Hi Admin,</p>
<p><strong>{{student_name}}</strong> has submitted a new refund request for order <strong>#{{order_id}}</strong>.</p>
<p>Open the request from your admin panel to review and approve or reject.</p>',
            ],
            [
                'name'    => 'notif_withdrawal_approved',
                'subject' => 'Payout approved — {{amount}}',
                'message' => '<p>Hi {{user_name}},</p>
<p>Your payout of <strong>{{amount}}</strong> has been approved and is on its way to your bank.</p>
<p>You can review your payout history from the payouts page.</p>',
            ],
            [
                'name'    => 'notif_withdrawal_rejected',
                'subject' => 'Payout request not approved — {{amount}}',
                'message' => '<p>Hi {{user_name}},</p>
<p>Your payout request of <strong>{{amount}}</strong> has been rejected.</p>
<p>Open the payouts page for more information or contact support.</p>',
            ],
            [
                'name'    => 'notif_new_withdrawal_request',
                'subject' => 'New payout request from {{coach_name}}',
                'message' => '<p>Hi Admin,</p>
<p><strong>{{coach_name}}</strong> has requested a payout of <strong>{{amount}}</strong>.</p>
<p>Open the withdraw list from your admin panel to review and process.</p>',
            ],
            [
                'name'    => 'notif_payment_due',
                'subject' => 'Reminder — order #{{order_id}} payment pending',
                'message' => '<p>Hi {{user_name}},</p>
<p>Your order <strong>#{{order_id}}</strong> has been waiting for payment for <strong>{{days_open}} day(s)</strong>.</p>
<p>Complete the payment to activate your enrollment and start learning.</p>',
            ],
        ];

        foreach ($templates as $t) {
            DB::table('email_templates')->updateOrInsert(
                ['name' => $t['name']],
                [
                    'subject'    => $t['subject'],
                    'message'    => $t['message'],
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('email_templates')->where('name', 'like', 'notif_%')->delete();
    }
};
