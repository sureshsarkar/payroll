<?php

namespace App\Notifications;

use App\Models\Announcement;

/**
 * Audit 2026-05-18 — sent to enrolled students when a coach creates
 * a new announcement targeting their batch (or the whole course).
 *
 * Inherits from InAppNotification so it automatically rides three
 * channels: database (bell), broadcast (Pusher real-time), and any
 * preference-driven channels (email / web push) the user opted into.
 */
class NewBatchAnnouncement extends InAppNotification
{
    protected string $event = 'batch_announcement';
    protected string $emailTemplate = 'notif_batch_announcement';
    protected string $emailCtaLabel = 'View announcement';

    private Announcement $announcement;
    private string $courseTitle;
    private ?string $batchTitle;
    private string $coachName;
    private ?string $batchStart;
    private ?string $batchEnd;

    public function __construct(Announcement $announcement, string $courseTitle, ?string $batchTitle, string $coachName, ?string $batchStart = null, ?string $batchEnd = null)
    {
        $this->announcement = $announcement;
        $this->coachId = $announcement->instructor_id ? (int) $announcement->instructor_id : null;
        $this->courseTitle  = $courseTitle;
        $this->batchTitle   = $batchTitle;
        $this->coachName    = $coachName;
        $this->batchStart   = $batchStart;
        $this->batchEnd     = $batchEnd;

        $scopeLabel = $batchTitle
            ? __('Batch') . ': ' . \Str::limit($batchTitle, 30)
            : \Str::limit($courseTitle, 40);

        $this->title     = 'New announcement — ' . $scopeLabel;
        $this->body      = \Str::limit(strip_tags($announcement->title), 80)
                          . ' — ' . \Str::limit(strip_tags($announcement->announcement), 100);
        $this->icon      = 'fa-bullhorn';
        $this->iconColor = '#f59e0b';

        // Link to the course-learning page where the student already
        // sees announcements (Frontend\LearningController). The path
        // expects slug — caller passes the parent course; we don't have
        // it here, so fall back to /student/dashboard which the bell
        // shows under "View all".
        $this->url = url('/student/dashboard');
    }

    protected function placeholders(object $notifiable): array
    {
        $start = $this->fmtTime($this->batchStart);
        $end   = $this->fmtTime($this->batchEnd);

        // Pre-composed batch line so the email template needs NO Blade @if
        // (substitute() only replaces {{...}}; @if/@endif leaked as literal text
        // — the reported bug). Empty when the announcement is course-wide.
        $batchLine = '';
        if (! empty($this->batchTitle)) {
            $batchLine = 'in batch <strong>' . e($this->batchTitle) . '</strong>';
            if ($start !== '') {
                $batchLine .= ' (' . $start . ($end !== '' ? ' – ' . $end : '') . ')';
            }
            $batchLine .= ' ';
        }

        return [
            'user_name'        => $notifiable->name ?? '',
            'course_title'     => $this->courseTitle,
            'batch_title'      => (string) ($this->batchTitle ?? ''),
            'batch_name'       => (string) ($this->batchTitle ?? ''),
            'batch_start_time' => $start,
            'batch_end_time'   => $end,
            'batch_line'       => $batchLine,
            'coach_name'       => $this->coachName,
            'title'            => $this->announcement->title,
            'message'          => \Str::limit(strip_tags($this->announcement->announcement), 240),
        ];
    }

    /** Format a "HH:MM:SS" batch time to "hh:mm AM/PM" (empty when unset). */
    private function fmtTime(?string $time): string
    {
        $time = trim((string) $time);
        if ($time === '') {
            return '';
        }
        try {
            return \Carbon\Carbon::parse($time)->format('h:i A');
        } catch (\Throwable $e) {
            return $time;
        }
    }
}
