<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\CourseListResource;
use App\Http\Resources\API\QnaReplyResource;
use App\Http\Resources\API\QnaResource;
use App\Models\Announcement;
use App\Models\Course;
use App\Models\CourseChapterItem;
use App\Models\CourseChapterLesson;
use App\Models\CourseProgress;
use App\Models\CourseReview;
use App\Models\LessonQuestion;
use App\Models\LessonReply;
use App\Models\QuizResult;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Modules\Order\app\Models\Enrollment;
use Modules\Order\app\Models\Order;
use Modules\Subscription\app\Models\SubscriptionHistory;
use Modules\Order\app\Models\OrderItem;
use Illuminate\Support\Str;
use App\Models\CourseChapter;
use Illuminate\Support\Facades\Auth;
use App\Models\UserEducation;
use App\Models\UserExperience;
use Modules\Location\app\Models\City;
use Modules\Location\app\Models\State;
use App\Http\Requests\Frontend\StudentProfileUpdateRequest;
use App\Http\Requests\Frontend\StudentBioUpdateRequest;
use App\Http\Requests\Frontend\StudentProfileAddressUpdateRequest;


// use App\Models\CourseChapterLesson;

class StudentApiDashboardController extends Controller
{


/**
 * Phase 1D of the Student mobile-app build (2026-05-13) — single-shot
 * dashboard payload that powers the mobile home screen. Aggregates the
 * "resume learning" card, today's + upcoming live classes, KPI counts,
 * recent activity, active membership, certificates-earned count, and
 * recommended-for-you courses in one round-trip.
 *
 * Cached per user for 60 seconds — matches the admin-dashboard cache
 * TTL elsewhere in the project. Pull-to-refresh on mobile bypasses the
 * cache via `?fresh=1`.
 *
 * Backward compatibility: the legacy thin payload (total_enrolled_courses,
 * total_quiz_attempts, total_reviews) is preserved inside `data` so any
 * pre-mobile web consumer still works.
 */
public function student_dashboard(\Illuminate\Http\Request $request): JsonResponse
{
    $user = auth()->user();
    $userId = $user->id;

    $cacheKey = "api.student.dashboard:{$userId}";
    if ($request->boolean('fresh')) {
        Cache::forget($cacheKey);
    }

    $data = Cache::remember($cacheKey, 60, function () use ($userId) {
        // ──────────────────────────────────────────────────────────────
        // Schema reality (verified 2026-05-13):
        //   enrollments        →  no percent_complete column; only access flags.
        //   course_chapter_items → polymorphic ordering rows. Has NO `title`.
        //                          Title lives on course_chapter_lessons,
        //                          joined via chapter_item_id.
        //   course_chapter_lessons → title, course_id, chapter_id, chapter_item_id, …
        //   quiz_results       →  user_grade (int 0..100), status ('pass'/'failed').
        //                          NO total_question / correct_answer columns.
        //   courses            →  category_id (not course_category_id),
        //                          discount (not discount_price),
        //                          NO total_enrollment column,
        //                          is_approved is enum('pending','approved','rejected'),
        //                          status is enum('active','is_draft','inactive').
        //   course_live_classes.start_time → varchar (not timestamp).
        //
        // Every section below is wrapped in try/catch so one bad row
        // doesn't 500 the whole dashboard — pre-fix, a single schema
        // mismatch made the entire screen unusable.
        // ──────────────────────────────────────────────────────────────

        // ── KPI counters
        $totalEnrolledCourses = Enrollment::where('user_id', $userId)->where('has_access', 1)->count();
        $totalQuizAttempts    = QuizResult::where('user_id', $userId)->count();
        $totalReviews         = CourseReview::where('user_id', $userId)->count();

        // ── Completed courses: where watched chapter-items >= total.
        //    Single query, no N+1. Pre-fix this used a non-existent
        //    enrollments.percent_complete column.
        $completedCourses = 0;
        try {
            $completedCourses = \DB::table('enrollments as e')
                ->where('e.user_id', $userId)
                ->where('e.has_access', 1)
                ->whereRaw(
                    '(SELECT COUNT(*) FROM course_chapter_items cci
                      INNER JOIN course_chapters cc ON cc.id = cci.chapter_id
                      WHERE cc.course_id = e.course_id) > 0'
                )
                ->whereRaw(
                    '(SELECT COUNT(*) FROM course_progress cp
                      WHERE cp.user_id = e.user_id
                        AND cp.course_id = e.course_id
                        AND cp.watched = 1)
                     >=
                     (SELECT COUNT(*) FROM course_chapter_items cci
                      INNER JOIN course_chapters cc ON cc.id = cci.chapter_id
                      WHERE cc.course_id = e.course_id)'
                )
                ->count();
        } catch (\Throwable $e) {
            \Log::warning('student_dashboard.completedCourses: '.$e->getMessage());
        }

        // ── Quiz average score (0..100). quiz_results.user_grade is the
        //    already-computed percentage per attempt; AVG of those gives
        //    the student's running average.
        $quizAvg = 0.0;
        try {
            $quizAvg = round((float) (QuizResult::where('user_id', $userId)
                ->avg('user_grade') ?? 0), 1);
        } catch (\Throwable $e) {
            \Log::warning('student_dashboard.quizAvg: '.$e->getMessage());
        }

        // ── Resume-learning card. course_progress.lesson_id is a
        //    course_chapter_items.id; lesson titles live on
        //    course_chapter_lessons keyed by chapter_item_id.
        $resume = null;
        try {
            $resumeRow = CourseProgress::where('user_id', $userId)
                ->where('current', 1)
                ->orderByDesc('id')
                ->first();
            if ($resumeRow) {
                $courseId    = $resumeRow->course_id;
                $course      = Course::withTrashed()->select('id', 'title', 'slug', 'thumbnail')->find($courseId);
                $chapterItemId = $resumeRow->lesson_id;

                $lessonTitle = \DB::table('course_chapter_lessons')
                    ->where('chapter_item_id', $chapterItemId)
                    ->value('title');

                $totalLessons = \DB::table('course_chapter_items')
                    ->join('course_chapters', 'course_chapters.id', '=', 'course_chapter_items.chapter_id')
                    ->where('course_chapters.course_id', $courseId)
                    ->count();
                $watchedLessons = CourseProgress::where('user_id', $userId)
                    ->where('course_id', $courseId)
                    ->where('watched', 1)
                    ->count();
                $progressPct = $totalLessons > 0
                    ? (int) round(($watchedLessons / $totalLessons) * 100)
                    : 0;

                if ($course) {
                    $resume = [
                        'course' => [
                            'id'        => $course->id,
                            'title'     => $course->title,
                            'slug'      => $course->slug,
                            'thumbnail' => $course->thumbnail ? asset($course->thumbnail) : null,
                        ],
                        'lesson_id'        => $chapterItemId,
                        'lesson_title'     => $lessonTitle,
                        'progress_pct'     => $progressPct,
                        'last_watched_at'  => optional($resumeRow->updated_at)->toIso8601String(),
                    ];
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('student_dashboard.resume: '.$e->getMessage());
        }

        // ── Enrolled (course, batch) pairs. course IDs feed Recommended
        //    below; the (course, batch) pairs batch-scope the live widget so a
        //    student never even SEES another batch's class on the home screen.
        $accessEnrollments = Enrollment::where('user_id', $userId)
            ->where('has_access', 1)
            ->get(['course_id', 'batch_id']);
        $enrolledCourseIds = $accessEnrollments->pluck('course_id')->unique()->values();

        // ── Upcoming live classes. clc.start_time is varchar — we still
        //    let MySQL coerce in the where clause; Carbon::parse handles
        //    the typical "Y-m-d H:i:s" format the rest of the app writes.
        //    Lesson title joins through course_chapter_lessons by
        //    chapter_item_id (same as resume-learning).
        $upcomingLive = collect();
        try {
            // 2026-06-05 — batch-scope the widget exactly like
            // student_live_classes(): pair-wise (course, batch). A legacy
            // null-batch enrollment still sees all of its course's classes;
            // a batched enrollment sees only course-wide + its own batch.
            $liveBatchScope = function ($outer) use ($accessEnrollments) {
                foreach ($accessEnrollments as $e) {
                    $outer->orWhere(function ($w) use ($e) {
                        $w->where('cc.course_id', $e->course_id);
                        if ($e->batch_id !== null) {
                            $w->where(function ($b) use ($e) {
                                $b->whereNull('clc.batch_id')
                                  ->orWhere('clc.batch_id', $e->batch_id);
                            });
                        }
                    });
                }
            };
            // 2026-06-05 — JOIN on course_chapter_lessons: clc.lesson_id is a
            // course_chapter_lessons.id (NOT a chapter_item id). The old
            // cci.id = clc.lesson_id join only matched when the two ids happened
            // to coincide, so the widget was silently empty in production.
            $upcomingLive = \DB::table('course_live_classes as clc')
                ->join('course_chapter_lessons as ccl', 'ccl.id', '=', 'clc.lesson_id')
                ->join('course_chapters as cc', 'cc.id', '=', 'ccl.chapter_id')
                ->whereIn('cc.course_id', $enrolledCourseIds->all() ?: [0])
                ->where($liveBatchScope)
                ->whereNull('clc.cancelled_at')
                ->where('clc.start_time', '>=', now())
                ->where('clc.start_time', '<=', now()->addDays(14))
                ->orderBy('clc.start_time')
                ->limit(5)
                ->select(
                    'clc.id',
                    'clc.lesson_id',
                    'cc.course_id',
                    'ccl.title as lesson_title',
                    'clc.start_time'
                )
                ->get()
                ->map(function ($row) {
                    // Robustly parse the varchar start_time. If parsing
                    // fails (legacy bad row), keep the raw string so the
                    // app can still render something readable.
                    $startIso = $row->start_time;
                    try {
                        $startIso = \Carbon\Carbon::parse($row->start_time)->toIso8601String();
                    } catch (\Throwable $e) { /* leave raw */ }
                    return [
                        'id'           => $row->id,
                        'lesson_id'    => $row->lesson_id,
                        'course_id'    => $row->course_id,
                        'lesson_title' => $row->lesson_title,
                        'start_time'   => $startIso,
                        'duration'     => null,   // duration column not present on course_live_classes
                    ];
                });
        } catch (\Throwable $e) {
            \Log::warning('student_dashboard.upcomingLive: '.$e->getMessage());
        }

        // ── Active membership card. Most recent active row wins.
        $activeMembership = null;
        try {
            $um = \App\Models\UserMembership::where('user_id', $userId)
                ->where('status', 'active')
                ->orderByDesc('id')
                ->first();
            if ($um) {
                $plan = \App\Models\MembershipPlan::find($um->plan_id);
                $expiresAt = $um->expires_at ? \Carbon\Carbon::parse($um->expires_at) : null;
                $activeMembership = [
                    'plan_name'  => $plan?->name ?? '—',
                    'role'       => $plan?->role ?? null,
                    'is_trial'   => $um->payment_method === 'trial',
                    'expires_at' => $expiresAt?->toIso8601String(),
                    'days_left'  => $expiresAt ? max(0, (int) now()->diffInDays($expiresAt, false)) : null,
                ];
            }
        } catch (\Throwable $e) {
            // UserMembership module not present — silently omit.
        }

        // ── Recommended courses: same-category, not-enrolled, approved + active.
        //    courses.category_id (NOT course_category_id), discount (NOT
        //    discount_price), and no total_enrollment column — ordering
        //    by created_at instead is the closest meaningful fallback.
        $recommended = [];
        try {
            if ($enrolledCourseIds->isNotEmpty()) {
                $categoryIds = Course::whereIn('id', $enrolledCourseIds)
                    ->pluck('category_id')
                    ->unique()
                    ->filter()
                    ->values();
                if ($categoryIds->isNotEmpty()) {
                    $recommended = Course::whereIn('category_id', $categoryIds)
                        ->whereNotIn('id', $enrolledCourseIds)
                        ->where('is_approved', 'approved')
                        ->where('status', 'active')
                        ->orderByDesc('created_at')
                        ->limit(10)
                        ->get(['id', 'title', 'slug', 'thumbnail', 'price', 'discount'])
                        ->map(fn ($c) => [
                            'id'             => $c->id,
                            'title'          => $c->title,
                            'slug'           => $c->slug,
                            'thumbnail'      => $c->thumbnail ? asset($c->thumbnail) : null,
                            'price'          => (float) $c->price,
                            'discount_price' => $c->discount !== null ? (float) $c->discount : null,
                        ])
                        ->toArray();
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('student_dashboard.recommended: '.$e->getMessage());
        }

        // ── Certificates earned (proxy: completed courses).
        $certificatesEarned = $completedCourses;

        return [
            // ── Legacy fields kept for backward compat ──
            'total_enrolled_courses'      => $totalEnrolledCourses,
            'total_quiz_attempts'         => $totalQuizAttempts,
            'total_reviews'               => $totalReviews,

            // ── Mobile-spec fields (new) ──
            'resume_learning'             => $resume,
            'upcoming_live_classes'       => $upcomingLive,
            'enrolled_courses_count'      => $totalEnrolledCourses,
            'completed_courses_count'     => $completedCourses,
            'quiz_average_score'          => $quizAvg,
            'active_membership'           => $activeMembership,
            'certificates_earned_count'   => $certificatesEarned,
            'recommended_courses'         => $recommended,
        ];
    });

    return response()->json([
        'status'  => true,
        'message' => 'Student dashboard data fetched successfully',
        'data'    => $data,
    ]);
}


 public function remove_from_cart(string $slug): JsonResponse {
        $user = auth()->user();

        $course = Course::select('id')->whereSlug($slug)->first();
        if (!$course || !$user->carts()->where('course_id', $course->id)->exists()) {
            return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
        }

        $user->carts()->where('course_id', $course->id)->delete();
        return response()->json(['status' => 'success', 'message' => 'Item removed from cart!'], 200);
    }

    

 public function add_to_cart(string $slug): JsonResponse {
        $course = Course::select('id', 'instructor_id')->whereSlug($slug)->first();
        $user = auth()->user();
        if (!$course) {
            return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
        }
        if ($course->instructor_id == $user->id) {
            return response()->json(['status' => 'error', 'message' => 'You can not add to cart your own course!'], 400);
        }
        if ($user->enrollments()->select('course_id')->where('course_id', $course->id)->exists()) {
            return response()->json(['status' => 'error', 'message' => 'Already purchased'], 400);
        }
        if ($user->carts()->select('course_id')->where('course_id', $course->id)->exists()) {
            return response()->json(['status' => 'error', 'message' => 'Already added to cart!'], 400);
        }
        $user->carts()->create(['course_id' => $course->id]);

        return response()->json(['status' => 'success', 'message' => 'Added to cart successfully!', 'cart_count' => $user->cartCount], 200);
    }

    public function cart_list(): JsonResponse
    {
        $user = auth()->user();
        $currency = strtoupper(request()->query('currency'));
        $data = [
            'total_qty'    => (int) $user->cartCount,
            'total_amount' => (string) apiCurrency($user->cartTotal, $currency),
        ];
        if ($user->cartCount > 0) {
            $cart_courses = Course::select('id', 'slug', 'title', 'instructor_id', 'thumbnail', 'price', 'discount')->active()->with(['instructor:id,name,image'])
                ->whereHas('carts', fn($q) => $q->where('user_id', $user->id))->withCount(['reviews as average_rating' => fn($q) => $q->select(DB::raw('coalesce(avg(rating), 0)'))->where('status', 1), 'enrollments'])->get();

            $data['cart_courses'] = CourseListResource::collection($cart_courses);
        }
        return response()->json(['status' => 'success', 'data' => $data], 200);
    }


    public function add_remove_wishlist(Course $course): JsonResponse
    {
        if (!$course) {
            return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
        }
        $favorite = auth()->user()->favoriteCourses();
        $favorite->toggle($course);
        return response()->json(['status' => 'success', 'message' => 'Success', 'data' => $favorite], 200);
    }




    public function profileAddressUpdate(StudentProfileAddressUpdateRequest $request)
    {
        $user = Auth::user();

        $user->address     = $request->address;
        $user->city        = $request->city;
        $user->state       = $request->state;
        $user->country_id  = $request->country;

        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'Address updated successfully',
            'data' => [
                'address' => $user->address,
                'city' => $user->city,
                'state' => $user->state,
                'country_id' => $user->country_id,
            ]
        ]);
    }

    public function profileBioUpdate(StudentBioUpdateRequest $request)
    {
        $user = Auth::user();

        $user->job_title = $request->designation;
        $user->bio = $request->bio;
        $user->short_bio = $request->short_bio;
        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'Bio updated successfully',
            'data' => [
                'job_title' => $user->job_title,
                'bio' => $user->bio,
                'short_bio' => $user->short_bio,
            ]
        ]);
    }



    public function profileUpdate(StudentProfileUpdateRequest $request)
    {
        $user = Auth::user();

        // Upload Avatar
        if ($request->hasFile('avatar')) {
            $imagePath = file_upload(
                file: $request->avatar,
                optimize: true
            );
            $user->image = $imagePath;
        }

        // Upload Cover
        if ($request->hasFile('cover')) {
            $imagePath = file_upload(
                file: $request->cover,
                optimize: true
            );
            $user->cover = $imagePath;
        }

        // Update Fields
        $user->name   = $request->name;
        $user->email  = $request->email;
        $user->phone  = $request->phone;
        $user->age    = $request->age;
        $user->gender = $request->gender;

        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'Profile updated successfully',
            'data' => $user
        ]);
    }


    public function profile()
    {
        $user = Auth::user();

        $experiences = UserExperience::where('user_id', $user->id)->get();
        $educations = UserEducation::where('user_id', $user->id)->get();

        $states = State::where([
            'country_id' => $user->country_id,
            'status' => 1
        ])->get();

        $cities = City::where([
            'state_id' => $user->state_id,
            'status' => 1
        ])->get();

        return response()->json([
            'status' => true,
            'message' => 'Profile data fetched successfully',
            'data' => [
                'user' => $user,
                'experiences' => $experiences,
                'educations' => $educations,
                'states' => $states,
                'cities' => $cities,
            ]
        ]);
    }


    public function wishlist()
    {
        $wishlistCourses = auth()->user()
            ->favoriteCourses()
            ->with([
                'category.translation',
                'instructor:id,name'
            ])
            ->withCount([
                'reviews as avg_rating' => function ($query) {
                    $query->select(DB::raw('coalesce(avg(rating), 0)'));
                },
            ])
            ->withCount([
                'lessons as active_lessons_count' => function ($query) {
                    $query->where('status', 'active');
                },
            ])
            ->withCount('enrollments')
            ->paginate(3);

        return response()->json([
            'status' => true,
            'message' => 'Wishlist courses fetched successfully',
            'data' => $wishlistCourses
        ]);
    }

    public function learn_course($slug)
    {
        $user = auth()->user();

        $course = Course::active()
            ->with([
                'chapters',
                'chapters.chapterItems.lesson',
                'chapters.chapterItems.quiz'
            ])
            ->withTrashed()
            ->where('slug', $slug)
            ->whereHas(
                'enrollments',
                fn($q) =>
                $q->where('user_id', $user->id)
            )
            ->first();

        if (!$course) {
            return response()->json([
                'status' => false,
                'message' => 'Course not found or not enrolled'
            ], 404);
        }

        // Current Progress
        $currentProgress = CourseProgress::where([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'current' => 1
        ])
            ->latest()
            ->first();

        // Watched Lessons
        $alreadyWatchedLectures = CourseProgress::where([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'type' => 'lesson',
            'watched' => 1
        ])
            ->pluck('lesson_id');

        // Completed Quiz
        $alreadyCompletedQuiz = CourseProgress::where([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'type' => 'quiz',
            'watched' => 1
        ])
            ->pluck('lesson_id');

        // Announcements — Audit 2026-05-18 — scope by student's batch + active.
        $studentBatchId = (int) (\Modules\Order\app\Models\Enrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('has_access', 1)
            ->value('batch_id') ?? 0);
        $studentBatchId = $studentBatchId > 0 ? $studentBatchId : null;
        $announcements = Announcement::visibleToBatchStudent($course->id, $studentBatchId)
            ->orderByDesc('is_pinned')           // Audit 2026-05-18 phase 3
            ->orderByDesc('sent_at')
            ->orderBy('id', 'desc')
            ->get();

        // Lecture Count
        $courseLectureCount = CourseChapterItem::whereHas('chapter', function ($q) use ($course) {
            $q->where('course_id', $course->id);
        })->count();

        $courseLectureCompletedByUser = CourseProgress::where([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'watched' => 1
        ])->count();

        $courseCompletedPercent = $courseLectureCount > 0
            ? round(($courseLectureCompletedByUser / $courseLectureCount) * 100, 2)
            : 0;

        // If no current progress → auto create first lesson
        if (!$currentProgress) {
            $firstChapter = $course->chapters->first();
            $firstItem = $firstChapter?->chapterItems->first();

            if ($firstItem && $firstItem->lesson) {
                $currentProgress = CourseProgress::create([
                    'user_id'    => $user->id,
                    'course_id'  => $course->id,
                    'chapter_id' => $firstChapter->id,
                    'lesson_id'  => $firstItem->lesson->id,
                    'current'    => 1,
                ]);
            }
        }
 


        return response()->json([
            'status' => true,
            'message' => 'Learning data fetched successfully',
            'data' => [
                'course' => $course,
                'current_progress' => $currentProgress,
                'announcements' => $announcements,
                'completion_percent' => $courseCompletedPercent,
                'total_lectures' => $courseLectureCount,
                'completed_lectures' => $courseLectureCompletedByUser,
                'watched_lessons' => $alreadyWatchedLectures,
                'completed_quizzes' => $alreadyCompletedQuiz
            ]
        ]);
    }




    /**
     * Paginated list of live classes for the authenticated student.
     *
     * Filtered by enrollment: only classes attached to lessons whose
     * parent course the student is enrolled in show up. The `tab` query
     * parameter narrows the result set:
     *
     *   tab=today     — classes whose start_time falls within today's
     *                   local window [start_of_day .. end_of_day]
     *   tab=upcoming  — start_time > now() (default; sorted ascending)
     *   tab=past      — start_time < now() (sorted descending — most
     *                   recently finished first)
     *   tab=all       — everything, newest first
     *
     * NOTE on the join: `course_live_classes.lesson_id` references
     * `course_chapter_items.id`, NOT `course_chapter_lessons.id`. The
     * student-dashboard query hit the same gotcha — we mirror its
     * join chain exactly so we never silently drop classes.
     *
     * `start_time` on `course_live_classes` is varchar — Carbon::parse
     * handles the typical "Y-m-d H:i:s" format, and the response always
     * emits ISO-8601 so the Android app can do date math reliably.
     *
     * Response shape (matches the rest of the student API):
     *   {
     *     "status": true,
     *     "data": [LiveClassDto, …],
     *     "pagination": { current_page, per_page, total, last_page }
     *   }
     *
     * Each LiveClassDto:
     *   { id, lesson_id, lesson_title, course_id, course_title,
     *     course_slug, course_thumbnail, start_time, source,
     *     web_join_url }
     */
    public function student_live_classes(\Illuminate\Http\Request $request): JsonResponse
    {
        $userId = auth()->id();
        $tab    = strtolower((string) $request->query('tab', 'upcoming'));
        $perPage = max(1, min(50, (int) $request->query('per_page', 15)));

        // 2026-06-02 — match the web StudentLiveClassController::index gate.
        // Two fixes vs the previous query:
        //   (a) require has_access=1 — live-class join URLs are paid content,
        //       so an unpaid/pending enrollment must NOT list them;
        //   (b) batch-scope pair-wise — a student must never see another
        //       batch's live class (and its join_url) within the same course.
        $enrollments = \Modules\Order\app\Models\Enrollment::where('user_id', $userId)
            ->where('has_access', 1)
            ->get(['course_id', 'batch_id']);
        $enrolledCourseIds = $enrollments->pluck('course_id')->unique()->values()->all();

        // Pair-wise (course, batch) scope, applied to BOTH the list and the
        // tab counts so they stay consistent. Legacy null-batch enrollments
        // see all of their course's classes (back-compat).
        $batchScope = function ($outer) use ($enrollments) {
            foreach ($enrollments as $e) {
                $outer->orWhere(function ($w) use ($e) {
                    $w->where('cc.course_id', $e->course_id);
                    if ($e->batch_id !== null) {
                        $w->where(function ($b) use ($e) {
                            $b->whereNull('clc.batch_id')
                              ->orWhere('clc.batch_id', $e->batch_id);
                        });
                    }
                });
            }
        };

        // Base query. 2026-06-05 — JOIN on course_chapter_lessons:
        // clc.lesson_id references course_chapter_lessons.id, so the lesson
        // (and its chapter → course) must be reached through ccl, not through
        // course_chapter_items (the old cci.id = clc.lesson_id join only matched
        // when the ids coincided, leaving the list empty in production).
        $query = \DB::table('course_live_classes as clc')
            ->join('course_chapter_lessons as ccl', 'ccl.id', '=', 'clc.lesson_id')
            ->join('course_chapters as cc', 'cc.id', '=', 'ccl.chapter_id')
            ->join('courses as c', 'c.id', '=', 'cc.course_id')
            ->whereIn('cc.course_id', $enrolledCourseIds ?: [0])
            ->where($batchScope)
            ->whereNull('clc.cancelled_at')
            ->select(
                'clc.id',
                'clc.lesson_id',
                'clc.start_time',
                'clc.type as source',
                'clc.join_url as zoom_join_url',
                'cc.course_id',
                'c.title as course_title',
                'c.slug as course_slug',
                'c.thumbnail as course_thumbnail',
                'ccl.title as lesson_title'
            );

        $now = now();
        switch ($tab) {
            case 'today':
                $query->where('clc.start_time', '>=', $now->copy()->startOfDay())
                      ->where('clc.start_time', '<=', $now->copy()->endOfDay())
                      ->orderBy('clc.start_time', 'asc');
                break;
            case 'past':
                $query->where('clc.start_time', '<', $now)
                      ->orderBy('clc.start_time', 'desc');
                break;
            case 'all':
                $query->orderBy('clc.start_time', 'desc');
                break;
            case 'upcoming':
            default:
                $query->where('clc.start_time', '>=', $now)
                      ->orderBy('clc.start_time', 'asc');
                break;
        }

        $paginated = $query->paginate($perPage);

        $rows = collect($paginated->items())->map(function ($r) {
            $startIso = $r->start_time;
            try {
                $startIso = \Carbon\Carbon::parse($r->start_time)->toIso8601String();
            } catch (\Throwable $e) { /* fall back to raw varchar */ }
            return [
                'id'               => (int)    $r->id,
                'lesson_id'        => (int)    $r->lesson_id,
                'lesson_title'     => (string) ($r->lesson_title ?? 'Live class'),
                'course_id'        => (int)    $r->course_id,
                'course_title'     => (string) $r->course_title,
                'course_slug'      => (string) $r->course_slug,
                'course_thumbnail' => $r->course_thumbnail
                    ? (string) \Illuminate\Support\Facades\URL::asset($r->course_thumbnail)
                    : null,
                'start_time'       => $startIso,
                'source'           => (string) ($r->source ?? 'zoom'),
                // Two join paths returned. `zoom_join_url` is the raw
                // Zoom URL (already includes passcode in the `pwd` query
                // param for almost all rows) — fastest path that drops
                // straight into the Zoom app / browser.
                // `web_join_url` is the existing student web page,
                // useful as a fallback because it handles the full ZAK
                // + signature dance for any edge cases. The Android app
                // prefers zoom_join_url and falls back to web_join_url.
                'zoom_join_url'    => $r->zoom_join_url ?: null,
                'web_join_url'     => url('/student/live-class/'.$r->lesson_id),
            ];
        });

        // Lightweight counts across all three tabs — lets the app render
        // "Today (2) · Upcoming (5) · Past (10)" without firing three
        // round-trips. Cheap because it operates on the same join chain
        // and only does aggregate counts.
        $countsBase = \DB::table('course_live_classes as clc')
            ->join('course_chapter_lessons as ccl', 'ccl.id', '=', 'clc.lesson_id')
            ->join('course_chapters as cc', 'cc.id', '=', 'ccl.chapter_id')
            ->whereIn('cc.course_id', $enrolledCourseIds ?: [0])
            ->where($batchScope);

        $counts = [
            'today' => (clone $countsBase)
                ->where('clc.start_time', '>=', $now->copy()->startOfDay())
                ->where('clc.start_time', '<=', $now->copy()->endOfDay())
                ->count(),
            'upcoming' => (clone $countsBase)
                ->where('clc.start_time', '>=', $now)->count(),
            'past' => (clone $countsBase)
                ->where('clc.start_time', '<', $now)->count(),
        ];

        return response()->json([
            'status'     => true,
            'data'       => $rows,
            'counts'     => $counts,
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
                'last_page'    => $paginated->lastPage(),
            ],
        ]);
    }


    /**
     * One-tap enrollment for zero-cost courses.
     *
     * Guards (all 4xx with a clear message — the mobile app surfaces these):
     *   - Course must exist + be active
     *   - Course must be priced free. Anything > 0 redirects the client
     *     to the cart / payment flow (Phase 7).
     *   - Student must not already be enrolled.
     *
     * Side effects:
     *   - Creates the `enrollments` row
     *   - Mirrors the row into `orders` as a zero-amount completed order
     *     so reporting + invoice download paths still work
     *
     * Wrapped in a DB transaction so a partial failure doesn't leave the
     * student "enrolled but no order record" (or vice-versa).
     */
    public function free_enroll(string $slug): JsonResponse
    {
        $user = auth()->user();

        // Audit 2026-05-18 — accept optional batch_id so free-course
        // enrolments also carry batch context. Validated against the
        // course's own batches below; safely ignored if not provided.
        $batchId = request()->filled('batch_id') ? (int) request('batch_id') : null;

        $course = \App\Models\Course::active()
            ->where('slug', $slug)
            ->select('id', 'title', 'slug', 'price', 'discount', 'instructor_id')
            ->first();

        if (! $course) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Course not found.',
            ], 404);
        }

        // "Free" = the effective price (after discount if present) is zero.
        $price    = (float) $course->price;
        $discount = (float) $course->discount;
        $effective = ($discount > 0 && $discount < $price) ? $discount : $price;
        if ($effective > 0) {
            return response()->json([
                'status'  => 'error',
                'message' => 'This is a paid course. Use the checkout flow.',
            ], 422);
        }

        $alreadyEnrolled = \Modules\Order\app\Models\Enrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->exists();
        if ($alreadyEnrolled) {
            return response()->json([
                'status'  => 'success',
                'message' => 'You are already enrolled.',
                'data'    => ['course_slug' => $course->slug],
            ]);
        }

        try {
            \DB::beginTransaction();

            // Schema notes:
            //   - orders.status (enum: pending|processing|completed|declined)
            //   - orders.payment_status is a free-form varchar. 2026-06-01
            //     (audit [6]) — normalize to 'paid' (was 'completed'). The
            //     canonical "fulfilled" marker across the codebase is
            //     payment_status='paid' (PaymentFulfilmentService,
            //     OrderStatusChangedToStudent, every coach/admin report and
            //     the access rule "paid + completed"). A free order is paid in
            //     full ($0), so 'paid' is correct; 'completed' was an outlier
            //     that excluded free orders from every payment_status='paid'
            //     query. The free-enrollment test fixtures already use 'paid'.
            //   - enrollments.has_access MUST be 1; the LearningController
            //     gate-checks on it before serving lesson content
            $order = \Modules\Order\app\Models\Order::create([
                'invoice_id'         => 'FREE-'.strtoupper(\Illuminate\Support\Str::random(10)),
                'buyer_id'           => $user->id,
                'seller_id'          => $course->instructor_id,
                'status'             => 'completed',
                'payment_status'     => 'paid',
                'payment_method'     => 'free',
                'payable_amount'     => 0,
                'paid_amount'        => 0,
                'order_type'         => 'course',
            ]);

            // Audit 2026-05-18 — propagate batch_id if it belongs to this course.
            $validBatchId = null;
            if ($batchId !== null) {
                $exists = \App\Models\CourseBatch::where('id', $batchId)
                    ->where('course_id', $course->id)
                    ->where('status', 'active')
                    ->exists();
                if ($exists) {
                    $validBatchId = $batchId;
                }
            }

            \Modules\Order\app\Models\Enrollment::create([
                'order_id'   => $order->id,
                'user_id'    => $user->id,
                'course_id'  => $course->id,
                'batch_id'   => $validBatchId,
                'has_access' => 1,
            ]);

            \DB::commit();
        } catch (\Throwable $e) {
            \DB::rollBack();
            \Log::warning('free_enroll failed: '.$e->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => 'Enrollment failed. Please try again.',
            ], 500);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Enrolled successfully.',
            'data'    => [
                'course_slug' => $course->slug,
                'course_id'   => $course->id,
            ],
        ]);
    }


    public function order_history()
    {
        $orders = Order::where('buyer_id', auth()->id())
            ->orderBy('id', 'desc')
            ->paginate(30);

        return response()->json([
            'status' => true,
            'message' => 'Order list fetched successfully',
            'data' => $orders
        ]);
    }


    /**
     * Order Details API
     */
    public function show_order_history($id)
    {
        $order = Order::where('id', $id)
            ->where('buyer_id', auth()->id())
            ->firstOrFail();

        return response()->json([
            'status' => true,
            'message' => 'Order details fetched successfully',
            'data' => $order
        ]);
    }

    public function print_invoice($id)
    {
        $order = Order::where('id', $id)
            ->where('buyer_id', auth()->id())
            ->with([
                'user',
                'orderItems.course.instructor' // 🔥 important
            ])
            ->firstOrFail();

        return response()->json([
            'status' => true,
            'message' => 'Invoice data fetched successfully',
            'data' => $order
        ]);
    }


    public function student_courses()
    {
        $enrolls = Enrollment::with([
            'course' => function ($q) {
                $q->withTrashed()
                    ->with('instructor'); // optional but recommended
            }
        ])
            ->where([
                'user_id' => auth()->id(),
                'has_access' => 1
            ])
            ->orderByDesc('id')
            ->paginate(10);

        return response()->json([
            'status' => true,
            'message' => 'Enrolled courses fetched successfully',
            'data' => $enrolls
        ]);
    }

    // ----API CODE END-------



    public function instructor_courses(Request $request): JsonResponse
    {
        $instructor_id = auth()->user()->id;

        $courses = Course::where('instructor_id', $instructor_id)->latest()->paginate(10);

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
     * 
     * @param Request $request
     * @return JsonResponse
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
     * 
     * @param Request $request
     * @param int $course_id
     * @return JsonResponse
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
        if (!$course) {
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
     * 
     * @param Request $request
     * @param int $lesson_id
     * @return JsonResponse
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

        if (!$lesson) {
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
     * 
     * @param Request $request
     * @param int $question_id
     * @return JsonResponse
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

        if (!$question) {
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
        if (!$question->seen) {
            $question->seen = true;
            $question->save();
        }

        $data = new QnaReplyResource($reply);
        return response()->json(['status' => 'success', 'data' => $data, 'message' => 'Reply created successfully'], 201);
    }

    /**
     * Mark question as seen/unseen
     * 
     * @param Request $request
     * @param int $question_id
     * @return JsonResponse
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

        if (!$question) {
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
     * 
     * @param int $question_id
     * @return JsonResponse
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

        if (!$question) {
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
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function students(Request $request): JsonResponse
    {
        $instructor_id = auth()->user()->id;
        $limit = $request->filled('limit') && is_numeric($request->limit) ? (int) $request->limit : 15;
        $search = $request->filled('search') ? $request->search : null;

        $query = User::where('added_by', $instructor_id)
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

        if ($students->isNotEmpty()) {
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

        return response()->json(['status' => 'error', 'message' => 'No students found!'], 404);
    }

    /**
     * Create a new student
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function createStudent(Request $request): JsonResponse
    {
        $instructor_id = auth()->user()->id;

        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            // FT-AUTH-7 fix (2026-05-27) — API path was `min:4`.
            // Parity with self-service signup (min:8) so the API
            // creation surface can't sidestep web-form policy.
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

        return response()->json([
            'status' => 'success',
            'message' => 'Student created successfully',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
            ]
        ], 201);
    }

    /**
     * Update an existing student
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateStudent(Request $request, int $id): JsonResponse
    {
        $instructor_id = auth()->user()->id;

        $student = User::where('id', $id)
            ->where('added_by', $instructor_id)
            ->where('role', 'student')
            ->first();

        if (!$student) {
            return response()->json(['status' => 'error', 'message' => 'Student not found!'], 404);
        }

        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $id,
            'status' => 'required|in:active,inactive',
        ];

        // FT-AUTH-7 (extension, 2026-05-28) — updateStudent kept
        // `min:4` even though createStudent was already brought up
        // to `min:8` in the FT-AUTH-7 sweep. A coach editing a
        // student could weaken the student's password below the
        // platform's actual policy. Same logic as the Customer
        // module fix earlier in this branch.
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
            ]
        ], 200);
    }

    /**
     * Delete a student
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function deleteStudent(int $id): JsonResponse
    {
        $instructor_id = auth()->user()->id;

        $student = User::where('id', $id)
            ->where('added_by', $instructor_id)
            ->where('role', 'student')
            ->first();

        if (!$student) {
            return response()->json(['status' => 'error', 'message' => 'Student not found!'], 404);
        }

        // Delete the student
        $student->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Student deleted successfully'
        ], 200);
    }

    /**
     * Get all announcements for the instructor
     * 
     * @param Request $request
     * @return JsonResponse
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
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function announcements_create(Request $request): JsonResponse
    {
        $instructor_id = auth()->user()->id;

        // FT-VAL-10 (2026-05-28) — cap `announcement` body length.
        // Pre-fix this was unbounded — a coach could POST a 10MB
        // payload into the announcement body, broadcast it to every
        // student in the course, and bloat the DB. Cap to 20KB
        // (longer than any reasonable announcement; coaches needing
        // more should attach a PDF).
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

        if (!$course) {
            return response()->json(['status' => 'error', 'message' => 'Course not found or you are not the instructor!'], 404);
        }

        $announcement = Announcement::create([
            'instructor_id' => $instructor_id,
            'course_id' => $request->course_id,
            'title' => $request->title,
            'announcement' => $request->announcement,
        ]);

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
            ]
        ], 201);
    }

    /**
     * Update an existing announcement
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function announcements_update(Request $request, int $id): JsonResponse
    {
        $instructor_id = auth()->user()->id;

        $announcement = Announcement::where('id', $id)
            ->where('instructor_id', $instructor_id)
            ->first();

        if (!$announcement) {
            return response()->json(['status' => 'error', 'message' => 'Announcement not found!'], 404);
        }

        // FT-VAL-10 (2026-05-28) — cap `announcement` body length.
        // Pre-fix this was unbounded — a coach could POST a 10MB
        // payload into the announcement body, broadcast it to every
        // student in the course, and bloat the DB. Cap to 20KB
        // (longer than any reasonable announcement; coaches needing
        // more should attach a PDF).
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

        if (!$course) {
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
            ]
        ], 200);
    }

    /**
     * Delete an announcement
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function announcements_delete(int $id): JsonResponse
    {
        $instructor_id = auth()->user()->id;

        $announcement = Announcement::where('id', $id)
            ->where('instructor_id', $instructor_id)
            ->first();

        if (!$announcement) {
            return response()->json(['status' => 'error', 'message' => 'Announcement not found!'], 404);
        }

        $announcement->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Announcement deleted successfully'
        ], 200);
    }

    /**
     * Get all sales for the instructor
     * 
     * @param Request $request
     * @return JsonResponse
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
        if ($sales->isNotEmpty()) {
            $data = $sales->map(function ($item) {
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

        return response()->json(['status' => 'error', 'message' => 'No sales found!'], 404);
    }

    /**
     * Get data for creating a new sale
     * 
     * @return JsonResponse
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
            ]
        ], 200);
    }

    /**
     * Store a new sale
     * 
     * @param Request $request
     * @return JsonResponse
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

        if (!$course) {
            return response()->json(['status' => 'error', 'message' => 'Course not found or you are not the instructor!'], 404);
        }

        // Verify the student exists and belongs to this instructor
        $student = User::where('id', $request->user_id)
            ->where('added_by', $instructor_id)
            ->where('role', 'student')
            ->first();

        if (!$student) {
            return response()->json(['status' => 'error', 'message' => 'Student not found or you are not the owner of this student!'], 404);
        }

        // 2026-06-05 — persist the SELECTED batch (if any) so this student's
        // live-class visibility is correctly scoped to that batch. Optional and
        // validated against the course; an invalid/foreign batch falls back to
        // null (course-wide) rather than breaking the sale.
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
                'conversion_rate'         => Session::get('currency_rate', 1),
                'commission_rate'         => Cache::get('setting')->commission_rate,
                'order_type' => 'course',
                'order_details' => null,
            ]);
            // Create order item
            $orderItem = OrderItem::create([
                'order_id' => $order->id,
                'price' => $paid_amount,
                'course_id' => $request->course_id,
                'batch_id' => $validBatchId,
                'commission_rate'         => Cache::get('setting')->commission_rate,
            ]);

            // FT-PAY-9 fix (2026-05-28) — pre-fix this credited
            // $paid_amount with NO commission subtracted, despite the
            // Order row above setting `commission_rate` from the
            // global setting (line 1949). Net effect: every instructor
            // sale logged via this API endpoint paid 100% of the price
            // to the coach instead of (1 - commission_rate)% — the
            // platform lost its commission take on every API-recorded
            // sale.
            //
            // The web equivalent (Order\OrderController::updateOrder
            // line ~196) correctly does:
            //     commissionAmount = item.price * (commission_rate / 100)
            //     amountAfterCommission = item.price - commissionAmount
            //     instructor->increment('wallet_balance', amountAfterCommission)
            // Mirror that formula here.
            $commissionRate         = (float) (Cache::get('setting')->commission_rate ?? 0);
            $commissionAmount       = $paid_amount * ($commissionRate / 100);
            $amountAfterCommission  = $paid_amount - $commissionAmount;
            $instructor = Course::find($request->course_id)->instructor;
            $instructor->increment('wallet_balance', $amountAfterCommission);

            // Create enrollment
            // 2026-06-06 — key on (user, course, BATCH) so a second sale for a
            // different batch of the same course creates its own enrollment row
            // (multi-batch) instead of crashing on the old (user, course) unique.
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
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create sale: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a sale
     * 
     * @param Request $request
     * @param string $id
     * @return JsonResponse
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

        if (!$orderItem) {
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

        // FT-PAY-12 fix (2026-05-28) — was missing wallet
        // credit/decrement on flip. Pre-fix this method changed the
        // order's payment_status WITHOUT touching the coach wallet:
        //   • Coach uses my_sells_store to record an offline sale
        //     (wallet credited per FT-PAY-9).
        //   • Coach then uses my_sells_update to flip status to
        //     'cancelled' — Enrollment->has_access flips to 0
        //     (buyer correctly loses access) but coach KEEPS the
        //     wallet credit. Net: free money via record-and-cancel.
        //   • Inverse: flipping pending→paid never credits the
        //     wallet, so a coach who uses the pending-then-paid
        //     workflow earns nothing.
        //
        // Mirror Order\OrderController::updateOrder (the web flow):
        //  - flipped to paid:   credit wallet (per-item commission)
        //  - flipped from paid: decrement wallet
        //
        // Run inside a transaction so the order/enrollment/wallet
        // state moves atomically.
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
            // with this payment-status change.
            if ($flippedToPaid) {
                app(\App\Services\ReferralCommissionService::class)->onOrderPaid($order);
            } elseif ($flippedFromPaid) {
                app(\App\Services\ReferralCommissionService::class)
                    ->onOrderReversed($order, 'Payment set to ' . $request->payment_status);
            }
        });

        // Check if order is completed & paid
        $hasAccess = ($order->status === 'completed' && $order->payment_status === 'paid') ? 1 : 0;

        // 2026-06-06 — key on (user, course, BATCH) for multi-batch support
        // (was keyed on order_id, which inserted a duplicate (user, course) row
        // and crashed on the unique when the student was in another batch).
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
            ]
        ], 200);
    }

    /**
     * Get all wishlist courses for the instructor
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function wishlist_list(Request $request): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
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
            'message' => 'No wishlist courses found!'
        ], 404);
    }

    /**
     * Remove a course from wishlist
     * 
     * @param Request $request
     * @param string $slug
     * @return JsonResponse
     */
    public function wishlist_delete(Request $request, string $slug): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 401);
        }

        $course = Course::where('slug', $slug)->first();

        if (!$course) {
            return response()->json([
                'status' => 'error',
                'message' => 'Course not found!'
            ], 404);
        }

        // Check if exists in wishlist
        if (!$user->favoriteCourses()->where('course_id', $course->id)->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Course is not in your wishlist!'
            ], 404);
        }

        // Detach by ID (recommended)
        $user->favoriteCourses()->detach($course->id);

        return response()->json([
            'status' => 'success',
            'message' => 'Course removed from wishlist successfully'
        ], 200);
    }

    /**
     * Create a new course
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createCourse(Request $request): JsonResponse
    {
        $instructor_id = auth()->id();

        // FT-VAL-9 (sibling) — same unbounded-text-fields bug as the
        // CoachDashboardController equivalent. See that commit for
        // full rationale. Two near-duplicate createCourse copies in
        // two API controllers.
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
                'errors' => $validator->errors()
            ], 422);
        }

        // Generate unique slug
        $slug = Str::slug($request->title);
        $slugExists = Course::where('slug', $slug)->exists();
        if ($slugExists) {
            $slug .= '-' . uniqid();
        }

        $course = Course::create([
            'instructor_id'      => $instructor_id,
            'title'              => $request->title,
            'slug'               => $slug,
            'seo_description'    => $request->seo_description,
            'thumbnail'          => $request->thumbnail,
            'demo_video_storage' => $request->demo_video_storage ?? 'upload',
            'demo_video_source'  => $request->demo_video_source,
            'price'              => $request->price,
            'discount'           => $request->discount_price,
            'description'        => $request->description,
            'status'             => 'is_draft',
            'is_approved'        => 'pending',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Basic info saved successfully',
            'data' => [
                'course_id' => $course->id,
                'title' => $course->title,
                'slug' => $course->slug,
                'price' => (float) $course->price,
                'discount' => $course->discount ? (float)$course->discount : null,
                'status' => $course->status,
                'is_approved' => $course->is_approved,
            ]
        ], 201);
    }

    /**
     * Update an existing course
     *
     * @param Request $request
     * @param int $course_id
     * @return JsonResponse
     */
    public function updateCourse(Request $request, $course_id): JsonResponse
    {
        $instructor_id = auth()->id();

        // 1️⃣ Find course (must belong to instructor)
        $course = Course::where('id', $course_id)
            ->where('instructor_id', $instructor_id)
            ->first();

        if (!$course) {
            return response()->json([
                'status' => 'error',
                'message' => 'Course not found or unauthorized'
            ], 404);
        }

        // 2️⃣ Validation
        // FT-VAL-9 (sibling, mirror createCourse).
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
                'errors' => $validator->errors()
            ], 422);
        }

        // 3️⃣ Slug update (if title changed)
        if ($request->filled('title') && $request->title !== $course->title) {
            $slug = Str::slug($request->title);
            $slugExists = Course::where('slug', $slug)
                ->where('id', '!=', $course->id)
                ->exists();

            if ($slugExists) {
                $slug .= '-' . uniqid();
            }

            $course->slug = $slug;
            $course->title = $request->title;
        }

        // 4️⃣ Update remaining fields
        $course->update([
            'seo_description'    => $request->seo_description ?? $course->seo_description,
            'thumbnail'          => $request->thumbnail ?? $course->thumbnail,
            'demo_video_storage' => $request->demo_video_storage ?? $course->demo_video_storage,
            'demo_video_source'  => $request->demo_video_source ?? $course->demo_video_source,
            'price'              => $request->price ?? $course->price,
            'discount'           => $request->discount_price ?? $course->discount,
            'description'        => $request->description ?? $course->description,
            'status'             => $request->status ?? $course->status,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Course updated successfully',
            'data' => [
                'course_id' => $course->id,
                'title' => $course->title,
                'slug' => $course->slug,
                'price' => (float) $course->price,
                'discount' => $course->discount ? (float)$course->discount : null,
                'status' => $course->status,
                'is_approved' => $course->is_approved,
            ]
        ]);
    }

    public function addChapter(Request $request, $course_id)
    {
        $instructor_id = auth()->id();

        $course = Course::where('id', $course_id)
            ->where('instructor_id', $instructor_id)
            ->firstOrFail();

        $request->validate([
            'title' => 'required|string|max:255'
        ]);

        $chapter = CourseChapter::create([
            'course_id' => $course->id,
            'instructor_id' => $instructor_id,
            'title' => $request->title,
            'order' => $course->chapters()->count() + 1,
            'status' => 'active'
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Chapter created successfully',
            'data' => $chapter
        ]);
    }

    public function updateChapter(Request $request, $chapter_id)
    {
        $instructor_id = auth()->id();

        // 1️⃣ Validate
        $request->validate([
            'title' => 'required|string|max:255',
            'status' => 'nullable|in:active,inactive'
        ]);

        // 2️⃣ Check chapter belongs to instructor
        $chapter = CourseChapter::where('id', $chapter_id)
            ->where('instructor_id', $instructor_id)
            ->first();

        if (!$chapter) {
            return response()->json([
                'status' => false,
                'message' => 'Chapter not found or unauthorized'
            ], 404);
        }

        // 3️⃣ Update chapter
        $chapter->update([
            'title' => $request->title,
            'status' => $request->status ?? $chapter->status
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Chapter updated successfully',
            'data' => $chapter
        ]);
    }

    public function addLesson(Request $request, $chapter_id)
    {
        $instructorId = auth()->user()->id;

        // FT-UPLOAD-2 (sibling, 2026-05-28) — same mime-less file
        // upload bug as the CoachDashboardController equivalent.
        // See that commit for full threat model (RCE / stored XSS
        // via SVG / arbitrary file types served from public storage).
        // 1️⃣ Validate Input
        $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string|max:10000',
            'file_type'   => 'required|string|in:video,audio,document,text',
            'duration'    => 'nullable|integer|min:0|max:36000',
            'is_free'     => 'nullable|boolean',
            'file'        => 'nullable|file|mimes:mp4,webm,mp3,m4a,pdf,docx,jpg,jpeg,png,webp|max:51200', // 50MB
            'file_path'   => 'nullable|string|max:1000'
        ]);

        // 2️⃣ Check Chapter Exists & Belongs to Instructor
        $chapter = CourseChapter::where('id', $chapter_id)
            ->where('instructor_id', $instructorId)
            ->first();

        if (!$chapter) {
            return response()->json([
                'status' => false,
                'message' => 'Chapter not found or unauthorized'
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
            'title'         => $request->title,
            'description'   => $request->description,
            'course_id'     => $chapter->course_id,
            'chapter_id'    => $chapter->id,
            'file_path'     => $filePath,
            'storage'       => $storageType,
            'file_type'     => $request->file_type,
            'instructor_id' => $instructorId,
            'duration'      => $request->duration,
            'is_free'       => $request->is_free ?? 0,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Lesson added successfully',
            'data'    => $lesson
        ]);
    }

    public function getCourseContent($course_id)
    {
        $course = Course::where('id', $course_id)
            ->where('instructor_id', auth()->id())
            ->first();

        if (!$course) {
            return response()->json([
                'status' => false,
                'message' => 'Course not found'
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
                'chapters' => $chapters
            ]
        ]);
    }

    public function deleteChapter($course_id, $chapter_id)
    {
        $instructor_id = auth()->id();

        $chapter = CourseChapter::where('id', $chapter_id)
            ->where('course_id', $course_id)
            ->where('instructor_id', $instructor_id)
            ->first();

        if (!$chapter) {
            return response()->json([
                'status' => false,
                'message' => 'Chapter not found or unauthorized.'
            ], 404);
        }

        $chapter->delete();

        return response()->json([
            'status' => true,
            'message' => 'Chapter deleted successfully'
        ]);
    }

    public function analyticsProgress($course_id)
    {
        // SECURITY (audit 2026-05-22) — these methods live in the
        // STUDENT controller but the route shape `/api/instructor/
        // course/{id}/analytics/progress` makes them coach-facing;
        // the original `Course::findOrFail($course_id)` exposed any
        // coach's progress data to any sanctum-authenticated user.
        // Now scoped to instructor_id == auth()->id().
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
        // analyticsProgress above.
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
            'status' => 'required|in:Publish,UnPublish,Draft'
        ], [
            'status.required' => 'Please select course status.',
            'status.in' => 'Invalid status selected. Allowed: Publish, UnPublish, Draft.'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $statusMap = [
            'Publish'   => 'active',
            'UnPublish' => 'inactive',
            'Draft'     => 'is_draft',
        ];

        $course->update([
            'message_for_reviewer' => $request->message_for_reviewer,
            'status' => $statusMap[$request->status]
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Course submitted successfully'
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
            'message' => 'More info updated successfully'
        ]);
    }

    /**
     * Delete Course
     *
     * @param string $slug
     * @return JsonResponse
     */
    public function deleteCourse(string $slug): JsonResponse
    {
        $user = auth()->user();

        $course = Course::where('slug', $slug)
            ->where('instructor_id', $user->id)
            ->first();

        if (!$course) {
            return response()->json([
                'status' => 'error',
                'message' => 'Course not found or unauthorized'
            ], 404);
        }

        $course->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Course deleted successfully'
        ], 200);
    }
}
