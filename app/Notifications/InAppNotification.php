<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use App\Notifications\Concerns\BrandedNotificationMail;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Base class for all in-app notifications.
 *
 * Subclasses set $title, $body, $url, $icon, $iconColor — that's it. The base handles:
 *   - storing into the `notifications` table (database channel)
 *   - broadcasting via Pusher to a per-user private channel (broadcast channel)
 *   - respecting the user's per-event preferences (User::notificationChannelEnabled)
 *
 * Subclasses MAY override the protected $event property to map this notification
 * class to a key in User::NOTIFICATION_EVENTS for preference filtering. If left
 * empty, no filtering happens.
 */
abstract class InAppNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use SerializesModels;
    use BrandedNotificationMail;

    /**
     * Force every channel onto the 'sync' connection by default → notifications
     * run INLINE exactly as before (no queue round-trip, no model
     * serialization), so a system that wasn't queueing them sees ZERO
     * behaviour change. The class implements ShouldQueue + uses SerializesModels,
     * so it is now fully queue-WORKER ready: to offload delivery onto the
     * existing `queue:work` worker, return [] here (falls back to
     * QUEUE_CONNECTION, e.g. 'database') — ideally per-environment after a
     * staging test, since queued notifications re-resolve their models.
     */
    public function viaConnections(): array
    {
        return [
            'database'                   => 'sync',
            'mail'                       => 'sync',
            'broadcast'                  => 'sync',
            WebPushChannel::class        => 'sync',
        ];
    }

    /** Headline (e.g. "Course sold") */
    public string $title = '';

    /** Plain-text body shown under the title */
    public string $body = '';

    /** URL to navigate to when the notification is clicked (optional) */
    public ?string $url = null;

    /** Icon class (Font Awesome) — e.g. "fa-bell", "fa-shopping-cart", "fa-comment" */
    public string $icon = 'fa-bell';

    /** Hex color for the icon background ring */
    // 2026-07-07 (white-label QA) — empty means "use the coach brand accent" in
    // branded emails; the in-app bell falls back to platform emerald (#10b981).
    // A subclass may still set a semantic colour (e.g. red for a failure).
    public string $iconColor = '';

    /**
     * Optional preference key (matches User::NOTIFICATION_EVENTS). Subclasses can
     * override this to opt into per-event preference filtering.
     */
    protected string $event = '';

    /**
     * Optional admin-editable email-template name (matches a row in the
     * `email_templates` table). When set, toMail() uses the template's subject
     * and message (with {{placeholder}} substitution) for the email body
     * instead of the bare $title/$body fields.
     */
    protected string $emailTemplate = '';

    /**
     * Optional override label for the CTA button in the email body.
     */
    protected string $emailCtaLabel = '';

    /**
     * Coach id whose brand this notification's email should use. Student-facing
     * notifications set this in their constructor from the entity they carry
     * (course->instructor_id, demand->coach_id, order->primary_coach_id, …).
     * Null → platform branding.
     */
    protected ?int $coachId = null;

    /**
     * White-label hook. Returns the coach id this notification belongs to so the
     * email is rendered with THAT coach's brand (logo, name, colour, footer,
     * signature, support contact) instead of the platform default.
     *
     * Default returns $this->coachId (platform when null → fully backward
     * compatible). Coach-/staff-facing notifications override this to return
     * recipientCoachId(). Admin/platform notifications leave it null.
     */
    protected function brandCoachId(object $notifiable): ?int
    {
        return $this->coachId;
    }

    /**
     * The coach that OWNS the recipient. A coach (role=instructor, coach_id
     * NULL) is their own coach; a staff/teacher row carries the coach in its
     * coach_id column. Used by coach-/staff-facing notifications so the email
     * is branded as the coach even when a staff member is the actual recipient.
     * Returns null for platform recipients (admins, plain users).
     */
    protected function recipientCoachId(object $notifiable): ?int
    {
        $cid = $notifiable->coach_id ?? null;
        if ($cid) {
            return (int) $cid;
        }
        if (($notifiable->role ?? null) === 'instructor') {
            return ((int) ($notifiable->id ?? 0)) ?: null;
        }
        return null;
    }

    /**
     * Channels to deliver on. By default both database (so it shows in the bell)
     * and broadcast (so it's pushed in real-time when Pusher is on).
     *
     * If $this->event is set AND the user has opted out of a channel for that
     * event in their preferences, that channel is filtered out here.
     */
    public function via(object $notifiable): array
    {
        $channels = ['database', 'broadcast'];

        // Mail channel — only if recipient has an email address.
        if (!empty($notifiable->email ?? null)) {
            $channels[] = 'mail';
        }

        // Add webpush only if recipient has at least one push subscription.
        // Using method_exists to avoid coupling: User has it (via HasPushSubscriptions),
        // Admin doesn't, so admin notifications won't try webpush.
        if (method_exists($notifiable, 'pushSubscriptions') && $notifiable->pushSubscriptions()->exists()) {
            $channels[] = WebPushChannel::class;
        }

        // Respect per-event preferences if the subclass declared an event key
        // and the recipient has the User::notificationChannelEnabled helper.
        if ($this->event !== '' && method_exists($notifiable, 'notificationChannelEnabled')) {
            $channels = array_values(array_filter(
                $channels,
                function ($ch) use ($notifiable) {
                    // Map class names back to the user-facing channel key.
                    $key = $ch === WebPushChannel::class ? 'broadcast' : $ch;
                    return $notifiable->notificationChannelEnabled($this->event, $key);
                }
            ));
        }

        return $channels;
    }

    /**
     * Build the email — uses the admin-editable email_templates row if the
     * subclass declared $emailTemplate, otherwise falls back to the bell's
     * $title/$body fields. Brand resolution, per-coach SMTP and the From
     * header are handled by the shared BrandedNotificationMail trait.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $coachId = null;
        try {
            $coachId = $this->brandCoachId($notifiable);
        } catch (\Throwable $e) {
            \Log::warning('brandCoachId() failed for notification email: ' . $e->getMessage());
        }

        $appName = Cache::get('setting')?->app_name ?? config('app.name');

        // Default: bell title/body. Overridden below if a DB template exists.
        $subject  = $this->title ?: ($appName . ' notification');
        $bodyHtml = null;

        if ($this->emailTemplate !== '') {
            try {
                // LMS removal phase 2 (2026-08-27) — dropped the per-coach
                // CoachEmailTemplate::override() lookup that took precedence
                // here. There is one set of email templates now.
                $row = \Modules\GlobalSetting\app\Models\EmailTemplate::where('name', $this->emailTemplate)->first();
                if ($row) {
                    $vars = $this->renderPlaceholders($notifiable);
                    $subject  = $this->substitute((string) $row->subject, $vars);
                    $bodyHtml = $this->substitute((string) $row->message, $vars);
                }
            } catch (\Throwable $e) {
                \Log::warning('Email template lookup failed for ' . $this->emailTemplate . ': ' . $e->getMessage());
            }
        }

        return $this->buildBrandedMail($notifiable, $coachId ? (int) $coachId : null, $subject, [
            'title'         => $this->title,
            'body'          => $this->body,
            'bodyHtml'      => $bodyHtml,
            'url'           => $this->url,
            'icon'          => $this->icon,
            'iconColor'     => $this->iconColor,
            // The DB template usually carries its own greeting, so suppress the
            // template-level "Hi {name}," when a bodyHtml is present.
            'recipientName' => $bodyHtml ? '' : ($notifiable->name ?? ''),
            'ctaLabel'      => $this->ctaLabel(),
        ]);
    }

    /**
     * Subclasses with $emailTemplate set MUST override this to provide the
     * variable substitution map. Default returns the recipient's name only.
     */
    protected function placeholders(object $notifiable): array
    {
        return [
            'user_name' => $notifiable->name ?? '',
        ];
    }

    /**
     * Resolves placeholder values, casting everything to string so
     * substitute() can splice them in safely.
     */
    private function renderPlaceholders(object $notifiable): array
    {
        $vars = $this->placeholders($notifiable);
        // Always provide a sane default for {{user_name}} if the subclass forgot.
        if (!array_key_exists('user_name', $vars)) {
            $vars['user_name'] = $notifiable->name ?? '';
        }
        return array_map(fn ($v) => (string) ($v ?? ''), $vars);
    }

    /**
     * Replace {{key}} tokens (whitespace-tolerant) with values.
     */
    private function substitute(string $text, array $vars): string
    {
        foreach ($vars as $k => $v) {
            // preg_replace_callback so a value containing $ / \ (e.g. an HTML
            // batch_line) is inserted literally, not treated as a backreference.
            $text = preg_replace_callback(
                '/\{\{\s*' . preg_quote($k, '/') . '\s*\}\}/',
                fn () => (string) $v,
                $text
            );
        }
        // 2026-07-09 (Phase 1.6): strip any placeholder the sender didn't supply so
        // a raw {{token}} never reaches the recipient — e.g. an admin- or coach-
        // edited DB template referencing a token this notification doesn't provide.
        $text = preg_replace('/\{\{\s*[a-zA-Z0-9_.]+\s*\}\}/', '', $text);
        return $text;
    }

    /**
     * Subclasses may override to set a domain-specific CTA button label.
     */
    protected function ctaLabel(): string
    {
        return $this->emailCtaLabel !== '' ? $this->emailCtaLabel : 'View details';
    }

    /**
     * Build the WebPushMessage shown by the browser. Uses the same fields the
     * subclass already populates ($title, $body, $url, $icon).
     */
    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        $msg = (new WebPushMessage())
            ->title($this->title ?: 'New notification')
            ->body($this->body ?: '')
            ->icon('/uploads/website-images/frontend-avatar.png')
            ->badge('/favicon.ico')
            ->vibrate([200, 100, 200])
            ->data(['url' => $this->coachHostRewrite($this->url, $this->brandCoachId($notifiable)) ?: '/']);
        return $msg;
    }

    /**
     * Database row payload — the `data` column of the `notifications` table.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title'     => $this->title,
            'body'      => $this->body,
            // 2026-07-09 (Phase 2.2): keep the bell redirect on the coach's own
            // domain when they have a verified one — parity with the email CTA.
            'url'       => $this->coachHostRewrite($this->url, $this->brandCoachId($notifiable)),
            'icon'      => $this->icon,
            'iconColor' => $this->iconColor ?: '#10b981',
        ];
    }

    /**
     * Broadcast payload + event name. Echo subscribers see this in real-time.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'title'     => $this->title,
            'body'      => $this->body,
            'url'       => $this->url,
            'icon'      => $this->icon,
            'iconColor' => $this->iconColor ?: '#10b981',
            'createdAt' => now()->toIso8601String(),
        ]);
    }

    /**
     * Pretty event name for the frontend listener (Echo: `.NotificationCreated`).
     */
    public function broadcastAs(): string
    {
        return 'NotificationCreated';
    }
}
