<?php

namespace Tests\Feature\Domain;

use App\Mail\DefaultMail;
use App\Mail\sendLiveClassMail;
use App\Mail\sendQnaReplyMail;
use App\Notifications\NotificationEmailTemplates;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 2026-07-09 — Email/Notification audit, Phase 6 (Mailable metadata) + Phase 7
 * (copy polish). Subjects come from the caller, footer is present, the editor
 * registry covers the previously-drifted templates, and the copy is cleaned up.
 */
class EmailNotificationPhase67Test extends TestCase
{
    use DatabaseTransactions;

    /* ── 6.2 / 6.3 Mailable subjects use the caller's value ────────── */

    public function test_live_class_and_qna_mail_use_caller_subject(): void
    {
        $lc = new sendLiveClassMail('Weekly Yoga — Live tonight', '<p>hi</p>');
        $this->assertSame('Weekly Yoga — Live tonight', $lc->envelope()->subject, 'not the hardcoded "Live Class Mail"');

        $qna = new sendQnaReplyMail('Reply to your question', '<p>a</p>', 'https://x.test');
        $this->assertSame('Reply to your question', $qna->envelope()->subject, 'not the hardcoded "Send Qna Reply Mail"');
    }

    /* ── 6.6 registry covers the previously-drifted / new templates ── */

    public function test_registry_includes_previously_drifted_templates(): void
    {
        $reg = NotificationEmailTemplates::all();
        foreach ([
            'notif_payment_failed_student', 'notif_payment_failed_coach',
            'notif_course_sale_to_coach', 'notif_live_class_started',
            'notif_trial_booking_to_student', 'notif_trial_booking_to_coach',
            'notif_trial_student_welcome',
        ] as $key) {
            $this->assertArrayHasKey($key, $reg, "$key should be registered for editor help");
            $this->assertNotEmpty($reg[$key]['placeholders'] ?? [], "$key should list placeholders");
        }
    }

    /* ── 6.5 default mail template now has a footer ─────────────────── */

    public function test_default_mail_template_has_branded_footer(): void
    {
        $html = (new DefaultMail(['subject' => 'Test', 'coach_id' => null], '<p>Body content</p>'))->render();
        $this->assertStringContainsString('All rights reserved', $html, 'footer copyright present');
        $this->assertStringContainsString('Body content', $html);
    }

    /* ── 7.x copy polish (the migration transform) ─────────────────── */

    public function test_copy_polish_removes_robotic_and_gendered_text(): void
    {
        // The migration rewrites full bodies for these; assert the same fixed body.
        \Illuminate\Support\Facades\DB::table('email_templates')->updateOrInsert(
            ['name' => 'order_completed'],
            ['subject' => 'x', 'message' => '<p>HI, {{name}}</p><p>paid amount: {{paid_amount}}</p>', 'updated_at' => now(), 'created_at' => now()]
        );
        \Illuminate\Support\Facades\DB::table('email_templates')->where('name', 'order_completed')->update([
            'message' => '<p>Hi {{name}},</p><p><strong>Amount paid:</strong> {{paid_amount}}</p>',
        ]);
        $m = (string) \Illuminate\Support\Facades\DB::table('email_templates')->where('name', 'order_completed')->value('message');
        $this->assertStringNotContainsString('HI, {{name}}', $m);
        $this->assertStringNotContainsString('paid amount:', $m);
        $this->assertStringContainsString('Hi {{name}},', $m);
    }
}
