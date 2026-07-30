<?php

namespace App\Notifications;

use App\Models\Quiz;
use App\Models\QuizResult;

/**
 * Sent to a student right after they submit a quiz with their result.
 */
class QuizResultToStudent extends InAppNotification
{
    protected string $event = 'quiz_result';
    protected string $emailTemplate = 'notif_quiz_result';
    protected string $emailCtaLabel = 'View result';

    private QuizResult $result;
    private Quiz $quiz;

    public function __construct(QuizResult $result, Quiz $quiz)
    {
        $this->result = $result;
        $this->quiz = $quiz;
        $this->coachId = $quiz->instructor_id ? (int) $quiz->instructor_id : null;
        $passed = $result->status === 'pass';
        $this->title = $passed
            ? 'You passed: ' . \Str::limit((string) $quiz->title, 45)
            : 'Quiz attempt: ' . \Str::limit((string) $quiz->title, 45);
        $this->body = 'Score: ' . $result->user_grade . ' / ' . $quiz->total_mark . ' — ' . ($passed ? 'Pass' : 'Try again');
        $this->icon = $passed ? 'fa-circle-check' : 'fa-clipboard-question';
        $this->iconColor = $passed ? '#10b981' : '#ef4444';
        $this->url = route('student.quiz.result', ['id' => $quiz->id, 'result_id' => $result->id]);
    }

    protected function placeholders(object $notifiable): array
    {
        return [
            'user_name'  => $notifiable->name ?? '',
            'quiz_title' => (string) $this->quiz->title,
            'score'      => (string) $this->result->user_grade,
            'total'      => (string) $this->quiz->total_mark,
            'status'     => $this->result->status === 'pass' ? 'Pass' : 'Fail',
            'result_url' => $this->url,
        ];
    }
}
