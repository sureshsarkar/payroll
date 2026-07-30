<?php

namespace App\Notifications;

use App\Models\Course;

/**
 * Sent to a student the moment they finish all lessons in a course.
 */
class CourseCompletedToStudent extends InAppNotification
{
    protected string $event = 'course_completed';
    protected string $emailTemplate = 'notif_course_completed';
    protected string $emailCtaLabel = 'Open my courses';

    private Course $course;

    public function __construct(Course $course)
    {
        $this->course = $course;
        $this->coachId = $course->instructor_id ? (int) $course->instructor_id : null;
        $this->title = 'Course completed: ' . \Str::limit((string) $course->title, 50);
        $this->body = 'Great work! Your certificate is ready to download.';
        $this->icon = 'fa-trophy';
        $this->iconColor = '#f59e0b';
        $this->url = route('student.enrolled-courses');
    }

    protected function placeholders(object $notifiable): array
    {
        return [
            'user_name'    => $notifiable->name ?? '',
            'course_title' => (string) $this->course->title,
            'courses_url'  => $this->url,
        ];
    }
}
