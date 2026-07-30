<?php

namespace App\Listeners;

use App\Models\NotificationEmailLog;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-06-22 — records mail-channel notification deliveries into
 * notification_email_logs for delivery observability. Resolves the coach the
 * email was branded as (via the notification's brandCoachId hook) so the log
 * is tenant-attributable. Best-effort: never breaks a send.
 */
class LogNotificationEmail
{
    public function sent(NotificationSent $event): void
    {
        if ($event->channel !== 'mail') {
            return;
        }
        $this->write($event->notifiable, $event->notification, 'sent', null);
    }

    public function failed(NotificationFailed $event): void
    {
        if ($event->channel !== 'mail') {
            return;
        }
        $error = '';
        try {
            $error = json_encode($event->data ?? [], JSON_UNESCAPED_SLASHES) ?: '';
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
        $this->write($event->notifiable, $event->notification, 'failed', $error);
    }

    private function write(object $notifiable, object $notification, string $status, ?string $error): void
    {
        try {
            if (!Schema::hasTable('notification_email_logs')) {
                return;
            }

            // Coach the email was branded as (null = platform).
            $coachId = null;
            if (method_exists($notification, 'brandCoachId')) {
                try {
                    $m = new \ReflectionMethod($notification, 'brandCoachId');
                    $m->setAccessible(true);
                    $coachId = $m->invoke($notification, $notifiable);
                } catch (\Throwable $e) { /* leave null */ }
            }

            NotificationEmailLog::create([
                'coach_id'           => $coachId ? (int) $coachId : null,
                'notifiable_type'    => $notifiable::class,
                'notifiable_id'      => $notifiable->id ?? null,
                'recipient_email'    => $notifiable->email ?? null,
                'notification_class' => $notification::class,
                'title'              => property_exists($notification, 'title') ? ($notification->title ?? null) : null,
                'status'             => $status,
                'error'              => $error ?: null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('LogNotificationEmail failed: ' . $e->getMessage());
        }
    }
}
