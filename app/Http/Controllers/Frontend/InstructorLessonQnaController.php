<?php

namespace App\Http\Controllers\Frontend;

use App\Models\Course;
use App\Models\LessonReply;
use Illuminate\Http\Request;
use App\Models\LessonQuestion;
use App\Rules\CustomRecaptcha;
use App\Services\MailSenderService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;

class InstructorLessonQnaController extends Controller {
    /**
     * Return a query of LessonQuestion ids whose course belongs to the current coach.
     * Used to scope index/edit/destroy/reply actions and prevent data leakage / IDOR.
     */
    private function ownedQuestionScope()
    {
        $coachId = userAuth()->role === 'instructor' ? userAuth()->id : userAuth()->coach_id;
        $courseIds = Course::where(function ($q) use ($coachId) {
            $q->where('added_by', $coachId)->orWhere('instructor_id', $coachId);
        })->pluck('id');
        return LessonQuestion::whereIn('course_id', $courseIds);
    }

    function index(Request $request) {
        $query = $this->ownedQuestionScope();
        $query->select('id', 'user_id', 'seen', 'course_id', 'lesson_id', 'question_title', 'question_description', 'created_at')->with(['course' => function ($query) {
            $query->select('id', 'slug', 'title', 'thumbnail');
        }, 'lesson' => function ($query) {
            $query->select('id','chapter_item_id', 'title')->with(['chapterItem' => function ($query) {
                $query->select('id', 'type');
            }]);
        }, 'user' => function ($query) {
            $query->select('id', 'name', 'image');
        }, 'replies'])->withCount('replies');

        $query->when($request->filled('seen'), function ($q) use ($request) {
            $q->where('seen', $request->seen);
        });
        $orderBy = $request->filled( 'sort_by' ) && $request->sort_by == 1 ? 'asc' : 'desc';
        $lesson_questions = $query->orderBy( 'id', $orderBy )->paginate(10)->withQueryString();
        return view('frontend.instructor-dashboard.lesson-qna.index', compact('lesson_questions'));
    }
    function destroyQuestion($id) {
        $question = $this->ownedQuestionScope()->where('id', $id)->first();
        if ($question) {
            $question->replies()->delete();
            extractAndFilterImageSrc($question?->question_description);
            $question->delete();

            $notification = ['messege' => __('Question deleted successfully'), 'alert-type' => 'success'];
            return back()->with($notification);
        }
        $notification = ['messege' => __('Something went wrong'), 'alert-type' => 'error'];
        return back()->with($notification);

    }
    function createReply(Request $request, $id) {
        // Verify the question belongs to a course owned by this coach BEFORE creating a reply.
        $this->ownedQuestionScope()->where('id', $id)->firstOrFail();

        $messages = [
            'reply.required'                => __('Replay is required'),
            'g-recaptcha-response.required' => __('Please complete the recaptcha to submit the form'),
        ];
        $request->validate([
            'reply'                => ['required', 'string'],
            'g-recaptcha-response' => Cache::get('setting')->recaptcha_status === 'active' ? ['required', new CustomRecaptcha()] : 'nullable',
        ], $messages);

        $reply = LessonReply::create([
            'user_id'     => userAuth()->id,
            'question_id' => $id,
            'reply'       => $request->reply,
        ]);
        $question = $this->ownedQuestionScope()->where('id', $id)->select('id', 'user_id', 'seen', 'course_id', 'lesson_id', 'question_title')->with(['course' => function ($query) {
            $query->select('id', 'slug', 'title');
        }, 'lesson' => function ($query) {
            $query->select('id','chapter_item_id', 'title')->with(['chapterItem' => function ($query) {
                $query->select('id', 'type');
            }]);
        }, 'user' => function ($query) {
            $query->select('id', 'name','email');
        }])->first();

        $question->update(['seen' => true]);

        (new MailSenderService)->sendQnaReplyMailTrait($question);

        // Persist + push a notification to the original questioner. Database channel
        // populates the bell-icon dropdown; broadcast channel pushes via Pusher in real-time.
        try {
            $student = \App\Models\User::find($question->user_id);
            if ($student) {
                $student->notify(new \App\Notifications\LessonQuestionRepliedToStudent(
                    $question,
                    $reply,
                    userAuth()->name ?? 'Instructor'
                ));
            }
        } catch (\Throwable $e) {
            // Don't fail the reply submission if notification dispatch hiccups.
            \Log::warning('Notify student of reply failed: ' . $e->getMessage());
        }

        $notification = ['messege' => __('Reply created successfully'), 'alert-type' => 'success'];
        return back()->with($notification);
    }
    function destroyReply($id) {
        // Only allow deleting a reply on a question that belongs to a course owned by this coach.
        $ownedQuestionIds = $this->ownedQuestionScope()->pluck('id');
        $reply = LessonReply::whereIn('question_id', $ownedQuestionIds)->where('id', $id)->first();
        if ($reply) {
            extractAndFilterImageSrc($reply?->reply);
            $reply->delete();
            $notification = ['messege' => __('Reply deleted successfully'), 'alert-type' => 'success'];
            return back()->with($notification);
        }
        $notification = ['messege' => __('Something went wrong'), 'alert-type' => 'error'];
        return back()->with($notification);
    }
    public function markAsReadUnread($id) {
        $question = $this->ownedQuestionScope()->where('id', $id)->firstOrFail();
        $seen = $question->seen == 1 ? 0 : 1;
        $question->update(['seen' => $seen]);

        $notification = __('Updated Successfully');

        return response()->json([
            'success' => true,
            'title' => $question->seen == 1 ? __('Mark read') : __('Mark as unread'),
            'message' => $notification,
        ]);
    }
}
