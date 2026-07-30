<?php

namespace App\Notifications;

use App\Models\LessonQuestion;
use App\Models\LessonReply;

/**
 * Sent to a student when a coach (or coach staff) replies to their lesson question.
 */
class LessonQuestionRepliedToStudent extends InAppNotification
{
    protected string $event = 'lesson_question_replied';

    public function __construct(LessonQuestion $question, LessonReply $reply, string $replierName)
    {
        $excerpt = mb_substr(trim(strip_tags((string) $reply->reply)), 0, 120);
        $courseTitle = (string) ($question->course->title ?? 'your course');
        $courseSlug = (string) ($question->course->slug ?? '');
        $this->coachId = $question->course?->instructor_id ? (int) $question->course->instructor_id : null;

        $this->title = $replierName . ' replied to "' . $question->question_title . '"';
        $this->body = $excerpt . (mb_strlen(strip_tags($reply->reply)) > 120 ? '…' : '');
        $this->url = $courseSlug
            ? route('student.learning.index', ['slug' => $courseSlug])
            : null;
        $this->icon = 'fa-comments';
        $this->iconColor = '#10b981';
    }
}
