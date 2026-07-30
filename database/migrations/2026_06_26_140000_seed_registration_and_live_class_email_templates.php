<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-06-26 — email templates for the registration + live-class email
 * enhancements (Features 1–3):
 *   - notif_student_welcome          (StudentWelcomeToStudent)        — NEW
 *   - notif_new_student_registered   (NewStudentRegisteredToCoach)    — NEW
 *   - notif_live_class_starting_soon (LiveClassStartingSoonToStudent) — UPDATED
 *     to include class title / batch / date-time / meeting link / coach name.
 *
 * Idempotent — updateOrInsert keyed on `name`. The email itself is rendered
 * (coach-branded, tenant SMTP with platform fallback) by the
 * BrandedNotificationMail pipeline; these rows only supply the editable
 * subject + body. Placeholders match each notification's placeholders() map.
 */
return new class extends Migration
{
    private array $templates = [];

    public function __construct()
    {
        $this->templates = [
            [
                'name'    => 'notif_student_welcome',
                'subject' => 'Welcome to {{organization_name}}, {{student_name}}!',
                'message' => <<<HTML
<p>Hi {{student_name}},</p>

<p>Welcome to <strong>{{organization_name}}</strong>! Your student account has been created successfully and is ready to use.</p>

<p><strong>Your login email:</strong> {{student_email}}</p>

<p><strong>Next steps:</strong></p>
<ol>
  <li>Log in to your student panel using the button below.</li>
  <li>Browse and access your purchased or enrolled courses.</li>
  <li>Join live classes and track your progress from your dashboard.</li>
</ol>

<p>We're excited to have you on board. If you have any questions, just reply to this email.</p>
HTML,
            ],
            [
                'name'    => 'notif_new_student_registered',
                'subject' => 'New student registered — {{student_name}}',
                'message' => <<<HTML
<p>Hi {{coach_name}},</p>

<p>A new student just registered through your website (<strong>{{organization_name}}</strong>):</p>

<p><strong>{{student_name}}</strong><br>
{{student_email}}<br>
Registered: {{registered_at}}</p>

<p>Open your students dashboard to view their details and follow up.</p>
HTML,
            ],
            [
                'name'    => 'notif_live_class_starting_soon',
                'subject' => 'Live class in {{minutes}} min — {{class_title}}',
                'message' => <<<HTML
<p>Hi {{user_name}},</p>

<p>Your live class starts in <strong>{{minutes}} minutes</strong>. Here are the details:</p>

<p><strong>Live class:</strong> {{class_title}}<br>
<strong>Course:</strong> {{course_title}}<br>
<strong>Batch:</strong> {{batch_name}}<br>
<strong>Date &amp; time:</strong> {{starts_at}}<br>
<strong>Instructor:</strong> {{coach_name}}</p>

<p>Click the button below to join the class on time.</p>
HTML,
            ],
        ];
    }

    public function up(): void
    {
        if (!Schema::hasTable('email_templates')) {
            return;
        }

        foreach ($this->templates as $t) {
            DB::table('email_templates')->updateOrInsert(
                ['name' => $t['name']],
                [
                    'subject'    => $t['subject'],
                    'message'    => $t['message'],
                    'updated_at' => now(),
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                ]
            );
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('email_templates')) {
            return;
        }
        // Only remove the two NEW rows on rollback; leave the pre-existing
        // live-class template in place.
        DB::table('email_templates')
            ->whereIn('name', ['notif_student_welcome', 'notif_new_student_registered'])
            ->delete();
    }
};
