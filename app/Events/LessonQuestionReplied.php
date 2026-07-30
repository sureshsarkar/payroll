<?php

namespace App\Events;

use App\Models\LessonQuestion;
use App\Models\LessonReply;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a coach (or staff) replies to a student's lesson question.
 * Broadcasts to the original questioner so they get a real-time toast.
 *
 * Frontend listens with:
 *   Echo.private('App.Models.User.' + userId)
 *       .listen('LessonQuestionReplied', (e) => { ... })
 */
class LessonQuestionReplied implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $studentId;
    public int $questionId;
    public string $questionTitle;
    public string $courseTitle;
    public string $courseSlug;
    public string $replyExcerpt;
    public string $replierName;

    public function __construct(LessonQuestion $question, LessonReply $reply, string $replierName)
    {
        $this->studentId = (int) $question->user_id;
        $this->questionId = (int) $question->id;
        $this->questionTitle = (string) $question->question_title;
        $this->courseTitle = (string) ($question->course->title ?? '');
        $this->courseSlug = (string) ($question->course->slug ?? '');
        // Trim the reply HTML to a safe excerpt (first 140 chars of plain text)
        $this->replyExcerpt = mb_substr(
            trim(strip_tags((string) $reply->reply)),
            0,
            140
        );
        $this->replierName = $replierName;
    }

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('App.Models.User.' . $this->studentId);
    }

    public function broadcastAs(): string
    {
        return 'LessonQuestionReplied';
    }
}
