<?php

namespace Modules\GlobalSetting\database\seeders;

use Illuminate\Database\Seeder;
use Modules\GlobalSetting\app\Models\EmailTemplate;

class EmailTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            [
                'name' => 'password_reset',
                'subject' => 'Password Reset',
                'message' => '<p>Dear {{user_name}},</p>
                <p>We received a request to reset your password. Click the button below to choose a new one.</p>
                <p style="color:#6b7280;font-size:13px;">This link will expire in 1 hour. If you did not request a password reset, you can safely ignore this email.</p>',
            ],
            [
                'name' => 'contact_mail',
                'subject' => 'Contact Email',
                'message' => '<p>Hello there,</p>
                <p>&nbsp;{{name}} has sent a new message. You can see the details below.&nbsp;</p>
                <p>Email: {{email}}</p>
                <p>Phone: {{phone}}</p>
                <p>Subject: {{subject}}</p>
                <p>Message: {{message}}</p>',
            ],
            [
                'name' => 'subscribe_notification',
                'subject' => 'Subscribe Notification',
                'message' => '<p>Hi there, Congratulations! Your Subscription has been created successfully. Please Click the following link and Verified Your Subscription. If you will not approve this link, you can not get any newsletter from us.</p>',
            ],

            [
                'name' => 'user_verification',
                'subject' => 'User Verification',
                'message' => '<p>Dear {{user_name}},</p>
                <p>Welcome! Your account has been created successfully. Please click the button below to activate your account.</p>',
            ],

            [
                'name' => 'approved_refund',
                'subject' => 'Refund Request Approval',
                'message' => '<p>Dear {{user_name}},</p>
                <p>We are happy to say that we have sent {{refund_amount}} to your provided bank information. </p>',
            ],

            [
                'name' => 'new_refund',
                'subject' => 'New Refund Request',
                'message' => '<p>Hello admin,</p>
                <p>{{user_name}} has submitted a new refund request. Please review it in the admin panel.</p>',
            ],

            [
                'name' => 'pending_wallet_payment',
                'subject' => 'Wallet Payment Approval',
                'message' => '<p>Hello {{user_name}},</p>
                <p>We have received your wallet payment request and will verify it against our bank account shortly.</p>
                <p>Thanks &amp; regards</p>',
            ],

            [
                'name' => 'approved_withdraw',
                'subject' => 'Withdraw Request Approval',
                'message' => '<p>Dear {{user_name}},</p>
                <p>We are happy to say that, we have send a withdraw amount to your provided bank information.</p>
                <p>Thanks &amp; Regards</p>',
            ],
            [
                'name' => 'rejected_withdraw',
                'subject' => 'Withdraw Request Rejected',
                'message' => '<p>Dear {{user_name}},</p>
                <p> your withdraw request has been rejected.</p>
                <p>Thanks &amp; Regards</p>',
            ],
            [
                'name' => 'pending_withdraw',
                'subject' => 'Withdraw Request Pending',
                'message' => '<p>Dear {{user_name}},</p>
                <p> your withdraw request is waiting for approval.</p>
                <p>Thanks &amp; Regards</p>',
            ],
            [
                'name' => 'instructor_request_approved',
                'subject' => 'Instructor Request Approval',
                'message' => '<p>Dear {{user_name}},</p>
                <p>you are now approved as an instructor.</p>',
            ],
            [
                'name' => 'instructor_request_rejected',
                'subject' => 'Instructor Request Rejected',
                'message' => '<p>Dear {{user_name}},</p>
                <p>your request has been rejected. please resubmit your request with proper document. or contact us.</p>',
            ],
            [
                'name' => 'instructor_request_pending',
                'subject' => 'Instructor Request is waiting for approval',
                'message' => '<p>Dear {{user_name}},</p>
                <p>your request for become an instructor is waiting for approval. please wait. we will send you an email when your request is approved.</p>',
            ],
            [
                'name' => 'instructor_quick_contact',
                'subject' => 'Mail for instructor contact form',
                'message' => '<p>Name: {{name}}</p>
                <p>Email: {{email}}</p>
                <p>Subject: {{subject}}</p>
                <p>{{message}}</p>',
            ],
            [
                'name' => 'order_completed',
                'subject' => 'Your order has been placed',
                'message' => '<p>Hi {{name}},</p>
                <p>Thank you for your purchase! Your order has been placed successfully.</p>
                <p><strong>Invoice ID:</strong> {{order_id}}</p>
                <p><strong>Amount paid:</strong> {{paid_amount}}</p>
                <p><strong>Payment method:</strong> {{payment_method}}</p>',
            ],
            [
                'name' => 'payment_status',
                'subject' => 'Update Payment Status',
                'message' => '<p>Hi {{name}},</p>
                <p>Here is an update on your order.</p>
                <p><strong>Invoice ID:</strong> {{order_id}}</p>
                <p><strong>Amount paid:</strong> {{paid_amount}}</p>
                <p><strong>Payment status:</strong> {{payment_status}}</p>',
            ],
            [
                'name' => 'qna_reply_mail',
                'subject' => 'QNA Replay mail',
                'message' => '<p>Hi {{user_name}}, your instructor has replied to your question. Please see the answer below:</p><p>Course: {{course}}</p><p>Lesson: {{lesson}}</p><p>Question: {{question}}</p>',
            ],
            [
                'name' => 'live_class_mail',
                'subject' => 'Live class notification mail',
                'message' => '<p>Hi {{user_name}},</p>
                <p>Your live class is starting at {{start_time}}. Please see the details below:</p>
                <p><strong>Course:</strong> {{course}}</p>
                <p><strong>Lesson:</strong> {{lesson}}</p>
                <p><strong>Meeting Link:</strong> <a href="{{join_url}}">{{join_url}}</a></p>',
            ],
            [
                'name' => 'gift_course',
                'subject' => 'Gift Course Notification',
                'message' => '<p>Hi {{name}},</p>
                <p>{{sender_name}} has gifted you a course! Click the link below to enroll and claim your course. <strong>Do not share this link with anyone.</strong></p>
                <p><strong>Claim Course:</strong> <a href="{{link}}">{{link}}</a></p>
                <p><strong>Visit Course:</strong> <a href="{{course_link}}">{{course_name}}</a></p>
                <p><strong>Sender Email:</strong> {{sender_email}}</p>
                <p><strong>Message from Sender:</strong> {{message}}</p>
                <p>Enjoy your learning!</p>',
            ]
        ];

        // 2026-07-10 (audit follow-up) — UPSERT by name instead of truncating.
        // The old EmailTemplate::truncate() wiped the WHOLE table, destroying the
        // migration-seeded notif_* templates (course sale, live class, trial,
        // payment-failed, fee, membership, …) if this seeder was ever re-run on a
        // live DB. Now each legacy template is inserted-or-updated by its (unique)
        // name, leaving every other row — including the notif_* family — intact.
        // Properties are set directly (the model's $fillable is empty).
        foreach ($templates as $template) {
            $row = EmailTemplate::where('name', $template['name'])->first() ?: new EmailTemplate();
            $row->name = $template['name'];
            $row->subject = $template['subject'];
            $row->message = $template['message'];
            $row->save();
        }
    }
}
