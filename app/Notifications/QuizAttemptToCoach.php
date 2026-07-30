<?php

namespace App\Notifications;

use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Coach quiz-attempt notification. Fired when a student submits a quiz on one
 * of the coach's courses, so it lands in the coach's in-app bell/inbox
 * ("Activity on your courses… quiz attempts… shows up here").
 *
 * Rides the shared InAppNotification pipeline (database + realtime), but
 * overrides via() to SKIP email — quiz attempts are high-frequency and coaches
 * shouldn't get an email per attempt. coachId resolves to the quiz's course
 * owner, so a coach only ever sees attempts on THEIR OWN courses.
 */
class QuizAttemptToCoach extends InAppNotification
{
    protected string $event = 'quiz_attempt_to_coach';
    protected string $emailTemplate = 'notif_quiz_attempt_to_coach';
    protected string $emailCtaLabel = 'View attempts';

    private string $studentName;
    private string $quizTitle;
    private string $resultStatus;
    private int $grade;

    public function __construct($quizResult, $quiz, ?User $student)
    {
        $this->coachId = ((int) ($quiz->course->instructor_id
            ?? Course::where('id', $quiz->course_id ?? 0)->value('instructor_id')
            ?? 0)) ?: null;

        $this->studentName  = (string) ($student?->name ?: __('A student'));
        $this->quizTitle    = (string) ($quiz->title ?? __('a quiz'));
        $this->resultStatus = ucfirst((string) ($quizResult->status ?? ''));
        $this->grade        = (int) ($quizResult->user_grade ?? 0);

        $this->title = __('Quiz attempt: :quiz', ['quiz' => Str::limit($this->quizTitle, 50)]);
        $this->body  = __(':student attempted ":quiz" — :status (:grade).', [
            'student' => $this->studentName,
            'quiz'    => Str::limit($this->quizTitle, 50),
            'status'  => $this->resultStatus ?: __('completed'),
            'grade'   => $this->grade,
        ]);
        $this->icon      = 'fa-clipboard-question';
        $this->iconColor = '#7c3aed';
        try { $this->url = route('instructor.dashboard'); } catch (\Throwable $e) { $this->url = url('/'); }
    }

    /** Quiz attempts are frequent — bell/app + realtime, but never per-attempt email. */
    public function via(object $notifiable): array
    {
        return array_values(array_filter(parent::via($notifiable), fn ($c) => $c !== 'mail'));
    }

    protected function placeholders(object $notifiable): array
    {
        return [
            'coach_name'    => $notifiable->name ?? '',
            'student_name'  => $this->studentName,
            'quiz_title'    => $this->quizTitle,
            'result_status' => $this->resultStatus,
            'grade'         => (string) $this->grade,
        ];
    }
}
