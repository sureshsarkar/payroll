<?php

namespace App\Notifications;

use App\Models\LessonQuestion;

/**
 * Sent to a coach (the course's instructor) when a student posts a new lesson question.
 */
class NewLessonQuestionToCoach extends InAppNotification
{
    protected string $event = 'new_lesson_question';

    /** Coach-facing: brand the email as the coach who owns the recipient. */
    protected function brandCoachId(object $notifiable): ?int
    {
        return $this->recipientCoachId($notifiable);
    }

    public function __construct(LessonQuestion $question)
    {
        $studentName = (string) ($question->user->name ?? 'A student');
        $courseTitle = (string) ($question->course->title ?? '');
        $excerpt = mb_substr(trim(strip_tags((string) $question->question_description)), 0, 120);

        $this->title = $studentName . ' asked: "' . $question->question_title . '"';
        $this->body = ($courseTitle ? '[' . $courseTitle . '] ' : '') . $excerpt;
        $this->url = route('instructor.lesson-questions.index');
        $this->icon = 'fa-question-circle';
        $this->iconColor = '#3b82f6';
    }
}
