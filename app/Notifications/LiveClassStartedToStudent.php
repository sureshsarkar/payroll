<?php

namespace App\Notifications;

use App\Models\CoachDomain;
use App\Models\CourseBatch;
use App\Models\CourseLiveClass;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * "Your live class has started — join now" (2026-06-30).
 *
 * Dispatched by the NotifyLiveClassStarted job the moment a coach/teacher STARTS
 * a live class (host join → coach_joined_at set), to each enrolled same-course +
 * same-batch student who has NOT joined yet. Rides the shared InAppNotification
 * pipeline → coach-branded email (per-coach SMTP + template + logo/colours), bell
 * entry, real-time toast. brandCoachId() resolves the brand to the course owner.
 *
 * The Join CTA points at the COACH's own domain/subdomain (never the platform),
 * keeping the student inside the coach's white-label experience.
 */
class LiveClassStartedToStudent extends InAppNotification
{
    protected string $event = 'live_class_started';
    protected string $emailTemplate = 'notif_live_class_started';
    protected string $emailCtaLabel = 'Join live class';

    private string $courseTitle;
    private string $classTitle;
    private string $batchName;
    private string $coachName;
    private string $startedAt;

    public function __construct(CourseLiveClass $liveClass, string $courseTitle, ?int $coachId)
    {
        $this->coachId = $coachId ?: null;
        $this->courseTitle = $courseTitle;

        $this->classTitle = trim((string) (optional($liveClass->lesson)->title ?: $courseTitle));
        $this->batchName  = $liveClass->batch_id
            ? (string) (CourseBatch::where('id', $liveClass->batch_id)->value('title') ?: '')
            : '';
        $this->coachName  = $this->coachId
            ? (string) (User::where('id', $this->coachId)->value('name') ?: '')
            : '';
        try {
            $this->startedAt = $liveClass->start_time
                ? Carbon::parse($liveClass->start_time)->format('d M Y, h:i A')
                : now()->format('d M Y, h:i A');
        } catch (\Throwable $e) {
            $this->startedAt = (string) $liveClass->start_time;
        }

        // Tenant-safe Join URL — the COACH's own host (custom domain/subdomain),
        // never the platform/Super-Admin domain. Falls back to the platform live
        // classes page only when the coach has no custom host configured.
        $host = $this->coachId ? CoachDomain::primaryHostFor((int) $this->coachId) : null;
        $base = $host ? ('https://' . $host) : rtrim((string) config('app.url'), '/');
        $this->url = $base . '/student/live-classes';

        $this->title     = __('Your live class has started — join now');
        $this->body      = __('":course" has started. Join now from your dashboard.', [
            'course' => Str::limit($this->courseTitle, 60),
        ]);
        $this->icon      = 'fa-video';
        $this->iconColor = '#ef4444';
    }

    protected function placeholders(object $notifiable): array
    {
        return [
            'user_name'    => $notifiable->name ?? '',
            'class_title'  => $this->classTitle,
            'course_title' => $this->courseTitle,
            'batch_name'   => $this->batchName,
            'coach_name'   => $this->coachName,
            'start_time'   => $this->startedAt,
            'join_url'     => $this->url,
        ];
    }
}
