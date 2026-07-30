<?php

namespace App\Notifications;

use App\Models\CourseBatch;
use App\Models\User;
use App\Notifications\Concerns\BrandedNotificationMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to a student when their coach assigns / reassigns them to a batch
 * (2026-07-15). Coach-branded (mail + in-app), carries the course, new batch,
 * schedule and a deep-link into the student's dashboard. Mirrors the shape of
 * CoachAssignedCourseToStudent so branding + delivery stay consistent.
 */
class StudentBatchAssignedToStudent extends Notification
{
    use Queueable;
    use BrandedNotificationMail;

    public function __construct(
        protected CourseBatch $batch,
        protected ?User $coach = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    private function schedule(): string
    {
        $days = is_array($this->batch->days) ? implode(', ', array_map(fn ($d) => ucfirst(substr($d, 0, 3)), $this->batch->days)) : '';
        $fmt  = fn ($t) => $t ? \Illuminate\Support\Carbon::parse($t)->format('g:i A') : '';
        $time = trim($fmt($this->batch->start_time) . ($this->batch->end_time ? ' – ' . $fmt($this->batch->end_time) : ''));
        return trim($days . ($days && $time ? ' · ' : '') . $time);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $coachName   = $this->coach?->name ?: __('Your coach');
        $courseTitle = $this->batch->course->title ?? __('your course');
        $batchTitle  = $this->batch->title ?? __('your batch');
        $coachId     = $this->batch->course->instructor_id ?? $this->coach?->id;
        $sched       = $this->schedule();

        $bodyHtml = '<p>' . e($coachName) . ' ' . e(__('has assigned you to a new batch.')) . '</p>'
                  . '<p><strong>' . e(__('Course')) . ':</strong> ' . e($courseTitle) . '<br>'
                  . '<strong>' . e(__('Batch')) . ':</strong> ' . e($batchTitle)
                  . ($sched ? '<br><strong>' . e(__('Schedule')) . ':</strong> ' . e($sched) : '')
                  . '<br><strong>' . e(__('Effective')) . ':</strong> ' . e(now()->format('d M Y, g:i A')) . '</p>'
                  . '<p>' . e(__('Your upcoming classes and schedule will now follow this batch.')) . '</p>';

        return $this->buildBrandedMail(
            $notifiable,
            $coachId ? (int) $coachId : null,
            __('You have been assigned to batch :batch', ['batch' => $batchTitle]),
            [
                'title'         => __('Batch assignment updated'),
                'bodyHtml'      => $bodyHtml,
                'url'           => url('/student/dashboard'),
                'icon'          => 'fa-users',
                'iconColor'     => '#4f46e5',
                'recipientName' => $notifiable->name ?? '',
                'ctaLabel'      => __('Open your dashboard'),
            ]
        );
    }

    public function toArray(object $notifiable): array
    {
        return [
            'event'     => 'student_batch_assigned',
            'title'     => '👥 ' . ($this->batch->title ?? __('Batch updated')),
            'body'      => ($this->coach?->name ?? __('Your coach')) . ' ' . __('assigned you to a batch'),
            'url'       => url('/student/dashboard'),
            'icon'      => 'fa-users',
            'iconColor' => '#4f46e5',
            'batch_id'  => $this->batch->id,
            'course_id' => $this->batch->course_id,
            'coach_id'  => $this->coach?->id,
        ];
    }
}
