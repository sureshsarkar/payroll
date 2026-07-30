<?php

namespace App\Notifications;

use App\Models\CourseBatch;

/**
 * Audit 2026-05-18 phase 3 — alerts coach when a batch's attendance
 * dropped below a threshold (e.g. 50%) for today's class.
 *
 * Inherits from InAppNotification → rides database/broadcast/web push.
 */
class LowAttendanceAlertToCoach extends InAppNotification
{
    protected string $event = 'low_attendance_alert';
    protected string $emailTemplate = 'notif_low_attendance_alert';
    protected string $emailCtaLabel = 'View attendance';

    private CourseBatch $batch;
    private int $totalStudents;
    private int $attended;
    private int $pct;

    /** Coach-facing: brand the email as the coach who owns the recipient. */
    protected function brandCoachId(object $notifiable): ?int
    {
        return $this->recipientCoachId($notifiable);
    }

    public function __construct(CourseBatch $batch, int $totalStudents, int $attended)
    {
        $this->batch         = $batch;
        $this->totalStudents = $totalStudents;
        $this->attended      = $attended;
        $this->pct           = $totalStudents > 0 ? (int) round(($attended / $totalStudents) * 100) : 0;

        $this->title     = 'Low attendance — ' . \Str::limit($batch->title, 40);
        $this->body      = sprintf(
            'Only %d of %d students (%d%%) attended today\'s class for %s.',
            $attended,
            $totalStudents,
            $this->pct,
            \Str::limit($batch->title, 40)
        );
        $this->icon      = 'fa-chart-line';
        $this->iconColor = '#ef4444';
        $this->url       = url('/instructor/batch-attendance/' . $batch->id);
    }

    protected function placeholders(object $notifiable): array
    {
        return [
            'user_name'       => $notifiable->name ?? '',
            'batch_title'     => $this->batch->title,
            'attended'        => (string) $this->attended,
            'total_students'  => (string) $this->totalStudents,
            'percent'         => (string) $this->pct,
        ];
    }
}
