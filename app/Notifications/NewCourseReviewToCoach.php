<?php

namespace App\Notifications;

use App\Models\CourseReview;

/**
 * Sent to a coach when a student leaves a review on one of their courses.
 */
class NewCourseReviewToCoach extends InAppNotification
{
    protected string $event = 'new_course_review';

    /** Coach-facing: brand the email as the coach who owns the recipient. */
    protected function brandCoachId(object $notifiable): ?int
    {
        return $this->recipientCoachId($notifiable);
    }

    public function __construct(CourseReview $review)
    {
        $studentName = (string) ($review->user->name ?? 'A student');
        $courseTitle = (string) ($review->course->title ?? 'your course');
        $stars = str_repeat('★', (int) $review->rating) . str_repeat('☆', max(0, 5 - (int) $review->rating));
        $excerpt = mb_substr(trim(strip_tags((string) ($review->review ?? ''))), 0, 100);

        $this->title = $studentName . ' rated ' . $courseTitle . ' ' . $stars;
        $this->body = $excerpt ?: '(no comment)';
        // Take coach to course review tab (deep-link if available; otherwise course edit)
        $this->url = $review->course
            ? route('instructor.courses.edit-view', ['id' => $review->course_id])
            : null;
        $this->icon = 'fa-star';
        $this->iconColor = '#f59e0b';
    }
}
