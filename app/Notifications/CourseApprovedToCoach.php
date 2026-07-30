<?php

namespace App\Notifications;

use App\Models\Course;

/**
 * Sent to a coach when an admin approves their course.
 */
class CourseApprovedToCoach extends InAppNotification
{
    protected string $event = 'course_approval_status';
    protected string $emailTemplate = 'notif_course_approved';
    protected string $emailCtaLabel = 'Open my courses';

    private Course $course;

    public function __construct(Course $course)
    {
        $this->course = $course;
        $this->title = '"' . \Str::limit((string) $course->title, 60) . '" approved';
        $this->body = 'Your course is now live and visible to students.';
        $this->icon = 'fa-circle-check';
        $this->iconColor = '#10b981';
        $this->url = route('instructor.courses.index');
    }

    protected function placeholders(object $notifiable): array
    {
        return [
            'user_name'     => $notifiable->name ?? '',
            'course_title'  => (string) $this->course->title,
            'dashboard_url' => $this->url,
        ];
    }
}
