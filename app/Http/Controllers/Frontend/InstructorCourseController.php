<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\CourseChapter;
use App\Models\CoursePartnerInstructor;
use App\Models\CourseSelectedLanguage;
use App\Models\CourseSelectedLevel;
use App\Models\User;
use App\Rules\ValidateDiscountRule;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;
use Modules\Course\app\Models\CourseCategory;
use Modules\Course\app\Models\CourseLanguage;
use Modules\Course\app\Models\CourseLevel;
use Modules\Order\app\Models\OrderItem;

class InstructorCourseController extends Controller
{
    protected $pageName;

    public function __construct(Course $model)
    {
        $this->model = $model;
        $this->admin_base_url = 'instructor.coach-staff.index';
        $this->admin_view = 'frontend.instructor-dashboard.coach-staff';
        $this->admin_error_view = 'errors.403';
        $this->pageName = 'courses';
    }

    /**
     * Resolve the coach id (own id for instructor, coach_id for staff).
     */
    private function effectiveCoachId(): int
    {
        return userAuth()->role === 'instructor' ? userAuth()->id : userAuth()->coach_id;
    }

    /**
     * Find a course by id that belongs to the current coach (added_by OR instructor_id).
     * Aborts 404 if not — prevents IDOR on course edit/delete.
     */
    private function findOwnedCourseOrFail($id): Course
    {
        $coachId = $this->effectiveCoachId();
        return Course::where('id', $id)
            ->where(function ($q) use ($coachId) {
                $q->where('added_by', $coachId)->orWhere('instructor_id', $coachId);
            })
            ->firstOrFail();
    }

    /**
     * Find a batch by id whose course belongs to the current coach.
     */
    private function findOwnedBatchOrFail($id): CourseBatch
    {
        $coachId = $this->effectiveCoachId();
        $courseIds = Course::where(function ($q) use ($coachId) {
            $q->where('added_by', $coachId)->orWhere('instructor_id', $coachId);
        })->pluck('id');
        return CourseBatch::where('id', $id)->whereIn('course_id', $courseIds)->firstOrFail();
    }

    public function index(): View
    {
        $flag = checkPermission($this->pageName);

        if ($flag == 1) {

            $status = request()->get('status');
             $search = request()->get('search'); // ✅ get search value

            $baseQuery = Course::where(function ($query) {
                $query->where('added_by', userAuth()->id)
                    ->orWhere('instructor_id', userAuth()->id);
            })
                ->where('coach_soft_delete', 0);

            // Apply status filter only if exists
            $coursesQuery = clone $baseQuery;

            if (!empty($status)) {
                    $coursesQuery->where('status', $status);
            } 
            
             // SEARCH FILTER (title + category)
                if (!empty($search)) {
                    $coursesQuery->where(function ($q) use ($search) {

                        // search by course title
                        $q->where('title', 'LIKE', "%{$search}%")

                        // search by category name
                        ->orWhereHas('category.translation', function ($q2) use ($search) {
                            $q2->where('name', 'LIKE', "%{$search}%");
                        });

                    });
                }
                
            $courses = $coursesQuery
                ->orderBy('id', 'desc')
                ->paginate(8)
                ->appends(request()->query());

            // Active courses count
            $activeCoursesCount = (clone $baseQuery)
                ->where('status', 'active')
                ->count();
            // Total courses count
            $totalCoursesCount = (clone $baseQuery)
                ->count();
            // Pending courses count
            $inactiveCoursesCount = (clone $baseQuery) 
              ->where('status', 'inactive')
                ->count();
            // Pending courses count
            $draftCoursesCount = (clone $baseQuery) 
              ->where('status', 'is_draft')
                ->count();

            return view(
                'frontend.instructor-dashboard.course.index',
                compact('totalCoursesCount','courses', 'activeCoursesCount','inactiveCoursesCount','draftCoursesCount')
            );

        } else {
            return view($this->admin_error_view);
        }
    }

    public function create()
    {
        $flag = checkPermission($this->pageName);
        if ($flag == 1) {
            return view('frontend.instructor-dashboard.course.create');
        } else {
            return view($this->admin_error_view);
        }
    }

    public function editView(string $id)
    {
        $course = $this->findOwnedCourseOrFail($id);
        Session::put('course_create', $id);
        $editMode = true;

        return view('frontend.instructor-dashboard.course.create', compact('course', 'editMode'));
    }

    /**
     * Per-course attendance watchlist for the instructor.
     *
     * "Attended a class" = the student has at least one LiveClassAttendance
     * row with duration_seconds >= 60. The 60-second floor filters out
     * flaky-network momentary connects that the launcher logs but that
     * weren't real attendance — without it a student who refreshed three
     * times would count as "attended" without ever sitting in the class.
     *
     * "At risk" = attendance % strictly LESS THAN course.attendance_threshold_percent.
     * Threshold = 0 disables flagging entirely (nobody's at-risk; meeting
     * is optional).
     *
     * IDOR gate: $this->findOwnedCourseOrFail($id) already aborts 404 for
     * courses the caller doesn't own — same gate as edit/delete.
     */
    public function attendanceWatchlist(string $id): \Illuminate\View\View
    {
        $course = $this->findOwnedCourseOrFail($id);
        $rows   = $this->computeAttendanceWatchlist($course);

        // #11 — Attendance heatmap (2026-05-12). For every enrolled
        // student, per live class, compute one of three states:
        //   'attended'  → >= 60s total in the class
        //   'partial'   → < 60s but at least one attendance row
        //   'missed'    → no attendance row at all
        // Surfaced as a grid in the watchlist view. Only computed when
        // there are both live classes AND students; otherwise empty grid.
        $heatmap = $this->computeAttendanceHeatmap($course, $rows['students']);

        // #6 — Course-level attendance trend (2026-05-12). One bar per
        // live class showing what % of enrolled students attended.
        // Plotted in chronological order so an instructor reads it like
        // a "did engagement drop off mid-cohort?" line.
        $trend = $this->computeAttendanceTrend($course, (int) $rows['totals']['enrolled']);

        return view('frontend.instructor-dashboard.course.attendance-watchlist', [
            'course'    => $course,
            'rows'      => $rows['students'],
            'totals'    => $rows['totals'],
            'threshold' => (int) ($course->attendance_threshold_percent ?? 75),
            'heatmap'   => $heatmap,
            'trend'     => $trend,
        ]);
    }

    /**
     * Per-class attendance % series (#6). For each live class in the
     * course, count distinct users who attended (>=60s aggregate),
     * divided by total enrolled students.
     *
     * Returns: list of {date, title, attended_count, percent}.
     * Empty when no live classes exist; downstream view hides the
     * trend card in that case.
     */
    private function computeAttendanceTrend(\App\Models\Course $course, int $enrolled): array
    {
        $classes = \App\Models\CourseLiveClass::where('course_id', $course->id)
            ->with('lesson:id,title')
            ->orderBy('start_time')
            ->get(['id', 'lesson_id', 'start_time']);

        if ($classes->isEmpty() || $enrolled === 0) {
            return [];
        }

        // Per-class distinct attendee count, one query.
        $attendedCounts = \Illuminate\Support\Facades\DB::table('live_class_attendances as a')
            ->whereIn('a.course_live_class_id', $classes->pluck('id'))
            ->where('a.duration_seconds', '>=', 60)
            ->selectRaw('a.course_live_class_id, COUNT(DISTINCT a.user_id) as c')
            ->groupBy('a.course_live_class_id')
            ->pluck('c', 'a.course_live_class_id')
            ->all();

        $out = [];
        foreach ($classes as $c) {
            $attended = (int) ($attendedCounts[$c->id] ?? 0);
            $pct      = $enrolled > 0 ? round(($attended / $enrolled) * 100, 1) : 0.0;
            $out[] = [
                'date'           => $c->start_time
                    ? \Illuminate\Support\Carbon::parse($c->start_time)->format('M d')
                    : '—',
                'title'          => (string) ($c->lesson?->title ?? __('Live class')),
                'attended_count' => $attended,
                'enrolled'       => $enrolled,
                'percent'        => $pct,
            ];
        }
        return $out;
    }

    /**
     * Build a student × class grid of attendance states (#11).
     *
     * Returns a structure shaped for the Blade table:
     *   [
     *     'classes'  => [ {id,date,title}, ... ],          // columns
     *     'students' => [ {user_id,name,cells: ['attended'|'partial'|'missed', ...]}, ... ],
     *   ]
     *
     * One query joins course_live_classes ⋈ live_class_attendances and
     * groups by (class_id, user_id) — O(classes × students) cells but
     * O(1) DB roundtrips.
     */
    private function computeAttendanceHeatmap(\App\Models\Course $course, array $students): array
    {
        if (empty($students)) {
            return ['classes' => [], 'students' => []];
        }
        $courseId = (int) $course->id;

        $classes = \App\Models\CourseLiveClass::where('course_id', $courseId)
            ->with('lesson:id,title')
            ->orderBy('start_time')
            ->get(['id', 'lesson_id', 'start_time']);

        if ($classes->isEmpty()) {
            return ['classes' => [], 'students' => []];
        }

        $classIds   = $classes->pluck('id')->all();
        $studentIds = array_column($students, 'user_id');

        // Per-cell totals: SUM(duration_seconds) per (class, user).
        // Includes zero-duration / null-left_at rows (joined briefly)
        // by COALESCEing to 0.
        $cellAggregates = \Illuminate\Support\Facades\DB::table('live_class_attendances')
            ->whereIn('course_live_class_id', $classIds)
            ->whereIn('user_id', $studentIds)
            ->selectRaw('course_live_class_id, user_id, COALESCE(SUM(duration_seconds),0) as total, COUNT(*) as sessions')
            ->groupBy(['course_live_class_id', 'user_id'])
            ->get();

        // Index by "classId:userId" for O(1) lookup when filling the grid.
        $byKey = [];
        foreach ($cellAggregates as $row) {
            $byKey[$row->course_live_class_id . ':' . $row->user_id] = [
                'total'    => (int) $row->total,
                'sessions' => (int) $row->sessions,
            ];
        }

        $colHeaders = $classes->map(fn ($c) => (object) [
            'id'    => (int) $c->id,
            'title' => (string) ($c->lesson?->title ?? __('Live class')),
            'date'  => $c->start_time
                ? \Illuminate\Support\Carbon::parse($c->start_time)->format('M d')
                : '—',
        ])->all();

        $studentRows = [];
        foreach ($students as $s) {
            $cells = [];
            foreach ($classIds as $cid) {
                $key = $cid . ':' . $s['user_id'];
                if (isset($byKey[$key])) {
                    $cells[] = $byKey[$key]['total'] >= 60 ? 'attended' : 'partial';
                } else {
                    $cells[] = 'missed';
                }
            }
            $studentRows[] = [
                'user_id' => $s['user_id'],
                'name'    => $s['name'] ?: $s['email'],
                'cells'   => $cells,
            ];
        }

        return ['classes' => $colHeaders, 'students' => $studentRows];
    }

    /**
     * CSV export of the at-risk watchlist. Streamed via streamDownload for
     * the same reason as the per-lesson export — large enrollments (1000s
     * of students) shouldn't OOM the request.
     */
    public function attendanceWatchlistExport(string $id): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $course = $this->findOwnedCourseOrFail($id);
        $rows   = $this->computeAttendanceWatchlist($course);
        $threshold = (int) ($course->attendance_threshold_percent ?? 75);

        $filename = sprintf(
            'attendance-watchlist_%s_%s.csv',
            \Illuminate\Support\Str::slug((string) ($course->title ?? 'course'), '-') ?: 'course',
            now()->format('Y-m-d'),
        );

        $callback = function () use ($course, $threshold, $rows) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM so Excel renders Devanagari / emoji names cleanly.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['Course',              (string) ($course->title ?? '')]);
            fputcsv($out, ['Threshold (%)',       $threshold]);
            fputcsv($out, ['Total live classes',  $rows['totals']['total_classes']]);
            fputcsv($out, ['Enrolled students',   $rows['totals']['enrolled']]);
            fputcsv($out, ['At-risk students',    $rows['totals']['at_risk']]);
            fputcsv($out, ['Exported at',         now()->format('Y-m-d H:i:s')]);
            fputcsv($out, []);

            fputcsv($out, [
                'Name', 'Email', 'Attended', 'Total classes', 'Attendance %', 'Status',
            ]);
            foreach ($rows['students'] as $s) {
                fputcsv($out, [
                    $s['name'],
                    $s['email'],
                    $s['attended_count'],
                    $s['total_classes'],
                    $s['percent'],
                    $s['at_risk'] ? 'At risk' : 'OK',
                ]);
            }

            fclose($out);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type'           => 'text/csv; charset=UTF-8',
            'Cache-Control'          => 'no-cache, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Shared computation between the HTML view and the CSV export. Single
     * source of truth so the two never disagree on who's at-risk.
     *
     * Implementation notes:
     *   - Filters out duration_seconds < 60 — flaky reconnects don't count
     *     as "attended". The launcher posts a 'join' on EVERY connection-
     *     change event so an unstable network can manufacture rows we
     *     shouldn't credit.
     *   - Uses three small queries instead of one big JOIN: total classes
     *     (scalar), enrolled users (one query), attended counts (one query
     *     with GROUP BY). Cheaper than COUNT(DISTINCT) inside a subquery
     *     repeated per row.
     *
     * @return array{
     *   students: list<array{user_id:int,name:string,email:string,attended_count:int,total_classes:int,percent:float,at_risk:bool}>,
     *   totals: array{total_classes:int,enrolled:int,at_risk:int}
     * }
     */
    private function computeAttendanceWatchlist(\App\Models\Course $course): array
    {
        $courseId  = (int) $course->id;
        $threshold = (int) ($course->attendance_threshold_percent ?? 75);

        // Count live classes in this course (canonical column is course_id;
        // fall back to lesson.course_id only if we're chasing legacy rows
        // that never wrote course_id on the live class row itself).
        $totalClasses = (int) \App\Models\CourseLiveClass::where('course_id', $courseId)->count();

        // Enrolled users with access. Using DB::table for the join to avoid
        // pulling the full Eloquent user model when we only need 3 columns.
        $enrolled = \Illuminate\Support\Facades\DB::table('enrollments')
            ->join('users', 'users.id', '=', 'enrollments.user_id')
            ->where('enrollments.course_id', $courseId)
            ->where('enrollments.has_access', 1)
            ->select('users.id as user_id', 'users.name', 'users.email')
            ->get()
            ->keyBy('user_id');

        // Attended class counts per user — single grouped query.
        // duration_seconds >= 60 filter strips flaky-reconnect noise; the
        // launcher posts join on every connection-change so multi-second
        // blips would otherwise inflate counts.
        $attended = $totalClasses > 0 && $enrolled->isNotEmpty()
            ? \Illuminate\Support\Facades\DB::table('live_class_attendances as a')
                ->join('course_live_classes as c', 'c.id', '=', 'a.course_live_class_id')
                ->where('c.course_id', $courseId)
                ->where('a.duration_seconds', '>=', 60)
                ->whereIn('a.user_id', $enrolled->keys()->all())
                ->select('a.user_id', \Illuminate\Support\Facades\DB::raw('COUNT(DISTINCT a.course_live_class_id) as attended_count'))
                ->groupBy('a.user_id')
                ->pluck('attended_count', 'a.user_id')
            : collect();

        $students = [];
        $atRiskCount = 0;
        foreach ($enrolled as $userId => $user) {
            $count   = (int) ($attended[$userId] ?? 0);
            $percent = $totalClasses > 0 ? round(($count / $totalClasses) * 100, 1) : 0.0;
            // Threshold == 0 disables flagging — nobody is at-risk when the
            // bar is zero, even with zero attendance.
            $atRisk  = $threshold > 0 && $totalClasses > 0 && $percent < $threshold;
            if ($atRisk) {
                $atRiskCount++;
            }
            $students[] = [
                'user_id'        => (int) $userId,
                'name'           => (string) ($user->name ?? ''),
                'email'          => (string) ($user->email ?? ''),
                'attended_count' => $count,
                'total_classes'  => $totalClasses,
                'percent'        => $percent,
                'at_risk'        => $atRisk,
            ];
        }

        // Sort at-risk first (instructor's eye lands on them), then by
        // attendance % ascending, then by name for stability.
        usort($students, function ($a, $b) {
            if ($a['at_risk'] !== $b['at_risk']) {
                return $a['at_risk'] ? -1 : 1;
            }
            if ($a['percent'] !== $b['percent']) {
                return $a['percent'] <=> $b['percent'];
            }
            return strcasecmp($a['name'], $b['name']);
        });

        return [
            'students' => $students,
            'totals'   => [
                'total_classes' => $totalClasses,
                'enrolled'      => $enrolled->count(),
                'at_risk'       => $atRiskCount,
            ],
        ];
    }

    public function store(Request $request)
    {
        // FT-IDOR-22 fix (2026-05-28) — was no permission gate.
        // store() is the multi-step course builder's first-step
        // entry point: it creates a new Course row when edit_mode != 1
        // and persists title/description/thumbnail edits otherwise.
        // Other methods in this class gate on `checkPermission($this->pageName)`
        // ($this->pageName === 'courses' from the constructor); store
        // and update were the two big writers without the gate, so
        // a sub-staff user without the `courses` permission could
        // still mint courses on behalf of the coach. Restore parity.
        $flag = checkPermission($this->pageName);
        if ($flag != 1) {
            return response()->json([
                'status'  => 'error',
                'message' => __('You do not have permission to perform this action.'),
            ], 403);
        }

        $rules = [
            'title' => ['required', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:255'],
            'thumbnail' => ['required', 'max:255'],
            'demo_video_source' => ['nullable', 'string'],
            // 'price' => ['required', 'numeric', 'min:0'],
            // 'discount_price' => ['nullable', 'numeric', new ValidateDiscountRule],
            'description' => ['required', 'string', 'max:5000'],
            // Audit 2026-05-19 phase 4 — SRS-CLC-001 §4.1:
            // Controlled enum, widened by migration 2026_05_19_100000.
            // 'nullable' so legacy callers without the field still pass
            // (we default to 'live' when missing — handled below).
            'type' => ['nullable', 'in:live,recorded,hybrid'],
        ];
        $messages = [
            'title.required' => __('Title is required'),
            'title.max' => __('Title must be less than 255 characters long'),
            'seo_description.string' => __('Seo description must be a string'),
            'seo_description.max' => __('Seo description must be less than 255 characters long'),
            'thumbnail.required' => __('Thumbnail is required'),
            'thumbnail.max' => __('Thumbnail must be less than 255 characters long'),
            'demo_video_source.string' => __('Demo video source must be a string'),
            'path.string' => __('Path must be a string'),
            // 'price.required' => __('Price is required'),
            // 'price.numeric' => __('Price must be a number'),
            // 'price.min' => __('Price must be greater than or equal to 0'),
            'discount.numeric' => __('Discount must be a number'),
            'description.required' => __('Description is required'),
            'description.string' => __('Description must be a string'),
            'description.max' => __('Description must be less than 5000 characters long'),
            'instructor.required' => __('Instructor is required'),
            'instructor.numeric' => __('Instructor must be a number'),
            'type.in' => __('Course type must be Live Class, Recorded Course, or Live + Recorded.'),
        ];

        $request->validate($rules, $messages);
        if ($request->edit_mode == 1) {
            $course = $this->findOwnedCourseOrFail($request->id);
        } else {
            $course = new Course;
            $slug = generateUniqueSlug(Course::class, $request->title);
            $course->slug = $slug;
        }

        $coachId = (userAuth()->role != 'instructor') ? userAuth()->coach_id : userAuth()->id;
        $course->title = $request->title;
        $course->instructor_id = $coachId; // auth('web')->user()->id;
        if (! isset($request->id)) {
            $course->added_by = userAuth()->id;
        }
        $course->seo_description = $request->seo_description;
        $course->thumbnail = $request->thumbnail;
        $course->demo_video_storage = $request->demo_video_storage;
        $course->demo_video_source = $request->demo_video_storage == 'upload' ? $request->upload_path : $request->external_path;
        // $course->price = $request->price;
        // $course->discount = $request->discount_price;
        $course->description = $request->description;
        // Audit 2026-05-19 phase 4 — SRS-CLC-001 §4.1:
        // Only overwrite type when an explicit choice arrives. Existing
        // rows keep their stored value; brand-new rows default to 'live'.
        if ($request->filled('type')) {
            $course->type = $request->input('type');
        } elseif (!$course->exists) {
            $course->type = 'live';
        }
        $course->save();

        // save course id in session
        Session::put('course_create', $course->id);

        return response()->json([
            'status' => 'success',
            'message' => __('Updated successfully'),
            'redirect' => route('instructor.courses.edit', ['id' => $course->id, 'step' => $request->next_step]),
        ]);
    }

    public function edit(Request $request)
    {

        if (! Session::get('course_create')) {
            return redirect(route('instructor.courses.create'));
        }

        switch (request('step')) {
            case '1':
                $course = $this->findOwnedCourseOrFail($request->id);
                $editMode = true;

                return view('frontend.instructor-dashboard.course.create', compact('course', 'editMode'));
                break;
            case '2':
                $courseId = request('id');
                $categories = CourseCategory::where('parent_id', null)->where('status', 1)->get();
                $course = $this->findOwnedCourseOrFail($courseId);
                $levels = CourseLevel::with(['translation'])->where('status', 1)->get();
                $category = CourseCategory::find($course->category_id);
                $subcategory = CourseCategory::where('parent_id', $course->category_id)->get();
                $languages = CourseLanguage::where('status', 1)->get();

                return view('frontend.instructor-dashboard.course.more-information', compact(
                    'categories',
                    'courseId',
                    'course',
                    'levels',
                    'category',
                    'subcategory',
                    'languages'
                ));
                break;
            case '3':
                $chapters = CourseChapter::with(['chapterItems'])->where(['course_id' => $request->id, 'status' => 'active'])->orderBy('order')->get();

                return view('frontend.instructor-dashboard.course.course-content', compact('chapters'));
                break;
            case '4':
                $course = $this->findOwnedCourseOrFail($request->id);

                $year = $request->input('year', Carbon::now()->year);

                // Get start and end of the year
                $start = Carbon::createFromDate($year, 1, 1)->startOfYear();
                $end = $start->copy()->endOfYear();

                $orderItems = OrderItem::where('course_id', $course->id)->whereHas('order', function ($q) {
                    $q->where('payment_status', 'paid');
                })->selectRaw('YEAR(created_at) as year,
                MONTH(created_at) as month,
                SUM(price) as total_price,
                AVG(commission_rate) as commission_rate')->whereBetween('created_at', [$start, $end])->groupBy('year', 'month')->orderBy('month', 'asc')->get();

                $monthlySales = array_fill(1, 12, 0);
                $commissionMonthly = array_fill(1, 12, 0);
                $netMonthly = array_fill(1, 12, 0);

                foreach ($orderItems as $item) {
                    $totalAmount = $item->total_price;
                    $commissionAmount = $totalAmount * ($item->commission_rate / 100);
                    $netEarnings = $totalAmount - $commissionAmount;
                    $monthlySales[$item->month] = $totalAmount;
                    $commissionMonthly[$item->month] = $commissionAmount;
                    $netMonthly[$item->month] = $netEarnings;
                }

                $oldestYear = Carbon::parse(OrderItem::orderBy('created_at', 'asc')->first()?->created_at)->year ?? Carbon::now()->year;
                $latestYear = Carbon::parse(OrderItem::orderBy('created_at', 'desc')->first()?->created_at)->year ?? Carbon::now()->year;
                $month_labels = array_map(fn ($month) => Carbon::create()->month($month)->format('M'), array_keys($monthlySales));

                $commission_monthly_data = array_values($commissionMonthly);
                $net_monthly_data = array_values($netMonthly);
                $order_monthly_data = array_values($monthlySales);

                $total_chapter_items = $course->chapterItems()->count();
                $enrollments = $course->enrollments;
                $progress_ranges = array_fill_keys(['0-10', '10-20', '20-30', '30-40', '40-50', '50-60', '60-70', '70-80', '80-90', '90-100'], 0);

                foreach ($enrollments as $enrollment) {
                    $percentage = min(($total_chapter_items > 0 ? $course->progresses()->where('user_id', $enrollment->user_id)->count() / $total_chapter_items * 100 : 0), 100);

                    $range = $percentage == 100 ? '90-100' : floor($percentage / 10) * 10 .'-'.(floor($percentage / 10) * 10 + 10);
                    $progress_ranges[$range]++;
                }
                $total_enrollments = $enrollments->count();

                return view('frontend.instructor-dashboard.course.analytics', compact('course', 'progress_ranges', 'total_enrollments', 'oldestYear', 'latestYear', 'month_labels', 'commission_monthly_data', 'net_monthly_data', 'order_monthly_data'));
                break;
            case '5':
                $courseId = request('id');
                $course = $this->findOwnedCourseOrFail($courseId);

                return view('frontend.instructor-dashboard.course.finish', compact('course'));
                break;
            default:
                break;
        }
    }

    public function update(Request $request)
    {
        // FT-IDOR-22 fix — see store(). update() drives course-builder
        // steps 2–5 (pricing / publishing). Staff without the `courses`
        // permission shouldn't be able to update the coach's published
        // course state. IDOR remains gated by the per-step
        // findOwnedCourseOrFail() calls in storeMoreInfo / storeFinish.
        $flag = checkPermission($this->pageName);
        if ($flag != 1) {
            return response()->json([
                'status'  => 'error',
                'message' => __('You do not have permission to perform this action.'),
            ], 403);
        }

        switch ($request->step) {
            case '2':
                $coachIdForTax = $this->effectiveCoachId();
                $request->validate([
                    // 'course_duration' => ['required', 'numeric', 'min:0'],
                    'category' => ['required'],
                    'sub_category' => ['required'],
                    'price' => ['required', 'numeric', 'min:0'],
                    'discount_price' => ['nullable', 'numeric', new ValidateDiscountRule],
                    // Cap to 0–100 server-side too — the input's min/max HTML
                    // attributes are client-side hints, not security.
                    'attendance_threshold_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
                    // F49 (audit 2026-06-26) — tax_rate_id must belong to THIS coach
                    // (TaxRate is coach-scoped). Closure keeps it table-name-safe.
                    'tax_rate_id' => ['nullable', 'integer', function ($attr, $value, $fail) use ($coachIdForTax) {
                        if ($value && ! \App\Models\TaxRate::where('id', $value)->where('coach_id', $coachIdForTax)->exists()) {
                            $fail(__('Selected tax rate is invalid.'));
                        }
                    }],
                ]);
                $this->storeMoreInfo($request);

                return response()->json([
                    'status' => 'success',
                    'message' => __('Updated Successfully'),
                    'redirect' => route('instructor.courses.edit', ['id' => Session::get('course_create'), 'step' => $request->next_step]),
                ]);
                break;
            case '3':
                return response()->json([
                    'status' => 'success',
                    'message' => __('Updated successfully'),
                    'redirect' => route('instructor.courses.edit', ['id' => Session::get('course_create'), 'step' => $request->next_step]),
                ]);
            case '4':
                return response()->json([
                    'status' => 'success',
                    'message' => __('Updated successfully'),
                    'redirect' => route('instructor.courses.edit', ['id' => Session::get('course_create'), 'step' => $request->next_step]),
                ]);
            case '5':
                $request->validate([
                    'status' => ['required'],
                    'message_for_reviewer' => ['nullable', 'max:1000'],
                ]);

                // ========================Check Batch Creation==================================
                // 2026-06-03 — only LIVE / HYBRID (Live + Recorded) courses host
                // batches, so only they need an active batch before going live.
                // RECORDED courses can't have batches at all (see the recorded
                // guard in the course-batches flow), so requiring one here made
                // recorded courses impossible to publish (deadlock). Skip the
                // batch gate for recorded courses.
                if ($request->status == 'active') {
                    $courseType = Course::where('id', $request->course_id)->value('type') ?? 'live';
                    $needsBatch = in_array($courseType, ['live', 'hybrid'], true);

                    if ($needsBatch) {
                        $batches = CourseBatch::where('course_id', $request->course_id)->where('status', 'active')->where('end_date', '>=', Carbon::today())->count();

                        if ($batches == 0) {
                            return response()->json([
                                'status' => 'batch',
                                'message' => __('No batch created so create a batch'),
                            ]);
                        }
                    }
                }
                // ==========================================================
                $this->storeFinish($request);

                return response()->json([
                    'status' => 'success',
                    'message' => __('Course Updated Successfully'),
                    'redirect' => $request->next_step == 5 ? route('instructor.courses.index') : route('instructor.courses.edit', ['id' => Session::get('course_create'), 'step' => $request->next_step]),
                ]);

            default:
                // code...
                break;
        }
    }

    public function storeMoreInfo(Request $request)
    {
        $course = $this->findOwnedCourseOrFail($request->course_id);
        $course->capacity = $request->capacity ?? '';
        $course->duration = $request->course_duration ?? '';
        $course->price = $request->price;
        $course->discount = $request->discount_price;
        $course->tax_rate_id = $request->tax_rate_id ?: null; // 2026-06-13 — optional per-course tax rate
        $course->category_id = $request->category;
        $course->sub_category_id = $request->sub_category;
        $course->qna = $request->qna;
        $course->downloadable = $request->downloadable;
        $course->certificate = $request->certificate;
        $course->partner_instructor = $request->partner_instructor;
        // Validated 0–100 in the case '2' block of update(). Coalesce to 75
        // (the migration default) for legacy course rows that haven't gone
        // through this form since the column was added.
        if ($request->has('attendance_threshold_percent')) {
            $course->attendance_threshold_percent = (int) $request->input('attendance_threshold_percent', 75);
        }
        $course->save();

        // delete unselected partner instructor
        CoursePartnerInstructor::where('course_id', $course->id)
            ->whereNotIn('instructor_id', $request->partner_instructors ?? [])->delete();

        // insert partner instructor
        foreach ($request->partner_instructors ?? [] as $instructor) {
            CoursePartnerInstructor::updateOrCreate(
                ['course_id' => $course->id, 'instructor_id' => $instructor],
            );
        }

        // insert levels
        CourseSelectedLevel::where('course_id', $course->id)
            ->whereNotIn('level_id', $request->levels ?? [])->delete();

        foreach ($request->levels ?? [] as $level) {
            CourseSelectedLevel::updateOrCreate(
                ['course_id' => $course->id, 'level_id' => $level],
            );
        }

        // insert languages
        CourseSelectedLanguage::where('course_id', $course->id)
            ->whereNotIn('language_id', $request->languages ?? [])->delete();

        foreach ($request->languages ?? [] as $language) {
            CourseSelectedLanguage::updateOrCreate(
                ['course_id' => $course->id, 'language_id' => $language],
            );
        }
    }

    public function storeFinish(Request $request)
    {
        $course = $this->findOwnedCourseOrFail($request->course_id);
        $course->message_for_reviewer = $request->message_for_reviewer;
        $course->status = $request->status;

        // 2026-06-01 (audit H3) — coach publish was self-approving
        // (is_approved='approved'), bypassing the admin moderation flow and
        // putting the course in the PUBLIC catalog with no review. This is now
        // gated by the platform setting `require_course_approval`:
        //   - OFF (default) → keep the existing behaviour (active ⇒ approved),
        //     so nothing changes unless an admin opts in.
        //   - ON            → a coach setting status=active leaves the course
        //     is_approved='pending' until an admin approves it.
        $requireApproval = (bool) (optional(\Illuminate\Support\Facades\Cache::get('setting'))->require_course_approval ?? false);
        $course->is_approved = ($request->status == 'active' && ! $requireApproval) ? 'approved' : 'pending';

        $course->save();

        // Enterprise H-A — audit course publish/finalize.
        \App\Services\ActivityLogger::log(
            \App\Models\ActivityLog::UPDATED, 'course', $course,
            null, ['status' => $course->status, 'is_approved' => $course->is_approved],
            'Course "' . $course->title . '" finalized (status: ' . $course->status . ')'
        );
    }

    public function getInstructors(Request $request)
    {
        // SECURITY (audit 2026-05-22)
        //   1. Return ONLY id+name+image — original returned the full User
        //      model which exposed `password` (hashed), `remember_token`,
        //      `two_factor_*`, `coach_id`, `referral_wallet_balance`, etc.
        //      All those columns are now stripped via ->select().
        //   2. Require a non-empty search term so this endpoint can't be
        //      used to enumerate every instructor on the platform.
        //   3. Cap result count to 20 — prevents response-size DoS.
        $q = (string) $request->q;
        if (mb_strlen(trim($q)) < 2) {
            return response()->json([]);
        }

        $instructors = User::query()
            ->select('id', 'name', 'image')
            ->where('role', 'instructor')
            ->where('status', 'active')
            ->where('id', '!=', auth()->id())
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', '%' . $q . '%')
                      ->orWhere('email', 'like', '%' . $q . '%');
            })
            ->limit(20)
            ->get();

        return response()->json($instructors);
    }

    public function getFiltersByCategory(string $id)
    {
        $levels = CourseLevel::with(['translation'])->where('status', 1)->get();
        $category = CourseCategory::find($id);
        $languages = CourseLanguage::where('status', 1)->get();

        return view('frontend.instructor-dashboard.course.partials.filters', compact('levels', 'category', 'languages'))->render();
    }

    public function showDeleteRequest(Request $request, $id)
    {
        return view('frontend.instructor-dashboard.course.partials.course-delete-request-modal', compact('id'));
    }

    public function sendDeleteRequest(Request $request)
    {

        $request->validate([
            'message' => ['required', 'max:1000'],
        ], ['message.required' => __('message is required'), 'message.max' => __('message should not be more than 1000 characters')]);
        $course = $this->findOwnedCourseOrFail($request->course_id);
        // check if there is already a request
        // if (CourseDeleteRequest::where('course_id', $course->id)->exists()) {
        //     return redirect()->back()->with(['messege' => __('you already have a pending request for this course'), 'alert-type' => 'error']);
        // }

        // $deleteRequest = new CourseDeleteRequest;
        // $deleteRequest->course_id = $course->id;
        // $deleteRequest->message = $request->message;
        // $deleteRequest->save();
        $course->status = 'is_draft';
        $course->is_approved = 'pending';
        $course->coach_soft_delete = 1;
        $course->save();

        return redirect()->back()->with(['messege' => __('Request sent successfully'), 'alert-type' => 'success']);
    }

    // ==================== Course Batches CRUD ====================

    public function getBatchesByCourse($course_id)
    {
        // SECURITY (audit 2026-05-22) — original version had NO ownership check
        // so any authenticated instructor could fetch another coach's batch
        // list + chapter list by URL-guessing course_id. Now gated by
        // findOwnedCourseOrFail() which throws 404 if course doesn't belong
        // to the current coach.
        $course = $this->findOwnedCourseOrFail($course_id);

        $batchQuery = CourseBatch::where('course_id', $course->id);

        // 2026-07-06 (Role Permission Test doc) — per-staff batch scope.
        // A real coach (null) sees every batch of the course; a staff teacher
        // only sees the batches their coach has assigned to them. Mirrors the
        // scope already enforced in Coach\LiveClassController::index() so the
        // live-class create dropdown never surfaces unassigned batches.
        $assignedBatchIds = \App\Models\TeacherBatchAssignment::assignedBatchIdsFor((int) userAuth()->id);
        if ($assignedBatchIds !== null) {
            $batchQuery->whereIn('id', $assignedBatchIds ?: [0]);
        }

        $batches = $batchQuery->get();

        // 2026-07-06 — expose a ready-to-use datetime-local value so the
        // live-class form can auto-fill Start Time when a batch is picked
        // (still editable). Combines the batch start_date with its start_time.
        $batches->transform(function ($b) {
            $b->start_datetime_local = null;
            if (! empty($b->start_time)) {
                try {
                    $date = $b->start_date ? \Carbon\Carbon::parse($b->start_date) : \Carbon\Carbon::today();
                    $time = \Carbon\Carbon::parse($b->start_time);
                    $b->start_datetime_local = $date->format('Y-m-d') . 'T' . $time->format('H:i');
                } catch (\Throwable $e) {
                    $b->start_datetime_local = null;
                }
            }
            return $b;
        });

        $chapters = CourseChapter::where('course_id', $course->id)->orderBy('order')->get();

        return response()->json([
            'batches' => $batches,
            'chapters' => $chapters,
        ]);
    }

    public function batchesIndex(Request $request, $course_id = null)
    {
        $this->pageName = 'course-batches';
        $flag = checkPermission($this->pageName);

        if ($flag != 1) {
            return view($this->admin_error_view);
        }
        $coachId = (userAuth()->role != 'instructor') ? userAuth()->coach_id : userAuth()->id;

        // 2026-07-09 (Some Changes.docx) — listing filters + search. Server-side
        // so it spans ALL of the coach's batches (bounded & tenant-scoped: still
        // nested under the coach's own courses below).
        $search = trim((string) $request->get('q', ''));
        $status = in_array($request->get('status'), ['active', 'inactive'], true) ? $request->get('status') : null;
        $date   = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->get('date')) ? $request->get('date') : null;
        $isFiltering = $search !== '' || $status !== null || $date !== null;

        // Audit 2026-05-19 (post-Phase 4C feedback) — only Live / Hybrid
        // courses can have batches. Recorded-only and legacy
        // 'course'/'webinar' rows are excluded — matches the Live Class
        // dropdown filter so a coach can't end up with a batch that
        // can't host live classes. The backfill migration
        // (2026_05_19_120000) already flipped legacy courses that had
        // existing live classes to 'live', so no active workflow regresses.
        //
        // 2026-05-20 — per-teacher batch scope. A staff teacher with
        // assignments only sees the batches they were granted; the
        // courses collection is also filtered to courses that contain
        // at least one assigned batch so the UI doesn't show empty
        // course cards.
        $uid = (int) userAuth()->id;
        $assignedBatchIds = \App\Models\TeacherBatchAssignment::assignedBatchIdsFor($uid);
        $isStaff = $assignedBatchIds !== null; // coach → null → no narrowing

        // 2026-07-10 (Staff Panel) — a staff/teacher must see batches they CREATED
        // (during course creation), not only ones explicitly assigned to them.
        // A staff-authored course sets courses.added_by = the staff id; batches
        // under those courses are theirs. Live/hybrid only (recorded courses have
        // no batches). These same authored courses feed the "Add Batch" dropdown.
        $staffCourseIds = $isStaff
            ? Course::where('added_by', $uid)->whereIn('type', ['live', 'hybrid'])
                ->pluck('id')->map(fn ($v) => (int) $v)->all()
            : [];

        // Visible batches for staff = assigned ∪ batches under their authored courses.
        $visibleBatchIds = $assignedBatchIds; // null for coach (unbounded)
        if ($isStaff) {
            $authoredBatchIds = \App\Models\CourseBatch::whereIn('course_id', $staffCourseIds ?: [0])
                ->pluck('id')->map(fn ($v) => (int) $v)->all();
            $visibleBatchIds = array_values(array_unique(array_merge($assignedBatchIds ?: [], $authoredBatchIds)));
        }

        // Constraint applied to the batches relation (per-staff scope + filters).
        $batchConstraint = function ($q) use ($visibleBatchIds, $search, $status, $date) {
            if ($visibleBatchIds !== null) {
                $q->whereIn('id', $visibleBatchIds ?: [0]);
            }
            if ($status !== null) {
                $q->where('status', $status);
            }
            if ($date !== null) {
                $q->whereDate('created_at', $date);
            }
            if ($search !== '') {
                $q->where(function ($qq) use ($search) {
                    $qq->where('title', 'like', "%{$search}%")
                       ->orWhereHas('course', fn ($c) => $c->where('title', 'like', "%{$search}%"));
                });
            }
        };

        $coursesQuery = Course::where('instructor_id', $coachId)
            ->whereIn('type', ['live', 'hybrid']);

        if ($isStaff) {
            // Show the course cards the staff can act on: ones they authored OR
            // that contain a batch they can see.
            $visBatchCourseIds = \App\Models\CourseBatch::whereIn('id', $visibleBatchIds ?: [0])
                ->pluck('course_id')->unique()->map(fn ($v) => (int) $v)->all();
            $allowed = array_values(array_unique(array_merge($staffCourseIds, $visBatchCourseIds)));
            $coursesQuery->whereIn('id', $allowed ?: [0]);
        }

        $coursesQuery->with(['batches' => $batchConstraint]);

        // When a filter/search is active, hide courses with no matching batch so
        // the results only show what the coach searched for.
        if ($isFiltering) {
            $coursesQuery->whereHas('batches', $batchConstraint);
        }

        $courses = $coursesQuery->get();

        // "Add Batch" dropdown: only courses that NEED batches (live/hybrid). For
        // staff, only the courses THEY created; for a coach, all their live/hybrid.
        $batchCourses = $isStaff
            ? Course::whereIn('id', $staffCourseIds ?: [0])->orderBy('title')->get()
            : Course::where('instructor_id', $coachId)->whereIn('type', ['live', 'hybrid'])->orderBy('title')->get();

        $filters = ['q' => $search, 'status' => $status, 'date' => $date, 'active' => $isFiltering];

        return view('frontend.instructor-dashboard.course.batches.all-batches', compact('courses', 'batchCourses', 'filters'));
    }

    public function batchesStore(Request $request)
    {
        // 2026-07-07 — server-side RBAC. The list (batchesIndex) was gated but
        // the write actions were not, so a staff member without the create
        // permission could POST here directly. abort(403) → in-panel card for a
        // browser hit, JSON 403 for the AJAX form.
        abort_unless(checkPermission('course-batches', 'store') === 1, 403);

        $request->validate([
            'course_id' => 'required|exists:courses,id',
            'title' => 'required|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date|after_or_equal:today',
            'start_time' => 'required',
            'end_time' => 'required|after:start_time',
            // FT-VAL-16 (2026-05-28) — cap to 7 days + whitelist.
            // Pre-fix accepted arbitrary strings (and unlimited length).
            // batches.days stores JSON like ["mon","wed","fri"] — bound
            // to actual weekday names.
            'days'   => 'required|array|min:1|max:7',
            'days.*' => 'in:sun,mon,tue,wed,thu,fri,sat,Sun,Mon,Tue,Wed,Thu,Fri,Sat,sunday,monday,tuesday,wednesday,thursday,friday,saturday',
            // Audit 2026-05-18 phase 5 — optional per-batch override
            'attendance_min_percent' => 'nullable|integer|min:1|max:100',
        ]);

        $course = $this->findOwnedCourseOrFail($request->course_id);

        // Audit 2026-05-19 (post-Phase 4C feedback) — Recorded courses
        // cannot host batches. This is the server-side belt; the UI
        // already hides the option. A 422 with a clear message means
        // the coach changed the course type to 'recorded' mid-flow
        // and tried to submit anyway.
        if ($course->type === 'recorded') {
            return response()->json([
                'status'  => 'error',
                'errors'  => [
                    'course_id' => [
                        __('This is a Recorded course — batches are not available. Change the Course Type to “Live Class” or “Live + Recorded” first.'),
                    ],
                ],
            ], 422);
        }

        // 2026-06-12 — DUPLICATE-SUBMIT GUARD (server-side belt).
        // A double-clicked Save (or a network retry / two open tabs) fired this
        // create POST more than once and inserted N identical batches. The Save
        // button is now disabled client-side too, but we never trust the client:
        // collapse an identical batch (same course + title + full schedule) into
        // the one already created via firstOrCreate, so repeated requests are
        // idempotent and only ONE batch exists per submission. The identity keys
        // deliberately exclude `days` (JSON — unreliable to match) and capacity;
        // a genuine "second batch" differs by title, date or time. Global for
        // every coach — keyed off the submitted course/schedule, no hardcoding.
        $batch = CourseBatch::firstOrCreate(
            [
                'course_id'  => $request->course_id,
                'title'      => $request->title,
                'start_date' => $request->start_date,
                'end_date'   => $request->end_date,
                'start_time' => $request->start_time,
                'end_time'   => $request->end_time,
            ],
            [
                'days'                   => $request->days, // cast to JSON by the model
                'capacity'               => $request->capacity,
                'attendance_min_percent' => $request->filled('attendance_min_percent')
                    ? (int) $request->attendance_min_percent
                    : null,
                'status'                 => $request->status ?? 'active',
            ]
        );

        return response()->json([
            'status'  => 'success',
            'message' => $batch->wasRecentlyCreated
                ? __('Batch created successfully')
                : __('Batch already created'),
        ]);
    }

    public function batchesEdit($id)
    {
        $batch = $this->findOwnedBatchOrFail($id);

        return response()->json($batch);
    }

    public function batchesUpdate(Request $request, $id)
    {
        // 2026-07-07 — server-side RBAC (see batchesStore).
        abort_unless(checkPermission('course-batches', 'update') === 1, 403);

        $request->validate([
            'course_id' => 'required|exists:courses,id',
            'title' => 'required|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'start_time' => 'required',
            'end_time' => 'required',
            // FT-VAL-16 (2026-05-28) — cap to 7 days + whitelist.
            // Pre-fix accepted arbitrary strings (and unlimited length).
            // batches.days stores JSON like ["mon","wed","fri"] — bound
            // to actual weekday names.
            'days'   => 'required|array|min:1|max:7',
            'days.*' => 'in:sun,mon,tue,wed,thu,fri,sat,Sun,Mon,Tue,Wed,Thu,Fri,Sat,sunday,monday,tuesday,wednesday,thursday,friday,saturday',
            // Audit 2026-05-18 phase 5 — optional per-batch override
            'attendance_min_percent' => 'nullable|integer|min:1|max:100',
        ]);

        $batch = $this->findOwnedBatchOrFail($id);
        // Also verify the new course_id (if changed) belongs to this coach
        $this->findOwnedCourseOrFail($request->course_id);

        $batch->course_id = $request->course_id;
        $batch->title = $request->title;
        $batch->start_date = $request->start_date;
        $batch->end_date = $request->end_date;
        $batch->start_time = $request->start_time;
        $batch->end_time = $request->end_time;
        $batch->days = $request->days; // json_encode($request->days);
        $batch->capacity = $request->capacity;
        $batch->attendance_min_percent = $request->filled('attendance_min_percent')
            ? (int) $request->attendance_min_percent
            : null;
        $batch->status = $request->status ?? 'active';
        $batch->save();

        return response()->json(['status' => 'success', 'message' => __('Batch updated successfully')]);
    }

    public function getBatches(Request $request)
    {
        // SECURITY (audit 2026-05-22) — original version was explicitly
        // marked "// security" with the ownership check COMMENTED OUT,
        // meaning any authenticated instructor could fetch any other
        // coach's batches by passing ?course_id=. Now properly scoped.
        $request->validate(['course_id' => 'required|integer']);
        $course = $this->findOwnedCourseOrFail($request->course_id);

        $batches = CourseBatch::where('course_id', $course->id)->get();

        return response()->json($batches);
    }

    public function batchesDestroy($id)
    {
        // 2026-07-07 — server-side RBAC (see batchesStore).
        abort_unless(checkPermission('course-batches', 'destroy') === 1, 403);

        $batch = $this->findOwnedBatchOrFail($id);
        $batch->delete();

        return response()->json(['status' => 'success', 'message' => __('Batch deleted successfully')]);
    }

    public function getSubCategories(Request $request)
    {
        $subCategories = CourseCategory::where('parent_id', $request->category_id)
            ->with('translation')
            ->get()
            ->map(function ($sub) {
                return [
                    'id' => $sub->id,
                    'name' => $sub->translation?->name,
                ];
            });

        return response()->json($subCategories);
    }
}
