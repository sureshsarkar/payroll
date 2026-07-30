<?php

namespace App\Notifications;

use App\Models\Course;
use App\Models\User;

/**
 * Sent to a coach when a new student enrolls in one of their courses
 * (i.e. an order is paid and the enrollment activates).
 */
class NewEnrollmentToCoach extends InAppNotification
{
    protected string $event = 'new_enrollment';
    protected string $emailTemplate = 'notif_new_enrollment';
    protected string $emailCtaLabel = 'Open my sales';

    private Course $course;
    private User $student;

    /** Coach-facing: brand the email as the coach who owns the recipient. */
    protected function brandCoachId(object $notifiable): ?int
    {
        return $this->recipientCoachId($notifiable);
    }

    public function __construct(Course $course, User $student)
    {
        $this->course = $course;
        $this->student = $student;
        $this->title = $student->name . ' enrolled in your course';
        $this->body = '"' . \Str::limit((string) $course->title, 60) . '"';
        $this->icon = 'fa-user-graduate';
        $this->iconColor = '';  // use the coach brand accent
        $this->url = route('instructor.my-sells.index');
    }

    protected function placeholders(object $notifiable): array
    {
        return [
            'coach_name'   => $notifiable->name ?? '',
            'student_name' => (string) $this->student->name,
            'course_title' => (string) $this->course->title,
            'sales_url'    => $this->url,
        ];
    }
}
