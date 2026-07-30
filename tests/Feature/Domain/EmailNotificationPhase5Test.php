<?php

namespace Tests\Feature\Domain;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * 2026-07-09 — Email/Notification audit, Phase 5 (Delivery & Security hardening).
 * Bounded SMTP timeout (5.1), universal mail-send trail (5.3), retry on the
 * previously silent-fail mail jobs (5.5).
 */
class EmailNotificationPhase5Test extends TestCase
{
    use DatabaseTransactions;

    /* ── 5.1 bounded SMTP timeout ──────────────────────────────────── */

    public function test_smtp_timeout_is_bounded(): void
    {
        $t = config('mail.mailers.smtp.timeout');
        $this->assertNotNull($t, 'SMTP timeout must not be null (would block indefinitely)');
        $this->assertIsNumeric($t);
        $this->assertGreaterThan(0, (int) $t);
    }

    /* ── 5.5 / 1.2 mail jobs retry + surface failures ──────────────── */

    public function test_previously_silent_mail_jobs_now_retry(): void
    {
        foreach ([
            \App\Jobs\sendLiveClassMailJob::class,
            \App\Jobs\sendQnaReplyMailJob::class,
            \App\Jobs\UserForgetPasswordJob::class,
            \App\Jobs\SocialLoginDefaultPasswordJob::class,
        ] as $jobClass) {
            $ref = new \ReflectionClass($jobClass);
            $this->assertTrue($ref->hasProperty('tries'), "$jobClass should declare \$tries");
            $tries = $ref->getProperty('tries')->getDefaultValue();
            $this->assertGreaterThanOrEqual(2, (int) $tries, "$jobClass \$tries should be >= 2");
        }
    }

    /* ── 5.3 every outbound email is logged (trail for legacy Mailables) ─ */

    public function test_outbound_mail_is_logged(): void
    {
        config(['mail.default' => 'array']); // send through the in-memory transport (still fires MessageSent)
        Log::spy();

        Mail::raw('Hello', function ($m) {
            $m->to('trail@example.test')->subject('Trail Test');
        });

        Log::shouldHaveReceived('info')
            ->withArgs(fn ($message, $ctx = []) => $message === 'email_sent'
                && ($ctx['subject'] ?? null) === 'Trail Test'
                && str_contains((string) ($ctx['to'] ?? ''), 'trail@example.test'))
            ->atLeast()->once();
    }
}
