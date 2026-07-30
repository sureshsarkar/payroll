<?php

namespace App\Notifications;

use App\Models\CourseBatch;
use App\Models\User;
use App\Notifications\Concerns\BrandedNotificationMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Sent to a student when a coach assigns a date-specific temporary batch slot
 * (2026-07-15). Coach-branded (mail + in-app). Their primary batch is unchanged;
 * this class runs only on the given date.
 */
class StudentTemporarySlotAssigned extends Notification
{
    use Queueable;
    use BrandedNotificationMail;

    public function __construct(
        protected CourseBatch $batch,
        protected string $slotDate,
        protected ?User $coach = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    private function schedule(): string
    {
        $fmt = fn ($t) => $t ? Carbon::parse($t)->format('g:i A') : '';
        return trim($fmt($this->batch->start_time) . ($this->batch->end_time ? ' – ' . $fmt($this->batch->end_time) : ''));
    }

    public function toMail(object $notifiable): MailMessage
    {
        $coachName   = $this->coach?->name ?: __('Your coach');
        $batchTitle  = $this->batch->title ?? __('a batch');
        $courseTitle = $this->batch->course->title ?? __('your course');
        $coachId     = $this->batch->course->instructor_id ?? $this->coach?->id;
        $dateStr     = Carbon::parse($this->slotDate)->format('d M Y');
        $sched       = $this->schedule();

        $bodyHtml = '<p>' . e($coachName) . ' ' . e(__('has scheduled a temporary class for you on a specific date.')) . '</p>'
                  . '<p><strong>' . e(__('Date')) . ':</strong> ' . e($dateStr) . '<br>'
                  . '<strong>' . e(__('Course')) . ':</strong> ' . e($courseTitle) . '<br>'
                  . '<strong>' . e(__('Attend batch')) . ':</strong> ' . e($batchTitle)
                  . ($sched ? ' (' . e($sched) . ')' : '') . '</p>'
                  . '<p>' . e(__('This applies only to that date. Your regular batch stays the same.')) . '</p>';

        return $this->buildBrandedMail(
            $notifiable,
            $coachId ? (int) $coachId : null,
            __('Temporary class on :date', ['date' => $dateStr]),
            [
                'title'         => __('Temporary class scheduled'),
                'bodyHtml'      => $bodyHtml,
                'url'           => url('/student/dashboard'),
                'icon'          => 'fa-calendar-day',
                'iconColor'     => '#d97706',
                'recipientName' => $notifiable->name ?? '',
                'ctaLabel'      => __('Open your dashboard'),
            ]
        );
    }

    public function toArray(object $notifiable): array
    {
        return [
            'event'     => 'student_temporary_slot',
            'title'     => '📅 ' . __('Temporary class') . ' · ' . Carbon::parse($this->slotDate)->format('d M'),
            'body'      => ($this->coach?->name ?? __('Your coach')) . ' ' . __('scheduled a temporary class'),
            'url'       => url('/student/dashboard'),
            'icon'      => 'fa-calendar-day',
            'iconColor' => '#d97706',
            'batch_id'  => $this->batch->id,
            'slot_date' => $this->slotDate,
            'coach_id'  => $this->coach?->id,
        ];
    }
}
