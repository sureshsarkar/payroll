<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\AnnouncementResource;
use App\Http\Resources\API\CourseDetailsCollection;
use App\Http\Resources\API\CourseListResource;
use App\Http\Resources\API\EnrolledCourseResource;
use App\Http\Resources\API\LessonResource;
use App\Http\Resources\API\LiveResource;
use App\Http\Resources\API\OrderDetailsResource;
use App\Http\Resources\API\OrderResource;
use App\Http\Resources\API\QnaReplyResource;
use App\Http\Resources\API\QnaResource;
use App\Http\Resources\API\QuizAttemptsResource;
use App\Http\Resources\API\QuizResource;
use App\Http\Resources\API\ReviewsResource;
use App\Http\Resources\API\UserResource;
use App\Models\Announcement;
use App\Models\Course;
use App\Models\CourseChapterItem;
use App\Models\CourseChapterLesson;
use App\Models\CourseProgress;
use App\Models\CourseReview;
use App\Models\User;
use Illuminate\Support\Facades\URL;
use App\Models\LessonQuestion;
use App\Models\LessonReply;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\QuizResult;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Modules\CertificateBuilder\app\Models\CertificateBuilder;
use Modules\CertificateBuilder\app\Models\CertificateBuilderItem;
use Modules\Order\app\Models\Enrollment;
use Modules\Order\app\Models\Order;

class DashboardController extends Controller {
    public function enrolled_courses(Request $request): JsonResponse {
        $user_id = auth()->user()->id;
        $limit = $request->filled('limit') && is_numeric($request->limit) ? (int) $request->limit : 6;

        $enrolls = Enrollment::select('course_id')->where('user_id', $user_id)->with([
            'course' => function ($q) {
                $q->select('id', 'instructor_id', 'title', 'slug', 'thumbnail')->with('instructor:id,name,image')->withTrashed()->withCount('enrollments');
            },
        ])->orderByDesc('id')->paginate(perPage: $limit); 
        
        //course percentage calculate
        $enrolls->getCollection()->transform(function ($enroll) use ($user_id) {
            $course = $enroll->course;
            $courseCompletedPercent = CourseChapterItem::whereHas('chapter', fn($q) => $q->where('course_id', $course->id))->count() > 0 ? CourseProgress::where('user_id', $user_id)->where('course_id', $course->id)->where('watched', 1)->count() / CourseChapterItem::whereHas('chapter', fn($q) => $q->where('course_id', $course->id))->count() * 100 : 0;

            $course->completed_percent = $courseCompletedPercent;
            return $enroll;
        });

        if ($enrolls->isNotEmpty()) {
            $data = EnrolledCourseResource::collection($enrolls);
            return response()->json(['status' => 'success',
                'data'                            => $data,
                'pagination'                      => [
                    'current_page' => $enrolls->currentPage(),
                    'per_page'     => $enrolls->perPage(),
                    'total'        => $enrolls->total(),
                    'last_page'    => $enrolls->lastPage(),
                    'links'        => [
                        'first' => $enrolls->url(1),
                        'prev'  => $enrolls->previousPageUrl(),
                        'next'  => $enrolls->nextPageUrl(),
                        'last'  => $enrolls->url($enrolls->lastPage()),
                    ],
                ],

            ], 200);
        }
        return response()->json(['status' => 'error', 'message' => 'Not Foundaa!'], 404);
    }
 

    public function wishlist_courses(Request $request): JsonResponse {
        $limit = $request->filled('limit') && is_numeric($request->limit) ? (int) $request->limit : 6;

        $courses = auth()->user()->favoriteCourses()
            // ->whereHas('category.parentCategory', fn($q) => $q->where('status', 1))
            ->whereHas('category', fn($q) => $q->where('status', 1))
            ->withCount(['reviews as average_rating' => function ($q) {
                $q->select(DB::raw('coalesce(avg(rating), 0) as average_rating'))->where('status', 1);
            }, 'enrollments'])->paginate($limit);

        if ($courses->isNotEmpty()) {
            $data = CourseListResource::collection($courses);
            return response()->json(['status' => 'success',
                'data'                            => $data,
                'pagination'                      => [
                    'current_page' => $courses->currentPage(),
                    'per_page'     => $courses->perPage(),
                    'total'        => $courses->total(),
                    'last_page'    => $courses->lastPage(),
                    'links'        => [
                        'first' => $courses->url(1),
                        'prev'  => $courses->previousPageUrl(),
                        'next'  => $courses->nextPageUrl(),
                        'last'  => $courses->url($courses->lastPage()),
                    ],
                ],

            ], 200);
        }
        return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
    }

    public function add_remove_wishlist(Course $course): JsonResponse {
        if (!$course) {
            return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
        }
        $favorite = auth()->user()->favoriteCourses();
        $favorite->toggle($course);
        return response()->json(['status' => 'success', 'message' => 'Success','data'=>$favorite], 200);
    }

    public function course_learning(string $slug): JsonResponse {
        $user = auth()->user();
        if (!self::checkEnrollments($user, $slug)) {
            return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
        }
        $user_id = $user->id;

        $course = Course::active()->where('slug', $slug)->select('id', 'instructor_id', 'thumbnail', 'title', 'description')->with([
            'instructor:id,name,image',
            'chapters'=> function ($query) {
                $query->where('status', 'active')->select('id', 'course_id', 'title')->orderBy('order', 'asc')->with([
                    'chapterItems:id,chapter_id,type',
                    'chapterItems.quiz'   => fn($q)   => $q->select('id', 'chapter_item_id', 'title', 'time', 'attempt', 'pass_mark', 'total_mark')->where('status', 'active'),
                    'chapterItems.lesson' => fn($q) => $q->select('id', 'chapter_item_id', 'title', 'file_path', 'storage', 'file_type', 'duration', 'is_free')->where('status', 'active'),
                ]);
            },
            'languages:id,course_id,language_id' => ['language:id,name'],
        ])
        //    ->whereHas('category.parentCategory', fn($q) => $q->where('status', 1))
            ->whereHas('category', fn($q) => $q->where('status', 1))->withCount([
            'reviews as average_rating' => fn($q) => $q->select(DB::raw('coalesce(avg(rating), 0)'))->where('status', 1),
            'reviews'                   => fn($q)                   => $q->where('status', 1),
            'lessons', 'quizzes', 'enrollments',
            'favoriteBy as is_wishlist' => function ($query) use ($user_id) {
                $query->where('user_id', $user_id);
            },
        ])->first();
   
        if (!$course) {
            return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
        } 
        
        $data = new CourseDetailsCollection($course);

        return response()->json(['status' => 'success', 'data' => $data], 200);
    }

    public function get_lesson_info(string $slug, string $type, int $lesson_id): JsonResponse {
        $user = auth()->user();
        if (!self::checkEnrollments($user, $slug)) {
            return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
        }

        if (in_array($type, ['lesson', 'document'])) {
            // SECURITY (audit 2026-06-12) — bind the lesson to the ENROLLED
            // course (slug). Was findOrFail($lesson_id) with no course link, so
            // a student enrolled in any one course could pass any lesson_id and
            // read another coach's paid lesson file_path/storage. The slug
            // constraint mirrors the safe quiz_index() pattern.
            $lesson = CourseChapterLesson::select('id', 'course_id', 'chapter_id', 'chapter_item_id', 'title', 'description', 'downloadable', 'file_path', 'storage', 'file_type', 'duration')
                ->where('id', $lesson_id)
                ->whereHas('course', fn($q) => $q->where('slug', $slug))
                ->first();

            if (!$lesson) {
                return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
            }
            // Update course progress
            self::updateCourseProgress($user->id, $lesson->course_id, $lesson->chapter_id, $lesson_id, $type);

            $data = new LessonResource($lesson);
            return response()->json(['status' => 'success', 'data' => $data], 200);
        } elseif ($type == 'live') {
            // SECURITY (audit 2026-06-12) — bind to the enrolled course (slug);
            // was an unscoped findOrFail that leaked other coaches' live-class
            // meeting_id / join_url.
            $live = CourseChapterLesson::with(['live:id,lesson_id,start_time,type,meeting_id,join_url'])->select('id', 'course_id', 'chapter_id', 'chapter_item_id', 'title', 'description', 'duration', 'is_free')
                ->where('id', $lesson_id)
                ->whereHas('course', fn($q) => $q->where('slug', $slug))
                ->first();

            if (!$live) {
                return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
            }
            // Update course progress
            self::updateCourseProgress($user->id, $live->course_id, $live->chapter_id, $lesson_id, $type);

            $now = Carbon::now();
            $startTime = Carbon::parse($live['live']['start_time']);
            $endTime = $startTime->clone()->addMinutes($live['duration']);
            $live['start_time'] = formattedDateTime($startTime);
            $live['end_time'] = formattedDateTime($endTime);

            if ($now->between($startTime, $endTime)) {
                $live['is_live_now'] = 'started';
            } elseif ($now->lt($startTime)) {
                $live['is_live_now'] = 'not_started';
            } else {
                $live['is_live_now'] = 'ended';
            }

            $data = new LiveResource($live);
            return response()->json(['status' => 'success', 'data' => $data], 200);
        } else {
            // SECURITY (audit 2026-06-12) — bind the quiz to the enrolled course.
            $quiz = Quiz::withCount('questions')
                ->where('id', $lesson_id)
                ->whereHas('course', fn($q) => $q->where('slug', $slug))
                ->first();
            if (!$quiz) {
                return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
            }
            // Update course progress
            self::updateCourseProgress($user->id, $quiz->course_id, $quiz->chapter_id, $lesson_id, $type);

            $data = new QuizResource($quiz);
            return response()->json(['status' => 'success', 'data' => $data], 200);
        }

    }
    public function make_lesson_complete(int $lesson_id): JsonResponse {
        $user = auth()->user();
        $progress = CourseProgress::where(['lesson_id' => $lesson_id, 'user_id' => $user->id])->first();
        if ($progress) {
            $progress->watched = !$progress->watched;
            $progress->save();
            return response()->json(['status' => 'success', 'message' => 'Updated successfully.'], 200);
        }
        return response()->json(['status' => 'error', 'message' => 'You didnt watched this lesson'], 400);
    }
    public function quiz_index(string $slug, int $id): JsonResponse {
        $user = auth()->user();
        if (!self::checkEnrollments($user, $slug)) {
            return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
        }
        $quiz = Quiz::whereHas('course', fn($q) => $q->where('slug', $slug))->with([
            'questions:id,quiz_id,title,type' => ['answers:id,question_id,title'],
        ])->withCount('questions')->find($id);
        $attempt = QuizResult::where('user_id', $user->id)->where('quiz_id', $id)->count();
        if ($attempt >= $quiz->attempt) {
            return response()->json(['status' => 'error', 'message' => 'You reached maximum attempt'], 400);
        }
        $quiz = new QuizResource($quiz);
        return response()->json(['status' => 'success', 'data' => ['quiz' => $quiz, 'attempt' => (int) $attempt]], 200);
    }
    public function quiz_store(Request $request, string $slug, int $id): JsonResponse {
        $user = auth()->user();
        if (!self::checkEnrollments($user, $slug)) {
            return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
        }

        $validator = Validator::make($request->all(), [
            'answers'               => 'required|array',
            'answers.*.question_id' => 'required|exists:quiz_questions,id',
            'answers.*.answer_id'   => 'required|integer',
        ], [
            'answers.required'               => 'Answers are required.',
            'answers.array'                  => 'Answers must be an array.',
            'answers.*.question_id.required' => 'Each answer must have a question ID.',
            'answers.*.question_id.exists'   => 'The provided question ID does not exist.',
            'answers.*.answer_id.required'   => 'Each answer must have an answer ID.',
            'answers.*.answer_id.integer'    => 'Answer ID must be an integer.',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()], 422);
        }
        $grad = 0;
        $result = [];

        // Fetch the quiz
        $quiz = Quiz::whereHas('course', fn($q) => $q->where('slug', $slug))->findOrFail($id);

        // Process each answer in the submitted data.
        //
        // Scoring (FIXED 2026-05-14): each `quiz_questions` row carries
        // its own `grade` (point value), and `pass_mark` / `total_mark`
        // on the quiz are in the SAME point scale. So a correct answer
        // adds the question's grade to the user's score — NOT just +1.
        //
        // The old logic incremented `$grad` by 1 per correct answer
        // regardless of the question's weight. A 1-question quiz with
        // `pass_mark=50` could therefore never be passed: max `$grad`
        // was 1, which is < 50.
        foreach ($request->answers as $key => $answerData) {
            $question = QuizQuestion::findOrFail($answerData['question_id']);
            $correctAnswers = $question->answers->where('correct', 1)->pluck('id')->toArray();

            // Check if the provided answer is correct
            $isCorrect = in_array($answerData['answer_id'], $correctAnswers);

            if ($isCorrect) {
                // Add this question's point value to the running grade.
                // Fall back to evenly-dividing total_mark across
                // questions if `grade` is null (legacy quizzes that
                // didn't set per-question grades).
                $points = (int) ($question->grade ?? 0);
                if ($points <= 0) {
                    $qCount = (int) $quiz->questions()->count();
                    $points = $qCount > 0
                        ? (int) ((int) $quiz->total_mark / $qCount)
                        : 0;
                }
                $grad += $points;
            }

            $result[$answerData['question_id']] = [
                "answer"  => $answerData['answer_id'],
                "correct" => $isCorrect,
            ];
        }

        // Store the quiz result
        $quizResult = QuizResult::create([
            'user_id'    => $user->id,
            'quiz_id'    => $id,
            'result'     => json_encode($result),
            'user_grade' => $grad,
            'status'     => $grad >= (int) $quiz->pass_mark ? 'pass' : 'failed',
        ]);

        // Notify the course's coach (in-app bell/inbox). Fire-and-forget —
        // wrapped so a notification failure can never break quiz submission.
        try {
            $coachId = (int) \App\Models\Course::where('id', $quiz->course_id)->value('instructor_id');
            if ($coachId) {
                optional(\App\Models\User::find($coachId))
                    ->notify(new \App\Notifications\QuizAttemptToCoach($quizResult, $quiz, $user));
            }
        } catch (\Throwable $e) {
            \Log::warning('api-quiz-attempt-notify-failed', ['quiz' => $id, 'err' => $e->getMessage()]);
        }

        return response()->json(['status' => 'success', 'data' => $quizResult], 200);
    }
    public function quiz_results(string $slug, int $id): JsonResponse {
        $user = auth()->user();
        if (!self::checkEnrollments($user, $slug)) {
            return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
        }
        $quiz_result = QuizResult::where(['user_id' => $user->id, 'quiz_id' => $id])->select('id', 'user_id', 'quiz_id', 'result', 'user_grade', 'status', 'created_at')->with(['quiz:id,course_id,title', 'quiz.course:id,title'])->first();

        if ($quiz_result) {
            $data = new QuizAttemptsResource($quiz_result);
            return response()->json(['status' => 'success', 'data' => $data], 200);
        }
        return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
    }
    public function fetch_lesson_questions(Request $request, string $course_slug, int $lesson_id): JsonResponse {
        $limit = $request->filled('limit') && is_numeric($request->limit) ? (int) $request->limit : 6;
        $user = auth()->user();

        $query = LessonQuestion::query();

        $query->where(['user_id'=> $user->id,'lesson_id'=> $lesson_id])->whereHas('course', function ($q) use ($course_slug) {
            $q->where('slug', $course_slug);
        })->with('user','replies')->withCount('replies');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('question_title', 'like', "%{$request->search}%")
                    ->orWhere('question_description', 'like', "%{$request->search}%");
            });
        }
        $questions = $query->oldest()->paginate($limit);

        if ($questions->isNotEmpty()) {
            $data = QnaResource::collection($questions);
            return response()->json(['status' => 'success',
                'data'                            => $data,
                'pagination'                      => [
                    'current_page' => $questions->currentPage(),
                    'per_page'     => $questions->perPage(),
                    'total'        => $questions->total(),
                    'last_page'    => $questions->lastPage(),
                    'links'        => [
                        'first' => $questions->url(1),
                        'prev'  => $questions->previousPageUrl(),
                        'next'  => $questions->nextPageUrl(),
                        'last'  => $questions->url($questions->lastPage()),
                    ],
                ],

            ], 200);
        }
        return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
    }
    public function create_lesson_questions(Request $request, string $course_slug, int $lesson_id): JsonResponse {
        $user = auth()->user();
        $course = Course::select('id')->whereSlug($course_slug)->whereHas('enrollments', fn($q) => $q->where(['user_id' => $user->id, 'has_access' => 1]))->whereHas('lessons', fn($q) => $q->where('id', $lesson_id))->first();
        if (!$course) {
            return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
        }

        $validator = Validator::make($request->all(), [
            'question'    => ['required', 'max:255'],
            'description' => ['required'],
        ], [
            'question.required'    => 'Question is required',
            'question.max'         => 'Question may not be greater than 255 characters',
            'description.required' => 'Description is required',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()], 422);
        }

        $question = LessonQuestion::create([
            'user_id'              => $user->id,
            'lesson_id'            => $lesson_id,
            'course_id'            => $course->id,
            'question_title'       => $request->question,
            'question_description' => $request->description,
        ]);
        $data = new QnaResource($question);
        return response()->json(['status' => 'success', 'message' => 'Question created successfully','data' => $data], 201);
    }
    public function destroyQuestion(int $question_id): JsonResponse {

        $question = LessonQuestion::where(['user_id' => auth()->id(), 'id' => $question_id])->first();
        if ($question) {
            $question->replies()->delete();
            extractAndFilterImageSrc($question?->question_description);
            $question->delete();

            return response()->json(['status' => 'success', 'message' => 'Question deleted successfully'], 200);
        }
        return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
    }
    public function create_replay_questions(Request $request, int $lesson_id, int $question_id): JsonResponse {
        $user = auth()->user();
        $question = LessonQuestion::select('id')->whereHas(
            'lesson', fn($q) => $q->where('id', $lesson_id)
        )->where(['user_id'=>$user->id,'id'=>$question_id])->first();
        if (!$question) {
            return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
        }
        $validator = Validator::make($request->all(), [
            'reply' => 'required',
        ], [
            'reply.required' => 'Replay is required',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()], 422);
        }

        $reply = LessonReply::create([
            'user_id'     => $user->id,
            'question_id' => $question->id,
            'reply'       => $request?->reply,
        ]);
        $data = new QnaReplyResource($reply);
        return response()->json(['status' => 'success', 'data' => $data, 'message' => 'Reply created successfully'], 201);
    }
    public function destroyReply(int $reply_id): JsonResponse {
        $reply = LessonReply::where(['user_id' => auth()->id(), 'id' => $reply_id])->first();
        if ($reply) {
            extractAndFilterImageSrc($reply?->reply);
            $reply->delete();
            return response()->json(['status' => 'success', 'message' => 'Reply deleted successfully'], 200);
        }
        return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
    }
    /**
     * Phase 33B (2026-05-15) — aggregate announcements across every
     * course the auth'd student is enrolled in. Used by the new
     * "Announcements" feed in the Profile tab.
     *
     * Returns a paginated list ordered newest-first. Each row carries
     * the course slug + title so the screen can render the source course
     * and (optionally) deep-link into it.
     */
    public function aggregateAnnouncements(\Illuminate\Http\Request $request): JsonResponse {
        $user = auth()->user();
        $limit = $request->filled('limit') && is_numeric($request->limit)
            ? max(1, min(50, (int) $request->limit))
            : 20;

        $enrolledIds = \DB::table('enrollments')
            ->where('user_id', $user->id)
            ->where('has_access', 1)
            ->pluck('course_id')
            ->all();

        if (empty($enrolledIds)) {
            return response()->json([
                'status' => 'success',
                'data'   => [],
                'pagination' => [
                    'current_page' => 1, 'per_page' => $limit, 'total' => 0, 'last_page' => 1,
                ],
            ]);
        }

        $page = \App\Models\Announcement::whereIn('course_id', $enrolledIds)
            ->with(['instructor:id,name,image', 'course:id,slug,title,thumbnail'])
            ->orderByDesc('id')
            ->paginate($limit);

        $items = $page->getCollection()->map(function ($a) {
            return [
                'id'           => $a->id,
                'title'        => $a->title,
                'announcement' => $a->announcement,
                'created_at'   => $a->created_at?->toIso8601String(),
                'time_ago'     => $a->created_at?->diffForHumans(),
                'instructor'   => $a->instructor ? [
                    'id'    => $a->instructor->id,
                    'name'  => $a->instructor->name,
                    'image' => $a->instructor->image,
                ] : null,
                'course'       => $a->course ? [
                    'id'        => $a->course->id,
                    'slug'      => $a->course->slug,
                    'title'     => $a->course->title,
                    'thumbnail' => $a->course->thumbnail,
                ] : null,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data'   => $items,
            'pagination' => [
                'current_page' => $page->currentPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'last_page'    => $page->lastPage(),
            ],
        ]);
    }

    public function course_announcements(string $slug): JsonResponse {
        $user = auth()->user();
        if (!self::checkEnrollments($user, $slug)) {
            return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
        }

        $announcements = Announcement::select('instructor_id', 'title', 'announcement', 'created_at')->whereHas('course', fn($q) => $q->where('slug', $slug))->with('instructor:id,name,image')->orderBy('id', 'desc')->get();

        if ($announcements->isNotEmpty()) {
            $data = AnnouncementResource::collection($announcements);
            return response()->json(['status' => 'success', 'data' => $data], 200);
        }
        
        return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
    }

    public function orders(Request $request): JsonResponse {
        $limit = $request->filled('limit') && is_numeric($request->limit) ? (int) $request->limit : 6;
        $orders = Order::select('invoice_id', 'payment_method', 'payable_currency', 'paid_amount', 'payment_status', 'status')->where('buyer_id', auth()->id())->latest()->paginate($limit);

        if ($orders->isNotEmpty()) {
            $data = OrderResource::collection($orders);
            return response()->json(['status' => 'success',
                'data'                            => $data,
                'pagination'                      => [
                    'current_page' => $orders->currentPage(),
                    'per_page'     => $orders->perPage(),
                    'total'        => $orders->total(),
                    'last_page'    => $orders->lastPage(),
                    'links'        => [
                        'first' => $orders->url(1),
                        'prev'  => $orders->previousPageUrl(),
                        'next'  => $orders->nextPageUrl(),
                        'last'  => $orders->url($orders->lastPage()),
                    ],
                ],
            ], 200);
        }
        return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
    }
    public function show_order(string $invoice_id): JsonResponse {
        $order = Order::select('id', 'invoice_id', 'buyer_id', 'payment_method', 'payable_currency', 'paid_amount', 'coupon_discount_amount', 'gateway_charge', 'conversion_rate', 'payment_status', 'created_at', 'status')->where('invoice_id', $invoice_id)->where('buyer_id', auth()->id())
            ->with([
                'user:id,name,email,phone,address',
                'orderItems:id,order_id,course_id,price',
                'orderItems.course:id,instructor_id,title',
                'orderItems.course.instructor:id,name',
            ])->first();
        if ($order) {
            $data = new OrderDetailsResource($order);
            return response()->json(['status' => 'success', 'data' => $data], 200);
        }
        return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
    }

    public function reviews(Request $request): JsonResponse {
        $limit = $request->filled('limit') && is_numeric($request->limit) ? (int) $request->limit : 6;
        $reviews = CourseReview::with('course:title,id')->where('user_id', auth()->id())->latest()->paginate($limit);

        if ($reviews->isNotEmpty()) {
            $data = ReviewsResource::collection($reviews);
            return response()->json(['status' => 'success',
                'data'                            => $data,
                'pagination'                      => [
                    'current_page' => $reviews->currentPage(),
                    'per_page'     => $reviews->perPage(),
                    'total'        => $reviews->total(),
                    'last_page'    => $reviews->lastPage(),
                    'links'        => [
                        'first' => $reviews->url(1),
                        'prev'  => $reviews->previousPageUrl(),
                        'next'  => $reviews->nextPageUrl(),
                        'last'  => $reviews->url($reviews->lastPage()),
                    ],
                ],
            ], 200);
        }
        return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
    }
    public function show_review(string $id): JsonResponse {
        $review = CourseReview::with('course:title,id')->where('user_id', auth()->id())->find($id);

        if ($review) {
            $data = new ReviewsResource($review);
            return response()->json(['status' => 'success', 'data' => $data], 200);
        }
        return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
    }
    public function destroy_review(string $id): JsonResponse {
        $review = CourseReview::where('user_id', auth()->id())->find($id);
        if ($review) {
            $review->delete();
            return response()->json(['status' => 'success', 'message' => 'Review deleted successfully'], 200);
        }
        return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
    }

    /**
     * Create a review for a course the student is enrolled in.
     *
     * Validates:
     *   - course_id exists
     *   - rating is 1..5 integer
     *   - review is required string, ≤ 1000 chars (matches web validator)
     *
     * Business rules:
     *   - Student must be enrolled in the course (otherwise 403 — prevents
     *     review-bombing by non-customers)
     *   - One review per (user, course); subsequent attempts return 409
     *     so the client can offer to update instead (deferred to a later
     *     phase — for v1 the user can delete + re-create)
     *
     * Side effect: notifies the course's coach of the new review,
     * matching the web flow's `NewCourseReviewToCoach` notification.
     */
    public function store_review(\Illuminate\Http\Request $request): JsonResponse
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'rating'    => ['required', 'integer', 'min:1', 'max:5'],
            'review'    => ['required', 'string', 'max:1000'],
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $userId   = auth()->id();
        $courseId = (int) $request->input('course_id');

        // Enrollment gate — non-enrolled students can't drive-by review.
        $enrolled = \Modules\Order\app\Models\Enrollment::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->exists();
        if (! $enrolled) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Enroll in this course before leaving a review.',
            ], 403);
        }

        $existing = CourseReview::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->first();
        if ($existing) {
            return response()->json([
                'status'  => 'error',
                'message' => 'You already reviewed this course.',
            ], 409);
        }

        $review = CourseReview::create([
            'course_id' => $courseId,
            'user_id'   => $userId,
            'rating'    => (int) $request->input('rating'),
            'review'    => (string) $request->input('review'),
        ]);

        // Mirror the web flow's coach notification (best-effort).
        try {
            $course = \App\Models\Course::find($courseId);
            if ($course && $course->instructor) {
                $review->load(['user:id,name', 'course:id,title']);
                $course->instructor->notify(
                    new \App\Notifications\NewCourseReviewToCoach($review)
                );
            }
        } catch (\Throwable $e) {
            \Log::warning('Notify coach of new review failed: '.$e->getMessage());
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Review added successfully.',
            'data'    => [
                'id'        => $review->id,
                'course_id' => $review->course_id,
                'rating'    => $review->rating,
                'review'    => $review->review,
            ],
        ], 201);
    }
    public function quiz_attempts(Request $request): JsonResponse {
        $limit = $request->filled('limit') && is_numeric($request->limit) ? (int) $request->limit : 6;
        $quizAttempts = QuizResult::select('id', 'user_id', 'quiz_id', 'result', 'user_grade', 'status', 'created_at')->with(['quiz:id,course_id,title', 'quiz.course:id,title'])->where('user_id', auth()->id())->latest()->paginate($limit);

        if ($quizAttempts->isNotEmpty()) {
            $data = QuizAttemptsResource::collection($quizAttempts);
            return response()->json(['status' => 'success',
                'data'                            => $data,
                'pagination'                      => [
                    'current_page' => $quizAttempts->currentPage(),
                    'per_page'     => $quizAttempts->perPage(),
                    'total'        => $quizAttempts->total(),
                    'last_page'    => $quizAttempts->lastPage(),
                    'links'        => [
                        'first' => $quizAttempts->url(1),
                        'prev'  => $quizAttempts->previousPageUrl(),
                        'next'  => $quizAttempts->nextPageUrl(),
                        'last'  => $quizAttempts->url($quizAttempts->lastPage()),
                    ],
                ],
            ], 200);
        }
        return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
    }
    public function show_quiz_attempt(string $id): JsonResponse {
        $quiz_result = QuizResult::select('id', 'user_id', 'quiz_id', 'result', 'user_grade', 'status', 'created_at')->with(['quiz:id,course_id,title', 'quiz.course:id,title'])->where('user_id', auth()->id())->find($id);

        if ($quiz_result) {
            $data = new QuizAttemptsResource($quiz_result);
            return response()->json(['status' => 'success', 'data' => $data], 200);
        }
        return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
    }
    public function profile(): JsonResponse {
        $user = auth()->user();
        $data = new UserResource($user);
        return response()->json(['status' => 'success', 'data' => $data], 200);
    }
    public function update_profile(Request $request): JsonResponse {
        $validator = Validator::make($request->all(), [
            'name'  => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'age'   => ['nullable', 'integer', 'max:150'],
        ], [
            'name.required'  => 'The name field is required',
            'name.string'    => 'The name must be a string',
            'name.max'       => 'The name may not be greater than 50 characters.',
            'email.required' => 'The email field is required',
            'email.email'    => 'The email must be a valid email address',
            'email.max'      => 'The email may not be greater than 255 characters',
            'phone.string'   => 'The phone must be a string',
            'phone.max'      => 'The phone may not be greater than 30 characters',
            'age.integer'    => 'The age must be an integer',
            'age.max'        => 'The age may not be greater than 150',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()], 422);
        }

        $user = auth()->user();
        $user->name = $request->name;
        $user->email = $request->email;
        $user->phone = $request->phone;
        $user->age = $request->age;
        $user->gender = $request->gender;
        $user->save();

        return response()->json(['status' => 'success', 'message' => 'Updated Successfully'], 200);
    }
    public function update_profile_picture(Request $request): JsonResponse {
        $validator = Validator::make($request->all(), [
            'image' => ['required', 'image', 'max:2000'],
        ], [
            'image.required' => 'The image is required.',
            'image.image'    => 'The image must be an image',
            'image.max'      => 'The image may not be greater than 2000 kilobytes',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()], 422);
        }

        // handle image files
        if ($request->hasFile('image')) {
            $user = auth()->user();
            $imagePath = file_upload(file: $request->image, optimize: true);
            $user->image = $imagePath;
            $user->save();
        }

        return response()->json(['status' => 'success', 'message' => 'Updated Successfully'], 200);
    }
    public function update_bio(Request $request): JsonResponse {
        $validator = Validator::make($request->all(), [
            'job_title' => ['required', 'string', 'max:255'],
            'bio'       => ['required', 'string', 'max:2000'],
            'short_bio' => ['required', 'string', 'max:300'],
        ], [
            'job_title.required' => 'The designation field is required',
            'job_title.string'   => 'The designation must be a string',
            'job_title.max'      => 'The designation may not be greater than 255 characters.',
            'bio.required'       => 'The bio field is required',
            'bio.string'         => 'The bio must be a string',
            'bio.max'            => 'The bio may not be greater than 2000 characters.',
            'short_bio.required' => 'The short bio field is required',
            'short_bio.string'   => 'The short bio must be a string',
            'short_bio.max'      => 'The short bio may not be greater than 300 characters.',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()], 422);
        }

        $user = auth()->user();
        $user->job_title = $request->job_title;
        $user->bio = $request->bio;
        $user->short_bio = $request->short_bio;
        $user->save();

        return response()->json(['status' => 'success', 'message' => 'Updated Successfully'], 200);
    }
    public function update_password(Request $request): JsonResponse {
        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'string', 'max:255', 'current_password'],
            'password'         => ['required', 'string', 'max:255', 'confirmed'],
        ], [
            'current_password.required'         => 'The current password field is required',
            'current_password.string'           => 'The current password must be a string',
            'current_password.max'              => 'The current password may not be greater than 255 characters.',
            'current_password.current_password' => 'The current password is incorrect.',
            'password.required'                 => 'The password field is required',
            'password.string'                   => 'The password must be a string',
            'password.max'                      => 'The password may not be greater than 255 characters.',
            'password.confirmed'                => 'The password confirmation does not match.',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()], 422);
        }

        $user = auth()->user();
        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json(['status' => 'success', 'message' => 'Updated Successfully'], 200);
    }
    public function update_address(Request $request): JsonResponse {
        $validator = Validator::make($request->all(), [
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'state'      => ['nullable', 'max:255'],
            'city'       => ['nullable', 'max:255'],
            'address'    => ['nullable', 'string', 'max:255'],
        ], [
            'country_id.required' => 'You must select a country.',
            'country_id.integer'  => 'Country ID must be an integer.',
            'country_id.exists'   => 'The selected country is invalid.',
            'state.integer'       => 'State ID must be an integer.',
            'state.exists'        => 'The selected state is invalid.',
            'city.integer'        => 'City ID must be an integer.',
            'city.exists'         => 'The selected city is invalid.',
            'address.string'      => 'The address must be a string.',
            'address.max'         => 'The address may not be greater than 255 characters.',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()], 422);
        }

        $user = auth()->user();
        $user->country_id = $request->country_id;
        $user->state = $request->state;
        $user->city = $request->city;
        $user->address = $request->address;
        $user->save();

        return response()->json(['status' => 'success', 'message' => 'Updated Successfully'], 200);
    }
    public function update_socials(Request $request): JsonResponse {
        $user = auth()->user();
        $user->facebook = $request->facebook;
        $user->twitter = $request->twitter;
        $user->website = $request->website;
        $user->linkedin = $request->linkedin;
        $user->github = $request->github;
        $user->save();

        return response()->json(['status' => 'success', 'message' => 'Updated Successfully'], 200);
    }
    /**
     * Check if a user is enrolled in a course with access.
     *
     * @param $user The user to check.
     * @param string $course_slug The course slug.
     * @return bool True if enrolled with access, false otherwise.
     */
    private function checkEnrollments($user, $course_slug) {
        return $user->enrollments()->where('has_access', 1)->whereHas('course', fn($q) => $q->where('slug', $course_slug))->exists();
    }
    /**
     * Update course progress.
     *
     * @param $user_id user_id.
     * @param int $courseId The course ID.
     * @param int $chapterId The chapter ID.
     * @param int $lessonId The lesson or quiz ID.
     * @param string $type The type of content ('lesson' or 'quiz').
     */
    private function updateCourseProgress($user_id, int $course_id, int $chapter_id, int $lesson_id, string $type): void {
        // Reset current progress for the course
        CourseProgress::where('course_id', $course_id)->update(['current' => 0]);

        // Update or create progress for the current lesson or quiz
        CourseProgress::updateOrCreate(
            [
                'user_id'    => $user_id,
                'course_id'  => $course_id,
                'chapter_id' => $chapter_id,
                'lesson_id'  => $lesson_id,
                'type'       => $type,
            ],
            ['current' => 1]
        );
    }

    //pdf download routes
    public function downloadCertificate(Request $request, string $slug) {
        $user = $this->resolveDownloadUser($request);
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'UnAuthenticated'], 401);
        }
        $course = Course::withTrashed()->whereSlug($slug)->first();
        // 2026-06-12 — per-coach branded certificate (owning coach, global fallback).
        $certificate = CertificateBuilder::forCoach($course?->instructor_id);
        $certificateItems = CertificateBuilderItem::forCoach($course?->instructor_id);

        $courseLectureCount = CourseChapterItem::whereHas('chapter', function ($q) use ($course) {
            $q->where('course_id', $course->id);
        })->count();

        $courseLectureCompletedByUser = CourseProgress::where('user_id', $user->id)
            ->where('course_id', $course->id)->where('watched', 1)->latest();

        $completed_date = formatDate($courseLectureCompletedByUser->first()?->created_at);

        $courseLectureCompletedByUser = CourseProgress::where('user_id', $user->id)
            ->where('course_id', $course->id)->where('watched', 1)->count();

        $courseCompletedPercent = $courseLectureCount > 0 ? ($courseLectureCompletedByUser / $courseLectureCount) * 100 : 0;

        if ($courseCompletedPercent != 100) {
            return abort(404);
        }

        $html = view('frontend.student-dashboard.certificate.index', compact('certificateItems', 'certificate'))->render();

        // Escape every interpolated value — they end up inside the PDF HTML
        // and Dompdf will dereference any <img src> with enable_remote=true,
        // turning a `<img src=http://attacker>` injected into the user's
        // name into an SSRF from this server. Same fix already applied on
        // the web-side StudentDashboardController::downloadCertificate.
        $esc = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $html = str_replace('[student_name]',    $esc($user->name), $html);
        $html = str_replace('[platform_name]',   $esc(cache()->get('setting')?->app_name ?? ''), $html);
        $html = str_replace('[course]',          $esc($course->title), $html);
        $html = str_replace('[date]',            $esc(formatDate($completed_date)), $html);
        $html = str_replace('[instructor_name]', $esc($course->instructor?->name ?? 'Unknown'), $html);

        // Initialize Dompdf
        $dompdf = new Dompdf(array('enable_remote' => true));

        // Load HTML content
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');

        $dompdf->render();
        return $dompdf->stream("certificate.pdf");
    }
    /**
     * F23 (audit 2026-06-26) — resolve the download user from EITHER the Sanctum
     * bearer token (legacy mobile path) OR a valid SIGNED URL's `uid` param (the
     * secure replacement: no token in the URL, so nothing leaks into logs /
     * history). The signed route is `signed`-gated, so a present+valid signature
     * proves WE minted the link for this uid.
     */
    private function resolveDownloadUser(Request $request): ?User
    {
        $u = auth('sanctum')->user();
        if ($u) {
            return $u;
        }
        if ($request->hasValidSignature() && $request->filled('uid')) {
            return User::find((int) $request->query('uid'));
        }
        return null;
    }

    /** Return a short-lived SIGNED invoice URL (no bearer token in it). */
    public function invoiceDownloadLink(Request $request, string $invoice_id) {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'UnAuthenticated'], 401);
        }
        if (! Order::where('invoice_id', $invoice_id)->where('buyer_id', $user->id)->exists()) {
            return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
        }
        $url = URL::temporarySignedRoute('api.invoice.signed', now()->addMinutes(10), [
            'invoice_id' => $invoice_id, 'uid' => $user->id,
        ]);
        return response()->json(['status' => 'success', 'url' => $url]);
    }

    /** Return a short-lived SIGNED certificate URL (no bearer token in it). */
    public function certificateDownloadLink(Request $request, string $course_slug) {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'UnAuthenticated'], 401);
        }
        $url = URL::temporarySignedRoute('api.certificate.signed', now()->addMinutes(10), [
            'course_slug' => $course_slug, 'uid' => $user->id,
        ]);
        return response()->json(['status' => 'success', 'url' => $url]);
    }

    public function downloadInvoice(Request $request, string $invoice_id) {
        $user = $this->resolveDownloadUser($request);
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'UnAuthenticated'], 401);
        }
        $order = Order::where('invoice_id', $invoice_id)->where('buyer_id', $user->id)->first();
        if ($order) {
            $html = view('frontend.student-dashboard.order.invoice', compact('order'))->render();

            // Initialize Dompdf
            $dompdf = new Dompdf(array('enable_remote' => true));

            // Load HTML content
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');

            $dompdf->render();
            return $dompdf->stream("invoice-{$invoice_id}.pdf");

        } else {
            return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
        }
    }
}
