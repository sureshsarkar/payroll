<?php

namespace App\Notifications;

use App\Models\Course;
use App\Models\User;
use App\Notifications\Concerns\BrandedNotificationMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Order\app\Models\Order;

/**
 * Sent to a student when their coach manually assigns a course to them
 * via /instructor/coach-orders/create.
 *
 * Triggered by InstructorDashboardController::store(). Carries the
 * course title + coach name + a deep-link into the student's enrolled
 * courses page so they can start watching immediately.
 *
 * Reported in bug-doc 2026-05-26 (C4): the existing code created the
 * enrollment but never notified the student, so the student had no idea
 * they had a new course until they happened to log in and browse.
 */
class CoachAssignedCourseToStudent extends Notification
{
    use Queueable;
    use BrandedNotificationMail;

    public function __construct(
        protected Course $course,
        protected Order $order,
        protected ?User $coach = null,
    ) {
    }

    /**
     * Both in-app (database) and email so the student definitely sees it.
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $coachName   = $this->coach?->name ?: 'Your coach';
        $courseTitle = $this->course->title ?? 'a new course';
        $coachId     = $this->course->instructor_id ?: $this->coach?->id;

        $bodyHtml = '<p>' . e($coachName) . ' just gave you access to "' . e($courseTitle) . '".</p>'
                  . '<p>' . e(__('You can start learning immediately — no payment required.')) . '</p>'
                  . '<p>' . e(__('Reach out to your coach if you have any questions.')) . '</p>';

        return $this->buildBrandedMail(
            $notifiable,
            $coachId ? (int) $coachId : null,
            __(':coach has assigned a course to you', ['coach' => $coachName]),
            [
                'title'         => __('New course access'),
                'bodyHtml'      => $bodyHtml,
                'url'           => url('/student/enrolled-courses'),
                'icon'          => 'fa-graduation-cap',
                'iconColor'     => '#16a34a',
                'recipientName' => $notifiable->name ?? '',
                'ctaLabel'      => __('Open your courses'),
            ]
        );
    }

    public function toArray(object $notifiable): array
    {
        return [
            'event'        => 'coach_assigned_course',
            'title'        => '🎓 ' . ($this->course->title ?? 'New course access'),
            'body'         => ($this->coach?->name ?? 'Your coach') . ' just enrolled you',
            'url'          => url('/student/enrolled-courses'),
            'icon'         => 'fa-graduation-cap',
            'iconColor'    => '#16a34a',
            'course_id'    => $this->course->id,
            'order_id'     => $this->order->id,
            'coach_id'     => $this->coach?->id,
        ];
    }
}
