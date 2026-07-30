<?php

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Log;

/**
 * 2026-07-09 (Email/Notification audit — Phase 5.3).
 *
 * A universal outbound-mail delivery trail. `NotificationEmailLog` only records
 * mail-channel NOTIFICATIONS; the legacy Mailables (registration, forgot-
 * password, social-login credentials, QnA, live-class, order/payment, contact)
 * had NO send record at all. This listener logs every actual SMTP send
 * (recipient + subject + mailer) so those credential-bearing emails are
 * auditable. Best-effort — never breaks a send. Toggle with MAIL_LOG_SENDS.
 */
class LogMailSent
{
    public function handle(MessageSent $event): void
    {
        try {
            if (! filter_var(env('MAIL_LOG_SENDS', true), FILTER_VALIDATE_BOOLEAN)) {
                return;
            }

            $email = $event->message; // Symfony\Component\Mime\Email
            $to = [];
            foreach ($email->getTo() as $addr) {
                $to[] = $addr->getAddress();
            }

            Log::info('email_sent', [
                'to'      => implode(', ', $to),
                'subject' => $email->getSubject(),
                'mailer'  => $event->data['mailer'] ?? null,
            ]);
        } catch (\Throwable $e) {
            // Delivery observability must never break the actual send.
        }
    }
}
