<?php

namespace App\Http\Controllers\Frontend;

use App\Models\Course;
use App\Models\LessonReply;
use Illuminate\Http\Request;
use App\Models\LessonQuestion;
use App\Rules\CustomRecaptcha;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;

class QnaController extends Controller {

    /**
     * 2026-06-10 — TENANT SCOPE (white-label isolation). On a coach custom
     * domain, Q&A endpoints must only act on THIS coach's courses, even if the
     * student is enrolled in the same course under another coach. 404 if the
     * given course belongs to a different coach. resolved_coach_id is 0 on the
     * platform domain → no effect.
     */
    private function denyForeignCoachCourse($courseId): void
    {
        $tenantCoachId = (int) request()->attributes->get('resolved_coach_id');
        if ($tenantCoachId > 0) {
            $ownerId = (int) (\App\Models\Course::whereKey($courseId)->value('instructor_id') ?? 0);
            abort_if($ownerId !== $tenantCoachId, 404);
        }
    }

    function create(Request $request) {
        $messages = [
            'question.required'             => __('Question is required'),
            'question.max'                  => __('Question may not be greater than 255 characters'),
            'description.required'          => __('Description is required'),
            'g-recaptcha-response.required' => __('Please complete the recaptcha to submit the form'),
        ];
        $request->validate([
            'question'             => ['required', 'max:255'],
            'description'          => ['required'],
            'lesson_id'            => ['required', 'integer', 'exists:course_chapter_lessons,id'],
            'course_id'            => ['required', 'integer', 'exists:courses,id'],
            'g-recaptcha-response' => Cache::get('setting')->recaptcha_status === 'active' ? ['required', new CustomRecaptcha()] : 'nullable',
        ], $messages);

        // On a coach domain, only this coach's course may receive questions.
        $this->denyForeignCoachCourse($request->course_id);

        // Verify the student is enrolled in the course they're posting on (anti-spam, anti-IDOR)
        $isEnrolled = \Modules\Order\app\Models\Enrollment::where('user_id', userAuth()->id)
            ->where('course_id', $request->course_id)
            ->where('has_access', 1)
            ->exists();
        if (!$isEnrolled) {
            return response()->json([
                'status'  => 'error',
                'message' => __('You must be enrolled in this course to post questions.'),
            ], 403);
        }

        $question = LessonQuestion::create([
            'user_id'              => userAuth()->id,
            'lesson_id'            => $request->lesson_id,
            'course_id'            => $request->course_id,
            'question_title'       => $request->question,
            'question_description' => $request->description,
        ]);

        // Notify the course's coach that a new question was posted.
        try {
            $course = Course::find($request->course_id);
            if ($course && $course->instructor) {
                $question->load(['user:id,name', 'course:id,title,slug']);
                $course->instructor->notify(new \App\Notifications\NewLessonQuestionToCoach($question));
            }
        } catch (\Throwable $e) {
            \Log::warning('Notify coach of new question failed: ' . $e->getMessage());
        }

        return response()->json([
            'status'   => 'success',
            'message'  => __('Question created successfully'),
            'question' => $question,
        ], 200);
    }

    function fetchLessonQuestions(Request $request) {
        // FT-IDOR-36 fix (2026-05-28) — was no enrollment check. The
        // method paginates lessonQuestions by the request-supplied
        // course_id and returns rendered HTML containing every Q&A
        // (title + replies count + user info). Anyone with auth
        // could enumerate course IDs and read the discussion threads
        // of paid courses they never bought — privacy / IP leak.
        $request->validate([
            'course_id' => ['required', 'integer'],
            'lesson_id' => ['nullable', 'integer'],
        ]);
        $this->denyForeignCoachCourse($request->course_id);
        $isEnrolled = \Modules\Order\app\Models\Enrollment::where('user_id', userAuth()->id)
            ->where('course_id', $request->course_id)
            ->where('has_access', 1)
            ->exists();
        abort_unless($isEnrolled, 403);

        $query = LessonQuestion::query();
        $query->withCount('replies');

        $query->when($request->has('query') && strlen($requestQuery = $request->query('query')) > 0, function ($q) use ($requestQuery) {
            $q->where('question_title', 'like', '%' . $requestQuery . '%');
        });
        $query->when($request->filled('filter') && $request->filter == 'current_lecture', function ($q) use ($request) {
            $q->where('lesson_id', $request->lesson_id);
        });
        $query->where('course_id', $request->course_id);

        $questions = $query->paginate(8, ['*'], 'page', $request->page ?: 1);

        return response()->json([
            'view'       => view('frontend.pages.learning-player.partials.question-card', compact('questions'))->render(),
            'page'       => $request->page,
            'last_page'  => $questions->lastPage(),
            'data_count' => $questions->count(),
        ]);
    }

    function createReply(Request $request) {
        $messages = [
            'reply.required'                => __('Replay is required'),
            'g-recaptcha-response.required' => __('Please complete the recaptcha to submit the form'),
        ];
        $request->validate([
            'question_id'          => ['required', 'integer', 'exists:lesson_questions,id'],
            'reply'                => ['required', 'string', 'max:5000'],
            'g-recaptcha-response' => Cache::get('setting')->recaptcha_status === 'active' ? ['required', new CustomRecaptcha()] : 'nullable',
        ], $messages);

        // FT-IDOR-36 fix — was no enrollment check. Replies are
        // visible to everyone in the lesson player; without this
        // check a non-enrolled user could spam Q&A threads on any
        // course on the platform. Resolve the question's course
        // first and enforce enrollment against THAT course
        // (preventing the "reply to a question in someone else's
        // course" variant where the course_id isn't even in the
        // request).
        $lesson_question = LessonQuestion::find($request->question_id);
        abort_if(! $lesson_question, 404);
        $this->denyForeignCoachCourse($lesson_question->course_id);

        // Coach replies via this endpoint are not the normal flow
        // (they have InstructorLessonQnaController::createReply),
        // but allow the course owner to reply without an Enrollment
        // row (they have permission via course ownership).
        $isOwner = (int) ($lesson_question->course?->instructor_id ?? 0) === (int) userAuth()->id
            || \App\Models\Course::where('id', $lesson_question->course_id)
                ->where(function ($q) {
                    $q->where('instructor_id', userAuth()->id)
                      ->orWhere('added_by', userAuth()->id);
                })->exists();

        if (! $isOwner) {
            $isEnrolled = \Modules\Order\app\Models\Enrollment::where('user_id', userAuth()->id)
                ->where('course_id', $lesson_question->course_id)
                ->where('has_access', 1)
                ->exists();
            abort_unless($isEnrolled, 403, __('You must be enrolled in this course to reply.'));
        }

        $reply = LessonReply::create([
            'user_id'     => userAuth()->id,
            'question_id' => $lesson_question->id,
            'reply'       => $request->reply,
        ]);
        if ($lesson_question && Course::where('id', $lesson_question->course_id)
                ->where('instructor_id', '<>', userAuth()->id)
                ->exists()) {
            $lesson_question->update(['seen' => false]);
        }

        return response()->json([
            'status'   => 'success',
            'message'  => __('Reply created successfully'),
            'question' => $reply,
        ], 200);
    }

    function fetchReply(Request $request) {
        // FT-IDOR-36 fix — was no enrollment check. fetchReply loads
        // every reply on a question and renders the partial. Without
        // the check anyone with auth could view replies on any
        // course's questions.
        $request->validate([
            'question_id' => ['required', 'integer', 'exists:lesson_questions,id'],
        ]);
        $question = LessonQuestion::with('user')->where('id', $request->question_id)->first();
        abort_if(! $question, 404);
        $this->denyForeignCoachCourse($question->course_id);

        $isOwner = \App\Models\Course::where('id', $question->course_id)
            ->where(function ($q) {
                $q->where('instructor_id', userAuth()->id)
                  ->orWhere('added_by', userAuth()->id);
            })->exists();
        if (! $isOwner) {
            $isEnrolled = \Modules\Order\app\Models\Enrollment::where('user_id', userAuth()->id)
                ->where('course_id', $question->course_id)
                ->where('has_access', 1)
                ->exists();
            abort_unless($isEnrolled, 403);
        }

        $replies = LessonReply::where('question_id', $question->id)->get();
        return view('frontend.pages.learning-player.partials.reply-card', compact('replies', 'question'))->render();
    }

    function destroyReply(string $id) {
        $reply = LessonReply::where(['user_id' => userAuth()->id, 'id' => $id])->first();
        if ($reply) {
            extractAndFilterImageSrc($reply?->reply);
            $reply->delete();
            return response()->json([
                'status'  => 'success',
                'message' => __('Reply deleted successfully'),
            ], 200);
        }
        return response()->json([
            'status'  => 'error',
            'message' => __('Something went wrong'),
        ]);
    }

    function destroyQuestion(string $id) {

        $question = LessonQuestion::where(['user_id' => userAuth()->id, 'id' => $id])->first();
        if ($question) {
            $question->replies()->delete();
            extractAndFilterImageSrc($question?->question_description);
            $question->delete();

            return response()->json([
                'status'  => 'success',
                'message' => __('Question deleted successfully'),
            ], 200);
        }
        return response()->json([
            'status'  => 'error',
            'message' => __('Something went wrong'),
        ]);

    }
}
