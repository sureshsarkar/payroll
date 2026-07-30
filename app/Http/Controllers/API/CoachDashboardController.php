<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\CourseListResource;
use App\Http\Resources\API\QnaReplyResource;
use App\Http\Resources\API\QnaResource;
use App\Models\Announcement;
use App\Models\Course;
use App\Models\CourseChapter;
use App\Models\CourseChapterLesson;
use App\Models\CourseLiveClass;
use App\Models\CourseReview;
use App\Models\LessonQuestion;
use App\Models\LessonReply;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Modules\Order\app\Models\Enrollment;
use Modules\Order\app\Models\Order;
use Modules\Order\app\Models\OrderItem;
use Modules\PaymentWithdraw\app\Models\WithdrawRequest;
use Modules\Subscription\app\Models\SubscriptionHistory;

// use App\Models\CourseChapterLesson;

class CoachDashboardController extends Controller
{
    public function instructor_dashboard(): JsonResponse
    {
        $user = auth()->user();
        $userId = $user->id;

        // Base course query
        $coursesQuery = Course::where(function ($query) use ($userId) {
            $query->where('added_by', $userId)
                ->orWhere('instructor_id', $userId);
        });

        // Clone query before executing
        $totalCourses = (clone $coursesQuery)->count();

        $totalPendingCourses = (clone $coursesQuery)
            ->where([
                'status' => 'pending',
                'is_approved' => 'pending',
            ])
            ->count();

        $courseIds = (clone $coursesQuery)->pluck('id');

        $totalOrders = OrderItem::whereIn('course_id', $courseIds)->count();

        $totalPendingOrders = OrderItem::whereIn('course_id', $courseIds)
            ->whereHas('order', function ($q) {
                $q->where('status', 'pending');
            })
            ->count();

        $totalWithdraw = WithdrawRequest::where([
            'user_id' => $userId,
            'status' => 'approved',
        ])->sum('withdraw_amount');

        return response()->json([
            'status' => true,
            'message' => 'Instructor dashboard data fetched successfully',
            'data' => [
                'total_courses' => $totalCourses,
                'total_pending_courses' => $totalPendingCourses,
                'total_orders' => $totalOrders,
                'total_pending_orders' => $totalPendingOrders,
                'total_withdraw_amount' => currency($totalWithdraw),
                'current_balance' => currency($user->wallet_balance ?? 0),
            ],
        ]);
    }

    public function instructor_courses(Request $request): JsonResponse
    {
        $instructor_id = auth()->user()->id;

        // $courses = Course::where('instructor_id', $instructor_id)->latest()->paginate(10);

        $user = auth()->user();

        $courses = Course::where(function ($query) use ($user) {
            $query->where('added_by', $user->id)
                ->orWhere('instructor_id', $user->id);
        })
            ->paginate(10);

        if ($courses->isNotEmpty()) {
            $data = CourseListResource::collection($courses);

            return response()->json(['status' => 'success', 'data' => $data], 200);
        }

        return response()->json(['status' => 'error', 'message' => 'Not Found!', 'id' => $instructor_id], 404);
    }

    public function instructor_subscription_history(Request $request): JsonResponse
    {
        $instructor_id = auth()->user()->id;

        $subscriptionHistory = SubscriptionHistory::with('subscription')
            ->where('user_id', $instructor_id)
            ->latest()
            ->paginate(10);
        // dd($subscriptionHistory);
        if ($subscriptionHistory->isNotEmpty()) {
            return response()->json(['status' => 'success', 'data' => $subscriptionHistory], 200);
        }

        return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
    }

    /**
     * Get all questions for instructor's courses
     */
    public function instructor_questions(Request $request): JsonResponse
    {
        $instructor_id = auth()->user()->id;
        $limit = $request->filled('limit') && is_numeric($request->limit) ? (int) $request->limit : 10;

        $query = LessonQuestion::query()
            ->whereHas('course', function ($q) use ($instructor_id) {
                $q->where('instructor_id', $instructor_id);
            })
            ->with(['user:id,name,image', 'course:id,title,slug', 'lesson:id,title', 'replies'])
            ->withCount('replies');

        // Filter by course_id
        if ($request->filled('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        // Filter by lesson_id
        if ($request->filled('lesson_id')) {
            $query->where('lesson_id', $request->lesson_id);
        }

        // Filter by seen status
        if ($request->filled('seen')) {
            $query->where('seen', $request->seen == 'true' || $request->seen == '1');
        }

        // Search by question title or description
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('question_title', 'like', "%{$request->search}%")
                    ->orWhere('question_description', 'like', "%{$request->search}%");
            });
        }

        $questions = $query->latest()->paginate($limit);

        if ($questions->isNotEmpty()) {
            $data = QnaResource::collection($questions);

            return response()->json([
                'status' => 'success',
                'data' => $data,
                'pagination' => [
                    'current_page' => $questions->currentPage(),
                    'per_page' => $questions->perPage(),
                    'total' => $questions->total(),
                    'last_page' => $questions->lastPage(),
                    'links' => [
                        'first' => $questions->url(1),
                        'prev' => $questions->previousPageUrl(),
                        'next' => $questions->nextPageUrl(),
                        'last' => $questions->url($questions->lastPage()),
                    ],
                ],
            ], 200);
        }

        return response()->json(['status' => 'error', 'message' => 'No questions found!'], 404);
    }

    /**
     * Get questions for a specific course
     */
    public function instructor_course_questions(Request $request, int $course_id): JsonResponse
    {
        $instructor_id = auth()->user()->id;
        $limit = $request->filled('limit') && is_numeric($request->limit) ? (int) $request->limit : 10;

        // Verify the course belongs to the instructor
        $course = Course::where('id', $course_id)
            ->where('instructor_id', $instructor_id)
            ->first();
        // dd($course);
        if (! $course) {
            return response()->json(['status' => 'error', 'message' => 'Course not found or you are not the instructor!'], 404);
        }

        $query = LessonQuestion::query()
            ->where('course_id', $course_id)
            ->with(['user:id,name,image', 'lesson:id,title', 'replies'])
            ->withCount('replies');
        // dd($query);
        // Filter by seen status
        if ($request->filled('seen')) {
            $query->where('seen', $request->seen == 'true' || $request->seen == '1');
        }

        // Search by question title or description
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('question_title', 'like', "%{$request->search}%")
                    ->orWhere('question_description', 'like', "%{$request->search}%");
            });
        }

        $questions = $query->latest()->paginate($limit);
        // dd($questions);
        if ($questions->isNotEmpty()) {
            $data = QnaResource::collection($questions);

            return response()->json([
                'status' => 'success',
                'data' => $data,
                'pagination' => [
                    'current_page' => $questions->currentPage(),
                    'per_page' => $questions->perPage(),
                    'total' => $questions->total(),
                    'last_page' => $questions->lastPage(),
                    'links' => [
                        'first' => $questions->url(1),
                        'prev' => $questions->previousPageUrl(),
                        'next' => $questions->nextPageUrl(),
                        'last' => $questions->url($questions->lastPage()),
                    ],
                ],
            ], 200);
        }

        // dd(123);
        return response()->json(['status' => 'error', 'message' => 'No questions found for this course!'], 404);
    }

    /**
     * Get questions for a specific lesson
     */
    public function instructor_lesson_questions(Request $request, int $lesson_id): JsonResponse
    {
        $instructor_id = auth()->user()->id;
        $limit = $request->filled('limit') && is_numeric($request->limit) ? (int) $request->limit : 10;

        // Verify the lesson belongs to a course of the instructor
        $lesson = CourseChapterLesson::where('id', $lesson_id)
            ->whereHas('course', function ($q) use ($instructor_id) {
                $q->where('instructor_id', $instructor_id);
            })
            ->first();

        if (! $lesson) {
            return response()->json(['status' => 'error', 'message' => 'Lesson not found or you are not the instructor!'], 404);
        }

        $query = LessonQuestion::query()
            ->where('lesson_id', $lesson_id)
            ->with(['user:id,name,image', 'course:id,title,slug', 'replies'])
            ->withCount('replies');

        // Search by question title or description
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('question_title', 'like', "%{$request->search}%")
                    ->orWhere('question_description', 'like', "%{$request->search}%");
            });
        }

        $questions = $query->latest()->paginate($limit);

        if ($questions->isNotEmpty()) {
            $data = QnaResource::collection($questions);

            return response()->json([
                'status' => 'success',
                'data' => $data,
                'pagination' => [
                    'current_page' => $questions->currentPage(),
                    'per_page' => $questions->perPage(),
                    'total' => $questions->total(),
                    'last_page' => $questions->lastPage(),
                    'links' => [
                        'first' => $questions->url(1),
                        'prev' => $questions->previousPageUrl(),
                        'next' => $questions->nextPageUrl(),
                        'last' => $questions->url($questions->lastPage()),
                    ],
                ],
            ], 200);
        }

        return response()->json(['status' => 'error', 'message' => 'No questions found for this lesson!'], 404);
    }

    /**
     * Reply to a question (as instructor)
     */
    public function reply_question(Request $request, int $question_id): JsonResponse
    {
        $instructor_id = auth()->user()->id;

        // Verify the question belongs to a course of the instructor
        $question = LessonQuestion::where('id', $question_id)
            ->whereHas('course', function ($q) use ($instructor_id) {
                $q->where('instructor_id', $instructor_id);
            })
            ->first();

        if (! $question) {
            return response()->json(['status' => 'error', 'message' => 'Question not found or you are not the instructor!'], 404);
        }

        $validator = Validator::make($request->all(), [
            // FT-VAL-13 (2026-05-28) — cap reply to 10KB.
            'reply' => 'required|string|max:10000',
        ], [
            'reply.required' => 'Reply is required',
            'reply.string' => 'Reply must be a string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()], 422);
        }

        $reply = LessonReply::create([
            'user_id' => $instructor_id,
            'question_id' => $question->id,
            'reply' => $request->reply,
        ]);

        // Mark the question as seen when instructor replies
        if (! $question->seen) {
            $question->seen = true;
            $question->save();
        }

        $data = new QnaReplyResource($reply);

        return response()->json(['status' => 'success', 'data' => $data, 'message' => 'Reply created successfully'], 201);
    }

    /**
     * Mark question as seen/unseen
     */
    public function mark_question_seen(Request $request, int $question_id): JsonResponse
    {
        $instructor_id = auth()->user()->id;

        // Verify the question belongs to a course of the instructor
        $question = LessonQuestion::where('id', $question_id)
            ->whereHas('course', function ($q) use ($instructor_id) {
                $q->where('instructor_id', $instructor_id);
            })
            ->first();

        if (! $question) {
            return response()->json(['status' => 'error', 'message' => 'Question not found or you are not the instructor!'], 404);
        }

        $validator = Validator::make($request->all(), [
            'seen' => 'required|boolean',
        ], [
            'seen.required' => 'Seen status is required',
            'seen.boolean' => 'Seen must be true or false',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()], 422);
        }

        $question->seen = $request->seen;
        $question->save();

        return response()->json(['status' => 'success', 'message' => 'Question seen status updated successfully', 'data' => [
            'id' => $question->id,
            'seen' => $question->seen,
        ]], 200);
    }

    /**
     * Delete a question (as instructor)
     */
    public function delete_question(int $question_id): JsonResponse
    {
        $instructor_id = auth()->user()->id;

        // Verify the question belongs to a course of the instructor
        $question = LessonQuestion::where('id', $question_id)
            ->whereHas('course', function ($q) use ($instructor_id) {
                $q->where('instructor_id', $instructor_id);
            })
            ->first();

        if (! $question) {
            return response()->json(['status' => 'error', 'message' => 'Question not found or you are not the instructor!'], 404);
        }

        // Delete all replies first
        $question->replies()->delete();

        // Delete the question
        $question->delete();

        return response()->json(['status' => 'success', 'message' => 'Question deleted successfully'], 200);
    }

    /**
     * Get all students for the instructor
     */
    public function students(Request $request): JsonResponse
    {
        $instructor_id = auth()->user()->id;
        $limit = $request->filled('limit') && is_numeric($request->limit) ? (int) $request->limit : 15;
        $search = $request->filled('search') ? $request->search : null;

        $query = User::where('coach_id', $instructor_id)
            ->where('role', 'student');

        // Search by name or email
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $students = $query->orderBy('id', 'desc')->paginate($limit);

        // An empty result is VALID (a coach with no students yet) — return an
        // empty data array inside the pagination envelope, never a 404 (2026-06-30).
        return response()->json([
            'status' => 'success',
            'data' => $students->items(),
            'pagination' => [
                'current_page' => $students->currentPage(),
                'per_page' => $students->perPage(),
                'total' => $students->total(),
                'last_page' => $students->lastPage(),
                'links' => [
                    'first' => $students->url(1),
                    'prev' => $students->previousPageUrl(),
                    'next' => $students->nextPageUrl(),
                    'last' => $students->url($students->lastPage()),
                ],
            ],
        ], 200);
    }

    /**
     * Create a new student
     */
    public function createStudent(Request $request): JsonResponse
    {
        $instructor_id = auth()->user()->id;

        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            // FT-AUTH-7 fix (2026-05-27) — Coach API path was `min:4`.
            // Parity with self-service signup (min:8); see also the
            // student API counterpart in StudentApiDashboardController.
            'password' => 'required|string|min:8',
            'status' => 'required|in:active,inactive',
        ];

        $messages = [
            'name.required' => 'Name is required',
            'email.required' => 'Email is required',
            'email.email' => 'Please enter a valid email',
            'email.unique' => 'Email already exists',
            'password.required' => 'Password is required',
            'password.min' => 'Password must be at least 8 characters',
            'status.required' => 'Status is required',
            'status.in' => 'Status must be either active or inactive',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        // Create user
        $user = User::create([
            'role' => 'student',
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'status' => $request->status,
            'email_verified_at' => now(),
            'added_by' => $instructor_id,
        ]);

        // TENANT (2026-06-16 audit M3) — mirror the web storeStudents flow: add
        // the new student to this coach's roster PIVOT. coach_student_links is
        // the multi-coach source of truth used by My Students AND by tenant
        // confinement (TenantAccess); `added_by` alone is not enough, so an
        // API-created student was previously invisible to the web roster and
        // not confined to the coach. Idempotent.
        try {
            \App\Models\CoachStudentLink::link((int) $instructor_id, (int) $user->id, 'added');
        } catch (\Throwable $e) {
            \Log::warning('api-create-student-link-failed', [
                'coach_id' => $instructor_id, 'student_id' => $user->id, 'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Student created successfully',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
            ],
        ], 201);
    }

    /**
     * Update an existing student
     */
    public function updateStudent(Request $request, int $id): JsonResponse
    {
        $instructor_id = auth()->user()->id;

        $student = User::where('id', $id)
            ->where('added_by', $instructor_id)
            ->where('role', 'student')
            ->first();

        if (! $student) {
            return response()->json(['status' => 'error', 'message' => 'Student not found!'], 404);
        }

        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$id,
            'status' => 'required|in:active,inactive',
        ];

        // FT-AUTH-7 (extension, sibling) — see the
        // StudentApiDashboardController equivalent for rationale.
        if ($request->filled('password')) {
            $rules['password'] = 'string|min:8';
        }

        $messages = [
            'name.required' => 'Name is required',
            'email.required' => 'Email is required',
            'email.email' => 'Please enter a valid email',
            'email.unique' => 'Email already exists',
            'status.required' => 'Status is required',
            'status.in' => 'Status must be either active or inactive',
            'password.min' => 'Password must be at least 8 characters',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        // Update student
        $student->name = $request->name;
        $student->email = $request->email;
        $student->status = $request->status;

        if ($request->filled('password')) {
            $student->password = Hash::make($request->password);
        }

        $student->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Student updated successfully',
            'data' => [
                'id' => $student->id,
                'name' => $student->name,
                'email' => $student->email,
                'status' => $student->status,
            ],
        ], 200);
    }

    /**
     * Delete a student
     */
    public function deleteStudent(int $id): JsonResponse
    {
        $instructor_id = auth()->user()->id;

        $student = User::where('id', $id)
            ->where('added_by', $instructor_id)
            ->where('role', 'student')
            ->first();

        if (! $student) {
            return response()->json(['status' => 'error', 'message' => 'Student not found!'], 404);
        }

        // Delete the student
        $student->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Student deleted successfully',
        ], 200);
    }

    /**
     * Get all announcements for the instructor
     */
    public function announcements_list(Request $request): JsonResponse
    {
        $instructor_id = auth()->user()->id;
        $limit = $request->filled('limit') && is_numeric($request->limit) ? (int) $request->limit : 10;

        $query = Announcement::with(['course:id,title,slug'])
            ->where('instructor_id', $instructor_id);

        // Filter by course_id
        if ($request->filled('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        // Search by title or announcement content
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', "%{$request->search}%")
                    ->orWhere('announcement', 'like', "%{$request->search}%");
            });
        }

        $announcements = $query->orderBy('id', 'desc')->paginate($limit);

        if ($announcements->isNotEmpty()) {
            return response()->json([
                'status' => 'success',
                'data' => $announcements->items(),
                'pagination' => [
                    'current_page' => $announcements->currentPage(),
                    'per_page' => $announcements->perPage(),
                    'total' => $announcements->total(),
                    'last_page' => $announcements->lastPage(),
                    'links' => [
                        'first' => $announcements->url(1),
                        'prev' => $announcements->previousPageUrl(),
                        'next' => $announcements->nextPageUrl(),
                        'last' => $announcements->url($announcements->lastPage()),
                    ],
                ],
            ], 200);
        }

        return response()->json(['status' => 'error', 'message' => 'No announcements found!'], 404);
    }

    /**
     * Create a new announcement
     */
    public function announcements_create(Request $request): JsonResponse
    {
        $instructor_id = auth()->user()->id;

        // FT-VAL-10 (sibling, 2026-05-28) — cap announcement body
        // to 20KB. See the StudentApiDashboardController equivalent
        // for the threat model.
        $rules = [
            'course_id' => 'required|integer',
            'title' => 'required|string|max:255',
            'announcement' => 'required|string|max:20000',
        ];

        $messages = [
            'course_id.required' => 'Course is required',
            'title.required' => 'Title is required',
            'title.max' => 'Title should not be more than 255 characters',
            'announcement.required' => 'Announcement content is required',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        // Verify the course belongs to the instructor
        $course = Course::where('id', $request->course_id)
            ->where('instructor_id', $instructor_id)
            ->first();

        if (! $course) {
            return response()->json(['status' => 'error', 'message' => 'Course not found or you are not the instructor!'], 404);
        }

        // 2026-06-03 (#3/#4) — this API create path previously stored only
        // instructor_id/course_id/title/body. It set NO audience_type and never
        // fanned out, so the announcement reached NOBODY: no mail, no in-app
        // notification, and the student read scopes (which key on audience_type
        // / course-wide) couldn't match it. Mirror the web
        // InstructorAnnouncementController@store: a coach announcement is
        // batch_specific and, with no batch chosen here, course-wide
        // (batch_id NULL + no pivot) so EVERY enrolled student is a recipient
        // and a viewer (scopeVisibleToStudent PATH 3 / scopeVisibleToBatchStudent
        // course-wide). Then fan out for the mail + bell notification.
        $announcement = Announcement::create([
            'instructor_id' => $instructor_id,
            'sender_role'   => 'instructor',
            'audience_type' => 'batch_specific',
            'course_id'     => $request->course_id,
            'batch_id'      => null,
            'title'         => $request->title,
            'announcement'  => $request->announcement,
            'status'        => 'active',
            'sent_at'       => now(),
            'scheduled_at'  => now(),
            'delivered_at'  => now(),
        ]);

        // Fan out immediately (parity with the web store path). Best-effort:
        // a delivery failure must not fail the create.
        try {
            app(\App\Services\AnnouncementNotifier::class)->fanOut($announcement);
        } catch (\Throwable $e) {
            \Log::warning('Announcement fanout failed (API create)', [
                'announcement_id' => $announcement->id,
                'error'           => $e->getMessage(),
            ]);
        }

        $announcement->load(['course:id,title,slug']);

        return response()->json([
            'status' => 'success',
            'message' => 'Announcement created successfully',
            'data' => [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'course_id' => $announcement->course_id,
                'course' => $announcement->course,
                'announcement' => $announcement->announcement,
                'created_at' => formatDate($announcement->created_at),
            ],
        ], 201);
    }

    /**
     * Update an existing announcement
     */
    public function announcements_update(Request $request, int $id): JsonResponse
    {
        $instructor_id = auth()->user()->id;

        $announcement = Announcement::where('id', $id)
            ->where('instructor_id', $instructor_id)
            ->first();

        if (! $announcement) {
            return response()->json(['status' => 'error', 'message' => 'Announcement not found!'], 404);
        }

        // FT-VAL-10 (sibling, 2026-05-28) — cap announcement body
        // to 20KB. See the StudentApiDashboardController equivalent
        // for the threat model.
        $rules = [
            'course_id' => 'required|integer',
            'title' => 'required|string|max:255',
            'announcement' => 'required|string|max:20000',
        ];

        $messages = [
            'course_id.required' => 'Course is required',
            'title.required' => 'Title is required',
            'title.max' => 'Title should not be more than 255 characters',
            'announcement.required' => 'Announcement content is required',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        // Verify the course belongs to the instructor
        $course = Course::where('id', $request->course_id)
            ->where('instructor_id', $instructor_id)
            ->first();

        if (! $course) {
            return response()->json(['status' => 'error', 'message' => 'Course not found or you are not the instructor!'], 404);
        }

        $announcement->update([
            'course_id' => $request->course_id,
            'title' => $request->title,
            'announcement' => $request->announcement,
        ]);

        $announcement->load(['course:id,title,slug']);

        return response()->json([
            'status' => 'success',
            'message' => 'Announcement updated successfully',
            'data' => [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'course_id' => $announcement->course_id,
                'course' => $announcement->course,
                'announcement' => $announcement->announcement,
                'created_at' => formatDate($announcement->created_at),
            ],
        ], 200);
    }

    /**
     * Delete an announcement
     */
    public function announcements_delete(int $id): JsonResponse
    {
        $instructor_id = auth()->user()->id;

        $announcement = Announcement::where('id', $id)
            ->where('instructor_id', $instructor_id)
            ->first();

        if (! $announcement) {
            return response()->json(['status' => 'error', 'message' => 'Announcement not found!'], 404);
        }

        $announcement->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Announcement deleted successfully',
        ], 200);
    }

    /**
     * Get all sales for the instructor
     */
    public function my_sells(Request $request): JsonResponse
    {
        $instructor_id = auth()->user()->id;
        $limit = $request->filled('limit') && is_numeric($request->limit) ? (int) $request->limit : 30;

        $courseIds = Course::where('instructor_id', $instructor_id)->pluck('id')->toArray();

        $query = OrderItem::whereIn('course_id', $courseIds)
            ->with(['order.user', 'course:id,title,slug,thumbnail,price'])
            ->orderBy('id', 'desc');

        // Filter by course_id
        if ($request->filled('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        // Filter by payment status
        if ($request->filled('payment_status')) {
            $query->whereHas('order', function ($q) use ($request) {
                $q->where('payment_status', $request->payment_status);
            });
        }

        // Filter by order status
        if ($request->filled('order_status')) {
            $query->whereHas('order', function ($q) use ($request) {
                $q->where('status', $request->order_status);
            });
        }

        $sales = $query->paginate($limit);
        // dd($sales);
        $data = $sales->getCollection()->map(function ($item) {
            return [
                'id' => $item->id,
                'order_id' => $item->order_id,
                'course_id' => $item->course_id,
                'course' => $item->course ? [
                    'id' => $item->course->id,
                    'title' => $item->course->title,
                    'slug' => $item->course->slug,
                    'thumbnail' => $item->course->thumbnail,
                    'price' => (float) $item->course->price,
                ] : null,
                'price' => (float) $item->price,
                'invoice_id' => $item->order?->invoice_id,
                'payment_status' => $item->order?->payment_status,
                'order_status' => $item->order?->status,
                'payment_method' => $item->order?->payment_method,
                // Order::user() is the buyer (belongsTo buyer_id) — exposed as `buyer`.
                'buyer' => $item->order?->user ? [
                    'id' => $item->order->user->id,
                    'name' => $item->order->user->name,
                    'email' => $item->order->user->email,
                ] : null,
                'created_at' => $item->order?->created_at?->format('Y-m-d H:i:s'),
            ];
        });

        // An empty result is VALID (a coach with no sales yet) — return an empty
        // data array inside the pagination envelope, never a 404 (2026-06-30).
        return response()->json([
            'status' => 'success',
            'data' => $data,
            'pagination' => [
                'current_page' => $sales->currentPage(),
                'per_page' => $sales->perPage(),
                'total' => $sales->total(),
                'last_page' => $sales->lastPage(),
                'links' => [
                    'first' => $sales->url(1),
                    'prev' => $sales->previousPageUrl(),
                    'next' => $sales->nextPageUrl(),
                    'last' => $sales->url($sales->lastPage()),
                ],
            ],
        ], 200);
    }

    /**
     * Get data for creating a new sale
     */
    public function my_sells_create(): JsonResponse
    {
        $instructor_id = auth()->user()->id;

        // Get students added by this instructor
        $students = User::where('role', 'student')
            ->where('added_by', $instructor_id)
            ->where('status', 'active')
            ->select('id', 'name', 'email')
            ->get();

        // Get courses created by this instructor
        $courses = Course::where('status', 'active')
            ->where('is_approved', 'approved')
            ->where('instructor_id', $instructor_id)
            ->select('id', 'title', 'slug', 'price', 'thumbnail')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'students' => $students,
                'courses' => $courses->map(function ($course) {
                    return [
                        'id' => $course->id,
                        'title' => $course->title,
                        'slug' => $course->slug,
                        'price' => (float) $course->price,
                        'thumbnail' => $course->thumbnail,
                    ];
                }),
            ],
        ], 200);
    }

    /**
     * Store a new sale
     */
    public function my_sells_store(Request $request): JsonResponse
    {
        $instructor_id = auth()->user()->id;

        $rules = [
            'user_id' => 'required|integer',
            'course_id' => 'required|integer',
        ];

        $messages = [
            'user_id.required' => 'Student is required',
            'course_id.required' => 'Course is required',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        // Verify the course belongs to the instructor
        $course = Course::where('id', $request->course_id)
            ->where('instructor_id', $instructor_id)
            ->first();

        if (! $course) {
            return response()->json(['status' => 'error', 'message' => 'Course not found or you are not the instructor!'], 404);
        }

        // Verify the student exists and belongs to this instructor
        $student = User::where('id', $request->user_id)
            ->where('added_by', $instructor_id)
            ->where('role', 'student')
            ->first();

        if (! $student) {
            return response()->json(['status' => 'error', 'message' => 'Student not found or you are not the owner of this student!'], 404);
        }

        // 2026-06-05 — persist the SELECTED batch (if any) so this student's
        // live-class visibility is correctly scoped. Optional + validated
        // against the course; invalid/foreign batch → null (course-wide).
        $validBatchId = null;
        if ($request->filled('batch_id')) {
            $validBatchId = \App\Models\CourseBatch::where('id', $request->batch_id)
                ->where('course_id', $request->course_id)
                ->value('id');
        }

        $payable_amount = $course->price;
        $paid_amount = $payable_amount;

        try {
            // Create order
            $order = Order::create([
                'invoice_id' => Str::random(10),
                'buyer_id' => $request->user_id,
                'has_coupon' => 0,
                'coupon_code' => '',
                'coupon_discount_percent' => '',
                'coupon_discount_amount' => 0,
                'payment_method' => 'offline',
                'payment_status' => 'paid',
                'status' => 'completed',
                'payable_amount' => $payable_amount,
                'gateway_charge' => 0,
                'payable_with_charge' => $paid_amount,
                'paid_amount' => $paid_amount,
                'payable_currency' => 'INR',
                'conversion_rate' => Session::get('currency_rate', 1),
                'commission_rate' => Cache::get('setting')->commission_rate,
                'order_type' => 'course',
                'order_details' => null,
            ]);
            // Create order item
            $orderItem = OrderItem::create([
                'order_id' => $order->id,
                'price' => $paid_amount,
                'course_id' => $request->course_id,
                'batch_id' => $validBatchId,
                'commission_rate' => Cache::get('setting')->commission_rate,
            ]);

            // FT-PAY-9 (sibling, 2026-05-28) — same money-leak as the
            // StudentApiDashboardController::my_sells_store fix earlier
            // in this branch. Pre-fix this credited the FULL $paid_amount
            // with no commission subtracted, even though the Order row
            // above sets commission_rate from the global setting.
            // Apply the same formula the web order-update path uses
            // (Modules\Order\OrderController::updateOrder line ~196).
            $commissionRate         = (float) (Cache::get('setting')->commission_rate ?? 0);
            $commissionAmount       = $paid_amount * ($commissionRate / 100);
            $amountAfterCommission  = $paid_amount - $commissionAmount;
            $instructor = Course::find($request->course_id)->instructor;
            $instructor->increment('wallet_balance', $amountAfterCommission);

            // Create enrollment
            // 2026-06-06 — key on (user, course, BATCH) for multi-batch support.
            Enrollment::updateOrCreate(
                [
                    'user_id'   => $request->user_id,
                    'course_id' => $request->course_id,
                    'batch_id'  => $validBatchId,
                ],
                [
                    'order_id'   => $order->id,
                    'has_access' => 1,
                ]
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Sale created successfully',
                'data' => [
                    'id' => $orderItem->id,
                    'order_id' => $order->id,
                    'invoice_id' => $order->invoice_id,
                    'course_id' => $orderItem->course_id,
                    'price' => (float) $orderItem->price,
                    'payment_status' => $order->payment_status,
                    'order_status' => $order->status,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create sale: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a sale
     */
    public function my_sells_update(Request $request, string $id): JsonResponse
    {
        $instructor_id = auth()->user()->id;

        // Get the order item and verify it belongs to instructor's course
        $orderItem = OrderItem::where('id', $id)
            ->whereHas('course', function ($q) use ($instructor_id) {
                $q->where('instructor_id', $instructor_id);
            })
            ->first();

        if (! $orderItem) {
            return response()->json(['status' => 'error', 'message' => 'Sale not found or you are not the instructor!'], 404);
        }

        $rules = [
            'order_status' => 'required|in:pending,completed,processing,declined',
            'payment_status' => 'required|in:pending,paid,cancelled',
        ];

        $messages = [
            'order_status.required' => 'Order status is required',
            'order_status.in' => 'Order status must be pending, completed, processing or declined',
            'payment_status.required' => 'Payment status is required',
            'payment_status.in' => 'Payment status must be pending, paid, or cancelled',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        // FT-PAY-12 (sibling, 2026-05-28) — same record-and-cancel
        // money-extraction bug as the StudentApiDashboardController
        // equivalent. See that commit for full threat model.
        // Mirror the web Order::updateOrder flip handling.
        $order = Order::lockForUpdate()->find($orderItem->order_id);
        $previousPaymentStatus = $order->payment_status;

        \DB::transaction(function () use ($order, $orderItem, $request, $previousPaymentStatus) {
            $order->status = $request->order_status;
            $order->payment_status = $request->payment_status;
            $order->save();

            $flippedToPaid   = $previousPaymentStatus !== 'paid' && $request->payment_status === 'paid';
            $flippedFromPaid = $previousPaymentStatus === 'paid' && $request->payment_status !== 'paid';

            if ($flippedToPaid || $flippedFromPaid) {
                $rate = (float) ($orderItem->commission_rate ?? $order->commission_rate ?? 0);
                $itemPrice = (float) ($orderItem->price ?? 0);
                $amountAfterCommission = $itemPrice - ($itemPrice * $rate / 100);
                $course = Course::withTrashed()->find($orderItem->course_id);
                $instructor = $course?->instructor;
                if ($instructor) {
                    if ($flippedToPaid) {
                        $instructor->increment('wallet_balance', $amountAfterCommission);
                    } else {
                        $instructor->decrement('wallet_balance', $amountAfterCommission);
                    }
                }
            }

            // 2026-06-03 (Referral A+) — keep the referral commission in lockstep
            // with this coach (API) payment-status change.
            if ($flippedToPaid) {
                app(\App\Services\ReferralCommissionService::class)->onOrderPaid($order);
            } elseif ($flippedFromPaid) {
                app(\App\Services\ReferralCommissionService::class)
                    ->onOrderReversed($order, 'Coach (API) set payment to ' . $request->payment_status);
            }
        });

        // Check if order is completed & paid
        $hasAccess = ($order->status === 'completed' && $order->payment_status === 'paid') ? 1 : 0;

        // Create or update enrollment. 2026-06-05 — carry the order item's
        // batch_id so the enrollment stays batch-scoped (matches the web
        // status-update path) instead of silently going course-wide.
        // 2026-06-06 — key on (user, course, BATCH) for multi-batch support.
        Enrollment::updateOrCreate(
            [
                'user_id'   => $order->buyer_id,
                'course_id' => $orderItem->course_id,
                'batch_id'  => $orderItem->batch_id ?? null,
            ],
            [
                'order_id'   => $orderItem->order_id,
                'has_access' => $hasAccess,
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Sale updated successfully',
            'data' => [
                'id' => $orderItem->id,
                'order_id' => $orderItem->order_id,
                'order_status' => $order->status,
                'payment_status' => $order->payment_status,
                'has_access' => $hasAccess,
            ],
        ], 200);
    }

    /**
     * Get all wishlist courses for the instructor
     */
    public function wishlist_list(Request $request): JsonResponse
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 401);
        }

        $limit = $request->filled('limit') && is_numeric($request->limit)
            ? (int) $request->limit
            : 10;

        $query = $user->favoriteCourses();

        // Search filter
        if ($request->filled('search')) {
            $query->where('title', 'like', "%{$request->search}%");
        }

        $wishlistCourses = $query->orderBy('id', 'desc')->paginate($limit);

        if ($wishlistCourses->count() > 0) {

            $data = $wishlistCourses->map(function ($course) {
                return [
                    'id' => $course->id,
                    'title' => $course->title,
                    'slug' => $course->slug,
                    'thumbnail' => $course->thumbnail,
                    'price' => (float) $course->price,
                    'discount' => $course->discount ? (float) $course->discount : null,
                    'created_at' => $course->created_at?->format('Y-m-d H:i:s'),
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => $data,
                'pagination' => [
                    'current_page' => $wishlistCourses->currentPage(),
                    'per_page' => $wishlistCourses->perPage(),
                    'total' => $wishlistCourses->total(),
                    'last_page' => $wishlistCourses->lastPage(),
                ],
            ], 200);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'No wishlist courses found!',
        ], 404);
    }

    /**
     * Remove a course from wishlist
     */
    public function wishlist_delete(Request $request, string $slug): JsonResponse
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 401);
        }

        $course = Course::where('slug', $slug)->first();

        if (! $course) {
            return response()->json([
                'status' => 'error',
                'message' => 'Course not found!',
            ], 404);
        }

        // Check if exists in wishlist
        if (! $user->favoriteCourses()->where('course_id', $course->id)->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Course is not in your wishlist!',
            ], 404);
        }

        // Detach by ID (recommended)
        $user->favoriteCourses()->detach($course->id);

        return response()->json([
            'status' => 'success',
            'message' => 'Course removed from wishlist successfully',
        ], 200);
    }

    /**
     * Create a new course
     */
    public function createCourse(Request $request): JsonResponse
    {
        $instructor_id = auth()->id();

        // FT-VAL-9 fix (2026-05-28) — add length caps + upper bounds.
        // Pre-fix every text field was just `string` (no max), and
        // price/discount had only `min:0`. An attacker authenticated as
        // a coach could:
        //   • set price = 999_999_999 → display overflow + checkout
        //     gateway rejections (each gateway has its own max amount),
        //   • POST a 10MB string into description / seo_description /
        //     thumbnail / demo_video_source — DOS-shaped DB write.
        // Bound to realistic per-column widths.
        $validator = Validator::make($request->all(), [
            'title'              => 'required|string|max:255',
            'seo_description'    => 'nullable|string|max:255',
            'thumbnail'          => 'nullable|string|max:500',
            'demo_video_storage' => 'nullable|in:upload,youtube,vimeo,external_link,aws,wasabi',
            'demo_video_source'  => 'nullable|string|max:500',
            'price'              => 'required|numeric|min:0|max:9999999',
            'discount_price'     => 'nullable|numeric|min:0|max:9999999|lte:price',
            'description'        => 'nullable|string|max:50000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Generate unique slug
        $slug = Str::slug($request->title);
        $slugExists = Course::where('slug', $slug)->exists();
        if ($slugExists) {
            $slug .= '-'.uniqid();
        }

        $course = Course::create([
            'instructor_id' => $instructor_id,
            'title' => $request->title,
            'slug' => $slug,
            'seo_description' => $request->seo_description,
            'thumbnail' => $request->thumbnail,
            'demo_video_storage' => $request->demo_video_storage ?? 'upload',
            'demo_video_source' => $request->demo_video_source,
            'price' => $request->price,
            'discount' => $request->discount_price,
            'description' => $request->description,
            'status' => 'is_draft',
            'is_approved' => 'pending',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Basic info saved successfully',
            'data' => [
                'course_id' => $course->id,
                'title' => $course->title,
                'slug' => $course->slug,
                'price' => (float) $course->price,
                'discount' => $course->discount ? (float) $course->discount : null,
                'status' => $course->status,
                'is_approved' => $course->is_approved,
            ],
        ], 201);
    }

    /**
     * Update an existing course
     *
     * @param  int  $course_id
     */
    public function updateCourse(Request $request, $course_id): JsonResponse
    {
        // Ownership: instructor_id OR added_by (and staff act for their coach).
        $coachId = $this->effectiveCoachId();
        $course = Course::where('id', $course_id)
            ->where(fn ($q) => $q->where('instructor_id', $coachId)->orWhere('added_by', $coachId))
            ->first();
        if (! $course) {
            return response()->json(['status' => 'error', 'message' => 'Course not found or not yours.'], 404);
        }

        // LENIENT validation — an unexpected value in ONE optional field must not
        // 422 the whole save (the old strict demo_video_storage `in:` + status
        // `in:` rules did exactly that → the title/price never persisted).
        $validator = Validator::make($request->all(), [
            'title'              => 'nullable|string|max:255',
            'seo_description'    => 'nullable|string|max:2000',
            'thumbnail'          => 'nullable|string|max:1000',
            'demo_video_storage' => 'nullable|string|max:50',
            'demo_video_source'  => 'nullable|string|max:1000',
            'price'              => 'nullable|numeric|min:0|max:99999999',
            'discount_price'     => 'nullable|numeric|min:0|max:99999999',
            'description'        => 'nullable|string|max:200000',
            'status'             => 'nullable|string|max:30',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        // Title + slug (only when actually changed).
        if ($request->filled('title') && trim($request->title) !== $course->title) {
            $course->title = trim($request->title);
            $slug = Str::slug($request->title);
            if (Course::where('slug', $slug)->where('id', '!=', $course->id)->exists()) {
                $slug .= '-' . substr(md5(uniqid('', true)), 0, 6);
            }
            $course->slug = $slug;
        }

        // Direct assignment for each PROVIDED field — bypasses mass-assignment
        // config entirely and never touches a field the app didn't send.
        if ($request->exists('seo_description'))    $course->seo_description    = $request->input('seo_description');
        if ($request->exists('thumbnail'))          $course->thumbnail          = $request->input('thumbnail');
        if ($request->exists('demo_video_storage')) $course->demo_video_storage = $request->input('demo_video_storage');
        if ($request->exists('demo_video_source'))  $course->demo_video_source  = $request->input('demo_video_source');
        if ($request->filled('price'))              $course->price              = $request->input('price');
        if ($request->exists('discount_price'))     $course->discount           = $request->input('discount_price');
        if ($request->exists('description'))        $course->description        = $request->input('description');

        // Status → DB enum (published→active, etc.), enum-safe.
        if ($request->filled('status')) {
            $map = [
                'published' => 'active', 'Publish' => 'active', 'publish' => 'active', 'active' => 'active',
                'is_draft' => 'is_draft', 'Draft' => 'is_draft', 'draft' => 'is_draft',
                'inactive' => 'inactive', 'UnPublish' => 'inactive', 'unpublish' => 'inactive',
            ];
            if (isset($map[$request->status])) {
                $course->status = $map[$request->status];
                if ($course->status === 'active' && $course->is_approved !== 'approved') {
                    $course->is_approved = 'pending';
                }
            }
        }

        $course->save();

        return response()->json([
            'status'  => 'success',
            'message' => 'Course updated successfully',
            'data'    => [
                'course_id'   => $course->id,
                'title'       => $course->title,
                'slug'        => $course->slug,
                'price'       => (float) $course->price,
                'discount'    => $course->discount ? (float) $course->discount : null,
                'status'      => $this->courseStatusForApp($course->status),
                'is_approved' => $course->is_approved,
            ],
        ]);
    }

    public function addChapter(Request $request, $course_id)
    {
        $instructor_id = auth()->id();

        $course = Course::where('id', $course_id)
            ->where('instructor_id', $instructor_id)
            ->firstOrFail();

        $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $chapter = CourseChapter::create([
            'course_id' => $course->id,
            'instructor_id' => $instructor_id,
            'title' => $request->title,
            'order' => $course->chapters()->count() + 1,
            'status' => 'active',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Chapter created successfully',
            'data' => $chapter,
        ]);
    }

    public function updateChapter(Request $request, $chapter_id)
    {
        $instructor_id = auth()->id();

        // 1️⃣ Validate
        $request->validate([
            'title' => 'required|string|max:255',
            'status' => 'nullable|in:active,inactive',
        ]);

        // 2️⃣ Check chapter belongs to instructor
        $chapter = CourseChapter::where('id', $chapter_id)
            ->where('instructor_id', $instructor_id)
            ->first();

        if (! $chapter) {
            return response()->json([
                'status' => false,
                'message' => 'Chapter not found or unauthorized',
            ], 404);
        }

        // 3️⃣ Update chapter
        $chapter->update([
            'title' => $request->title,
            'status' => $request->status ?? $chapter->status,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Chapter updated successfully',
            'data' => $chapter,
        ]);
    }

    public function addLesson(Request $request, $chapter_id)
    {
        $instructorId = auth()->user()->id;

        // FT-UPLOAD-2 fix (2026-05-28) — pre-fix `file` had no `mimes:`
        // restriction, only `file|max:51200`. The file is stored
        // under `storage/app/public/lessons/` (Storage::disk('public'))
        // and the public storage is symlinked into `public/storage/` —
        // an attacker authenticated as a coach could:
        //   • upload `malicious.php` → if the web server serves PHP
        //     from the public directory (default Apache without
        //     restrictions), the file is executable RCE.
        //   • upload `malicious.svg` → served as SVG and rendered
        //     inline anywhere the lesson is shown; SVG can carry
        //     <script> and event handlers (the same FT-UPLOAD-1
        //     threat fixed for other surfaces in this branch).
        //   • upload `malicious.html` → served as HTML, executes
        //     arbitrary JS in students' browsers.
        // Constrain to the same media types the platform lesson
        // viewer can actually render: video (mp4/webm), audio
        // (mp3/m4a), documents (pdf/docx). Same set as the web
        // counterpart's existing rules.
        // 1️⃣ Validate Input
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:10000',
            'file_type' => 'required|string|in:video,audio,document,text',
            'duration' => 'nullable|integer|min:0|max:36000',
            'is_free' => 'nullable|boolean',
            'file' => 'nullable|file|mimes:mp4,webm,mp3,m4a,pdf,docx,jpg,jpeg,png,webp|max:51200', // 50MB
            'file_path' => 'nullable|string|max:1000',
        ]);

        // 2️⃣ Check Chapter Exists & Belongs to Instructor
        $chapter = CourseChapter::where('id', $chapter_id)
            ->where('instructor_id', $instructorId)
            ->first();

        if (! $chapter) {
            return response()->json([
                'status' => false,
                'message' => 'Chapter not found or unauthorized',
            ], 403);
        }

        // 3️⃣ Handle File Upload
        $filePath = null;
        $storageType = null;

        if ($request->hasFile('file')) {
            $filePath = $request->file('file')->store('lessons', 'public');
            $storageType = 'local';
        } elseif ($request->file_path) {
            $filePath = $request->file_path;
            $storageType = 'external';
        }

        // 4️⃣ Create Lesson
        $lesson = CourseChapterLesson::create([
            'title' => $request->title,
            'description' => $request->description,
            'course_id' => $chapter->course_id,
            'chapter_id' => $chapter->id,
            'file_path' => $filePath,
            'storage' => $storageType,
            'file_type' => $request->file_type,
            'instructor_id' => $instructorId,
            'duration' => $request->duration,
            'is_free' => $request->is_free ?? 0,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Lesson added successfully',
            'data' => $lesson,
        ]);
    }

    public function getCourseContent($course_id)
    {
        $course = Course::where('id', $course_id)
            ->where('instructor_id', auth()->id())
            ->first();

        if (! $course) {
            return response()->json([
                'status' => false,
                'message' => 'Course not found',
            ], 404);
        }

        // Get chapters
        $chapters = CourseChapter::where('course_id', $course->id)
            ->orderBy('order')
            ->get();

        // Attach lessons manually
        $chapters->transform(function ($chapter) {
            $chapter->lessons = CourseChapterLesson::where('chapter_id', $chapter->id)
                ->orderBy('id')
                ->get();

            return $chapter;
        });

        return response()->json([
            'status' => true,
            'data' => [
                'course_id' => $course->id,
                'course_title' => $course->title,
                'chapters' => $chapters,
            ],
        ]);
    }

    public function deleteChapter($course_id, $chapter_id)
    {
        $instructor_id = auth()->id();

        $chapter = CourseChapter::where('id', $chapter_id)
            ->where('course_id', $course_id)
            ->where('instructor_id', $instructor_id)
            ->first();

        if (! $chapter) {
            return response()->json([
                'status' => false,
                'message' => 'Chapter not found or unauthorized.',
            ], 404);
        }

        $chapter->delete();

        return response()->json([
            'status' => true,
            'message' => 'Chapter deleted successfully',
        ]);
    }

    public function analyticsProgress($course_id)
    {
        // SECURITY (audit 2026-05-22) — sibling finishCourse() at L1556
        // already scopes by instructor_id; these two analytics methods
        // were missed and exposed any coach's student-progress data to
        // any other sanctum-authenticated instructor.
        $course = Course::where('id', $course_id)
            ->where('instructor_id', auth()->id())
            ->firstOrFail();

        $data = $course->progresses()
            ->selectRaw('FLOOR(progress_percentage/10)*10 as range_group, COUNT(*) as total')
            ->groupBy('range_group')
            ->get();

        return response()->json($data);
    }

    public function analyticsSales($course_id)
    {
        // SECURITY (audit 2026-05-22) — same ownership scope as
        // analyticsProgress + finishCourse.
        $course = Course::where('id', $course_id)
            ->where('instructor_id', auth()->id())
            ->firstOrFail();

        $sales = $course->orders()
            ->selectRaw('MONTH(created_at) as month, SUM(total_amount) as total')
            ->groupBy('month')
            ->get();

        return response()->json($sales);
    }

    public function finishCourse(Request $request, $course_id)
    {
        // Robust ownership — coach may own via instructor_id OR added_by (and
        // staff act for their coach). Returns 404 JSON (not a firstOrFail 500).
        $coachId = $this->effectiveCoachId();
        $course = Course::where('id', $course_id)
            ->where(fn ($q) => $q->where('instructor_id', $coachId)->orWhere('added_by', $coachId))
            ->first();
        if (! $course) {
            return response()->json(['status' => 'error', 'message' => 'Course not found or not yours.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'message_for_reviewer' => 'nullable|string|max:2000',
            // Accept the app's semantic keys AND the raw enum values.
            'status' => 'required|string|in:Publish,UnPublish,Draft,active,inactive,is_draft',
        ], [
            'status.required' => 'Please select course status.',
            'status.in'       => 'Invalid status selected. Allowed: Publish, UnPublish, Draft.',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        // Map Publish→active, UnPublish→inactive, Draft→is_draft (enum-safe).
        $statusMap = [
            'Publish' => 'active',   'publish' => 'active',   'active'   => 'active',
            'UnPublish' => 'inactive', 'unpublish' => 'inactive', 'inactive' => 'inactive',
            'Draft' => 'is_draft',   'draft' => 'is_draft',   'is_draft' => 'is_draft',
        ];
        $newStatus = $statusMap[$request->status] ?? null;
        if ($newStatus === null) {
            return response()->json(['status' => 'error', 'message' => 'Invalid status.'], 422);
        }

        // Direct assignment (mirrors the web flow; avoids mass-assignment config).
        // Guard the optional column so a schema drift can't 500 the submit.
        if (\Illuminate\Support\Facades\Schema::hasColumn('courses', 'message_for_reviewer')) {
            $course->message_for_reviewer = $request->input('message_for_reviewer');
        }
        $course->status = $newStatus;
        // Publishing submits for admin review; PRESERVE an existing approval on
        // re-submit (matches the dialog: "existing approval is preserved").
        if ($newStatus === 'active' && $course->is_approved !== 'approved') {
            $course->is_approved = 'pending';
        }
        $course->save();

        return response()->json([
            'status'  => 'success',
            'message' => 'Course submitted successfully.',
            'data'    => ['id' => (int) $course->id, 'status' => $this->courseStatusForApp($course->status), 'is_approved' => (string) $course->is_approved],
        ], 200);
    }

    public function updateMoreInfo(Request $request, $course_id)
    {
        $course = Course::where('id', $course_id)
            ->where('instructor_id', auth()->id())
            ->firstOrFail();

        $request->validate([
            'capacity' => 'nullable|integer|min:1',
            'duration' => 'required|integer|min:1',
            'qna' => 'nullable|boolean',
            'certificate' => 'nullable|boolean',
            'partner_instructor' => 'nullable|boolean',
            'category_id' => 'required|exists:course_categories,id',
            'levels' => 'nullable|array',
            'languages' => 'nullable|array',
        ]);

        $course->update([
            'capacity' => $request->capacity,
            'duration' => $request->duration,
            'qna' => $request->qna ?? 0,
            'certificate' => $request->certificate ?? 0,
            'partner_instructor' => $request->partner_instructor ?? 0,
            'category_id' => $request->category_id,
        ]);

        // Sync Levels
        if ($request->levels) {
            $course->levels()->delete();
            foreach ($request->levels as $level_id) {
                $course->levels()->create(['level_id' => $level_id]);
            }
        }

        // Sync Languages
        if ($request->languages) {
            $course->languages()->delete();
            foreach ($request->languages as $lang_id) {
                $course->languages()->create(['language_id' => $lang_id]);
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'More info updated successfully',
        ]);
    }

    /**
     * Delete Course
     */
    public function deleteCourse(string $slug): JsonResponse
    {
        $user = auth()->user();

        $course = Course::where('slug', $slug)
            ->where('instructor_id', $user->id)
            ->first();

        if (! $course) {
            return response()->json([
                'status' => 'error',
                'message' => 'Course not found or unauthorized',
            ], 404);
        }

        $course->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Course deleted successfully',
        ], 200);
    }

    /* ───────────────────────────────────────────────────────────────────────
     * Instructor Dashboard API endpoints (2026-06-30) — restore/implement the
     * four endpoints the dashboard SPA calls that were missing/commented out, so
     * they stop 404-ing. All are scoped to the authenticated instructor
     * (auth()->user()->id) and the surrounding group enforces auth:sanctum +
     * api.instructor, so a coach can only ever see their OWN data.
     * ─────────────────────────────────────────────────────────────────────── */

    /**
     * GET /api/instructor/sales/trend?months=12
     * Monthly paid-sales revenue (+ order count) for THIS instructor's courses,
     * as a continuous series with missing months zero-filled (for the chart).
     */
    public function sales_trend(Request $request): JsonResponse
    {
        $instructorId = auth()->user()->id;
        $months = $request->filled('months') && is_numeric($request->months)
            ? max(1, min(36, (int) $request->months)) : 12;

        $courseIds = Course::where('instructor_id', $instructorId)->pluck('id')->all();
        $start = now()->startOfMonth()->subMonths($months - 1);

        $rows = collect();
        if (! empty($courseIds)) {
            $rows = OrderItem::query()
                ->whereIn('order_items.course_id', $courseIds)
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.payment_status', 'paid')
                ->where('orders.created_at', '>=', $start)
                ->selectRaw("DATE_FORMAT(orders.created_at, '%Y-%m') as ym, SUM(order_items.price) as total, COUNT(*) as orders_count")
                ->groupBy('ym')
                ->get()
                ->keyBy('ym');
        }

        $series = [];
        for ($i = 0; $i < $months; $i++) {
            $m  = $start->copy()->addMonths($i);
            $ym = $m->format('Y-m');
            $series[] = [
                'month'  => $ym,
                'label'  => $m->format('M Y'),
                'total'  => (float) ($rows[$ym]->total ?? 0),
                'orders' => (int) ($rows[$ym]->orders_count ?? 0),
            ];
        }

        return response()->json([
            'status'  => 'success',
            'months'  => $months,
            'data'    => $series,
            'summary' => [
                'total_revenue' => (float) collect($series)->sum('total'),
                'total_orders'  => (int) collect($series)->sum('orders'),
            ],
        ], 200);
    }

    /**
     * GET /api/instructor/sales/{id}
     * A single sale (order item) belonging to THIS instructor's course. Returns
     * 404 only when the sale does not exist OR is not the instructor's (correct
     * for a single resource — not a misleading empty-list 404).
     */
    public function my_sells_show($id): JsonResponse
    {
        $instructorId = auth()->user()->id;
        $courseIds = Course::where('instructor_id', $instructorId)->pluck('id')->all();

        $item = OrderItem::whereIn('course_id', $courseIds)
            ->with(['order.user', 'course:id,title,slug,thumbnail,price'])
            ->find($id);

        if (! $item) {
            return response()->json(['status' => 'error', 'message' => 'Sale not found'], 404);
        }

        // Shared sub-objects (same shapes the app already parses fine inside the
        // sales list / sale), reused both nested in `sale` and as data-root keys.
        $coursePayload = $item->course ? [
            'id'        => $item->course->id,
            'title'     => $item->course->title,
            'slug'      => $item->course->slug,
            'thumbnail' => $item->course->thumbnail,
            'price'     => (float) $item->course->price,
        ] : null;

        // Order::user() is the buyer (belongsTo buyer_id) — exposed as `buyer`.
        $buyerPayload = $item->order?->user ? [
            'id'    => $item->order->user->id,
            'name'  => $item->order->user->name,
            'email' => $item->order->user->email,
            'phone' => $item->order->user->phone ?? null,
        ] : null;

        $salePayload = [
            'id'             => $item->id,
            'order_id'       => $item->order_id,
            'course_id'      => $item->course_id,
            'course'         => $coursePayload,
            'price'          => (float) $item->price,
            'invoice_id'     => $item->order?->invoice_id,
            'payment_status' => $item->order?->payment_status,
            'order_status'   => $item->order?->status,
            'payment_method' => $item->order?->payment_method,
            'payable_amount' => (float) ($item->order?->payable_amount ?? 0),
            'paid_amount'    => (float) ($item->order?->paid_amount ?? 0),
            'currency'       => $item->order?->payable_currency,
            'buyer'          => $buyerPayload,
            'created_at'     => $item->order?->created_at?->format('Y-m-d H:i:s'),
        ];

        // Instructor NET earning = item price minus the platform commission %.
        // The mobile "Sale details" model requires an `earnings` object at the
        // `data` root ({net, currency}); Moshi rejects the response without it.
        $rate = (float) ($item->commission_rate ?? $item->order?->commission_rate ?? 0);
        $net  = $rate > 0 ? (float) $item->price * (1 - $rate / 100.0) : (float) $item->price;
        $earningsPayload = [
            'net'      => round($net, 2),
            'currency' => (string) ($item->order?->payable_currency ?? ''),
        ];

        // The mobile app's "Sale details" model reads several required fields at
        // the `data` root (sale, course, buyer, …) — it reveals missing ones one
        // at a time via Gson. We expose all of them as siblings; the app uses what
        // it needs and ignores the rest. `sale` also keeps course/buyer nested.
        return response()->json([
            'status' => 'success',
            'data'   => [
                'sale'     => $salePayload,
                'course'   => $coursePayload,
                'buyer'    => $buyerPayload,
                'student'  => $buyerPayload, // alias — the buyer IS the student on a coach sale
                'earnings' => $earningsPayload,
            ],
        ], 200);
    }

    /**
     * GET /api/instructor/account/sessions
     * The authenticated instructor's active API sessions (Sanctum tokens). The
     * token currently making the request is flagged `current`. Empty → [] + 200.
     */
    public function account_sessions(Request $request): JsonResponse
    {
        $user = auth()->user();
        $currentId = optional($user->currentAccessToken())->id;

        $sessions = $user->tokens()
            ->orderByRaw('last_used_at IS NULL, last_used_at DESC')
            ->get()
            ->map(function ($t) use ($currentId) {
                return [
                    'id'           => $t->id,
                    'name'         => $t->name,
                    'last_used_at' => $t->last_used_at?->toDateTimeString(),
                    'created_at'   => $t->created_at?->toDateTimeString(),
                    'current'      => $currentId !== null && (int) $t->id === (int) $currentId,
                ];
            })
            ->values();

        // The mobile app expects `data` to be an OBJECT with the list nested under
        // `sessions` (not a bare array), so wrap it. Empty → {sessions: []} + 200.
        return response()->json(['status' => 'success', 'data' => ['sessions' => $sessions]], 200);
    }

    /**
     * GET /api/instructor/withdraw-requests?page=1&limit=15
     * Bug-fix: the app's InstructorWithdrawIndex model expects `data` to be an
     * OBJECT { current_balance, methods, requests } (was returning a bare array
     * → "Expected BEGIN_OBJECT but was BEGIN_ARRAY at $.data" crash on the Payout
     * screen). current_balance = the coach's wallet_balance. Empty → still an
     * object with requests: [] + 200.
     */
    public function withdraw_requests(Request $request): JsonResponse
    {
        $user  = auth()->user();
        $limit = $request->filled('limit') && is_numeric($request->limit) ? (int) $request->limit : 15;

        $query = WithdrawRequest::where('user_id', $user->id)->orderByDesc('id');
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        $rows = $query->paginate($limit);

        $requests = $rows->getCollection()->map(fn ($w) => [
            'id'              => (int) $w->id,
            'method'          => $w->method,
            'withdraw_amount' => (float) $w->withdraw_amount,
            // account_info is encrypted at rest; a legacy/corrupt row must not
            // 500 the whole list — fall back to null on a decrypt failure.
            'account_info'    => rescue(fn () => $w->account_info, null, false),
            'status'          => $w->status,
            'approved_date'   => $w->approved_date,
            'created_at'      => $w->created_at?->format('Y-m-d H:i:s'),
        ])->values();

        $methods = \Modules\PaymentWithdraw\app\Models\WithdrawMethod::where('status', 'active')
            ->get(['id', 'name', 'min_amount', 'max_amount', 'description'])
            ->map(fn ($m) => [
                'id'          => (int) $m->id,
                'name'        => (string) $m->name,
                'min_amount'  => (float) $m->min_amount,
                'max_amount'  => (float) $m->max_amount,
                'description' => $m->description,
            ])->values();

        return response()->json([
            'status'     => 'success',
            'data'       => [
                'current_balance' => (float) ($user->wallet_balance ?? 0),
                'methods'         => $methods,
                'requests'        => $requests,
            ],
            'pagination' => [
                'current_page' => $rows->currentPage(),
                'per_page'     => $rows->perPage(),
                'total'        => $rows->total(),
                'last_page'    => $rows->lastPage(),
            ],
        ], 200);
    }

    /**
     * Resolve the effective coach id for the authenticated caller.
     * A real coach (role=instructor, no coach_id) owns their own courses;
     * coach-staff carry coach_id and act within that coach's tenant. Using this
     * everywhere keeps the live-class feature tenant-safe (no cross-coach data).
     */
    private function effectiveCoachId(): int
    {
        $user = auth()->user();
        return (int) ($user->coach_id ?: $user->id);
    }

    /**
     * GET /api/instructor/live-classes/create-context
     * Phase 1 (form load) — the data the mobile "Schedule live class" form needs:
     * the coach's own courses, each with its active chapters + active batches,
     * plus whether the coach's Zoom is connected. Read-only. Scoped to the
     * effective coach id → a coach (or their staff) only ever sees that coach's
     * courses/chapters/batches. No cross-tenant leakage.
     */
    public function instructorLiveClassCreateContext(Request $request): JsonResponse
    {
        $coachId = $this->effectiveCoachId();

        $courses = Course::where('instructor_id', $coachId)
            ->select('id', 'title')
            ->with(['chapters' => fn ($q) => $q
                ->where('status', 'active')
                ->select('id', 'course_id', 'title')
                ->orderBy('order')])
            ->orderByDesc('id')
            ->get();

        // Active batches for those courses, grouped by course_id.
        $batchesByCourse = \App\Models\CourseBatch::where('status', 'active')
            ->whereIn('course_id', $courses->pluck('id')->all() ?: [0])
            ->orderByDesc('id')
            ->get(['id', 'course_id', 'title', 'start_date', 'end_date'])
            ->groupBy('course_id');

        $data = $courses->map(fn ($c) => [
            'id'       => (int) $c->id,
            'title'    => (string) $c->title,
            'chapters' => $c->chapters->map(fn ($ch) => [
                'id'    => (int) $ch->id,
                'title' => (string) $ch->title,
            ])->values(),
            'batches'  => ($batchesByCourse[$c->id] ?? collect())->map(fn ($b) => [
                'id'         => (int) $b->id,
                'title'      => (string) $b->title,
                'start_date' => $b->start_date ? (string) $b->start_date : null,
                'end_date'   => $b->end_date ? (string) $b->end_date : null,
            ])->values(),
        ]);

        // Zoom is "ready" when the coach has stored credentials (account + client).
        $zoomReady = \App\Models\ZoomCredential::where('instructor_id', $coachId)
            ->whereNotNull('account_id')
            ->whereNotNull('client_id')
            ->exists();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'courses'    => $data->values(),
                'zoom_ready' => $zoomReady,
            ],
        ], 200);
    }

    /**
     * GET /api/instructor/live-classes?page=1&limit=15&scope=upcoming|past|all
     * THIS instructor's scheduled live classes (Instructor Dashboard). Scoped
     * via lesson.course.instructor_id — the same ownership path the model's own
     * visibleToStudent() uses, so a coach only ever sees their own classes.
     * Empty result is VALID (a coach with no classes) → [] + 200, never 404.
     */
    public function instructorLiveClasses(Request $request): JsonResponse
    {
        $instructorId = auth()->user()->id;
        $limit = $request->filled('limit') && is_numeric($request->limit) ? (int) $request->limit : 15;
        $scope = in_array($request->scope, ['upcoming', 'past', 'all'], true) ? $request->scope : 'upcoming';

        $courseIds = Course::where('instructor_id', $instructorId)->pluck('id')->all();

        $query = CourseLiveClass::with([
                'lesson:id,title,course_id',
                'lesson.course:id,title',
                'batch:id,title',
            ])
            ->whereHas('lesson', fn ($q) => $q->whereIn('course_id', $courseIds ?: [0]));

        if ($scope === 'upcoming') {
            $query->where('start_time', '>=', now())->orderBy('start_time', 'asc');
        } elseif ($scope === 'past') {
            $query->where('start_time', '<', now())->orderByDesc('start_time');
        } else {
            $query->orderByDesc('start_time');
        }

        $rows = $query->paginate($limit);

        $data = $rows->getCollection()->map(fn ($lc) => [
            'id'           => (int) $lc->id,
            'lesson_id'    => (int) ($lc->lesson_id ?? 0),
            'title'        => (string) ($lc->lesson?->title ?? ''),
            'course_title' => (string) ($lc->lesson?->course?->title ?? ''),
            'batch_title'  => $lc->batch?->title ? (string) $lc->batch->title : null,
            'start_time'   => $lc->start_time?->format('Y-m-d H:i:s'),
            'type'         => (string) ($lc->type ?? ''),
            'meeting_id'   => $lc->meeting_id ? (string) $lc->meeting_id : null,
            'join_url'     => $lc->join_url ? (string) $lc->join_url : null,
        ]);

        return response()->json([
            'status'     => 'success',
            'data'       => $data,
            'pagination' => [
                'current_page' => $rows->currentPage(),
                'per_page'     => $rows->perPage(),
                'total'        => $rows->total(),
                'last_page'    => $rows->lastPage(),
            ],
        ], 200);
    }

    /**
     * GET /api/instructor/course-reviews?page=1&limit=15&status=0|1&course_id=
     * Reviews on THIS instructor's courses (Instructor Dashboard moderation).
     * status: 0=pending, 1=approved. Scoped to the coach's own course ids so
     * there is no cross-instructor leak. Empty → [] + 200, never 404.
     */
    public function instructorCourseReviews(Request $request): JsonResponse
    {
        $instructorId = auth()->user()->id;
        $limit = $request->filled('limit') && is_numeric($request->limit) ? (int) $request->limit : 15;

        $courseIds = Course::where('instructor_id', $instructorId)->pluck('id');

        $query = CourseReview::with(['user:id,name,image', 'course:id,title'])
            ->whereIn('course_id', $courseIds)
            ->orderByDesc('id');

        if ($request->filled('status') && is_numeric($request->status)) {
            $query->where('status', (int) $request->status);
        }
        if ($request->filled('course_id') && is_numeric($request->course_id)) {
            $query->where('course_id', (int) $request->course_id);
        }

        $rows = $query->paginate($limit);

        $data = $rows->getCollection()->map(fn ($r) => [
            'id'           => (int) $r->id,
            'course_id'    => (int) $r->course_id,
            'course_title' => (string) ($r->course?->title ?? ''),
            'rating'       => (int) $r->rating,
            'review'       => (string) ($r->review ?? ''),
            'status'       => (int) $r->status,
            'user'         => $r->user ? [
                'id'    => (int) $r->user->id,
                'name'  => (string) ($r->user->name ?? ''),
                'image' => $r->user->image ? (string) $r->user->image : null,
            ] : null,
            'created_at'   => $r->created_at?->format('Y-m-d H:i:s'),
        ]);

        return response()->json([
            'status'     => 'success',
            'data'       => $data,
            'pagination' => [
                'current_page' => $rows->currentPage(),
                'per_page'     => $rows->perPage(),
                'total'        => $rows->total(),
                'last_page'    => $rows->lastPage(),
            ],
        ], 200);
    }

    /* ───────────────────────────────────────────────────────────────────────
     * Phase 2 — Live-class management (create / reschedule / delete / host-start).
     * Zoom provisioning is gated behind config('app.live_class_zoom_enabled').
     * When OFF (default), a 'zoom' create is stored as an 'external' placeholder so
     * the full create→list→reschedule→delete flow is QA-able without touching Zoom.
     * Every method is scoped to the effective coach id → tenant-safe.
     * ─────────────────────────────────────────────────────────────────────── */

    private function liveClassZoomEnabled(): bool
    {
        return (bool) config('app.live_class_zoom_enabled', false);
    }

    /** Assert (course, batch, chapter) all belong to the effective coach; 403 otherwise. */
    private function assertOwnsLiveClassContext(int $courseId, int $batchId, int $chapterId): void
    {
        $coachId = $this->effectiveCoachId();

        abort_unless(
            Course::where('id', $courseId)
                ->where(fn ($q) => $q->where('added_by', $coachId)->orWhere('instructor_id', $coachId))
                ->exists(),
            403, 'You do not own this course.'
        );
        abort_unless(
            \App\Models\CourseBatch::where('id', $batchId)->where('course_id', $courseId)->exists(),
            403, 'Batch does not belong to this course.'
        );
        abort_unless(
            CourseChapter::where('id', $chapterId)->where('course_id', $courseId)->exists(),
            403, 'Chapter does not belong to this course.'
        );
    }

    /** First chapter of the course, or a freshly-created "Live Sessions" one. */
    private function resolveDefaultChapterForCoach(int $courseId): CourseChapter
    {
        return CourseChapter::where('course_id', $courseId)->orderBy('id')->first()
            ?? CourseChapter::create(['course_id' => $courseId, 'title' => 'Live Sessions', 'order' => 1]);
    }

    /** One live class owned by the effective coach (direct course_id OR via lesson), else 404. */
    private function findOwnedLiveClassOrFail($id): CourseLiveClass
    {
        $coachId = $this->effectiveCoachId();
        $courseIds = Course::where(fn ($q) => $q->where('added_by', $coachId)->orWhere('instructor_id', $coachId))
            ->pluck('id');

        return CourseLiveClass::where('id', $id)
            ->where(fn ($q) => $q->whereIn('course_id', $courseIds)
                ->orWhereHas('lesson', fn ($l) => $l->whereIn('course_id', $courseIds)))
            ->firstOrFail();
    }

    private function zoomMeetingPayloadApi(string $topic, string $startIso, int $duration): array
    {
        return [
            'topic' => $topic, 'type' => 2, 'start_time' => $startIso,
            'duration' => $duration, 'timezone' => 'Asia/Kolkata', 'password' => '',
            'settings' => [
                'password' => false, 'meeting_authentication' => false, 'waiting_room' => false,
                'approval_type' => 2, 'join_before_host' => true, 'jbh_time' => 0,
                'host_video' => true, 'participant_video' => true, 'mute_upon_entry' => true,
                'audio' => 'both', 'registrants_email_notification' => false,
                'registration_type' => 1, 'auto_recording' => 'none',
            ],
        ];
    }

    /**
     * POST /api/instructor/live-classes — create. Builds chapter-item + lesson +
     * live-class row. Real Zoom meeting only when the flag is ON and live_type=zoom;
     * otherwise stored as an 'external' placeholder. Batch-scoped notification sent
     * via the shared service. Returns CreatedLiveClass.
     */
    public function createInstructorLiveClass(Request $request): JsonResponse
    {
        $data = $request->validate([
            'live_class_title'  => 'required|string|max:255',
            'course_id'         => 'required|integer|exists:courses,id',
            'batch_id'          => 'required|integer|exists:course_batches,id',
            'chapter_id'        => 'nullable|integer|exists:course_chapters,id',
            'live_type'         => 'required|string|in:zoom,external',
            'start_time'        => 'required|date',
            'duration'          => 'required|integer|min:1',
            'description'       => 'nullable|string',
            'external_join_url' => 'nullable|url',
        ]);

        $chapterId = $request->filled('chapter_id')
            ? (int) $data['chapter_id']
            : $this->resolveDefaultChapterForCoach((int) $data['course_id'])->id;

        $this->assertOwnsLiveClassContext((int) $data['course_id'], (int) $data['batch_id'], (int) $chapterId);

        $startTime = date('Y-m-d H:i:s', strtotime($data['start_time']));
        $duration  = (int) $data['duration'];
        // Provision a real Zoom meeting only when the coach chose zoom AND the
        // feature flag is on. Store the coach's chosen type verbatim — a 'zoom'
        // class whose meeting isn't provisioned yet (flag off) is still type
        // 'zoom' with an empty meeting_id (Phase 3 fills it in).
        $wantsZoom  = $data['live_type'] === 'zoom' && $this->liveClassZoomEnabled();
        $storedType = $data['live_type'] === 'external' ? 'external' : 'zoom';

        $chapterItem = \App\Models\CourseChapterItem::create([
            'instructor_id' => auth()->id(),
            'chapter_id'    => $chapterId,
            'type'          => 'live',
            'order'         => \App\Models\CourseChapterItem::whereChapterId($chapterId)->count() + 1,
        ]);
        $lesson = CourseChapterLesson::create([
            'title'           => $data['live_class_title'],
            'description'     => $data['description'] ?? null,
            'instructor_id'   => auth()->id(),
            'course_id'       => $data['course_id'],
            'chapter_id'      => $chapterId,
            'chapter_item_id' => $chapterItem->id,
            'duration'        => $duration,
            'storage'         => 'live',
            'file_type'       => 'live',
        ]);

        $meetingId = null; $joinUrl = null; $password = null;

        if ($wantsZoom) {
            $cred = User::find($this->effectiveCoachId())?->zoom_credential;
            if (! $cred || ! $cred->account_id || ! $cred->client_id || ! $cred->client_secret) {
                $lesson->delete(); $chapterItem->delete();
                return response()->json(['status' => 'error', 'message' => 'Zoom is not configured for this coach.'], 400);
            }
            $token = app(\App\Services\ZoomApiService::class)->ensureFreshAccessToken($cred);
            if (! $token) {
                $lesson->delete(); $chapterItem->delete();
                return response()->json(['status' => 'error', 'message' => 'Zoom rejected the credentials.'], 400);
            }
            $resp = \Illuminate\Support\Facades\Http::withToken($token)->timeout(20)
                ->post('https://api.zoom.us/v2/users/me/meetings',
                    $this->zoomMeetingPayloadApi((string) $data['live_class_title'], date('c', strtotime($data['start_time'])), $duration));
            $m = $resp->json();
            $meetingId = $m['id'] ?? null; $joinUrl = $m['join_url'] ?? null; $password = $m['password'] ?? null;
            if (! $meetingId) {
                $lesson->delete(); $chapterItem->delete();
                return response()->json(['status' => 'error', 'message' => 'Zoom rejected the meeting creation. Live class not saved.'], 400);
            }
        } else {
            // external → the coach's supplied URL; zoom placeholder (flag off)
            // → no URL yet (Phase 3 provisions the Zoom meeting + join_url).
            $joinUrl = $data['live_type'] === 'external' ? ($data['external_join_url'] ?? null) : null;
        }

        $liveClass = CourseLiveClass::create([
            'batch_id'                  => $data['batch_id'],
            'course_id'                 => $lesson->course_id,
            'lesson_id'                 => $lesson->id,
            'start_time'                => $startTime,
            'expected_duration_minutes' => $duration,
            'meeting_id'                => $meetingId ?? '',
            'password'                  => $password,
            'join_url'                  => $joinUrl,
            'type'                      => $storedType,
        ]);

        // Batch-scoped notification (in-app + email to eligible batch students only).
        try {
            app(\App\Services\LiveClassNotificationService::class)
                ->notifyScheduled($liveClass, (string) $lesson->title, true);
        } catch (\Throwable $e) {
            \Log::warning('api-live-class-notify-failed', ['id' => $liveClass->id, 'err' => $e->getMessage()]);
        }

        return response()->json([
            'status'  => 'success',
            'message' => ($data['live_type'] === 'external' || $wantsZoom)
                ? 'Live class created.'
                : 'Live class created. The Zoom meeting will be provisioned once live scheduling is enabled.',
            'data'    => [
                'live_class_id' => (int) $liveClass->id,
                'lesson_id'     => (int) $lesson->id,
                'join_url'      => $joinUrl,
                'meeting_id'    => $meetingId ? (string) $meetingId : null,
                'start_time'    => $startTime,
            ],
        ], 201);
    }

    /**
     * PUT /api/instructor/live-classes/{id} — reschedule / edit. All fields optional;
     * unset fields keep their existing value. (Reschedule emails are Phase 3.)
     */
    public function updateInstructorLiveClass(Request $request, $id): JsonResponse
    {
        $liveClass = $this->findOwnedLiveClassOrFail($id);
        $data = $request->validate([
            'live_class_title' => 'nullable|string|max:255',
            'description'      => 'nullable|string',
            'start_time'       => 'nullable|date',
            'duration'         => 'nullable|integer|min:1',
        ]);

        $lesson = $liveClass->lesson;
        if ($lesson) {
            if (! empty($data['live_class_title'])) $lesson->title = $data['live_class_title'];
            if ($request->exists('description'))    $lesson->description = $data['description'] ?? null;
            if (! empty($data['duration']))         $lesson->duration = (int) $data['duration'];
            $lesson->save();
        }

        if (! empty($data['start_time'])) {
            $liveClass->start_time = date('Y-m-d H:i:s', strtotime($data['start_time']));
        }
        if (! empty($data['duration'])) {
            $liveClass->expected_duration_minutes = (int) $data['duration'];
        }
        $liveClass->save();

        return response()->json([
            'status'  => 'success',
            'message' => 'Live class updated.',
            'data'    => [
                'id'         => (int) $liveClass->id,
                'title'      => (string) ($lesson?->title ?? ''),
                'start_time' => $liveClass->start_time?->format('Y-m-d H:i:s'),
                'duration'   => (int) ($liveClass->expected_duration_minutes ?? $lesson?->duration ?? 0),
            ],
        ], 200);
    }

    /**
     * DELETE /api/instructor/live-classes/{id} — cancel + remove the row and its
     * parent lesson/chapter-item. Best-effort Zoom cleanup when the flag is ON.
     */
    public function deleteInstructorLiveClass($id): JsonResponse
    {
        $liveClass = $this->findOwnedLiveClassOrFail($id);

        if ($this->liveClassZoomEnabled() && $liveClass->type === 'zoom' && $liveClass->meeting_id) {
            try {
                $cred = User::find($this->effectiveCoachId())?->zoom_credential;
                if ($cred) app(\App\Services\ZoomApiService::class)->endMeeting($cred, (string) $liveClass->meeting_id);
            } catch (\Throwable $e) {
                \Log::warning('api-live-class-zoom-delete-failed', ['id' => $liveClass->id, 'err' => $e->getMessage()]);
            }
        }

        $lesson = $liveClass->lesson;
        $chapterItemId = $lesson?->chapter_item_id;
        $liveClass->delete();
        if ($lesson) $lesson->delete();
        if ($chapterItemId) \App\Models\CourseChapterItem::where('id', $chapterItemId)->delete();

        return response()->json(['status' => 'success', 'message' => 'Live class deleted.'], 200);
    }

    /**
     * GET /api/instructor/live-classes/{id}/host-start — host launch URL.
     * Phase 3: for a provisioned Zoom class, returns the real host `start_url`
     * (host token embedded) + ZAK so the app launches the coach AS HOST. For
     * external / not-yet-provisioned classes, falls back to the stored join_url.
     */
    public function instructorLiveClassHostStart($id): JsonResponse
    {
        $liveClass = $this->findOwnedLiveClassOrFail($id);

        if ($liveClass->type === 'zoom' && $liveClass->meeting_id) {
            $cred = User::find($this->effectiveCoachId())?->zoom_credential;
            if ($cred) {
                $host = $this->fetchZoomHostStart($cred, (string) $liveClass->meeting_id);
                if (! empty($host['start_url'])) {
                    return response()->json(['status' => 'success', 'data' => $host], 200);
                }
            }
            // Zoom fetch failed → fall through to the stored join_url below.
        }

        if (! $liveClass->join_url) {
            return response()->json(['status' => 'error', 'message' => 'No launch URL for this live class yet.'], 404);
        }
        return response()->json([
            'status' => 'success',
            'data'   => ['start_url' => (string) $liveClass->join_url, 'zak' => null],
        ], 200);
    }

    /**
     * Fetch the Zoom host start_url (GET /meetings/{id}) + ZAK
     * (GET /users/me/token?type=zak). Null-safe: any failure returns nulls so
     * the caller can fall back to the participant join_url. Requires the S2S
     * OAuth scopes meeting:read + user:read:zak:admin on the coach's Zoom app.
     */
    private function fetchZoomHostStart(\App\Models\ZoomCredential $cred, string $meetingId): array
    {
        $out = ['start_url' => null, 'zak' => null];
        try {
            $token = app(\App\Services\ZoomApiService::class)->ensureFreshAccessToken($cred);
            if (! $token) return $out;

            $meeting = \Illuminate\Support\Facades\Http::withToken($token)->acceptJson()->timeout(10)
                ->get('https://api.zoom.us/v2/meetings/' . urlencode($meetingId));
            if ($meeting->successful()) {
                $out['start_url'] = $meeting->json('start_url');
            }

            $zak = \Illuminate\Support\Facades\Http::withToken($token)->acceptJson()->timeout(5)
                ->get('https://api.zoom.us/v2/users/me/token', ['type' => 'zak']);
            if ($zak->successful()) {
                $out['zak'] = $zak->json('token');
            }
        } catch (\Throwable $e) {
            \Log::warning('api-live-class-hoststart-zoom-failed', ['meeting' => $meetingId, 'err' => $e->getMessage()]);
        }
        return $out;
    }

    /**
     * GET /api/instructor/live-classes/{id}/attendance — per-user attendance
     * report for one of the coach's own live classes. Mirrors the web
     * LiveClassController::attendance aggregation (late = best/min, early-leave =
     * worst/max across a user's sessions) and lists enrolled-but-absent students.
     */
    public function liveClassAttendance($id): JsonResponse
    {
        $liveClass = $this->findOwnedLiveClassOrFail($id);
        $liveClass->load(['lesson:id,course_id,title,duration', 'lesson.course:id,title']);

        $classStart  = $liveClass->start_time ? \Illuminate\Support\Carbon::parse($liveClass->start_time) : null;
        $classDurMin = (int) ($liveClass->lesson?->duration ?: ($liveClass->expected_duration_minutes ?: 60));
        $classEnd    = $classStart ? $classStart->copy()->addMinutes($classDurMin) : null;

        $attendances = \App\Models\LiveClassAttendance::with(['user:id,name,email,image'])
            ->where('course_live_class_id', $liveClass->id)
            ->orderByDesc('joined_at')->get();

        $attendances->transform(function ($a) use ($classStart, $classEnd) {
            $a->late_seconds = null; $a->early_leave_seconds = null;
            if ($classStart && $a->joined_at) {
                $diff = \Illuminate\Support\Carbon::parse($a->joined_at)->diffInSeconds($classStart, false);
                $a->late_seconds = $diff < 0 ? abs($diff) : 0;
            }
            if ($classEnd && $a->left_at) {
                $diff = \Illuminate\Support\Carbon::parse($a->left_at)->diffInSeconds($classEnd, false);
                $a->early_leave_seconds = $diff > 0 ? $diff : 0;
            }
            return $a;
        });

        $attendees = $attendances->groupBy('user_id')->map(function ($rows) {
            $first = $rows->last(); // earliest joined_at (ordered desc)
            $late  = $rows->pluck('late_seconds')->filter(fn ($v) => $v !== null);
            $early = $rows->pluck('early_leave_seconds')->filter(fn ($v) => $v !== null);
            return [
                'user_id'             => (int) $first->user_id,
                'name'                => $first->user?->name,
                'email'               => $first->user?->email,
                'image'               => $first->user?->image ?: null,
                'role'                => $rows->first()->role,
                'sessions'            => $rows->count(),
                'total_seconds'       => (int) ($rows->sum('duration_seconds') ?: 0),
                'first_joined_at'     => $rows->min('joined_at')?->format('Y-m-d H:i:s'),
                'is_currently_in'     => $rows->whereNull('left_at')->isNotEmpty(),
                'late_seconds'        => $late->isEmpty()  ? null : (int) $late->min(),
                'early_leave_seconds' => $early->isEmpty() ? null : (int) $early->max(),
            ];
        })->values();

        $attendedIds = $attendances->pluck('user_id')->unique()->all();
        $courseId    = (int) ($liveClass->course_id ?: $liveClass->lesson?->course_id);
        $absentees = \Illuminate\Support\Facades\DB::table('enrollments')
            ->join('users', 'users.id', '=', 'enrollments.user_id')
            ->where('enrollments.course_id', $courseId)
            ->where('enrollments.has_access', 1)
            ->whereNotIn('users.id', $attendedIds ?: [0])
            ->select('users.id', 'users.name', 'users.email', 'users.image')
            ->orderBy('users.name')->get()
            ->map(fn ($u) => [
                'user_id' => (int) $u->id,
                'name'    => $u->name,
                'email'   => $u->email,
                'image'   => $u->image ?: null,
            ])->values();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'live_class' => [
                    'id'              => (int) $liveClass->id,
                    'title'           => $liveClass->lesson?->title,
                    'course_title'    => $liveClass->lesson?->course?->title,
                    'start_time'      => $liveClass->start_time?->format('Y-m-d H:i:s'),
                    'duration_minutes'=> $classDurMin,
                    'type'            => (string) ($liveClass->type ?? ''),
                ],
                'summary' => [
                    'attended_count' => $attendees->count(),
                    'currently_in'   => $attendees->where('is_currently_in', true)->count(),
                    'absent_count'   => $absentees->count(),
                ],
                'attendees' => $attendees,
                'absentees' => $absentees,
            ],
        ], 200);
    }

    /**
     * POST /api/instructor/live-classes/{id}/attendance/manual — mark a student
     * present manually (audit-trailed). reason required; duration defaults 60m.
     */
    public function liveClassManualAttendance(Request $request, $id): JsonResponse
    {
        $liveClass = $this->findOwnedLiveClassOrFail($id);
        $data = $request->validate([
            'user_id'      => 'required|integer|exists:users,id',
            'reason'       => 'required|string|max:500',
            'duration_min' => 'nullable|integer|min:1',
        ]);

        \App\Models\LiveClassAttendance::create([
            'course_live_class_id' => $liveClass->id,
            'user_id'              => (int) $data['user_id'],
            'role'                 => 'student',
            'joined_at'            => $liveClass->start_time ?: now(),
            'left_at'              => null,
            'duration_seconds'     => (int) (($data['duration_min'] ?? 60) * 60),
            'is_manual'            => 1,
            'manual_reason'        => $data['reason'],
            'marked_by'            => auth()->id(),
        ]);

        return response()->json(['status' => 'success', 'message' => 'Attendance marked.'], 200);
    }

    /**
     * GET /api/instructor/live-classes/{id}/recordings — stored recordings
     * (Zoom cloud MP4s / transcripts / chat) for one of the coach's classes.
     */
    public function liveClassRecordings($id): JsonResponse
    {
        $liveClass = $this->findOwnedLiveClassOrFail($id);
        $liveClass->load(['lesson:id,course_id,title', 'lesson.course:id,title', 'recordings']);

        $recordings = ($liveClass->recordings ?? collect())->map(fn ($r) => [
            'id'              => (int) $r->id,
            'file_type'       => $r->file_type,
            'file_extension'  => $r->file_extension,
            'play_url'        => $r->play_url,
            'download_url'    => $r->download_url,
            'file_size'       => $r->file_size !== null ? (int) $r->file_size : null,
            'file_size_human' => $r->file_size !== null ? $this->humanBytes((int) $r->file_size) : null,
            'duration_seconds'=> $r->duration_seconds !== null ? (int) $r->duration_seconds : null,
            'recording_start' => $r->recording_start ? (string) $r->recording_start : null,
            'recording_end'   => $r->recording_end ? (string) $r->recording_end : null,
            'created_at'      => $r->created_at?->format('Y-m-d H:i:s'),
        ])->values();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'live_class' => [
                    'id'           => (int) $liveClass->id,
                    'title'        => $liveClass->lesson?->title,
                    'course_title' => $liveClass->lesson?->course?->title,
                    'start_time'   => $liveClass->start_time?->format('Y-m-d H:i:s'),
                ],
                'recordings' => $recordings,
            ],
        ], 200);
    }

    /** Human-readable byte size for recording cards ("124 MB"). */
    private function humanBytes(int $bytes): string
    {
        if ($bytes <= 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));
        $i = max(0, min($i, count($units) - 1));
        return round($bytes / (1024 ** $i), $i ? 1 : 0) . ' ' . $units[$i];
    }

    /* ───────────────────────────────────────────────────────────────────────
     * 404-fix Phase A — Coach coupons CRUD. Scoped to the effective coach id
     * (coach → own id; staff → coach_id) so a coach only ever sees/edits their
     * OWN coupons. Mirrors the web Modules\Coupon\CouponController scoping.
     * ─────────────────────────────────────────────────────────────────────── */

    private function couponPayload($c): array
    {
        return [
            'id'               => (int) $c->id,
            'coupon_code'      => (string) $c->coupon_code,
            'offer_percentage' => (float) $c->offer_percentage,
            'min_price'        => (float) ($c->min_price ?? 0),
            'expired_date'     => $c->expired_date ? (string) $c->expired_date : null,
            'status'           => (string) $c->status,
            'usage_limit'      => $c->usage_limit !== null ? (int) $c->usage_limit : null,
            'usage_count'      => (int) ($c->usage_count ?? 0),
            'per_user_limit'   => $c->per_user_limit !== null ? (int) $c->per_user_limit : null,
            'created_at'       => $c->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    /** GET /api/instructor/coupons — this coach's coupons, paginated. Empty → []+200. */
    public function instructorCouponsList(Request $request): JsonResponse
    {
        $coachId = $this->effectiveCoachId();
        $limit = $request->filled('limit') && is_numeric($request->limit) ? (int) $request->limit : 15;

        $query = \Modules\Coupon\app\Models\Coupon::where('coach_id', $coachId);
        // Bug-fix: the list must honour ?status=active|inactive — without this
        // the "inactive" tab showed active coupons too.
        if ($request->filled('status') && in_array($request->status, ['active', 'inactive'], true)) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $query->where('coupon_code', 'like', '%' . $request->search . '%');
        }
        $rows = $query->orderByDesc('id')->paginate($limit);

        return response()->json([
            'status'     => 'success',
            'data'       => $rows->getCollection()->map(fn ($c) => $this->couponPayload($c))->values(),
            'pagination' => [
                'current_page' => $rows->currentPage(),
                'per_page'     => $rows->perPage(),
                'total'        => $rows->total(),
                'last_page'    => $rows->lastPage(),
            ],
        ], 200);
    }

    /** POST /api/instructor/coupons — create a coupon for this coach. */
    public function instructorCouponStore(Request $request): JsonResponse
    {
        $coachId = $this->effectiveCoachId();
        $data = $request->validate([
            'coupon_code'      => 'required|string|max:100',
            'offer_percentage' => 'required|numeric|min:0|max:100',
            'min_price'        => 'required|numeric|min:0',
            'expired_date'     => 'required|date',
            'status'           => 'required|in:active,inactive',
            'usage_limit'      => 'nullable|integer|min:1',
            'per_user_limit'   => 'nullable|integer|min:1',
        ]);

        // Scoped uniqueness — no two live coupons with the same code per coach.
        if (\Modules\Coupon\app\Models\Coupon::where('coach_id', $coachId)
                ->where('coupon_code', $data['coupon_code'])->exists()) {
            return response()->json(['status' => 'error', 'message' => 'A coupon with this code already exists.'], 422);
        }

        $coupon = \Modules\Coupon\app\Models\Coupon::create(array_merge($data, [
            'author_id'   => auth()->id(),
            'coach_id'    => $coachId,
            'usage_count' => 0,
        ]));

        return response()->json(['status' => 'success', 'message' => 'Coupon created.', 'data' => $this->couponPayload($coupon)], 201);
    }

    /** PUT /api/instructor/coupons/{id} — update one of this coach's coupons. */
    public function instructorCouponUpdate(Request $request, $id): JsonResponse
    {
        $coachId = $this->effectiveCoachId();
        $coupon = \Modules\Coupon\app\Models\Coupon::where('coach_id', $coachId)->find($id);
        if (! $coupon) {
            return response()->json(['status' => 'error', 'message' => 'Coupon not found.'], 404);
        }

        $data = $request->validate([
            'coupon_code'      => 'required|string|max:100',
            'offer_percentage' => 'required|numeric|min:0|max:100',
            'min_price'        => 'required|numeric|min:0',
            'expired_date'     => 'required|date',
            'status'           => 'required|in:active,inactive',
            'usage_limit'      => 'nullable|integer|min:1',
            'per_user_limit'   => 'nullable|integer|min:1',
        ]);

        if (\Modules\Coupon\app\Models\Coupon::where('coach_id', $coachId)
                ->where('coupon_code', $data['coupon_code'])
                ->where('id', '!=', $coupon->id)->exists()) {
            return response()->json(['status' => 'error', 'message' => 'A coupon with this code already exists.'], 422);
        }

        $coupon->update($data);
        return response()->json(['status' => 'success', 'message' => 'Coupon updated.', 'data' => $this->couponPayload($coupon->fresh())], 200);
    }

    /** DELETE /api/instructor/coupons/{id} — remove one of this coach's coupons. */
    public function instructorCouponDelete($id): JsonResponse
    {
        $coachId = $this->effectiveCoachId();
        $coupon = \Modules\Coupon\app\Models\Coupon::where('coach_id', $coachId)->find($id);
        if (! $coupon) {
            return response()->json(['status' => 'error', 'message' => 'Coupon not found.'], 404);
        }
        $coupon->delete();
        return response()->json(['status' => 'success', 'message' => 'Coupon deleted.'], 200);
    }

    /* ───────────────────────────────────────────────────────────────────────
     * 404-fix Phase B — Coach staff + roles. Staff are Users under the coach
     * (coach_id = coach, role_id → coach_staff_roles). Scoped to effectiveCoachId.
     * Mirrors web CoachStaffController (role clamping, roles pivot).
     * ─────────────────────────────────────────────────────────────────────── */

    /** GET /api/instructor/staff-roles — this coach's custom roles. */
    public function instructorStaffRoles(Request $request): JsonResponse
    {
        $coachId = $this->effectiveCoachId();
        $roles = \App\Models\CoachStaffRole::where('added_by', $coachId)
            ->where('status', 1)->orderBy('role_name')
            ->get(['id', 'role_name'])
            ->map(fn ($r) => ['id' => (int) $r->id, 'name' => (string) $r->role_name])->values();
        return response()->json(['status' => 'success', 'data' => $roles], 200);
    }

    /** GET /api/instructor/staff — this coach's staff users, paginated. */
    public function instructorStaff(Request $request): JsonResponse
    {
        $coachId = $this->effectiveCoachId();
        $limit = $request->filled('limit') && is_numeric($request->limit) ? (int) $request->limit : 15;

        $q = User::where('coach_id', $coachId)->whereNotNull('role_id');
        if ($request->filled('status'))  $q->where('status', $request->status);
        if ($request->filled('role_id') && is_numeric($request->role_id)) $q->where('role_id', (int) $request->role_id);
        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"));
        }
        $rows = $q->orderByDesc('id')->paginate($limit);

        $roleNames = \App\Models\CoachStaffRole::whereIn('id', $rows->pluck('role_id')->filter()->unique()->all() ?: [0])
            ->pluck('role_name', 'id');

        $data = $rows->getCollection()->map(fn ($u) => [
            'id'         => (int) $u->id,
            'name'       => (string) $u->name,
            'email'      => (string) ($u->email ?? ''),
            'status'     => (string) ($u->status ?? ''),
            'role'       => $u->role ? (string) $u->role : null,
            'role_id'    => $u->role_id !== null ? (int) $u->role_id : null,
            'role_name'  => $roleNames[$u->role_id] ?? ($u->role ?? null),
            'created_at' => $u->created_at?->format('Y-m-d H:i:s'),
        ])->values();

        return response()->json([
            'status'     => 'success',
            'data'       => $data,
            'pagination' => [
                'current_page' => $rows->currentPage(),
                'per_page'     => $rows->perPage(),
                'total'        => $rows->total(),
                'last_page'    => $rows->lastPage(),
            ],
        ], 200);
    }

    /** POST /api/instructor/staff — create a staff user for this coach. */
    public function createInstructorStaff(Request $request): JsonResponse
    {
        $coachId = $this->effectiveCoachId();
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|max:255',
            'role_id'  => ['required', 'integer', \Illuminate\Validation\Rule::exists('coach_staff_roles', 'id')->where('added_by', $coachId)],
            'status'   => 'required|in:active,banned',
        ]);

        $role     = \App\Models\CoachStaffRole::find($data['role_id']);
        $reserved = ['instructor', 'student', 'admin', 'super-admin', 'superadmin', 'institute-branch'];
        $roleStr  = ($role && in_array(strtolower(trim((string) $role->role_name)), $reserved, true))
            ? 'staff' : (string) ($role->role_name ?? 'staff');

        $plaintextPassword = (string) $data['password'];   // kept for the welcome email

        $user = new User();
        $user->name               = $data['name'];
        $user->email              = $data['email'];
        $user->password           = \Illuminate\Support\Facades\Hash::make($data['password']);
        $user->status             = $data['status'];
        $user->role_id            = $data['role_id'];
        $user->role               = $roleStr;
        $user->is_banned          = 'no';
        $user->verification_token = hash('sha256', bin2hex(random_bytes(32)));
        $user->email_verified_at  = now();
        $user->added_by           = $coachId;
        $user->coach_id           = $coachId;
        $user->save();
        try { $user->roles()->attach($data['role_id']); } catch (\Throwable $e) {}

        // Bug-fix: email the new staff their login + temp password (the web path
        // does this; the API path was missing it). Wrapped so a mail hiccup
        // doesn't fail the staff creation.
        try {
            $user->notify(new \App\Notifications\CoachStaffWelcomeToStaff($user, $plaintextPassword, auth()->user()));
        } catch (\Throwable $e) {
            \Log::warning('api-coach-staff-welcome-mail-failed', ['id' => $user->id, 'err' => $e->getMessage()]);
        }

        return response()->json([
            'status' => 'success', 'message' => 'Staff created.',
            'data'   => ['id' => (int) $user->id, 'name' => $user->name, 'email' => $user->email, 'status' => $user->status],
        ], 201);
    }

    /** PUT /api/instructor/staff/{id} — update one of this coach's staff. */
    public function updateInstructorStaff(Request $request, $id): JsonResponse
    {
        $coachId = $this->effectiveCoachId();
        $user = User::where('coach_id', $coachId)->whereNotNull('role_id')->find($id);
        if (! $user) return response()->json(['status' => 'error', 'message' => 'Staff not found.'], 404);

        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => ['required', 'email', \Illuminate\Validation\Rule::unique('users', 'email')->ignore($user->id)],
            'status'   => 'required|in:active,banned',
            'role_id'  => ['required', 'integer', \Illuminate\Validation\Rule::exists('coach_staff_roles', 'id')->where('added_by', $coachId)],
            'password' => 'nullable|string|min:8|max:255',
        ]);

        $role     = \App\Models\CoachStaffRole::find($data['role_id']);
        $reserved = ['instructor', 'student', 'admin', 'super-admin', 'superadmin', 'institute-branch'];
        $user->name    = $data['name'];
        $user->email   = $data['email'];
        $user->status  = $data['status'];
        $user->role_id = $data['role_id'];
        $user->role    = ($role && in_array(strtolower(trim((string) $role->role_name)), $reserved, true))
            ? 'staff' : (string) ($role->role_name ?? 'staff');
        if (! empty($data['password'])) $user->password = \Illuminate\Support\Facades\Hash::make($data['password']);
        $user->save();
        try { $user->roles()->sync([$data['role_id']]); } catch (\Throwable $e) {}

        return response()->json([
            'status' => 'success', 'message' => 'Staff updated.',
            'data'   => ['id' => (int) $user->id, 'name' => $user->name, 'email' => $user->email, 'status' => $user->status],
        ], 200);
    }

    /** DELETE /api/instructor/staff/{id} — remove one of this coach's staff. */
    public function deleteInstructorStaff($id): JsonResponse
    {
        $coachId = $this->effectiveCoachId();
        $user = User::where('coach_id', $coachId)->whereNotNull('role_id')->find($id);
        if (! $user) return response()->json(['status' => 'error', 'message' => 'Staff not found.'], 404);
        $user->delete();
        return response()->json(['status' => 'success', 'message' => 'Staff deleted.'], 200);
    }

    /* ─── 404-fix Phase E — Coach payout account + methods (coach-scoped) ─── */

    /** GET /api/instructor/payout — payout account/info + active withdraw methods. */
    public function instructorPayout(Request $request): JsonResponse
    {
        $coachId = $this->effectiveCoachId();
        $gateway = \Modules\InstructorRequest\app\Models\InstructorRequest::where('user_id', $coachId)->first();
        $methods = \Modules\PaymentWithdraw\app\Models\WithdrawMethod::where('status', 'active')
            ->get(['id', 'name', 'min_amount', 'max_amount', 'description'])
            ->map(fn ($m) => [
                'id'          => (int) $m->id,
                'name'        => (string) $m->name,
                'min_amount'  => (float) $m->min_amount,
                'max_amount'  => (float) $m->max_amount,
                'description' => $m->description,
            ])->values();

        return response()->json(['status' => 'success', 'data' => [
            'payout_account'     => $gateway?->payout_account,
            'payout_information' => $gateway?->payout_information,
            'methods'            => $methods,
        ]], 200);
    }

    /** PUT /api/instructor/payout — update the coach's payout account + info. */
    public function updateInstructorPayout(Request $request): JsonResponse
    {
        $coachId = $this->effectiveCoachId();
        $data = $request->validate([
            'payout_account'     => 'required|string|max:255',
            'payout_information' => 'required|string|max:5000',
        ]);
        $gateway = \Modules\InstructorRequest\app\Models\InstructorRequest::where('user_id', $coachId)->first();
        if (! $gateway) {
            return response()->json(['status' => 'error', 'message' => 'Instructor profile not found.'], 404);
        }
        $gateway->payout_account     = $data['payout_account'];
        $gateway->payout_information = $data['payout_information'];
        $gateway->save();

        return response()->json(['status' => 'success', 'message' => 'Payout details updated.', 'data' => [
            'payout_account'     => $gateway->payout_account,
            'payout_information' => $gateway->payout_information,
            'methods'            => [],
        ]], 200);
    }

    /* ─── 404-fix Phase F — Coach landing page (added_by-scoped) ─── */

    private function landingPayload($p): array
    {
        return [
            'id'           => (int) $p->id,
            'title'        => $p->title,
            'website_name' => $p->website_name,
            'subdomain'    => $p->subdomain,
            'theme'        => $p->theme,
            'is_published' => (bool) $p->is_published,
            'created_at'   => $p->created_at?->format('Y-m-d H:i:s'),
            'updated_at'   => $p->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    /** GET /api/instructor/landing-page — the coach's landing page (or null). */
    public function instructorLandingPage(Request $request): JsonResponse
    {
        $coachId = $this->effectiveCoachId();
        $page = \App\Models\CoachLandingPage::where('added_by', $coachId)->first();
        return response()->json(['status' => 'success', 'data' => $page ? $this->landingPayload($page) : null], 200);
    }

    /** PUT /api/instructor/landing-page — update the coach's landing page. */
    public function updateInstructorLandingPage(Request $request): JsonResponse
    {
        $coachId = $this->effectiveCoachId();
        $page = \App\Models\CoachLandingPage::where('added_by', $coachId)->first();
        if (! $page) {
            return response()->json(['status' => 'error', 'message' => 'No landing page found. Create one from the web builder first.'], 404);
        }

        $data = $request->validate([
            'title'        => 'nullable|string|max:255',
            'website_name' => ['nullable', 'string', 'max:50', \Illuminate\Validation\Rule::unique('coach_landing_pages', 'website_name')->ignore($page->id)],
            'subdomain'    => ['nullable', 'string', 'max:50', 'alpha_dash', \Illuminate\Validation\Rule::unique('coach_landing_pages', 'subdomain')->ignore($page->id)],
            'theme'        => 'nullable|string|max:50',
            'is_published' => 'nullable|boolean',
        ]);

        foreach (['title', 'website_name', 'subdomain', 'theme'] as $f) {
            if ($request->exists($f) && $data[$f] !== null) $page->$f = $data[$f];
        }
        if ($request->exists('is_published')) $page->is_published = (bool) $data['is_published'];
        $page->save();

        return response()->json(['status' => 'success', 'message' => 'Landing page updated.', 'data' => $this->landingPayload($page->fresh())], 200);
    }

    /* ───────────────────────────────────────────────────────────────────────
     * Bug-fix — Coach direct messages. The app expects DmConversations/DmThread
     * shapes (data.conversations[].peer{}, data.messages[].{sender_id,from_me,…});
     * the old MobileExtras handlers returned a different shape, so the app
     * couldn't parse them. These return the exact shapes the app models declare.
     * direct_messages(sender_id, recipient_id, low_user_id, high_user_id, body, read_at).
     * ─────────────────────────────────────────────────────────────────────── */

    /** GET /api/instructor/messages — conversation list (latest per peer). */
    public function instructorMessages(Request $request): JsonResponse
    {
        $me = (int) auth()->id();
        $latest = \Illuminate\Support\Facades\DB::table('direct_messages')
            ->select('low_user_id', 'high_user_id', \Illuminate\Support\Facades\DB::raw('MAX(id) as last_id'))
            ->where(fn ($q) => $q->where('sender_id', $me)->orWhere('recipient_id', $me))
            ->groupBy('low_user_id', 'high_user_id');

        $rows = \Illuminate\Support\Facades\DB::table('direct_messages as dm')
            ->joinSub($latest, 'l', fn ($j) => $j->on('dm.id', '=', 'l.last_id'))
            ->orderByDesc('dm.id')->get();

        $peerIds = $rows->map(fn ($r) => (int) $r->sender_id === $me ? (int) $r->recipient_id : (int) $r->sender_id)->unique();
        $users   = User::whereIn('id', $peerIds)->get(['id', 'name', 'email', 'image'])->keyBy('id');
        $unread  = \Illuminate\Support\Facades\DB::table('direct_messages')
            ->where('recipient_id', $me)->whereNull('read_at')
            ->select('sender_id', \Illuminate\Support\Facades\DB::raw('COUNT(*) as c'))
            ->groupBy('sender_id')->pluck('c', 'sender_id');

        $conversations = $rows->map(function ($r) use ($me, $users, $unread) {
            $peerId = (int) $r->sender_id === $me ? (int) $r->recipient_id : (int) $r->sender_id;
            $peer   = $users[$peerId] ?? null;
            return [
                'peer'         => ['id' => $peerId, 'name' => (string) ($peer->name ?? ''), 'email' => (string) ($peer->email ?? ''), 'image' => $peer->image ?? null],
                'last_message' => (string) $r->body,
                'last_from_me' => (int) $r->sender_id === $me,
                'last_at'      => \Carbon\Carbon::parse($r->created_at)->toIso8601String(),
                'unread_count' => (int) ($unread[$peerId] ?? 0),
            ];
        })->values();

        return response()->json(['status' => 'success', 'data' => ['conversations' => $conversations]], 200);
    }

    /** GET /api/instructor/messages/{peerId} — thread with one peer (marks read). */
    public function instructorMessageThread(Request $request, $peerId): JsonResponse
    {
        $me     = (int) auth()->id();
        $peerId = (int) $peerId;
        if ($peerId === $me) return response()->json(['status' => 'error', 'message' => 'Invalid peer.'], 422);
        $peer = User::find($peerId, ['id', 'name', 'email', 'image']);
        if (! $peer) return response()->json(['status' => 'error', 'message' => 'User not found.'], 404);

        $low = min($me, $peerId); $high = max($me, $peerId);
        $msgs = \Illuminate\Support\Facades\DB::table('direct_messages')
            ->where('low_user_id', $low)->where('high_user_id', $high)
            ->orderBy('id')->get();

        \Illuminate\Support\Facades\DB::table('direct_messages')
            ->where('sender_id', $peerId)->where('recipient_id', $me)
            ->whereNull('read_at')->update(['read_at' => now()]);

        $messages = $msgs->map(fn ($m) => [
            'id'         => (int) $m->id,
            'sender_id'  => (int) $m->sender_id,
            'from_me'    => (int) $m->sender_id === $me,
            'body'       => (string) $m->body,
            'read_at'    => $m->read_at ? \Carbon\Carbon::parse($m->read_at)->toIso8601String() : null,
            'created_at' => \Carbon\Carbon::parse($m->created_at)->toIso8601String(),
        ])->values();

        return response()->json(['status' => 'success', 'data' => [
            'peer'     => ['id' => (int) $peer->id, 'name' => (string) ($peer->name ?? ''), 'email' => (string) ($peer->email ?? ''), 'image' => $peer->image ?? null],
            'messages' => $messages,
        ]], 200);
    }

    /** POST /api/instructor/messages/{peerId} — send a message to a peer. */
    public function instructorMessageSend(Request $request, $peerId): JsonResponse
    {
        $me     = (int) auth()->id();
        $peerId = (int) $peerId;
        $data   = $request->validate(['body' => 'required|string|max:2000']);
        if ($peerId === $me) return response()->json(['status' => 'error', 'message' => 'Invalid recipient.'], 422);
        if (! User::whereKey($peerId)->exists()) return response()->json(['status' => 'error', 'message' => 'Recipient not found.'], 404);

        $now = now();
        $id  = \Illuminate\Support\Facades\DB::table('direct_messages')->insertGetId([
            'sender_id'    => $me,
            'recipient_id' => $peerId,
            'low_user_id'  => min($me, $peerId),
            'high_user_id' => max($me, $peerId),
            'body'         => $data['body'],
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);

        return response()->json(['status' => 'success', 'data' => [
            'id'         => (int) $id,
            'sender_id'  => $me,
            'from_me'    => true,
            'body'       => $data['body'],
            'read_at'    => null,
            'created_at' => $now->toIso8601String(),
        ]], 201);
    }

    /**
     * POST /api/instructor/withdraw-requests — submit a payout withdrawal.
     * Bug-fix: only GET was routed, so submitting returned 405. Creates a
     * pending WithdrawRequest for the coach (validated against active methods).
     */
    public function instructorWithdrawStore(Request $request): JsonResponse
    {
        $coachId = $this->effectiveCoachId();
        $data = $request->validate([
            'method'       => 'required|string|max:255',
            'amount'       => 'required|numeric|min:1',
            'account_info' => 'required|string|max:5000',
        ]);

        $method = \Modules\PaymentWithdraw\app\Models\WithdrawMethod::where('status', 'active')
            ->where('name', $data['method'])->first();
        if (! $method) {
            return response()->json(['status' => 'error', 'message' => 'Invalid or inactive withdraw method.'], 422);
        }
        if ($data['amount'] < (float) $method->min_amount
            || ((float) $method->max_amount > 0 && $data['amount'] > (float) $method->max_amount)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Amount must be between ' . (float) $method->min_amount . ' and ' . (float) $method->max_amount . '.',
            ], 422);
        }

        $w = new \Modules\PaymentWithdraw\app\Models\WithdrawRequest();
        $w->user_id         = $coachId;
        $w->method          = $method->name;
        $w->withdraw_amount = (float) $data['amount'];
        $w->current_amount  = (float) $data['amount'];
        $w->account_info    = $data['account_info'];
        $w->status          = 'pending';
        $w->save();

        return response()->json([
            'status'  => 'success',
            'message' => 'Withdrawal request submitted.',
            'data'    => [
                'id'              => (int) $w->id,
                'method'          => $w->method,
                'withdraw_amount' => (float) $w->withdraw_amount,
                'account_info'    => $w->account_info,
                'status'          => $w->status,
                'created_at'      => $w->created_at?->format('Y-m-d H:i:s'),
            ],
        ], 201);
    }

    /* ─── 404-fix Phase L — course-review moderation actions (coach-scoped) ─── */

    /** PUT /api/instructor/course-reviews/{id}/status — approve (1) / unapprove (0). */
    public function instructorCourseReviewSetStatus(Request $request, $id): JsonResponse
    {
        $coachId = $this->effectiveCoachId();
        $data = $request->validate(['status' => 'required|integer|in:0,1']);
        $courseIds = Course::where('instructor_id', $coachId)->pluck('id');
        $review = CourseReview::whereIn('course_id', $courseIds)->find($id);
        if (! $review) return response()->json(['status' => 'error', 'message' => 'Review not found.'], 404);
        $review->status = (int) $data['status'];
        $review->save();
        return response()->json(['status' => 'success', 'message' => 'Review status updated.', 'data' => ['id' => (int) $review->id, 'status' => (int) $review->status]], 200);
    }

    /** DELETE /api/instructor/course-reviews/{id} — remove a review on the coach's course. */
    public function instructorCourseReviewDelete($id): JsonResponse
    {
        $coachId = $this->effectiveCoachId();
        $courseIds = Course::where('instructor_id', $coachId)->pluck('id');
        $review = CourseReview::whereIn('course_id', $courseIds)->find($id);
        if (! $review) return response()->json(['status' => 'error', 'message' => 'Review not found.'], 404);
        $review->delete();
        return response()->json(['status' => 'success', 'message' => 'Review deleted.'], 200);
    }

    /* ─── 404-fix Phase J — account session revoke (Sanctum tokens) ─── */

    /** DELETE /api/instructor/account/sessions/{tokenId} — revoke one token. */
    public function instructorRevokeSession(Request $request, $tokenId): JsonResponse
    {
        $user    = auth()->user();
        $current = $request->user()->currentAccessToken();
        if ($current && (int) $current->id === (int) $tokenId) {
            return response()->json(['status' => 'error', 'message' => 'Use sign out to revoke the current session.'], 400);
        }
        $deleted = $user->tokens()->where('id', $tokenId)->delete();
        if (! $deleted) return response()->json(['status' => 'error', 'message' => 'Session not found.'], 404);
        return response()->json(['status' => 'success', 'message' => 'Session revoked.'], 200);
    }

    /** POST /api/instructor/account/sessions/revoke-others — revoke all but current. */
    public function instructorRevokeOtherSessions(Request $request): JsonResponse
    {
        $user    = auth()->user();
        $current = $request->user()->currentAccessToken();
        // Safety: never run the bulk delete if we can't identify the current
        // token — otherwise `id != 0` would nuke EVERY token (including this
        // request's), logging the user out everywhere.
        if (! $current) {
            return response()->json(['status' => 'error', 'message' => 'Could not identify the current session.'], 400);
        }
        $deleted = $user->tokens()->where('id', '!=', (int) $current->id)->delete();
        return response()->json([
            'status' => 'success', 'message' => 'Other sessions revoked.',
            'data'   => ['revoked_count' => (int) $deleted],
        ], 200);
    }

    /* ─── 404-fix Phase I — lesson edit/delete (coach-scoped) ─── */

    /** PUT /api/instructor/lessons/{lesson_id} — update a lesson on the coach's course. */
    public function updateInstructorLesson(Request $request, $lessonId): JsonResponse
    {
        $coachId = $this->effectiveCoachId();
        $courseIds = Course::where('instructor_id', $coachId)->pluck('id');
        $lesson = CourseChapterLesson::whereIn('course_id', $courseIds)->find($lessonId);
        if (! $lesson) return response()->json(['status' => 'error', 'message' => 'Lesson not found.'], 404);

        $data = $request->validate([
            'title'       => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'file_type'   => 'nullable|string|max:50',
            'duration'    => 'nullable|integer|min:0',
            'is_free'     => 'nullable|boolean',
            'file_path'   => 'nullable|string|max:2000',
        ]);
        foreach (['title', 'description', 'file_type', 'file_path'] as $f) {
            if ($request->exists($f) && $data[$f] !== null) $lesson->$f = $data[$f];
        }
        if (! empty($data['duration']))    $lesson->duration = (int) $data['duration'];
        if ($request->exists('is_free'))   $lesson->is_free  = (bool) $data['is_free'];
        $lesson->save();

        return response()->json(['status' => 'success', 'message' => 'Lesson updated.', 'data' => [
            'id'          => (int) $lesson->id,
            'title'       => $lesson->title,
            'description' => $lesson->description,
            'file_type'   => $lesson->file_type,
            'duration'    => (int) ($lesson->duration ?? 0),
            'is_free'     => (bool) $lesson->is_free,
            'file_path'   => $lesson->file_path,
        ]], 200);
    }

    /** DELETE /api/instructor/lessons/{lesson_id} — delete a lesson + its chapter-item. */
    public function deleteInstructorLesson($lessonId): JsonResponse
    {
        $coachId = $this->effectiveCoachId();
        $courseIds = Course::where('instructor_id', $coachId)->pluck('id');
        $lesson = CourseChapterLesson::whereIn('course_id', $courseIds)->find($lessonId);
        if (! $lesson) return response()->json(['status' => 'error', 'message' => 'Lesson not found.'], 404);
        $chapterItemId = $lesson->chapter_item_id;
        $lesson->delete();
        if ($chapterItemId) \App\Models\CourseChapterItem::where('id', $chapterItemId)->delete();
        return response()->json(['status' => 'success', 'message' => 'Lesson deleted.'], 200);
    }

    /* ───────────────────────────────────────────────────────────────────────
     * 404-fix Phase G — Quizzes (list / edit / CRUD / questions / attempts /
     * analytics). Ownership: quiz.course_id ∈ the coach's courses. QuizResult
     * rows drive attempt/pass/fail counts. Quiz.time/attempt/pass_mark/total_mark
     * are string columns → cast to int for the app.
     * ─────────────────────────────────────────────────────────────────────── */

    private function ownedQuiz($id): ?\App\Models\Quiz
    {
        $courseIds = Course::where('instructor_id', $this->effectiveCoachId())->pluck('id');
        return \App\Models\Quiz::whereIn('course_id', $courseIds)->find($id);
    }

    /** GET /api/instructor/courses/{courseId}/quizzes */
    public function instructorCourseQuizzes(Request $request, $courseId): JsonResponse
    {
        $course = Course::where('instructor_id', $this->effectiveCoachId())->find($courseId);
        if (! $course) return response()->json(['status' => 'error', 'message' => 'Course not found.'], 404);

        $quizzes = \App\Models\Quiz::where('course_id', $course->id)->orderByDesc('id')->get();
        $results = \App\Models\QuizResult::whereIn('quiz_id', $quizzes->pluck('id')->all() ?: [0])->get(['quiz_id', 'status']);

        $rows = $quizzes->map(function ($q) use ($results) {
            $r = $results->where('quiz_id', $q->id);
            return [
                'id'             => (int) $q->id,
                'title'          => $q->title,
                'total_mark'     => (int) $q->total_mark,
                'pass_mark'      => (int) $q->pass_mark,
                'time_minutes'   => (int) $q->time,
                'attempts_limit' => (int) $q->attempt,
                'status'         => (string) $q->status,
                'attempt_count'  => $r->count(),
                'pass_count'     => $r->where('status', 'pass')->count(),
                'fail_count'     => $r->where('status', 'failed')->count(),
            ];
        })->values();

        return response()->json(['status' => 'success', 'data' => [
            'course'  => ['id' => (int) $course->id, 'title' => $course->title],
            'quizzes' => $rows,
        ]], 200);
    }

    /** GET /api/instructor/quizzes/{id}/edit — quiz + its questions/answers. */
    public function instructorQuizEdit(Request $request, $id): JsonResponse
    {
        $quiz = $this->ownedQuiz($id);
        if (! $quiz) return response()->json(['status' => 'error', 'message' => 'Quiz not found.'], 404);

        $questions = \App\Models\QuizQuestion::with('answers')->where('quiz_id', $quiz->id)->orderBy('id')->get()
            ->map(fn ($q) => [
                'id'      => (int) $q->id,
                'title'   => (string) $q->title,
                'type'    => (string) $q->type,
                'grade'   => $q->grade !== null ? (int) $q->grade : null,
                'answers' => $q->answers->map(fn ($a) => [
                    'id'      => (int) $a->id,
                    'title'   => (string) $a->title,
                    'correct' => (int) $a->correct === 1,
                ])->values(),
            ])->values();

        return response()->json(['status' => 'success', 'data' => [
            'id'         => (int) $quiz->id,
            'title'      => (string) $quiz->title,
            'time'       => (int) $quiz->time,
            'attempt'    => (int) $quiz->attempt,
            'pass_mark'  => (int) $quiz->pass_mark,
            'total_mark' => (int) $quiz->total_mark,
            'status'     => (string) $quiz->status,
            'questions'  => $questions,
        ]], 200);
    }

    /** POST /api/instructor/chapters/{chapter_id}/quiz — create a quiz in a chapter. */
    public function createInstructorQuiz(Request $request, $chapterId): JsonResponse
    {
        $coachId = $this->effectiveCoachId();
        $chapter = CourseChapter::find($chapterId);
        if (! $chapter) return response()->json(['status' => 'error', 'message' => 'Chapter not found.'], 404);
        if (! Course::where('id', $chapter->course_id)->where('instructor_id', $coachId)->exists()) {
            return response()->json(['status' => 'error', 'message' => 'You do not own this chapter.'], 403);
        }
        $data = $request->validate([
            'title'      => 'required|string|max:255',
            'time'       => 'nullable|integer|min:0',
            'attempt'    => 'nullable|integer|min:0',
            'pass_mark'  => 'nullable|integer|min:0',
            'total_mark' => 'nullable|integer|min:0',
            'status'     => 'nullable|in:active,inactive',
        ]);

        $item = \App\Models\CourseChapterItem::create([
            'instructor_id' => auth()->id(),
            'chapter_id'    => $chapter->id,
            'type'          => 'quiz',
            'order'         => \App\Models\CourseChapterItem::whereChapterId($chapter->id)->count() + 1,
        ]);
        $quiz = new \App\Models\Quiz();
        $quiz->chapter_item_id = $item->id;
        $quiz->instructor_id   = auth()->id();
        $quiz->chapter_id      = $chapter->id;
        $quiz->course_id       = $chapter->course_id;
        $quiz->title           = $data['title'];
        $quiz->time            = $data['time'] ?? null;
        $quiz->attempt         = $data['attempt'] ?? null;
        $quiz->pass_mark       = $data['pass_mark'] ?? null;
        $quiz->total_mark      = $data['total_mark'] ?? null;
        $quiz->status          = $data['status'] ?? 'active';
        $quiz->save();

        return response()->json(['status' => 'success', 'message' => 'Quiz created.', 'data' => ['id' => (int) $quiz->id, 'title' => $quiz->title]], 201);
    }

    /** PUT /api/instructor/quizzes/{id} — update quiz meta. */
    public function updateInstructorQuiz(Request $request, $id): JsonResponse
    {
        $quiz = $this->ownedQuiz($id);
        if (! $quiz) return response()->json(['status' => 'error', 'message' => 'Quiz not found.'], 404);
        $data = $request->validate([
            'title'      => 'nullable|string|max:255',
            'time'       => 'nullable|integer|min:0',
            'attempt'    => 'nullable|integer|min:0',
            'pass_mark'  => 'nullable|integer|min:0',
            'total_mark' => 'nullable|integer|min:0',
            'status'     => 'nullable|in:active,inactive',
        ]);
        foreach (['title', 'time', 'attempt', 'pass_mark', 'total_mark', 'status'] as $f) {
            if ($request->exists($f) && $data[$f] !== null) $quiz->$f = $data[$f];
        }
        $quiz->save();
        return response()->json(['status' => 'success', 'message' => 'Quiz updated.', 'data' => ['id' => (int) $quiz->id, 'title' => $quiz->title]], 200);
    }

    /** DELETE /api/instructor/quizzes/{id} — delete quiz (+ questions/answers/item). */
    public function deleteInstructorQuiz($id): JsonResponse
    {
        $quiz = $this->ownedQuiz($id);
        if (! $quiz) return response()->json(['status' => 'error', 'message' => 'Quiz not found.'], 404);
        $qIds = \App\Models\QuizQuestion::where('quiz_id', $quiz->id)->pluck('id');
        \App\Models\QuizQuestionAnswer::whereIn('question_id', $qIds)->delete();
        \App\Models\QuizQuestion::where('quiz_id', $quiz->id)->delete();
        if ($quiz->chapter_item_id) \App\Models\CourseChapterItem::where('id', $quiz->chapter_item_id)->delete();
        $quiz->delete();
        return response()->json(['status' => 'success', 'message' => 'Quiz deleted.'], 200);
    }

    /** POST /api/instructor/quizzes/{id}/questions — add a question (+ answers). */
    public function addInstructorQuizQuestion(Request $request, $id): JsonResponse
    {
        $quiz = $this->ownedQuiz($id);
        if (! $quiz) return response()->json(['status' => 'error', 'message' => 'Quiz not found.'], 404);
        $data = $request->validate([
            'title'            => 'required|string|max:2000',
            'grade'            => 'nullable|integer|min:0',
            'type'             => 'nullable|in:descriptive,multiple',
            'answers'          => 'required|array|min:1',
            'answers.*.title'  => 'required|string|max:1000',
            'answers.*.correct'=> 'required|boolean',
        ]);
        $question = new \App\Models\QuizQuestion();
        $question->quiz_id = $quiz->id;
        $question->title   = $data['title'];
        $question->type    = $data['type'] ?? 'multiple';
        $question->grade   = $data['grade'] ?? null;
        $question->save();
        foreach ($data['answers'] as $ans) {
            $a = new \App\Models\QuizQuestionAnswer();
            $a->question_id = $question->id;
            $a->title       = $ans['title'];
            $a->correct     = ! empty($ans['correct']) ? 1 : 0;
            $a->save();
        }
        return response()->json(['status' => 'success', 'message' => 'Question added.', 'data' => ['id' => (int) $question->id, 'title' => $question->title]], 201);
    }

    /** PUT /api/instructor/questions/{id} — edit a quiz question (+ replace answers). */
    public function updateInstructorQuizQuestion(Request $request, $id): JsonResponse
    {
        $question = \App\Models\QuizQuestion::find($id);
        if (! $question || ! $this->ownedQuiz($question->quiz_id)) {
            return response()->json(['status' => 'error', 'message' => 'Question not found.'], 404);
        }
        $data = $request->validate([
            'title'            => 'required|string|max:2000',
            'grade'            => 'nullable|integer|min:0',
            'type'             => 'nullable|in:descriptive,multiple',
            'answers'          => 'required|array|min:1',
            'answers.*.title'  => 'required|string|max:1000',
            'answers.*.correct'=> 'required|boolean',
        ]);
        $question->title = $data['title'];
        $question->type  = $data['type'] ?? $question->type;
        $question->grade = $data['grade'] ?? $question->grade;
        $question->save();
        \App\Models\QuizQuestionAnswer::where('question_id', $question->id)->delete();
        foreach ($data['answers'] as $ans) {
            $a = new \App\Models\QuizQuestionAnswer();
            $a->question_id = $question->id;
            $a->title       = $ans['title'];
            $a->correct     = ! empty($ans['correct']) ? 1 : 0;
            $a->save();
        }
        return response()->json(['status' => 'success', 'message' => 'Question updated.', 'data' => ['id' => (int) $question->id, 'title' => $question->title]], 200);
    }

    /** DELETE /api/instructor/questions/{id} — delete a quiz question (+ answers). */
    public function deleteInstructorQuizQuestion($id): JsonResponse
    {
        $question = \App\Models\QuizQuestion::find($id);
        if (! $question || ! $this->ownedQuiz($question->quiz_id)) {
            return response()->json(['status' => 'error', 'message' => 'Question not found.'], 404);
        }
        \App\Models\QuizQuestionAnswer::where('question_id', $question->id)->delete();
        $question->delete();
        return response()->json(['status' => 'success', 'message' => 'Question deleted.'], 200);
    }

    /** GET /api/instructor/quizzes/{id}/attempts — student attempts, paginated. */
    public function instructorQuizAttempts(Request $request, $id): JsonResponse
    {
        $quiz = $this->ownedQuiz($id);
        if (! $quiz) return response()->json(['status' => 'error', 'message' => 'Quiz not found.'], 404);
        $limit = $request->filled('limit') && is_numeric($request->limit) ? (int) $request->limit : 15;

        $rows = \App\Models\QuizResult::with('user:id,name,email,image')
            ->where('quiz_id', $quiz->id)->orderByDesc('id')->paginate($limit);

        $courseTitle = Course::where('id', $quiz->course_id)->value('title');
        $attempts = $rows->getCollection()->map(fn ($r) => [
            'id'         => (int) $r->id,
            'user_id'    => (int) $r->user_id,
            'user_name'  => $r->user?->name,
            'user_email' => $r->user?->email,
            'user_image' => $r->user?->image ?: null,
            'user_grade' => $r->user_grade !== null ? (int) $r->user_grade : null,
            'status'     => (string) ($r->status ?? ''),
            'created_at' => $r->created_at?->format('Y-m-d H:i:s'),
        ])->values();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'quiz'     => ['id' => (int) $quiz->id, 'title' => $quiz->title, 'course_title' => $courseTitle],
                'attempts' => $attempts,
            ],
            'pagination' => [
                'current_page' => $rows->currentPage(),
                'per_page'     => $rows->perPage(),
                'total'        => $rows->total(),
                'last_page'    => $rows->lastPage(),
            ],
        ], 200);
    }

    /** GET /api/instructor/quiz-attempts/{attemptId} — one attempt (header + student + quiz). */
    public function instructorQuizAttemptDetail(Request $request, $attemptId): JsonResponse
    {
        $attempt = \App\Models\QuizResult::with('user:id,name,email,image')->find($attemptId);
        if (! $attempt) return response()->json(['status' => 'error', 'message' => 'Attempt not found.'], 404);
        $quiz = $this->ownedQuiz($attempt->quiz_id);
        if (! $quiz) return response()->json(['status' => 'error', 'message' => 'Attempt not found.'], 404);

        // Best-effort answer breakdown from the stored result JSON (shape varies).
        $resultArr = is_array($attempt->result) ? $attempt->result : (json_decode((string) $attempt->result, true) ?: []);
        $answers = collect($resultArr)
            ->map(fn ($a) => [
                'question'       => (string) ($a['question'] ?? $a['title'] ?? ''),
                'answer'         => (string) ($a['answer'] ?? $a['selected'] ?? ''),
                'correct_answer' => (string) ($a['correct_answer'] ?? ''),
                'correct'        => (bool) ($a['correct'] ?? false),
            ])->values();

        return response()->json(['status' => 'success', 'data' => [
            'attempt' => ['id' => (int) $attempt->id, 'user_grade' => $attempt->user_grade !== null ? (int) $attempt->user_grade : null, 'status' => (string) ($attempt->status ?? ''), 'created_at' => $attempt->created_at?->format('Y-m-d H:i:s')],
            'student' => ['id' => (int) ($attempt->user?->id ?? 0), 'name' => $attempt->user?->name, 'email' => $attempt->user?->email, 'image' => $attempt->user?->image ?: null],
            'quiz'    => ['id' => (int) $quiz->id, 'title' => $quiz->title, 'total_mark' => (int) $quiz->total_mark, 'pass_mark' => (int) $quiz->pass_mark],
            'answers' => $answers,
        ]], 200);
    }

    /** GET /api/instructor/quizzes/{id}/question-analytics — per-question correctness. */
    public function instructorQuizQuestionAnalytics(Request $request, $id): JsonResponse
    {
        $quiz = $this->ownedQuiz($id);
        if (! $quiz) return response()->json(['status' => 'error', 'message' => 'Quiz not found.'], 404);

        $results = \App\Models\QuizResult::where('quiz_id', $quiz->id)->get(['result']);
        $questions = \App\Models\QuizQuestion::where('quiz_id', $quiz->id)->orderBy('id')->get(['id', 'title']);

        // Tally correct/incorrect per question from each attempt's result JSON.
        $tally = [];
        foreach ($results as $res) {
            $resArr = is_array($res->result) ? $res->result : (json_decode((string) $res->result, true) ?: []);
            foreach ($resArr as $a) {
                $qid = $a['question_id'] ?? null;
                if ($qid === null) continue;
                $tally[$qid]['attempts'] = ($tally[$qid]['attempts'] ?? 0) + 1;
                if (! empty($a['correct'])) $tally[$qid]['correct'] = ($tally[$qid]['correct'] ?? 0) + 1;
            }
        }

        $data = $questions->map(function ($q) use ($tally) {
            $att = $tally[$q->id]['attempts'] ?? 0;
            $cor = $tally[$q->id]['correct'] ?? 0;
            return [
                'id'          => (int) $q->id,
                'title'       => (string) $q->title,
                'attempts'    => (int) $att,
                'correct'     => (int) $cor,
                'incorrect'   => (int) ($att - $cor),
                'correct_pct' => $att > 0 ? round($cor / $att * 100, 1) : 0.0,
            ];
        })->values();

        return response()->json(['status' => 'success', 'data' => [
            'quiz'      => ['id' => (int) $quiz->id, 'title' => $quiz->title, 'attempt_count' => $results->count()],
            'questions' => $data,
        ]], 200);
    }

    /* ───────────────────────────────────────────────────────────────────────
     * 404-fix Phase H (part 1) — course management: status, enrollments,
     * enrollment access, chapter/lesson reorder. All coach-scoped.
     * ─────────────────────────────────────────────────────────────────────── */

    /** PATCH /api/instructor/courses/{courseId}/status */
    public function instructorSetCourseStatus(Request $request, $courseId): JsonResponse
    {
        $course = Course::where('instructor_id', $this->effectiveCoachId())->find($courseId);
        if (! $course) return response()->json(['status' => 'error', 'message' => 'Course not found.'], 404);
        $data = $request->validate(['status' => 'required|string|max:30']);
        $course->status = $data['status'];
        $course->save();
        return response()->json(['status' => 'success', 'message' => 'Course status updated.', 'data' => ['id' => (int) $course->id, 'status' => (string) $course->status]], 200);
    }

    /** GET /api/instructor/courses/{courseId}/enrollments — enrolled students + progress. */
    public function instructorCourseEnrollments(Request $request, $courseId): JsonResponse
    {
        $course = Course::where('instructor_id', $this->effectiveCoachId())->find($courseId);
        if (! $course) return response()->json(['status' => 'error', 'message' => 'Course not found.'], 404);
        $limit = $request->filled('limit') && is_numeric($request->limit) ? (int) $request->limit : 15;

        $totalLessons = CourseChapterLesson::where('course_id', $course->id)->count();
        $rows = Enrollment::with('user:id,name,email,image')->where('course_id', $course->id)->orderByDesc('id')->paginate($limit);

        $watched = \App\Models\CourseProgress::where('course_id', $course->id)->where('watched', 1)
            ->whereIn('user_id', $rows->pluck('user_id')->all() ?: [0])
            ->select('user_id', \Illuminate\Support\Facades\DB::raw('COUNT(*) as c'), \Illuminate\Support\Facades\DB::raw('MAX(updated_at) as last'))
            ->groupBy('user_id')->get()->keyBy('user_id');

        $enrollments = $rows->getCollection()->map(function ($e) use ($watched, $totalLessons) {
            $w = (int) ($watched[$e->user_id]->c ?? 0);
            return [
                'id'            => (int) $e->id,
                'user_id'       => (int) $e->user_id,
                'order_id'      => $e->order_id ? (int) $e->order_id : null,
                'has_access'    => (bool) $e->has_access,
                'enrolled_at'   => $e->created_at?->format('Y-m-d H:i:s'),
                'user_name'     => $e->user?->name,
                'user_email'    => $e->user?->email,
                'user_image'    => $e->user?->image ?: null,
                'watched_count' => $w,
                'progress_pct'  => $totalLessons > 0 ? round($w / $totalLessons * 100, 1) : 0.0,
                'last_active_at'=> isset($watched[$e->user_id]) ? (string) $watched[$e->user_id]->last : null,
            ];
        })->values();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'course'      => ['id' => (int) $course->id, 'title' => $course->title, 'total_lessons' => $totalLessons],
                'enrollments' => $enrollments,
            ],
            'pagination' => [
                'current_page' => $rows->currentPage(),
                'per_page'     => $rows->perPage(),
                'total'        => $rows->total(),
                'last_page'    => $rows->lastPage(),
            ],
        ], 200);
    }

    /** PATCH /api/instructor/enrollments/{enrollmentId} — grant/revoke access. */
    public function instructorEnrollmentSetAccess(Request $request, $enrollmentId): JsonResponse
    {
        $courseIds = Course::where('instructor_id', $this->effectiveCoachId())->pluck('id');
        $enrollment = Enrollment::whereIn('course_id', $courseIds)->find($enrollmentId);
        if (! $enrollment) return response()->json(['status' => 'error', 'message' => 'Enrollment not found.'], 404);
        $data = $request->validate(['has_access' => 'required|boolean']);
        $enrollment->has_access = $data['has_access'] ? 1 : 0;
        $enrollment->save();
        return response()->json(['status' => 'success', 'message' => 'Access updated.', 'data' => ['id' => (int) $enrollment->id, 'has_access' => (bool) $enrollment->has_access]], 200);
    }

    /** POST /api/instructor/courses/{course_id}/chapters/sort — reorder chapters. */
    public function instructorSortChapters(Request $request, $courseId): JsonResponse
    {
        $course = Course::where('instructor_id', $this->effectiveCoachId())->find($courseId);
        if (! $course) return response()->json(['status' => 'error', 'message' => 'Course not found.'], 404);
        $data = $request->validate(['chapter_ids' => 'required|array', 'chapter_ids.*' => 'integer']);
        foreach (array_values($data['chapter_ids']) as $i => $cid) {
            CourseChapter::where('id', $cid)->where('course_id', $course->id)->update(['order' => $i + 1]);
        }
        return response()->json(['status' => 'success', 'message' => 'Chapters reordered.'], 200);
    }

    /** POST /api/instructor/chapters/{chapter_id}/lessons/sort — reorder chapter items. */
    public function instructorSortLessons(Request $request, $chapterId): JsonResponse
    {
        $coachId = $this->effectiveCoachId();
        $chapter = CourseChapter::find($chapterId);
        if (! $chapter || ! Course::where('id', $chapter->course_id)->where('instructor_id', $coachId)->exists()) {
            return response()->json(['status' => 'error', 'message' => 'Chapter not found.'], 404);
        }
        $data = $request->validate(['chapter_item_ids' => 'required|array', 'chapter_item_ids.*' => 'integer']);
        foreach (array_values($data['chapter_item_ids']) as $i => $iid) {
            \App\Models\CourseChapterItem::where('id', $iid)->where('chapter_id', $chapter->id)->update(['order' => $i + 1]);
        }
        return response()->json(['status' => 'success', 'message' => 'Lessons reordered.'], 200);
    }

    /* ─── 404-fix Phase H (part 2) — course leaderboard + funnel (reads) ─── */

    /** GET /api/instructor/courses/{courseId}/leaderboard — top students by progress. */
    public function instructorCourseLeaderboard(Request $request, $courseId): JsonResponse
    {
        $course = Course::where('instructor_id', $this->effectiveCoachId())->find($courseId);
        if (! $course) return response()->json(['status' => 'error', 'message' => 'Course not found.'], 404);
        $limit = $request->filled('limit') && is_numeric($request->limit) ? (int) $request->limit : 20;
        $totalLessons = CourseChapterLesson::where('course_id', $course->id)->count();

        $watched = \App\Models\CourseProgress::where('course_id', $course->id)->where('watched', 1)
            ->select('user_id', \Illuminate\Support\Facades\DB::raw('COUNT(*) as c'))
            ->groupBy('user_id')->orderByDesc('c')->limit($limit)->get();
        $users = User::whereIn('id', $watched->pluck('user_id')->all() ?: [0])->get(['id', 'name', 'email', 'image'])->keyBy('id');

        $leaders = $watched->map(function ($w) use ($users, $totalLessons) {
            $u = $users[$w->user_id] ?? null;
            return [
                'user_id'       => (int) $w->user_id,
                'name'          => (string) ($u->name ?? ''),
                'email'         => (string) ($u->email ?? ''),
                'image'         => $u->image ?? null,
                'watched_count' => (int) $w->c,
                'progress_pct'  => $totalLessons > 0 ? round($w->c / $totalLessons * 100, 1) : 0.0,
            ];
        })->values();

        return response()->json(['status' => 'success', 'data' => [
            'course'  => ['id' => (int) $course->id, 'title' => $course->title, 'total_lessons' => $totalLessons],
            'leaders' => $leaders,
        ]], 200);
    }

    /** GET /api/instructor/courses/{courseId}/funnel — per-chapter reach/completion. */
    public function instructorCourseFunnel(Request $request, $courseId): JsonResponse
    {
        $course = Course::where('instructor_id', $this->effectiveCoachId())->find($courseId);
        if (! $course) return response()->json(['status' => 'error', 'message' => 'Course not found.'], 404);
        $enrolled = Enrollment::where('course_id', $course->id)->where('has_access', 1)->count();

        $chapters = CourseChapter::where('course_id', $course->id)->orderBy('order')->get(['id', 'title']);
        $data = $chapters->map(function ($ch) use ($course) {
            $lessonCount = CourseChapterLesson::where('chapter_id', $ch->id)->count();
            $reached = \App\Models\CourseProgress::where('course_id', $course->id)->where('chapter_id', $ch->id)
                ->distinct('user_id')->count('user_id');
            $completed = 0;
            if ($lessonCount > 0) {
                $completed = \App\Models\CourseProgress::where('course_id', $course->id)->where('chapter_id', $ch->id)
                    ->where('watched', 1)
                    ->select('user_id', \Illuminate\Support\Facades\DB::raw('COUNT(*) as c'))
                    ->groupBy('user_id')->having('c', '>=', $lessonCount)->get()->count();
            }
            return [
                'id'              => (int) $ch->id,
                'title'           => (string) $ch->title,
                'lesson_count'    => $lessonCount,
                'reached_count'   => (int) $reached,
                'completed_count' => (int) $completed,
            ];
        })->values();

        return response()->json(['status' => 'success', 'data' => [
            'course'   => ['id' => (int) $course->id, 'title' => $course->title],
            'enrolled' => $enrolled,
            'chapters' => $data,
        ]], 200);
    }

    /** GET /api/instructor/course/{id}/analytics — course analytics dashboard. */
    public function instructorCourseAnalytics(Request $request, $id): JsonResponse
    {
        $course = Course::where('instructor_id', $this->effectiveCoachId())->find($id);
        if (! $course) return response()->json(['status' => 'error', 'message' => 'Course not found.'], 404);

        $totalLessons = CourseChapterLesson::where('course_id', $course->id)->count();
        $enrollments  = Enrollment::where('course_id', $course->id)->count();

        $perUser = \App\Models\CourseProgress::where('course_id', $course->id)->where('watched', 1)
            ->select('user_id', \Illuminate\Support\Facades\DB::raw('COUNT(*) as c'))
            ->groupBy('user_id')->pluck('c', 'user_id');

        $completed = 0; $sumPct = 0.0; $buckets = [0 => 0, 25 => 0, 50 => 0, 75 => 0, 100 => 0];
        foreach ($perUser as $c) {
            $pct = $totalLessons > 0 ? $c / $totalLessons * 100 : 0;
            $sumPct += $pct;
            if ($pct >= 100) $completed++;
            $b = $pct >= 100 ? 100 : ($pct >= 75 ? 75 : ($pct >= 50 ? 50 : ($pct >= 25 ? 25 : 0)));
            $buckets[$b]++;
        }
        $avgProgress = $perUser->count() > 0 ? round($sumPct / $perUser->count(), 1) : 0.0;
        $progressBuckets = collect($buckets)->map(fn ($cnt, $b) => ['bucket' => (int) $b, 'count' => (int) $cnt])->values();

        $salesBase = fn () => OrderItem::where('order_items.course_id', $course->id)
            ->join('orders', 'orders.id', '=', 'order_items.order_id')->where('orders.payment_status', 'paid');
        $totalSalesCount  = $salesBase()->count();
        $totalSalesAmount = (float) $salesBase()->sum('order_items.price');

        $start = now()->startOfMonth()->subMonths(11);
        $mrows = $salesBase()->where('orders.created_at', '>=', $start)
            ->selectRaw("DATE_FORMAT(orders.created_at,'%Y-%m') as ym, SUM(order_items.price) as total, COUNT(*) as c")
            ->groupBy('ym')->get()->keyBy('ym');
        $monthly = [];
        for ($i = 0; $i < 12; $i++) {
            $m = $start->copy()->addMonths($i); $ym = $m->format('Y-m');
            $monthly[] = ['month' => $ym, 'label' => $m->format('M Y'), 'total' => (float) ($mrows[$ym]->total ?? 0), 'count' => (int) ($mrows[$ym]->c ?? 0)];
        }

        $recent = OrderItem::with('order.user')->where('course_id', $course->id)
            ->whereHas('order', fn ($q) => $q->where('payment_status', 'paid'))
            ->orderByDesc('id')->limit(5)->get()
            ->map(fn ($it) => ['id' => (int) $it->id, 'price' => (float) $it->price, 'buyer_name' => $it->order?->user?->name, 'buyer_image' => $it->order?->user?->image ?: null, 'created_at' => $it->created_at?->format('Y-m-d H:i:s')])->values();

        $isApproved = ($course->is_approved === 'approved' || (int) $course->is_approved === 1) ? 1 : 0;

        return response()->json(['status' => 'success', 'data' => [
            'course'  => ['id' => (int) $course->id, 'title' => $course->title, 'slug' => $course->slug, 'status' => (string) ($course->status ?? ''), 'is_approved' => $isApproved],
            'summary' => ['enrollments' => $enrollments, 'completed' => $completed, 'avg_progress' => $avgProgress, 'total_sales_count' => $totalSalesCount, 'total_sales_amount' => $totalSalesAmount],
            'progress_buckets' => $progressBuckets,
            'monthly_sales'    => $monthly,
            'recent_sales'     => $recent,
        ]], 200);
    }

    /** GET /api/instructor/course/{id}/edit — course fields for the edit form. */
    public function instructorCourseEditData(Request $request, $id): JsonResponse
    {
        $course = Course::where('instructor_id', $this->effectiveCoachId())->find($id);
        if (! $course) return response()->json(['status' => 'error', 'message' => 'Course not found.'], 404);
        $isApproved = ($course->is_approved === 'approved' || (int) $course->is_approved === 1) ? 1 : 0;
        return response()->json(['status' => 'success', 'data' => [
            'id'                 => (int) $course->id,
            'title'              => (string) $course->title,
            'slug'               => (string) $course->slug,
            'thumbnail'          => $course->thumbnail,
            'price'              => $course->price !== null ? (float) $course->price : null,
            'discount'           => $course->discount !== null ? (float) $course->discount : null,
            'description'        => $course->description,
            'seo_description'    => $course->seo_description ?? null,
            'demo_video_storage' => $course->demo_video_storage ?? null,
            'demo_video_source'  => $course->demo_video_source ?? null,
            // Map DB enum → the app's edit-screen vocabulary ("published"/"is_draft"),
            // else the toggle can't recognize "active" and looks unsaved.
            'status'             => $this->courseStatusForApp($course->status),
            'is_approved'        => $isApproved,
        ]], 200);
    }

    /** DB course status → the app's edit-screen vocabulary (published / is_draft). */
    private function courseStatusForApp($status): string
    {
        return match ((string) $status) {
            'active'   => 'published',
            'is_draft' => 'is_draft',
            'inactive' => 'is_draft', // unpublished shows as Draft in the binary toggle
            default    => 'is_draft',
        };
    }

    /* ───────────────────────────────────────────────────────────────────────
     * 404-fix final batch — announcements feed, student profile, email-change
     * (OTP), broadcast, bulk-enroll, course duplicate.
     * ─────────────────────────────────────────────────────────────────────── */

    /** GET /api/announcements/feed — announcements from the user's enrolled courses. */
    public function announcementsFeed(Request $request): JsonResponse
    {
        $userId    = auth()->id();
        $courseIds = Enrollment::where('user_id', $userId)->pluck('course_id')->unique();
        $limit     = $request->filled('limit') && is_numeric($request->limit) ? (int) $request->limit : 20;

        $rows = \App\Models\Announcement::with(['course:id,slug,title,thumbnail,instructor_id', 'course.instructor:id,name,image'])
            ->whereIn('course_id', $courseIds->all() ?: [0])->orderByDesc('id')->limit($limit)->get();

        $data = $rows->map(fn ($a) => [
            'id'           => (int) $a->id,
            'title'        => $a->title,
            'announcement' => $a->announcement,
            'created_at'   => $a->created_at?->format('Y-m-d H:i:s'),
            'time_ago'     => $a->created_at?->diffForHumans(),
            'instructor'   => $a->course?->instructor ? ['id' => (int) $a->course->instructor->id, 'name' => $a->course->instructor->name, 'image' => $a->course->instructor->image ?: null] : null,
            'course'       => $a->course ? ['id' => (int) $a->course->id, 'slug' => (string) $a->course->slug, 'title' => $a->course->title, 'thumbnail' => $a->course->thumbnail] : null,
        ])->values();

        return response()->json(['status' => 'success', 'data' => $data], 200);
    }

    /** GET /api/instructor/students/{userId}/profile — a student's profile (coach-scoped). */
    public function instructorStudentProfile(Request $request, $userId): JsonResponse
    {
        $coachId   = $this->effectiveCoachId();
        $courseIds = Course::where('instructor_id', $coachId)->pluck('id');
        $student   = User::find($userId);
        if (! $student) return response()->json(['status' => 'error', 'message' => 'Student not found.'], 404);

        $enrolledInCoach = Enrollment::where('user_id', $userId)->whereIn('course_id', $courseIds)->exists();
        if (! $enrolledInCoach && (int) $student->coach_id !== (int) $coachId) {
            return response()->json(['status' => 'error', 'message' => 'Student not found.'], 404);
        }

        $enrollments = Enrollment::whereIn('course_id', $courseIds)->where('user_id', $userId)->get();
        $enrolledCourses = $enrollments->map(function ($e) use ($userId) {
            $course = Course::find($e->course_id, ['id', 'title', 'thumbnail']);
            $totalLessons = CourseChapterLesson::where('course_id', $e->course_id)->count();
            $watched = \App\Models\CourseProgress::where('course_id', $e->course_id)->where('user_id', $userId)->where('watched', 1)->count();
            return [
                'enrollment_id' => (int) $e->id,
                'course_id'     => (int) $e->course_id,
                'course_title'  => (string) ($course?->title ?? ''),
                'thumbnail'     => $course?->thumbnail,
                'has_access'    => (bool) $e->has_access,
                'enrolled_at'   => $e->created_at?->format('Y-m-d H:i:s'),
                'watched_count' => $watched,
                'total_lessons' => $totalLessons,
                'progress_pct'  => $totalLessons > 0 ? round($watched / $totalLessons * 100, 1) : 0.0,
                'last_active_at'=> null,
            ];
        })->values();

        $coachQuizIds = \App\Models\Quiz::whereIn('course_id', $courseIds)->pluck('id');
        $attempts = \App\Models\QuizResult::whereIn('quiz_id', $coachQuizIds->all() ?: [0])->where('user_id', $userId)->orderByDesc('id')->limit(10)->get();
        $quizTitles = \App\Models\Quiz::whereIn('id', $attempts->pluck('quiz_id')->all() ?: [0])->pluck('title', 'id');
        $recentAttempts = $attempts->map(fn ($r) => [
            'id'         => (int) $r->id,
            'quiz_id'    => (int) $r->quiz_id,
            'quiz_title' => (string) ($quizTitles[$r->quiz_id] ?? ''),
            'user_grade' => $r->user_grade !== null ? (int) $r->user_grade : null,
            'status'     => (string) ($r->status ?? ''),
            'created_at' => $r->created_at?->format('Y-m-d H:i:s'),
        ])->values();

        $totalSpend = (float) OrderItem::whereIn('course_id', $courseIds)
            ->whereHas('order', fn ($q) => $q->where('buyer_id', $userId)->where('payment_status', 'paid'))->sum('price');

        return response()->json(['status' => 'success', 'data' => [
            'student' => ['id' => (int) $student->id, 'name' => (string) $student->name, 'email' => (string) $student->email, 'phone' => $student->phone, 'image' => $student->image ?: null, 'joined_at' => $student->created_at?->format('Y-m-d H:i:s')],
            'totals'  => ['enrolled_count' => $enrollments->count(), 'completed_count' => $enrolledCourses->where('progress_pct', 100)->count(), 'attempt_count' => $attempts->count(), 'total_spend' => $totalSpend],
            'enrolled_courses' => $enrolledCourses,
            'recent_attempts'  => $recentAttempts,
        ]], 200);
    }

    /** POST /api/instructor/account/email-change/request — send OTP to the new email. */
    public function instructorEmailChangeRequest(Request $request): JsonResponse
    {
        $user = auth()->user();
        $data = $request->validate(['new_email' => 'required|email|unique:users,email', 'password' => 'required|string']);
        if (! \Illuminate\Support\Facades\Hash::check($data['password'], $user->password)) {
            return response()->json(['status' => 'error', 'message' => 'Password is incorrect.'], 422);
        }
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        \Illuminate\Support\Facades\Cache::put('email_change:' . $user->id, ['otp' => $otp, 'new_email' => $data['new_email']], now()->addMinutes(15));
        try {
            \Illuminate\Support\Facades\Mail::raw("Your email-change verification code is: {$otp}\nIt is valid for 15 minutes.", function ($m) use ($data) {
                $m->to($data['new_email'])->subject('Confirm your new email');
            });
        } catch (\Throwable $e) {
            \Log::warning('email-change-otp-mail-failed', ['user' => $user->id, 'err' => $e->getMessage()]);
        }
        return response()->json(['status' => 'success', 'message' => 'A verification code was sent to the new email.', 'data' => ['expires_in_seconds' => 900]], 200);
    }

    /** POST /api/instructor/account/email-change/confirm — verify OTP + change email. */
    public function instructorEmailChangeConfirm(Request $request): JsonResponse
    {
        $user = auth()->user();
        $data = $request->validate(['otp' => 'required|string']);
        $pending = \Illuminate\Support\Facades\Cache::get('email_change:' . $user->id);
        if (! $pending) return response()->json(['status' => 'error', 'message' => 'No pending email change, or it expired.'], 422);
        if (! hash_equals((string) $pending['otp'], (string) $data['otp'])) {
            return response()->json(['status' => 'error', 'message' => 'Invalid verification code.'], 422);
        }
        if (User::where('email', $pending['new_email'])->where('id', '!=', $user->id)->exists()) {
            return response()->json(['status' => 'error', 'message' => 'That email is already in use.'], 422);
        }
        $user->email = $pending['new_email'];
        $user->save();
        \Illuminate\Support\Facades\Cache::forget('email_change:' . $user->id);
        return response()->json(['status' => 'success', 'message' => 'Email updated.'], 200);
    }

    /** POST /api/instructor/courses/{courseId}/broadcast — post an announcement to a course. */
    public function instructorCourseBroadcast(Request $request, $courseId): JsonResponse
    {
        $course = Course::where('instructor_id', $this->effectiveCoachId())->find($courseId);
        if (! $course) return response()->json(['status' => 'error', 'message' => 'Course not found.'], 404);
        $data = $request->validate([
            'subject'           => 'required|string|max:255',
            'message'           => 'required|string',
            'send_email'        => 'boolean',
            'send_announcement' => 'boolean',
        ]);

        $announcementId = null;
        if ($request->boolean('send_announcement', true)) {
            $a = new \App\Models\Announcement();
            $a->course_id     = $course->id;
            $a->instructor_id = auth()->id();
            $a->title         = $data['subject'];
            $a->announcement  = $data['message'];
            $a->save();
            $announcementId = (int) $a->id;
        }
        // Email delivery to enrolled students flows through the platform's existing
        // announcement notification pipeline; a raw bulk-mail loop is intentionally
        // NOT run here to avoid uncontrolled sending. send_email is accepted for
        // forward-compat.
        return response()->json(['status' => 'success', 'message' => 'Broadcast posted to the course.', 'data' => ['announcement_id' => $announcementId]], 201);
    }

    /** POST /api/instructor/courses/{courseId}/bulk-enroll — enroll students by email. */
    public function instructorBulkEnroll(Request $request, $courseId): JsonResponse
    {
        $coachId = $this->effectiveCoachId();
        $course  = Course::where('instructor_id', $coachId)->find($courseId);
        if (! $course) return response()->json(['status' => 'error', 'message' => 'Course not found.'], 404);
        $data = $request->validate([
            'emails'      => 'required|array|min:1|max:200',
            'emails.*'    => 'email',
            'name_prefix' => 'nullable|string|max:100',
        ]);

        $enrolled = 0; $created = 0; $skipped = 0;
        foreach (array_unique($data['emails']) as $email) {
            $user = User::where('email', $email)->first();
            if (! $user) {
                $user = new User();
                $user->name     = trim(($data['name_prefix'] ?? 'Student') . ' ' . \Illuminate\Support\Str::before($email, '@'));
                $user->email    = $email;
                $user->password = \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(14));
                $user->role     = 'student';
                $user->status   = 'active';
                $user->coach_id = $coachId;
                $user->added_by = $coachId;
                $user->email_verified_at = now();
                $user->save();
                $created++;
            }
            if (Enrollment::where('course_id', $course->id)->where('user_id', $user->id)->exists()) { $skipped++; continue; }
            $e = new Enrollment();
            $e->course_id  = $course->id;
            $e->user_id    = $user->id;
            $e->has_access = 1;
            $e->save();
            $enrolled++;
        }

        return response()->json([
            'status'  => 'success',
            'message' => "Enrolled {$enrolled}, created {$created} new, skipped {$skipped} already-enrolled.",
            'data'    => ['enrolled' => $enrolled, 'created' => $created, 'skipped' => $skipped],
        ], 200);
    }

    /** POST /api/instructor/courses/{courseId}/duplicate — clone a course (draft/inactive). */
    public function instructorCourseDuplicate(Request $request, $courseId): JsonResponse
    {
        $coachId = $this->effectiveCoachId();
        $course  = Course::where('instructor_id', $coachId)->find($courseId);
        if (! $course) return response()->json(['status' => 'error', 'message' => 'Course not found.'], 404);

        $new = null;
        \Illuminate\Support\Facades\DB::transaction(function () use ($course, &$new) {
            $new = $course->replicate();
            $new->title = $course->title . ' (Copy)';
            $new->slug  = $course->slug . '-copy-' . substr(md5(uniqid('', true)), 0, 6);
            $new->status = 'inactive';
            if (array_key_exists('is_approved', $new->getAttributes())) $new->is_approved = 0;
            $new->save();

            foreach (CourseChapter::where('course_id', $course->id)->orderBy('order')->get() as $ch) {
                $nch = $ch->replicate(); $nch->course_id = $new->id; $nch->save();
                foreach (\App\Models\CourseChapterItem::where('chapter_id', $ch->id)->orderBy('order')->get() as $it) {
                    $nit = $it->replicate(); $nit->chapter_id = $nch->id; $nit->save();
                    foreach (CourseChapterLesson::where('chapter_item_id', $it->id)->get() as $ls) {
                        $nls = $ls->replicate();
                        $nls->course_id       = $new->id;
                        $nls->chapter_id      = $nch->id;
                        $nls->chapter_item_id = $nit->id;
                        $nls->save();
                    }
                }
            }
        });

        return response()->json(['status' => 'success', 'message' => 'Course duplicated (saved as inactive).', 'data' => ['id' => (int) $new->id, 'title' => $new->title, 'slug' => $new->slug]], 201);
    }

    /* ───────────────────────────────────────────────────────────────────────
     * Coach notifications — the app expects the InboxEnvelope shape
     * { data:{ notifications:[...], unread_count }, pagination }, NOT the student
     * NotificationController's { items, unread_count, meta }. Note icon_color
     * (snake_case) — the app rejects the camelCase iconColor. Reads the same
     * $user->notifications() source.
     * ─────────────────────────────────────────────────────────────────────── */

    /** GET /api/instructor/notifications?filter=all|unread|read */
    public function instructorNotifications(Request $request): JsonResponse
    {
        $user   = auth()->user();
        $filter = $request->get('filter', 'all');
        $limit  = $request->filled('limit') && is_numeric($request->limit) ? (int) $request->limit : 25;

        $base = $user->notifications();
        if ($filter === 'unread')      $base->whereNull('read_at');
        elseif ($filter === 'read')    $base->whereNotNull('read_at');
        $page = $base->paginate($limit);

        $notifications = collect($page->items())->map(function ($n) {
            $d = is_array($n->data) ? $n->data : (json_decode((string) $n->data, true) ?: []);
            return [
                'id'         => (string) $n->id,
                'type'       => (string) ($n->type ?? ''),
                'title'      => (string) ($d['title'] ?? ''),
                'body'       => (string) ($d['body'] ?? ''),
                'url'        => $d['url'] ?? null,
                'icon'       => (string) ($d['icon'] ?? 'fa-bell'),
                'icon_color' => (string) ($d['iconColor'] ?? $d['icon_color'] ?? '#5751e1'),
                'event'      => (string) ($d['event'] ?? ''),
                'read_at'    => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at?->toIso8601String(),
            ];
        })->values();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'notifications' => $notifications,
                'unread_count'  => $user->unreadNotifications()->count(),
            ],
            'pagination' => [
                'current_page' => $page->currentPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'last_page'    => $page->lastPage(),
            ],
        ], 200);
    }

    /** POST /api/instructor/notifications/{id}/read */
    public function instructorMarkNotificationRead(Request $request, $id): JsonResponse
    {
        $user = auth()->user();
        $n = $user->notifications()->where('id', $id)->first();
        $marked = 0;
        if ($n && $n->read_at === null) { $n->markAsRead(); $marked = 1; }
        return response()->json(['status' => 'success', 'data' => ['unread_count' => $user->unreadNotifications()->count(), 'marked' => $marked]], 200);
    }

    /** POST /api/instructor/notifications/read-all */
    public function instructorMarkAllNotificationsRead(Request $request): JsonResponse
    {
        $user  = auth()->user();
        $count = $user->unreadNotifications()->count();
        $user->unreadNotifications->markAsRead();
        return response()->json(['status' => 'success', 'data' => ['unread_count' => 0, 'marked' => $count]], 200);
    }
}
