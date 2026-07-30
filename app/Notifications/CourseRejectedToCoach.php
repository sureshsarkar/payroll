<?php

namespace App\Notifications;

use App\Models\Course;

/**
 * Sent to a coach when an admin rejects/marks-pending their course.
 */
class CourseRejectedToCoach extends InAppNotification
{
    protected string $event = 'course_approval_status';
    protected string $emailTemplate = 'notif_course_rejected';
    protected string $emailCtaLabel = 'Open my courses';

    private Course $course;
    private string $newStatus;

    public function __construct(Course $course, string $newStatus = 'pending')
    {
        $this->course = $course;
        $this->newStatus = $newStatus;
        $verb = $newStatus === 'rejected' ? 'rejected' : 'sent back for review';
        $this->title = '"' . \Str::limit((string) $course->title, 60) . '" ' . $verb;
        $this->body = 'Open the course to see admin feedback and resubmit when ready.';
        $this->icon = 'fa-triangle-exclamation';
        $this->iconColor = '#f59e0b';
        $this->url = route('instructor.courses.index');
    }

    protected function placeholders(object $notifiable): array
    {
        return [
            'user_name'     => $notifiable->name ?? '',
            'course_title'  => (string) $this->course->title,
            'status'        => $this->newStatus,
            'dashboard_url' => $this->url,
        ];
    }
}
