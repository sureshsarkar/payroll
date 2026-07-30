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
        $instructor_id = auth()->id();

        // 1️⃣ Find course (must belong to instructor)
        $course = Course::where('id', $course_id)
            ->where('instructor_id', $instructor_id)
            ->first();

        if (! $course) {
            return response()->json([
                'status' => 'error',
                'message' => 'Course not found or unauthorized',
            ], 404);
        }

        // 2️⃣ Validation
        // FT-VAL-9 (mirror createCourse). Same bounds — see that
        // method's docstring for the rationale.
        $validator = Validator::make($request->all(), [
            'title'              => 'nullable|string|max:255',
            'seo_description'    => 'nullable|string|max:255',
            'thumbnail'          => 'nullable|string|max:500',
            'demo_video_storage' => 'nullable|in:upload,youtube,vimeo,external_link,aws,wasabi',
            'demo_video_source'  => 'nullable|string|max:500',
            'price'              => 'nullable|numeric|min:0|max:9999999',
            'discount_price'     => 'nullable|numeric|min:0|max:9999999',
            'description'        => 'nullable|string|max:50000',
            'status'             => 'nullable|in:is_draft,published',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        // 3️⃣ Slug update (if title changed)
        if ($request->filled('title') && $request->title !== $course->title) {
            $slug = Str::slug($request->title);
            $slugExists = Course::where('slug', $slug)
                ->where('id', '!=', $course->id)
                ->exists();

            if ($slugExists) {
                $slug .= '-'.uniqid();
            }

            $course->slug = $slug;
            $course->title = $request->title;
        }

        // 4️⃣ Update remaining fields
        $course->update([
            'seo_description' => $request->seo_description ?? $course->seo_description,
            'thumbnail' => $request->thumbnail ?? $course->thumbnail,
            'demo_video_storage' => $request->demo_video_storage ?? $course->demo_video_storage,
            'demo_video_source' => $request->demo_video_source ?? $course->demo_video_source,
            'price' => $request->price ?? $course->price,
            'discount' => $request->discount_price ?? $course->discount,
            'description' => $request->description ?? $course->description,
            'status' => $request->status ?? $course->status,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Course updated successfully',
            'data' => [
                'course_id' => $course->id,
                'title' => $course->title,
                'slug' => $course->slug,
                'price' => (float) $course->price,
                'discount' => $course->discount ? (float) $course->discount : null,
                'status' => $course->status,
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
        $course = Course::where('id', $course_id)
            ->where('instructor_id', auth()->id())
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'message_for_reviewer' => 'nullable|string',
            'status' => 'required|in:Publish,UnPublish,Draft',
        ], [
            'status.required' => 'Please select course status.',
            'status.in' => 'Invalid status selected. Allowed: Publish, UnPublish, Draft.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $statusMap = [
            'Publish' => 'active',
            'UnPublish' => 'inactive',
            'Draft' => 'is_draft',
        ];

        $course->update([
            'message_for_reviewer' => $request->message_for_reviewer,
            'status' => $statusMap[$request->status],
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Course submitted successfully',
        ]);
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

        // The mobile app's "Sale details" model reads several required fields at
        // the `data` root (sale, course, buyer, …) — it reveals missing ones one
        // at a time via Gson. We expose all of them as siblings; the app uses what
        // it needs and ignores the rest. `sale` also keeps course/buyer nested.
        return response()->json([
            'status' => 'success',
            'data'   => [
                'sale'    => $salePayload,
                'course'  => $coursePayload,
                'buyer'   => $buyerPayload,
                'student' => $buyerPayload, // alias — the buyer IS the student on a coach sale
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
     * THIS instructor's payout/withdraw requests, paginated. Empty → [] + 200.
     */
    public function withdraw_requests(Request $request): JsonResponse
    {
        $instructorId = auth()->user()->id;
        $limit = $request->filled('limit') && is_numeric($request->limit) ? (int) $request->limit : 15;

        $query = WithdrawRequest::where('user_id', $instructorId)->orderByDesc('id');
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $rows = $query->paginate($limit);

        $data = $rows->getCollection()->map(function ($w) {
            return [
                'id'             => $w->id,
                'amount'         => (float) $w->withdraw_amount,
                'current_amount' => (float) ($w->current_amount ?? 0),
                'method'         => $w->method,
                'status'         => $w->status,
                // account_info is encrypted at rest; a legacy/corrupt row must not
                // 500 the whole list — fall back to null on a decrypt failure.
                'account_info'   => rescue(fn () => $w->account_info, null, false),
                'approved_date'  => $w->approved_date,
                'created_at'     => $w->created_at?->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json([
            'status'     => 'success',
            'data'       => $data,
            'pagination' => [
                'current_page' => $rows->currentPage(),
                'per_page'     => $rows->perPage(),
                'total'        => $rows->total(),
                'last_page'    => $rows->lastPage(),
                'links'        => [
                    'first' => $rows->url(1),
                    'prev'  => $rows->previousPageUrl(),
                    'next'  => $rows->nextPageUrl(),
                    'last'  => $rows->url($rows->lastPage()),
                ],
            ],
        ], 200);
    }
}
