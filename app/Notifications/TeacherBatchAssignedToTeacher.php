<?php

namespace App\Notifications;

use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\User;
use App\Notifications\Concerns\BrandedNotificationMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to a teacher when a coach assigns them to one or more batches
 * via /instructor/teacher-batches/create.
 *
 * Reported in bug-doc 2026-05-26 (C11): assignments saved silently, so
 * the teacher had no idea they were now expected to lead a batch.
 */
class TeacherBatchAssignedToTeacher extends Notification
{
    use Queueable;
    use BrandedNotificationMail;

    public function __construct(
        protected Course $course,
        protected array $batches,         // array of CourseBatch instances
        protected ?User $coach = null,
        protected string $permissionType = 'manage',
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $coachName   = $this->coach?->name ?: 'Your coach';
        $courseTitle = (string) ($this->course->title ?? 'a course');
        $coachId     = $this->coach?->id ?: ($notifiable->coach_id ?? null);

        $batchTitles = collect($this->batches)
            ->map(fn (CourseBatch $b) => $b->title ?: ('Batch #' . $b->id))
            ->implode(', ');

        $bodyHtml = '<p>' . e(__(':coach has assigned you to the following batch(es) in ":course":', [
                        'coach' => $coachName, 'course' => $courseTitle,
                    ])) . '</p>'
                  . '<p><strong>' . e($batchTitles) . '</strong></p>'
                  . '<p>' . e(__('Permission level: :p', ['p' => $this->permissionType])) . '</p>';

        return $this->buildBrandedMail(
            $notifiable,
            $coachId ? (int) $coachId : null,
            __(':coach assigned you to batches in :course', ['coach' => $coachName, 'course' => $courseTitle]),
            [
                'title'         => __('New batch assignment'),
                'bodyHtml'      => $bodyHtml,
                'url'           => url('/login'),
                'icon'          => 'fa-chalkboard-user',
                'iconColor'     => '#3b82f6',
                'recipientName' => $notifiable->name ?? '',
                'ctaLabel'      => __('Log in to your dashboard'),
            ]
        );
    }
}
