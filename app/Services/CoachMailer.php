<?php

namespace App\Services;

use App\Models\CoachBrandSetting;
use Illuminate\Contracts\Mail\Mailer as MailerContract;
use Illuminate\Mail\Mailer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

/**
 * Per-coach email sender.
 *
 * Resolution at send time:
 *   1. Coach has full SMTP override (smtp_host set + verified) →
 *      build a fresh SMTP transport from coach's creds, send via
 *      that. Coach's mail_from_address / mail_from_name is the
 *      From header.
 *   2. Coach has only FROM override (mail_from_address set) → use
 *      the platform's transport, but rewrite the From + Reply-To
 *      headers for this one message.
 *   3. No coach context OR coach hasn't customised anything → send
 *      with platform defaults (no behaviour change for unmigrated
 *      coaches).
 *
 * The existing MailSenderService can call into this with the
 * coach id resolved from BrandResolver, so no per-call-site refactor
 * is needed beyond a small handful of mail templates.
 *
 * Smart fallback: if a coach's SMTP credentials FAIL the live send
 * (auth rejection, host unreachable), we log a warning and fall
 * back to platform SMTP for THAT send. Coach sees the message went
 * out; ops gets a log line ("coach-smtp-failed") that flags the
 * broken config. Better than silent failure or hard error.
 *
 * Outbound URL building (P5 hook): when this mailer is used inside
 * a coach context, the From / Reply-To addresses point at the
 * coach's domain — but absolute links INSIDE the body should also
 * point at the coach's hostname (otherwise the email says "click
 * here to log in" and links to the PLATFORM's URL). That link
 * rewriting will come with the email-template overhaul in P4.
 */
class CoachMailer
{
    /**
     * Send a raw HTML mail to a recipient on behalf of a coach.
     *
     * For most production paths the existing MailSenderService /
     * Mailable classes call into this once they've resolved the
     * coach id. Direct usage:
     *
     *   app(CoachMailer::class)->send(
     *       coachId: 1079,
     *       to:      'student@example.com',
     *       subject: 'Welcome',
     *       html:    '<p>...</p>',
     *   );
     */
    public function send(int $coachId, string $to, string $subject, string $html): bool
    {
        $row = CoachBrandSetting::firstOrCreateForCoach($coachId);
        $mode = $this->resolveMode($row);

        try {
            $mailer = $this->buildMailerFor($mode, $row);

            // Mailer::html() takes inline HTML (vs ::send() which
            // expects a view NAME). The callback gets a Message
            // builder for headers + addresses.
            $mailer->html($html, function ($message) use ($row, $to, $subject, $mode) {
                $message->to($to)->subject($subject);

                $fromAddr = $row->mail_from_address ?: config('mail.from.address');
                $fromName = $row->mail_from_name    ?: ($row->brand_name ?: config('mail.from.name'));
                if ($fromAddr) {
                    $message->from($fromAddr, $fromName);
                }
                if ($row->mail_reply_to) {
                    $message->replyTo($row->mail_reply_to, $fromName);
                }
            });

            if ($mode === 'smtp_override') {
                $row->forceFill(['smtp_verified_at' => now()])->saveQuietly();
            }
            return true;
        } catch (\Throwable $e) {
            // Coach SMTP failed — graceful fallback to platform.
            // We log so ops knows the coach's config is broken, but
            // the student still gets the message.
            if ($mode === 'smtp_override') {
                Log::warning('coach-smtp-failed', [
                    'coach_id' => $coachId,
                    'host'     => $row->smtp_host,
                    'error'    => $e->getMessage(),
                ]);
                // Re-attempt via platform transport, header overrides only.
                return $this->sendWithPlatformTransport($row, $to, $subject, $html);
            }
            Log::error('coach-mail-failed', [
                'coach_id' => $coachId,
                'mode'     => $mode,
                'error'    => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Decide which mode applies for this coach row.
     */
    protected function resolveMode(CoachBrandSetting $row): string
    {
        if ($row->smtp_host && $row->smtp_username && $row->smtp_password_encrypted) {
            return 'smtp_override';
        }
        if ($row->mail_from_address) {
            return 'from_override';
        }
        return 'platform';
    }

    /**
     * Build the Mailer to use for this send.
     *
     * 'smtp_override' returns a fresh ad-hoc Mailer with a
     * coach-specific SMTP transport. 'from_override' and 'platform'
     * both return the default platform Mailer (we just twiddle the
     * From header inside the closure for from_override).
     */
    protected function buildMailerFor(string $mode, CoachBrandSetting $row): MailerContract
    {
        if ($mode !== 'smtp_override') {
            return Mail::mailer();
        }

        // Symfony Mailer DSN string keeps username/password URL-encoded
        // so passwords with special chars don't break parsing.
        $transport = new EsmtpTransport(
            host: (string) $row->smtp_host,
            port: (int) ($row->smtp_port ?: ($row->smtp_encryption === 'ssl' ? 465 : 587)),
            tls:  $row->smtp_encryption !== 'none',
        );
        $transport->setUsername((string) $row->smtp_username);
        $transport->setPassword((string) $row->smtp_password_encrypted);

        // 2026-05-29 Doc-C-SMTP fix.
        // Was wrapping the transport in a SymfonyMailer and passing
        // that to Illuminate\Mail\Mailer's $transport. Laravel's Mailer
        // constructor expects a TransportInterface, NOT a Mailer
        // wrapper. Pass the EsmtpTransport directly.
        $view = app('view');
        $events = app('events');
        $mailer = new Mailer('coach-' . $row->coach_id, $view, $transport, $events);

        return $mailer;
    }

    /**
     * Fallback path when coach SMTP refused the send.
     */
    protected function sendWithPlatformTransport(CoachBrandSetting $row, string $to, string $subject, string $html): bool
    {
        try {
            Mail::mailer()->html($html, function ($message) use ($row, $to, $subject) {
                $message->to($to)->subject($subject);
                $fromAddr = $row->mail_from_address ?: config('mail.from.address');
                $fromName = $row->mail_from_name    ?: ($row->brand_name ?: config('mail.from.name'));
                if ($fromAddr) {
                    $message->from($fromAddr, $fromName);
                }
                if ($row->mail_reply_to) {
                    $message->replyTo($row->mail_reply_to, $fromName);
                }
            });
            return true;
        } catch (\Throwable $e) {
            Log::error('coach-mail-platform-fallback-failed', [
                'coach_id' => $row->coach_id,
                'error'    => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Verify a set of SMTP credentials WITHOUT persisting them.
     *
     * Used by the brand-settings UI's "Test SMTP" button: build a
     * transport from the form values, attempt one send to the coach
     * themselves, return [ok=>bool, error=>?string]. UI shows the
     * outcome inline; coach only saves on success.
     */
    public function verifyCredentials(array $creds, string $toEmail): array
    {
        try {
            // 2026-05-29 Doc-C-SMTP fix.
            //
            // Bug: was wrapping the EsmtpTransport in a Symfony\Mailer and
            // passing THAT to Illuminate\Mail\Mailer's $transport argument.
            // Laravel's Mailer expects a Symfony TransportInterface, not a
            // SymfonyMailer wrapper. The resulting TypeError surfaced as
            // an obscure 'GatewayCommandHandlerInterface given' on the
            // user's environment (different container resolution path).
            //
            // Fix: pass the EsmtpTransport directly. SymfonyMailer is
            // unused now (Laravel's Mailer wraps the transport itself).
            $transport = new EsmtpTransport(
                host: (string) ($creds['smtp_host'] ?? ''),
                port: (int) ($creds['smtp_port'] ?? 587),
                tls:  ($creds['smtp_encryption'] ?? 'tls') !== 'none',
            );
            $transport->setUsername((string) ($creds['smtp_username'] ?? ''));
            $transport->setPassword((string) ($creds['smtp_password'] ?? ''));

            $mailer = new Mailer('coach-test', app('view'), $transport, app('events'));

            $mailer->raw('SMTP verification test from ' . config('app.name'),
                function ($message) use ($creds, $toEmail) {
                    $message->to($toEmail)
                        ->subject('SMTP verification - ' . config('app.name'))
                        ->from((string) ($creds['mail_from_address'] ?? $creds['smtp_username']),
                               (string) ($creds['mail_from_name'] ?? 'White-label test'));
                });
            return ['ok' => true, 'error' => null];
        } catch (\Throwable $e) {
            // Sanitize the exception message so credentials don't leak.
            $msg = $e->getMessage();
            $msg = preg_replace('/(password|secret)\s*[:=]\s*\S+/i', '$1: [REDACTED]', $msg);
            return ['ok' => false, 'error' => $msg];
        }
    }
}
