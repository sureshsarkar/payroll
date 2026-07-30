<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\TeacherBatchAssignment;
use App\Models\User;
use App\Services\MailSenderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Modules\Order\app\Models\Enrollment;
use Modules\Order\app\Models\Order;
use Modules\Order\app\Models\OrderItem;
use Modules\PaymentWithdraw\app\Models\WithdrawRequest;

class InstructorDashboardController extends Controller
{
    protected $pageName;

    public function __construct()
    {
        $this->admin_error_view = 'errors.403';
    }

    public function printInvoice(Request $request, $id)
    {
        $this->pageName = 'coach-orders';
        $flag = checkPermission($this->pageName);
        if ($flag != 1) {
            return view($this->admin_error_view);
        }

        // 2026-06-01 fix — invoice link 404'd for student-purchased orders.
        //
        // The My Sales listing paginates ORDER ITEMS, so both action links
        // (View order + Download invoice) pass an order-ITEM id. The "View
        // order" sibling (mySellsShow) correctly resolves it via
        // coachOwnedOrderItemQuery() — scoped by COURSE ownership. This
        // method, however, used:
        //     Order::where('id', $id)->where('seller_id', $coachId)->firstOrFail()
        // which was wrong twice over:
        //   1. it treated the order-item id as an ORDER id, and
        //   2. it scoped by `seller_id`, which is only ever set on coach
        //      *manual* orders — so normal student purchases of the coach's
        //      course (seller_id = null) were listed yet 404'd on invoice.
        //
        // Resolve the same way as mySellsShow so the two links stay in
        // lockstep, then render the parent order. Course-ownership scoping
        // (and the teacher batch gate inside coachOwnedOrderItemQuery) keeps
        // the PII/amount protection that the seller_id check was added for.
        $orderItem = $this->coachOwnedOrderItemQuery()
            ->with('order')
            ->where('id', $id)
            ->firstOrFail();

        $order = $orderItem->order;
        abort_if(!$order, 404);

        // 2026-06-02 — corporate: stream a real downloadable PDF instead of
        // opening the HTML invoice in a new tab. Mirrors the API invoice path
        // (DashboardController::downloadInvoice): DomPDF render + attachment.
        // Falls back to the HTML view if PDF generation ever fails, so the coach
        // never hits a hard error.
        // Eager-load what the PDF template needs (student + items + course coach).
        $order->load(['user', 'orderItems.course.instructor']);

        try {
            // invoice-pdf.blade.php is DomPDF-safe (plain tables, no flex) — the
            // legacy invoice.blade.php uses display:flex on tables, which DomPDF
            // can't render ("Parent table not found for table cell").
            $html = view('frontend.instructor-dashboard.order.invoice-pdf', compact('order'))->render();
            $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans']);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            // output() returns the PDF as a string (no direct echo/exit), so we
            // wrap it in a normal Laravel response — testable + goes through the
            // response pipeline, unlike $dompdf->stream().
            $filename = 'invoice-' . ($order->invoice_id ?? $order->id) . '.pdf';

            return response($dompdf->output(), 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (\Throwable $e) {
            \Log::warning('coach-invoice-pdf-failed', ['order_id' => $order->id, 'err' => $e->getMessage()]);

            return view('frontend.instructor-dashboard.order.invoice', compact('order'));
        }
    }

    public function index(): View
    {

        // $totalCourses = Course::where('instructor_id', userAuth()->id)->count();
        $totalCourses = Course::where(function ($query) {
            $query->where('added_by', userAuth()->id)->orWhere('instructor_id', userAuth()->id);
        })->count();

        // $totalPendingCourses = Course::where('instructor_id', userAuth()->id)->where(['status' => 'pending', 'is_approved' => 'pending'])->count();
        $totalPendingCourses = Course::where(function ($query) {
            $query->where('added_by', userAuth()->id)->orWhere('instructor_id', userAuth()->id);
        })->where(['status' => 'pending', 'is_approved' => 'pending'])->count();

        $courseIds = Course::where(function ($query) {
            $query->where('added_by', userAuth()->id)->orWhere('instructor_id', userAuth()->id);
        })->pluck('id')->toArray();

        $totalOrders = OrderItem::whereIn('course_id', $courseIds)->count();

        $totalPendingOrders = OrderItem::whereIn('course_id', $courseIds)->whereHas('order', function ($q) {
            $q->where('status', 'pending');
        })->count();
        $totalWithdraw = WithdrawRequest::where(['user_id' => auth('web')->user()->id, 'status' => 'approved'])->sum('withdraw_amount');

        // 2026-07-11 — "Offline collected this month" KPI. Effective offline
        // payments (approved/auto, not cancelled) for the coach, this calendar
        // month. Guarded so the dashboard never 500s before the migration runs.
        $offlineThisMonth = 0.0;
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('offline_payments')) {
                $opCoachId = userAuth()->role === 'instructor'
                    ? (int) userAuth()->id
                    : (int) (userAuth()->coach_id ?? userAuth()->id);
                $offlineThisMonth = (float) \App\Models\OfflinePayment::forCoach($opCoachId)
                    ->active()
                    ->whereIn('approval_status', [\App\Models\OfflinePayment::APPROVAL_AUTO, \App\Models\OfflinePayment::APPROVAL_APPROVED])
                    ->whereYear('paid_at', now()->year)
                    ->whereMonth('paid_at', now()->month)
                    ->sum('amount');
            }
        } catch (\Throwable $e) {
            // never break the dashboard
        }

        // 2026-07-18 (Dashboard Nav Enhancement #2) — the "Today's Pulse" KPI
        // strip (revenue / new orders / live-classes-today, cached 60s) has been
        // retired. Those day-scoped figures duplicated Lifetime Performance and
        // the new Reports module, so the strip, its cached query, and the
        // $coachPulse view variable were all removed.

        $coachId = (int) userAuth()->id;

        // Audit 2026-05-19 phase 4 — "My Content" section.
        // The dashboard previously only showed COUNTS for courses,
        // batches, and live classes. Coaches needed to click into 3
        // separate pages to see what's actually scheduled / managed.
        // This section surfaces the next 5 of each so a coach can see
        // "what am I running" at a glance.
        $myContent = \Illuminate\Support\Facades\Cache::remember(
            "instructor.dashboard.content:$coachId",
            60,
            function () use ($coachId, $courseIds) {
                // Upcoming live classes — next 5 starting from "now", any
                // course owned by this coach. start_time is varchar in
                // this schema, parsed via STR_TO_DATE; falls back to
                // LEFT(start_time, 10) >= today for misformatted rows.
                $now      = now();
                $todayStr = $now->toDateString();
                $upcomingLive = empty($courseIds) ? collect() : \DB::table('course_live_classes as c')
                    ->leftJoin('courses as co', 'co.id', '=', 'c.course_id')
                    ->leftJoin('course_batches as b', 'b.id', '=', 'c.batch_id')
                    ->leftJoin('course_chapter_lessons as l', 'l.id', '=', 'c.lesson_id')
                    ->whereIn('c.course_id', $courseIds)
                    ->whereNull('c.ended_at')
                    ->where(function ($q) use ($todayStr) {
                        $q->whereRaw('STR_TO_DATE(c.start_time, "%Y-%m-%d %H:%i:%s") >= ?', [$todayStr.' 00:00:00'])
                          ->orWhereRaw('LEFT(c.start_time, 10) >= ?', [$todayStr]);
                    })
                    ->orderBy('c.start_time', 'asc')
                    ->limit(5)
                    ->get([
                        'c.id', 'c.start_time', 'c.batch_id', 'c.course_id',
                        'c.expected_duration_minutes', 'c.verification_status',
                        'co.title as course_title',
                        'b.title as batch_title',
                        'l.title as lesson_title',
                    ]);

                // Active batches — all of them, but with the NEXT scheduled
                // class included so the row carries a "next" timestamp.
                $activeBatches = \App\Models\CourseBatch::query()
                    ->whereHas('course', function ($q) use ($coachId) {
                        $q->where('instructor_id', $coachId)->orWhere('added_by', $coachId);
                    })
                    ->where('status', 'active')
                    ->with('course:id,title,slug')
                    ->orderBy('start_date', 'desc')
                    ->limit(5)
                    ->get(['id', 'course_id', 'title', 'start_date', 'end_date', 'capacity', 'status']);

                // Recent courses — last 5 created.
                $recentCourses = \App\Models\Course::query()
                    ->where(function ($q) use ($coachId) {
                        $q->where('instructor_id', $coachId)->orWhere('added_by', $coachId);
                    })
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get(['id', 'title', 'slug', 'thumbnail', 'status', 'is_approved', 'created_at']);

                return [
                    'upcoming_live'   => $upcomingLive,
                    'active_batches'  => $activeBatches,
                    'recent_courses'  => $recentCourses,
                ];
            }
        );

        // 2026-05-20 — Teacher-tailored dashboard (P1 of teacher panel).
        // When a CoachStaff with role != 'instructor' logs in, the coach
        // payload is still computed (their coach owns courses, so the
        // queries return data scoped to the coach's catalog), but the
        // view branches on $isTeacher and renders a different KPI strip
        // / content section sized to what they actually control.
        $isTeacher = (userAuth()->role ?? null) !== 'instructor';
        $teacherDashboard = $isTeacher
            ? $this->buildTeacherDashboard((int) userAuth()->id)
            : null;

        // 2026-06-25 — Configurable trial: auto-grant on first dashboard load so a
        // head coach lands ON the trial (not an empty paywall), then surface the
        // live trial/grace state + the purchasable plans for the picker.
        $trialSvc = app(\App\Services\CoachTrialService::class);
        if (! $isTeacher) {
            try { $trialSvc->grantTrial(userAuth()); } catch (\Throwable $e) {}
        }
        $trialStatus = $trialSvc->status(userAuth());
        $plans = \App\Models\MembershipPlan::activeForRoleCached('instructor')
            ->reject(fn ($p) => $p->slug === 'coach-free-trial')
            ->values();

        return view('frontend.instructor-dashboard.index', compact(
            'totalCourses',
            'totalOrders',
            'totalPendingCourses',
            'totalPendingOrders',
            'totalWithdraw',
            'offlineThisMonth',
            'myContent',
            'isTeacher',
            'teacherDashboard',
            'trialStatus',
            'plans',
        ));
    }

    /**
     * P1 — teacher-tailored dashboard payload.
     *
     * Cached 60s per user so the dashboard doesn't pile 8 aggregate
     * queries on every page load while the teacher is actively
     * navigating. Cache key includes the teacher id only; the gate
     * cache (assignedBatchIdsFor) invalidates independently when the
     * coach grants/revokes, so the dashboard self-heals on next load.
     */
    protected function buildTeacherDashboard(int $teacherId): ?array
    {
        return Cache::remember(
            "teacher.dashboard:$teacherId",
            60,
            function () use ($teacherId) {
                $assigned = TeacherBatchAssignment::assignedBatchIdsFor($teacherId);
                // Coach role somehow routed through here — return null
                // and let the view fall back to the coach payload.
                if ($assigned === null) {
                    return null;
                }
                $batchIds = $assigned ?: [0]; // guard whereIn on []

                $courseIds = \DB::table('course_batches')
                    ->whereIn('id', $batchIds)
                    ->pluck('course_id')->unique()->all();

                // ── Headline counters ───────────────────────────
                $assignedBatches = count($assigned);
                $assignedCourses = count($courseIds);

                $studentCount = (int) \DB::table('enrollments')
                    ->whereIn('batch_id', $batchIds)
                    ->where('has_access', 1)
                    ->distinct('user_id')->count('user_id');

                $today = now()->toDateString();

                $todayClasses = (int) \DB::table('course_live_classes')
                    ->whereIn('batch_id', $batchIds)
                    ->whereRaw('LEFT(start_time, 10) = ?', [$today])
                    ->count();

                $upcomingClassesCount = (int) \DB::table('course_live_classes')
                    ->whereIn('batch_id', $batchIds)
                    ->whereNull('ended_at')
                    ->where(function ($q) use ($today) {
                        $q->whereRaw('LEFT(start_time, 10) >= ?', [$today]);
                    })
                    ->count();

                $completedClasses = (int) \DB::table('course_live_classes')
                    ->whereIn('batch_id', $batchIds)
                    ->whereNotNull('ended_at')
                    ->count();

                // ── Fee KPIs (graceful if module not installed) ──
                $pendingFeesAmount = 0.0;
                $collectedFeesAmount = 0.0;
                try {
                    if (\Schema::hasTable('fee_demands') && \Schema::hasTable('fee_payments')) {
                        // Pending = sum of (demand amount - paid so far)
                        // for active demands targeting our batches.
                        $pendingFeesAmount = (float) \DB::table('fee_demands as d')
                            ->leftJoin('fee_payments as p', function ($j) {
                                $j->on('p.fee_demand_id', '=', 'd.id')
                                  ->where('p.status', 'paid');
                            })
                            ->whereIn('d.batch_id', $batchIds)
                            ->where('d.status', 'published')
                            ->selectRaw('COALESCE(SUM(d.amount), 0) - COALESCE(SUM(p.amount), 0) as outstanding')
                            ->value('outstanding') ?? 0.0;
                        $pendingFeesAmount = max(0.0, $pendingFeesAmount);

                        $collectedFeesAmount = (float) \DB::table('fee_payments as p')
                            ->join('fee_demands as d', 'd.id', '=', 'p.fee_demand_id')
                            ->whereIn('d.batch_id', $batchIds)
                            ->where('p.status', 'paid')
                            ->sum('p.amount');
                    }
                } catch (\Throwable $e) {
                    \Log::warning('teacher dashboard fee counters failed: ' . $e->getMessage());
                }

                // ── Upcoming list (next 5) ──────────────────────
                $upcomingList = empty($batchIds) ? collect() : \DB::table('course_live_classes as c')
                    ->leftJoin('courses as co', 'co.id', '=', 'c.course_id')
                    ->leftJoin('course_batches as b', 'b.id', '=', 'c.batch_id')
                    ->leftJoin('course_chapter_lessons as l', 'l.id', '=', 'c.lesson_id')
                    ->whereIn('c.batch_id', $batchIds)
                    ->whereNull('c.ended_at')
                    ->whereRaw('LEFT(c.start_time, 10) >= ?', [$today])
                    ->orderBy('c.start_time', 'asc')
                    ->limit(5)
                    ->get([
                        'c.id', 'c.start_time', 'c.batch_id', 'c.course_id',
                        'c.expected_duration_minutes', 'c.lesson_id',
                        'co.title as course_title',
                        'b.title as batch_title',
                        'l.title as lesson_title',
                    ]);

                // ── Recent activity feed (last 5 events) ───────
                // Rolled up from: live classes ended, fees collected,
                // announcements sent. Each carries a (type, when,
                // message) so the view renders them uniformly.
                $activity = collect();

                $recentEnded = \DB::table('course_live_classes as c')
                    ->leftJoin('course_batches as b', 'b.id', '=', 'c.batch_id')
                    ->leftJoin('course_chapter_lessons as l', 'l.id', '=', 'c.lesson_id')
                    ->whereIn('c.batch_id', $batchIds)
                    ->whereNotNull('c.ended_at')
                    ->orderBy('c.ended_at', 'desc')
                    ->limit(5)
                    ->get(['c.id', 'c.ended_at', 'b.title as batch_title', 'l.title as lesson_title']);
                foreach ($recentEnded as $r) {
                    $activity->push((object) [
                        'type' => 'class_ended',
                        'when' => $r->ended_at,
                        'icon' => 'fa-video',
                        'message' => ($r->lesson_title ?: 'Live class') . ' ended for ' . ($r->batch_title ?: '—'),
                    ]);
                }

                try {
                    if (\Schema::hasTable('fee_payments')) {
                        $recentPaid = \DB::table('fee_payments as p')
                            ->join('fee_demands as d', 'd.id', '=', 'p.fee_demand_id')
                            ->leftJoin('course_batches as b', 'b.id', '=', 'd.batch_id')
                            ->leftJoin('users as u', 'u.id', '=', 'p.student_id')
                            ->whereIn('d.batch_id', $batchIds)
                            ->where('p.status', 'paid')
                            ->orderBy('p.paid_at', 'desc')
                            ->limit(5)
                            ->get(['p.id', 'p.amount', 'p.paid_at', 'b.title as batch_title', 'u.name as student_name']);
                        foreach ($recentPaid as $r) {
                            $activity->push((object) [
                                'type' => 'fee_paid',
                                'when' => $r->paid_at,
                                'icon' => 'fa-coin',
                                'message' => ($r->student_name ?: 'Student') . ' paid ₹' . number_format($r->amount, 0) . ' — ' . ($r->batch_title ?: '—'),
                            ]);
                        }
                    }
                } catch (\Throwable $e) { /* fee module absent — skip */ }

                $activity = $activity
                    ->filter(fn ($a) => ! empty($a->when))
                    ->sortByDesc('when')
                    ->take(8)
                    ->values();

                return [
                    'assigned_batches'      => $assignedBatches,
                    'assigned_courses'      => $assignedCourses,
                    'student_count'         => $studentCount,
                    'today_classes'         => $todayClasses,
                    'upcoming_classes'      => $upcomingClassesCount,
                    'completed_classes'     => $completedClasses,
                    'pending_fees_amount'   => $pendingFeesAmount,
                    'collected_fees_amount' => $collectedFeesAmount,
                    'upcoming_list'         => $upcomingList,
                    'activity'              => $activity,
                ];
            }
        );
    }

    public function create()
    {

        // checkAdminHasPermissionAndThrowException('order.management');
        $this->pageName = 'coach-orders';
        // 2026-07-13 — enforce the granular create permission server-side so it
        // matches the "Add manual order" button gate (coach-orders-create).
        // A real coach short-circuits to full access inside checkPermission().
        $flag = checkPermission($this->pageName, 'create');
        if ($flag == 1) {

            // $stidents = User::where(function ($query) {
            //   $query->where('added_by', 1086)
            //   ->orWhere('coach_id', userAuth()->coach_id);
            //     })
            //     // ->where('status', 'active')
            //     ->get();

            // 2026-07-11 fix — the student roster is the CoachStudentLink pivot
            // (added + purchased + linked), the same source Offline Payment and the
            // Students module use. The old added_by/coach_id-only query hid students
            // who joined via purchase, so they couldn't be manually invoiced. Show
            // the roster UNION directly-owned students (matches store() validation).
            $rosterCoachId = userAuth()->role === 'instructor' ? (int) userAuth()->id : (int) userAuth()->coach_id;
            $rosterIds = array_map('intval', (array) \App\Models\CoachStudentLink::studentIdsForCoach($rosterCoachId));
            $stidents = User::where('role', 'student')
                ->where(function ($q) use ($rosterCoachId, $rosterIds) {
                    $q->whereIn('id', $rosterIds ?: [0])
                        ->orWhere('added_by', $rosterCoachId)
                        ->orWhere('coach_id', $rosterCoachId);
                })
                ->orderBy('id', 'desc')
                ->get();

            // $stidents = User::where('role', 'student')->where('added_by', userAuth()->id)->where('status', 'active')->get();
            // $courses = Course::where('status', 'active')->where('is_approved', 'approved')->where('instructor_id', userAuth()->id)->get();

            //   $coachId = (userAuth()->role !='instructor')?userAuth()->coach_id:userAuth()->id;
            $courses = Course::where(function ($query) {
                $query->where('added_by', userAuth()->id)->orWhere('instructor_id', userAuth()->id);
            })->where('status', 'active')->where('is_approved', 'approved')->get();

            return view('frontend.instructor-dashboard.my-sells.create', ['stidents' => $stidents, 'courses' => $courses]);
        } else {
            return view($this->admin_error_view);
        }
    }

    // -------------------------------------------------------------------------------

    public function store(Request $request)
    {
        $this->pageName = 'coach-orders';
        // 2026-07-13 — see create(): staff need coach-orders-create to submit.
        $flag = checkPermission($this->pageName, 'store');
        if ($flag != 1) {
            return view($this->admin_error_view);
        }

        $coachId = userAuth()->role === 'instructor' ? userAuth()->id : userAuth()->coach_id;

        // 2026-07-11 fix — allowed students = the coach's CoachStudentLink roster
        // (added + purchased + linked, the SAME source as the dropdown) UNION any
        // students they directly own (added_by/coach_id). The old added_by-only
        // check wrongly rejected a purchase-linked student ("not yours"); the union
        // also covers a just-added student not yet in the pivot. Still tenant-safe.
        $rosterIds = array_map('intval', (array) \App\Models\CoachStudentLink::studentIdsForCoach($coachId));
        $ownedIds  = \App\Models\User::where('role', 'student')
            ->where(fn ($q) => $q->where('added_by', $coachId)->orWhere('coach_id', $coachId))
            ->pluck('id')->map(fn ($v) => (int) $v)->all();
        $allowedStudentIds = array_values(array_unique(array_merge($rosterIds, $ownedIds)));

        $validated = $request->validate([
            'user_id'   => [
                'required', 'integer',
                \Illuminate\Validation\Rule::in($allowedStudentIds),
            ],
            'course_id' => [
                'required', 'integer',
                \Illuminate\Validation\Rule::exists('courses', 'id')->where(function ($q) use ($coachId) {
                    $q->where(function ($q) use ($coachId) {
                        $q->where('added_by', $coachId)->orWhere('instructor_id', $coachId);
                    });
                }),
            ],
            // SECURITY (audit 2026-05-22) — original batch_id check was just
            // `exists:course_batches,id` with no coach scope. A coach could
            // attach ANOTHER coach's batch to a paid enrollment, then the
            // enrolled student would appear on that other coach's batch
            // attendance roster. Now scoped to batches belonging to the
            // current coach's own courses.
            'batch_id'  => [
                'nullable', 'integer',
                \Illuminate\Validation\Rule::exists('course_batches', 'id')
                    ->where(function ($q) use ($coachId) {
                        $q->whereExists(function ($sub) use ($coachId) {
                            $sub->select(DB::raw(1))
                                ->from('courses')
                                ->whereColumn('courses.id', 'course_batches.course_id')
                                ->where(function ($cq) use ($coachId) {
                                    $cq->where('added_by', $coachId)
                                       ->orWhere('instructor_id', $coachId);
                                });
                        });
                    }),
            ],
        ], [
            'user_id.in'       => __('Selected student is not on your roster'),
            'course_id.exists' => __('Selected course is not yours'),
            'batch_id.exists'  => __('Selected batch is not yours'),
        ]);

        $course = Course::findOrFail($validated['course_id']);
        $setting = Cache::get('setting');
        $commissionRate = $setting?->commission_rate ?? 0;
        // 2026-06-12 — charge the EFFECTIVE sale price ($course->discount ?: price),
        // matching the storefront. Using the raw MRP here billed the student the
        // struck-through price on a discounted course (price/invoice mismatch).
        $payableAmount = $course->effective_price;
        // 2026-06-13 — apply the coach's tax (opt-in). No tax → $tax is all-zero
        // and the amounts below are unchanged.
        $tax = app(\App\Services\Tax\TaxService::class)
            ->computeForOrder((int) $coachId, (float) $payableAmount, $course->tax_rate_id);

        try {
            DB::beginTransaction();

            // 2026-06-01 — coach-generated orders are created PENDING and
            // grant NO course access until the student actually pays. This
            // intentionally REVERSES the 2026-05-26 "bug-doc C4"
            // immediate-grant behaviour, per the coach's request: a freshly
            // generated order must read "Pending", not auto-"Paid".
            //
            // How it becomes paid: the coach opens the order and sets
            // Payment = Paid on the order screen → mySellsupdate() routes the
            // unpaid→paid transition through
            // PaymentFulfilmentService::markPaid(), which grants access AND
            // credits the coach's commission (idempotently).
            //
            // paid_amount stays = price ON PURPOSE — markPaid() reads
            // $order->paid_amount to size the commission/wallet credit at the
            // moment it's marked paid. `payment_status='pending'` is the
            // single source of truth for "not yet paid".
            $order = Order::create([
                'invoice_id'              => Str::random(10),
                'buyer_id'                => $validated['user_id'],
                // 2026-06-01 (audit) — populate seller_id with the coach so the
                // admin commission report (CoachCommissionController scopes by
                // seller_id) includes coach-manual sales. Was NULL on 100% of
                // manual orders.
                'seller_id'               => $coachId,
                'has_coupon'              => 0,
                'coupon_code'             => '',
                'coupon_discount_percent' => '',
                'coupon_discount_amount'  => 0,
                'payment_method'          => 'coach_manual',
                'payment_status'          => 'pending',
                'status'                  => 'pending',
                // payable_amount = pre-tax coach revenue base (commission base);
                // the student pays charge_total (taxable + tax).
                'payable_amount'          => $tax['taxable_amount'],
                'tax_amount'              => $tax['tax_amount'],
                'taxable_amount'          => $tax['taxable_amount'],
                'tax_rate_applied'        => $tax['rate'],
                'tax_mode'                => $tax['has_tax'] ? $tax['mode'] : null,
                'tax_label'               => $tax['has_tax'] ? $tax['label'] : null,
                'tax_registration'        => $tax['has_tax'] ? $tax['registration'] : null,
                'tax_components'          => $tax['has_tax'] ? $tax['components'] : null,
                'gateway_charge'          => 0,
                'payable_with_charge'     => $tax['charge_total'],
                'paid_amount'             => $tax['charge_total'],
                'payable_currency'        => 'INR',
                'conversion_rate'         => Session::get('currency_rate', 1),
                'commission_rate'         => $commissionRate,
                'order_type'              => 'course',
                'order_details'           => 'Assigned by coach (manual)',
                'transaction_id'          => 'COACH-' . Str::random(12),
            ]);

            $orderItem = OrderItem::create([
                'order_id'         => $order->id,
                'price'            => $tax['taxable_amount'], // pre-tax line (commission base)
                'tax_amount'       => $tax['tax_amount'],
                'tax_rate_applied' => $tax['has_tax'] ? $tax['rate'] : null,
                'course_id'        => $validated['course_id'],
                'commission_rate'  => $commissionRate,
                'batch_id'         => $validated['batch_id'] ?? null,
            ]);

            // 2026-05-29 Doc-C-OfflineOrder: defensive logging.
            // User reported that the FIRST manual order didn't appear in
            // /instructor/coach-orders despite the enrollment being
            // created correctly. The reproduction was inconsistent so the
            // root cause was never confirmed. Log every manual-order
            // create with full IDs so any recurrence is traceable from
            // a single log line.
            \Log::info('coach-manual-order-created', [
                'coach_id'     => $coachId,
                'order_id'     => $order->id,
                'order_item_id' => $orderItem->id,
                'course_id'    => $validated['course_id'],
                'buyer_id'     => $validated['user_id'],
                'batch_id'     => $validated['batch_id'] ?? null,
                'price'        => $payableAmount,
            ]);

            // 2026-06-01 — intentionally DO NOT create an Enrollment here.
            // The order is pending, so the student must have NO access yet.
            // Access is granted only when the coach marks the order Paid:
            // PaymentFulfilmentService::markPaid() then does
            // Enrollment::firstOrCreate(user_id+course_id, has_access=1).
            //
            // Critically, we must NOT pre-create a has_access=0 row: markPaid
            // uses firstOrCreate(), which would FIND that row and never flip
            // access to 1 — leaving the student locked out even after paying.
            // Leaving enrollment creation entirely to markPaid keeps the
            // pending→paid→access path correct.

            // Link the student to this coach if not already linked. The
            // CoachStudentLink::link() helper is idempotent, so repeat
            // assignments (e.g. coach gives student a 2nd course later)
            // never duplicate rows.
            try {
                \App\Models\CoachStudentLink::link(
                    (int) $coachId,
                    (int) $validated['user_id'],
                    'added'
                );
            } catch (\Throwable $e) {
                \Log::warning('coach-order-link-failed', [
                    'coach_id'   => $coachId,
                    'student_id' => $validated['user_id'],
                    'error'      => $e->getMessage(),
                ]);
            }

            DB::commit();

            // 2026-05-26 (bug-doc C4) — notify the student outside the
            // transaction so a mail-server hiccup doesn't roll back the
            // order. The notification carries course title + login link.
            try {
                $student = \App\Models\User::find($validated['user_id']);
                if ($student) {
                    $student->notify(
                        new \App\Notifications\CoachAssignedCourseToStudent(
                            $course,
                            $order,
                            userAuth()
                        )
                    );
                }
            } catch (\Throwable $e) {
                \Log::warning('coach-order-notify-failed', [
                    'student_id' => $validated['user_id'],
                    'order_id'   => $order->id,
                    'error'      => $e->getMessage(),
                ]);
            }

            return redirect()->route('instructor.my-sells.index')->with('success', __('Added Successfully'));
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Coach order creation failed', [
                'error'     => $e->getMessage(),
                'coach_id'  => $coachId,
                'course_id' => $validated['course_id'] ?? null,
                'user_id'   => $validated['user_id'] ?? null,
            ]);
            return redirect()->back()->with(['messege' => __('Order creation failed'), 'alert-type' => 'error']);
        }
    }

    // -------------------------------------------------------------------------------

    public function mySells()
    {

        $this->pageName = 'coach-orders';
        $flag = checkPermission($this->pageName);
        if ($flag == 1) {

            // 2026-07-10 (New Changes for UI #6) — order visibility scope.
            // A real coach sees every order in their tenant; a STAFF member
            // sees ONLY orders for courses they themselves created
            // (courses.added_by = staff id). orderScopeCourseIds() encodes
            // that split, and the SAME course-id set feeds the list AND every
            // derived metric below, so the restriction holds for counts,
            // revenue, commission and pagination — never frontend-only.
            $courseIds = $this->orderScopeCourseIds();

            $baseQuery = OrderItem::whereIn('course_id', $courseIds ?: [0])
                // order.orderItems is needed so netPaid() can allocate the
                // coupon discount proportionally without an N+1 per row.
                ->with(['order.orderItems', 'course']);

            // base query add
            $ordersQuery = clone $baseQuery;
            $orders = $ordersQuery->orderBy('id', 'desc')->paginate(10);
 
            // total revenue 
            $totalRevenue = $baseQuery->sum('price');

            // total commission (= coach earnings, "after platform cut")
            // 2026-07-07 ("Coach Order amount Issue") — earnings on the
            // coupon-adjusted amount paid, not the gross price. Each item's net
            // = price − (order coupon discount × item's share of the order gross);
            // coach earning = net × (1 − commission_rate). gt.gross gives the
            // per-order gross so the discount is allocated proportionally.
            $grossSub = \DB::table('order_items')
                ->select('order_id', \DB::raw('SUM(price) as gross'))
                ->groupBy('order_id');

            $totalCommission = (clone $baseQuery)
                ->join('orders as o', 'o.id', '=', 'order_items.order_id')
                ->leftJoinSub($grossSub, 'gt', 'gt.order_id', '=', 'order_items.order_id')
                ->selectRaw(
                    'SUM('
                    . ' (order_items.price - COALESCE(o.coupon_discount_amount,0) * (order_items.price / NULLIF(gt.gross,0)))'
                    . ' * (1 - COALESCE(order_items.commission_rate, o.commission_rate, 0) / 100)'
                    . ') as total'
                )
                ->value('total');
            // total pending 
                $pendingCount = (clone $baseQuery)
                ->whereHas('order', function ($q) {
                    $q->where('status','pending');
                })
                ->count();

            return view('frontend.instructor-dashboard.my-sells.index', compact('orders', 'totalRevenue','totalCommission','pendingCount'));
        } else {
            return view($this->admin_error_view);
        }
    }

    /**
     * Course IDs whose orders the current user may see.
     *   - Real coach (role='instructor')  → every course in the tenant
     *     (added_by = coach OR instructor_id = coach).
     *   - Staff (any other role)          → ONLY courses the staff created
     *     (added_by = staff id).
     *
     * 2026-07-10 (New Changes for UI #6) — a staff member with the Orders
     * permission must see orders for their OWN courses only, never the
     * coach's or another staff member's. This single source of truth is
     * reused by the list, all KPI metrics, and the per-order lookup, so the
     * restriction cannot be bypassed via a direct URL, a tampered filter,
     * or an export.
     */
    private function orderScopeCourseIds(): array
    {
        $u = userAuth();

        if ($u->role === 'instructor') {
            return Course::where(function ($query) use ($u) {
                $query->where('added_by', $u->id)->orWhere('instructor_id', $u->id);
            })->pluck('id')->all();
        }

        return Course::where('added_by', (int) $u->id)->pluck('id')->all();
    }

    /**
     * Order-item query scoped to the courses the current user may see.
     * Centralised so the ownership scope (coach = tenant, staff = own
     * courses) is reusable + auditable — and IDOR-safe: show/update/invoice
     * all resolve through this, so a staff member cannot open another
     * course's order item by guessing its id.
     */
    private function coachOwnedOrderItemQuery()
    {
        return OrderItem::whereIn('course_id', $this->orderScopeCourseIds() ?: [0]);
    }

    public function mySellsShow(string $id)
    {
        $this->pageName = 'coach-orders';
        $flag = checkPermission($this->pageName);
        if ($flag == 1) {
            // 2026-06-02 — corporate detail page. Eager-load the student
            // (order.user), the course, and resolve the batch so the view can
            // show a complete order summary instead of just two status dropdowns.
            $orderitem = $this->coachOwnedOrderItemQuery()
                ->with(['order.user', 'course'])
                ->where('id', $id)
                ->firstOrFail();

            $batch = $orderitem->batch_id
                ? \App\Models\CourseBatch::select('id', 'title')->find($orderitem->batch_id)
                : null;

            return view('frontend.instructor-dashboard.my-sells.show', compact('orderitem', 'batch'));
        } else {
            return view($this->admin_error_view);
        }
    }

    public function mySellsupdate(Request $request, $id)
    {
        $this->pageName = 'coach-orders';
        // 2026-07-13 — editing an order (status / payment) requires the granular
        // edit permission (coach-orders-edit), matching the row Edit button gate.
        $flag = checkPermission($this->pageName, 'update');
        if ($flag != 1) {
            return view($this->admin_error_view);
        }

        // 2026-06-01 — order_status allow-list now matches the actual
        // orders.status ENUM (pending|processing|completed|declined). The old
        // list allowed 'cancelled'/'failed' (NOT in the enum → silently
        // coerced to '') and rejected 'processing'/'declined' that the form
        // offers (422). payment_status keeps paid/pending/refunded/failed —
        // the values the refund/markPaid logic actually handles.
        $request->validate([
            'order_status'   => ['required', 'in:pending,processing,completed,declined'],
            'payment_status' => ['required', 'in:pending,paid,failed,refunded'],
        ]);

        $orderItem = $this->coachOwnedOrderItemQuery()->where('id', $id)->firstOrFail();
        $order = Order::findOrFail($orderItem->order_id);

        // FINANCIAL INTEGRITY (audit 2026-05-22)
        //
        // Original behaviour: this method just bumped the order columns
        // and upserted an Enrollment row. It did NOT credit the coach's
        // wallet, propagate the batch_id, link the student to the coach's
        // roster, or track coupon usage — all of which the Razorpay
        // webhook path (PaymentFulfilmentService::markPaid) does.
        //
        // Result: a coach who manually marked an order paid gave the
        // student course access but never received their commission.
        //
        // Fix: when the coach transitions an order from NOT paid → paid,
        // route through PaymentFulfilmentService::markPaid. That service
        // is idempotent (`if ($order->payment_status === 'paid') return
        // false;`), so even if the Razorpay webhook ALSO fires later
        // there's no double-credit. Other transitions (cancel, refund,
        // pending) fall back to the direct column update.
        $wasUnpaid = $order->payment_status !== 'paid';
        $becomesPaid = $request->payment_status === 'paid' && $request->order_status === 'completed';

        // 2026-06-02 — an order whose buyer was deleted (buyer_id NULL, shown as
        // "Deleted / Guest") has no student to enrol or credit. markPaid() and
        // the enrollment upserts below all key on user_id, so a NULL buyer would
        // INSERT user_id=NULL → "Column 'user_id' cannot be null". Skip the
        // fulfilment path for such orders; the status columns still save below.
        if ($wasUnpaid && $becomesPaid && $order->buyer_id) {
            try {
                app(\App\Services\PaymentFulfilmentService::class)->markPaid(
                    $order,
                    $order->transaction_id ?? ('coach-manual-' . now()->format('YmdHis')),
                    json_encode([
                        'source' => 'coach_manual_update',
                        'coach_id' => (int) userAuth()->id,
                        'at' => now()->toIso8601String(),
                    ])
                );
            } catch (\Throwable $e) {
                \Log::warning('coach-mysells-markpaid-failed', [
                    'order_id' => $order->id,
                    'err'      => $e->getMessage(),
                ]);
                return redirect()->back()->withErrors([
                    'payment_status' => __('Failed to mark paid: :err', ['err' => $e->getMessage()]),
                ]);
            }

            // 2026-06-01 — re-grant access on RE-payment.
            // markPaid() creates the enrollment via Enrollment::firstOrCreate(),
            // which does NOT update an existing row. So when an order had been
            // paid before (enrollment exists), then toggled to Pending
            // (has_access=0), re-marking it Paid left the student locked out
            // (the reported bug). Force has_access=1 here, keyed by the
            // (user_id, course_id) UNIQUE pair so the existing row is UPDATED
            // rather than (failing to) insert a duplicate.
            // 2026-06-06 — key on (user, course, BATCH) for multi-batch support
            // (avoid colliding with another batch's enrollment row).
            Enrollment::updateOrCreate(
                [
                    'user_id'   => $order->buyer_id,
                    'course_id' => $orderItem->course_id,
                    'batch_id'  => $orderItem->batch_id ?? null,
                ],
                [
                    'order_id'   => $order->id,
                    'has_access' => 1,
                ]
            );
        } else {
            // Non-paid transition (cancel / fail / refund / pending).
            //
            // FINANCIAL INTEGRITY (audit 2026-05-22) — when an order moves
            // from paid → refunded, we MUST reverse the coach's wallet
            // credit that was registered at fulfilment time. Otherwise
            // the wallet stays inflated post-refund and the coach can
            // withdraw money on a refunded sale.
            //
            // Idempotency: we only reverse if (a) the order WAS paid and
            // (b) the new status is refunded AND (c) the order hasn't
            // been reversed before. We mark the order with a server-side
            // payment_details flag once reversed, so re-saving with
            // payment_status=refunded a second time doesn't re-deduct.
            $wasPaid = $order->payment_status === 'paid';
            $becomesRefunded = $request->payment_status === 'refunded';
            // 2026-06-01 — reverse the coach's commission credit whenever the
            // order LEAVES the paid state (refund OR a Paid→Pending/Cancelled/
            // Failed toggle), not only on refund. A Paid→Pending toggle used to
            // keep the wallet inflated, so a later Pending→Paid (markPaid)
            // credited a SECOND time → double commission. The wallet_reversed
            // flag is idempotent within a not-paid state; markPaid() overwrites
            // payment_details (clearing the flag) when it re-credits, so the
            // next leave-paid reverses cleanly. Net across a Paid→Pending→Paid
            // cycle = exactly one credit.
            $leavingPaid = $wasPaid && $request->payment_status !== 'paid';
            $alreadyReversed = str_contains((string) $order->payment_details, '"wallet_reversed":true');

            // 2026-06-03 (Referral A+) — leaving the paid state reverses any
            // referral commission too: claw back if credited, else reject.
            // Idempotent (only acts on live rows); a later Pending→Paid re-mints
            // it via markPaid()/onOrderPaid().
            if ($leavingPaid) {
                try {
                    app(\App\Services\ReferralCommissionService::class)
                        ->onOrderReversed($order, 'Coach set payment to ' . $request->payment_status);
                } catch (\Throwable $e) {
                    \Log::warning('Referral reversal (coach mysells) failed: ' . $e->getMessage());
                }
            }

            if ($leavingPaid && ! $alreadyReversed) {
                try {
                    \DB::transaction(function () use ($order, $orderItem, $becomesRefunded, $request) {
                        $course = \App\Models\Course::withTrashed()->find($orderItem->course_id);
                        $instructor = $course?->instructor;
                        if ($instructor) {
                            $itemPrice            = (float) $order->paid_amount;
                            $commissionAmount     = $itemPrice * ((float) $order->commission_rate / 100);
                            $amountAfterCommission = $itemPrice - $commissionAmount;
                            // Reverse the original credit. Wallet can go
                            // negative if the coach has already withdrawn —
                            // that's accounting-correct (they owe the platform).
                            $instructor->decrement('wallet_balance', $amountAfterCommission);

                            // Mark reversed so re-saving the same not-paid
                            // status doesn't double-reverse.
                            $details = json_decode((string) $order->payment_details, true) ?: [];
                            $details['wallet_reversed'] = true;
                            $details['wallet_reversed_at'] = now()->toIso8601String();
                            $details['wallet_reversed_amount'] = $amountAfterCommission;
                            $details['wallet_reversed_by'] = (int) userAuth()->id;
                            $order->payment_details = json_encode($details);
                        }
                        // A refund is terminal → force a "no longer active"
                        // order status + refunded payment. Other leave-paid
                        // transitions keep the coach's chosen statuses.
                        //
                        // NB: the orders.status column is
                        // enum(pending|processing|completed|declined) — it has
                        // NO 'cancelled', so the previous code's 'cancelled'
                        // silently coerced to '' (empty). Use the valid enum
                        // value 'declined'. (2026-06-01 fix)
                        if ($becomesRefunded) {
                            $order->status = 'declined';
                            $order->payment_status = 'refunded';
                        } else {
                            $order->status = $request->order_status;
                            $order->payment_status = $request->payment_status;
                        }
                        $order->save();
                    });
                } catch (\Throwable $e) {
                    \Log::error('coach-mysells-credit-reversal-failed', [
                        'order_id' => $order->id,
                        'err'      => $e->getMessage(),
                    ]);
                    return redirect()->back()->withErrors([
                        'payment_status' => __('Failed to update order: :err', ['err' => $e->getMessage()]),
                    ]);
                }
            } else {
                $order->status = $request->order_status;
                $order->payment_status = $request->payment_status;
                $order->save();
            }

            $hasAccess = ($order->status === 'completed' && $order->payment_status === 'paid') ? 1 : 0;

            // 2026-06-06 — key by (user_id, course_id, BATCH) so a student in
            // multiple batches of the same course is upserted per batch. Keying
            // on (user, course) alone tried to move one enrollment's batch_id
            // onto a value another batch row already holds -> UniqueConstraint
            // violation on enrollments_user_course_batch_unique.
            // Guard a deleted/guest buyer (buyer_id NULL) — there's no student to
            // enrol, and an unguarded upsert would insert user_id=NULL and crash.
            if ($order->buyer_id) {
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
            }
        }

        return redirect()->route('instructor.my-sells.index')->with(['messege' => __('Status Updated'), 'alert-type' => 'success']);
    }

    // -------------------Add Students------------------------------------

    public function myStudents(?Request $request = null)
    {
        // Tolerate a direct no-arg call (unit tests / internal callers); the
        // route always injects the Request.
        $request = $request ?: request();

        $this->pageName = 'coach-students';
        $flag = checkPermission($this->pageName);
        if ($flag != 1) {
            return view($this->admin_error_view);
        }

        // 2026-05-20 P2 — teacher panel.
        // For a coach the page still lists every student they added or
        // coach. For a CoachStaff teacher we narrow to the students
        // actually enrolled in batches the coach has assigned them.
        // Without this gate, a teacher logging in would see ALL their
        // coach's students, including ones from batches they were never
        // granted access to.
        $assigned = TeacherBatchAssignment::assignedBatchIdsFor((int) userAuth()->id);

        if ($assigned === null) {
            // 2026-05-21 — multi-coach: source of truth for "this
            // student is on my roster" is now the pivot. Replaces
            // the old (added_by OR coach_id) query which only saw
            // students directly added by this coach. Students who
            // joined via a purchase, or are on multiple coaches'
            // rosters, are now included automatically.
            $coachId = (int) userAuth()->id;
            $studentIds = \App\Models\CoachStudentLink::studentIdsForCoach($coachId);
        } else {
            // Teacher/staff — students enrolled in their assigned batches …
            $batchIds = $assigned ?: [0]; // guard whereIn on []
            $enrolledIds = \DB::table('enrollments')
                ->whereIn('batch_id', $batchIds)
                ->where('has_access', 1)
                ->pluck('user_id');

            // 2026-07-10 (Staff Panel) — … PLUS students this staff member created
            // themselves. A staff-added student sets users.added_by = the staff id
            // but (by design) no coach_id and often no batch enrolment yet, so the
            // batch-only filter above hid them from their own creator. Union them
            // in so a staff-created student shows in the staff Students module (they
            // already show in the coach panel via the coach_student_links pivot).
            $createdIds = User::where('added_by', (int) userAuth()->id)
                ->where('role', 'student')
                ->pluck('id');

            $studentIds = $enrolledIds->merge($createdIds)->unique();
        }

        // 2026-07-10 (New Changes for UI #7) — server-side Status filter +
        // Search. Both are applied AFTER the tenant/roster scope above, so a
        // coach or staff can only ever search WITHIN their authorised student
        // set (tenant isolation preserved). Status maps to the users table:
        //   active   → not banned + status='active'
        //   inactive → not banned + status != 'active' (e.g. 'deactive')
        //   blocked  → is_banned='yes'
        // Search matches name / email / phone (case-insensitive via LIKE).
        $search = trim((string) $request->get('q', ''));
        $status = (string) $request->get('status', 'all');
        if (! in_array($status, ['all', 'active', 'inactive', 'blocked'], true)) {
            $status = 'all';
        }

        $query = User::whereIn('id', $studentIds ?: [0])->where('role', 'student');

        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
            $query->where(function ($w) use ($like) {
                $w->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            });
        }
        if ($status === 'blocked') {
            $query->where('is_banned', 'yes');
        } elseif ($status === 'active') {
            $query->where('is_banned', '!=', 'yes')->where('status', 'active');
        } elseif ($status === 'inactive') {
            $query->where('is_banned', '!=', 'yes')->where('status', '!=', 'active');
        }

        $mystudents = $query->orderBy('id', 'desc')
            ->paginate(30)
            ->withQueryString(); // keep filters across pagination links

        // 2026-07-15 — coach's active batches for the bulk "Assign to batch"
        // action on the list (grouped by course). Tenant-scoped via the course.
        $coachId = userAuth()->role === 'instructor' ? userAuth()->id : userAuth()->coach_id;
        $batchOptions = \App\Models\CourseBatch::query()
            ->whereHas('course', fn ($q) => $q->where('instructor_id', $coachId)->orWhere('added_by', $coachId))
            ->where('status', 'active')
            ->with('course:id,title')
            ->orderBy('title')
            ->get(['id', 'course_id', 'title']);

        return view('frontend.instructor-dashboard.my-students.index', compact('mystudents', 'search', 'status', 'batchOptions'));
    }

    public function createStudents()
    {
        $this->pageName = 'coach-students';
        $flag = checkPermission($this->pageName);
        if ($flag == 1) {

            // checkAdminHasPermissionAndThrowException('order.management');

            $stidents = User::where('role', 'student')->where('verification_token', '!=', null)->get();
            $courses = Course::where('status', 'active')->where('is_approved', 'approved')->where('instructor_id', userAuth()->id)->get();

            // Audit 2026-05-19 phase 4 (post-SRS) — Batch dropdown on
            // Add Student form. Build a list of {batch_id, course_title,
            // batch_title} pairs scoped to this coach's active batches
            // so the coach can assign a student to a batch at creation.
            $coachId = (userAuth()->role !== 'instructor') ? userAuth()->coach_id : userAuth()->id;
            $batches = \App\Models\CourseBatch::query()
                ->whereHas('course', function ($q) use ($coachId) {
                    $q->where('instructor_id', $coachId)->orWhere('added_by', $coachId);
                })
                ->where('status', 'active')
                ->with('course:id,title')
                ->orderBy('title')
                ->get(['id', 'course_id', 'title']);

            return view('frontend.instructor-dashboard.my-students.create', [
                'stidents' => $stidents,
                'courses'  => $courses,
                'batches'  => $batches,
            ]);
        } else {
            return view($this->admin_error_view);
        }
    }

    public function storeStudetns(Request $request)
    {
        // FT-IDOR-25 fix (2026-05-28) — was missing permission gate.
        // Sibling methods on this controller (createStudents,
        // editStudents, updateStudents) all gate on
        // checkPermission('coach-students'); storeStudetns was the
        // outlier. Without it, a coach-staff user without the
        // `coach-students` slug could still POST /instructor/student/store
        // and either link existing students to the coach's roster
        // (privacy leak: a malicious staff could bulk-link known
        // student emails to the coach to see whether they exist) OR
        // create brand-new student accounts attributed to the coach.
        $this->pageName = 'coach-students';
        $flag = checkPermission($this->pageName);
        if ($flag != 1) {
            return view($this->admin_error_view);
        }

        // 2026-05-21 — multi-coach support.
        // The `unique:users` rule on email used to make "this student
        // already exists" a hard error. With the M2M model the same
        // email may belong to a student already on another coach's
        // roster — we LINK them to the current coach instead of
        // rejecting the form. Password / role are only honoured when
        // we genuinely create a new user.
        $rules = [
            'name' => 'required',
            'email' => 'required|email|max:255',
            // FT-AUTH-7 fix (2026-05-27) — was `nullable|min:4`.
            // 4-char passwords fall to offline cracking trivially.
            // Self-service signup already requires min:8; coach-driven
            // student creation must match so a coach can't backdoor a
            // weaker password than the user could choose themselves.
            'password' => 'nullable|min:8',
            'status' => 'required',
            // Audit 2026-05-19 phase 4 — Batch field on Add Student.
            // Optional: a coach may want to add a student without
            // assigning them to a batch yet.
            'batch_id' => 'nullable|exists:course_batches,id',
        ];
        $customMessages = [
            'name.required' => __('Name is required'),
            'email.required' => __('Email is required'),
            'email.email' => __('Please enter a valid email address'),
            'status.required' => __('Status is required'),
            'password.min' => __('Password must be at least 8 characters'),
        ];
        $this->validate($request, $rules, $customMessages);

        $coachId = (userAuth()->role != 'instructor') ? userAuth()->coach_id : userAuth()->id;

        $existing = User::where('email', $request->email)->first();

        if ($existing && $existing->role !== 'student') {
            // Email belongs to a non-student account (another coach,
            // an admin, etc.). Refuse — we can't repurpose them.
            return back()->withInput()->withErrors([
                'email' => __('This email belongs to a non-student account and cannot be added as a student.'),
            ]);
        }

        if ($existing) {
            // Existing student — link to this coach's roster via pivot.
            // Skip the password / status update (we don't have the
            // right to mutate another coach's student). Idempotent:
            // re-linking an already-linked student is a no-op,
            // re-linking a removed link reactivates it.
            \App\Models\CoachStudentLink::link((int) $coachId, (int) $existing->id, 'added');
            $user = $existing;
            $wasLinked = true;
        } else {
            // Genuinely new — password is required here.
            if (! $request->filled('password')) {
                return back()->withInput()->withErrors([
                    'password' => __('Password is required for new students'),
                ]);
            }

            $user = User::create([
                'role' => 'student',
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'status' => $request->status,
                'email_verified_at' => now(),
                'added_by' => userAuth()->id,
                // coach_id intentionally NOT set on student rows —
                // per the 2026-05-18 cleanup migration that column is
                // reserved for STAFF users (their parent coach). The
                // pivot is the source of truth for student↔coach.
            ]);

            \App\Models\CoachStudentLink::link((int) $coachId, (int) $user->id, 'added');
            $wasLinked = false;
        }

        // If a batch was picked, link the student to that batch via an
        // enrollment row. Verify the batch belongs to one of this
        // coach's courses (defence-in-depth — the dropdown is already
        // scoped, but a coach could craft a request directly).
        if ($request->filled('batch_id')) {
            $batch = \App\Models\CourseBatch::with('course')->find($request->batch_id);
            $ownsBatch = $batch && (
                (int) ($batch->course?->instructor_id ?? 0) === $coachId ||
                (int) ($batch->course?->added_by      ?? 0) === $coachId
            );
            if ($ownsBatch) {
                // 2026-06-06 — was firstOrCreate(), which IGNORED batch_id when
                // an enrollment for this (user, course) already existed — so
                // re-assigning a student to a different batch silently did
                // nothing, and the new batch's live classes stayed invisible to
                // them. Use firstOrNew so an EXISTING enrollment's batch_id is
                // UPDATED, while a brand-new one still gets has_access=1. Never
                // wipe an existing (possibly paid) enrollment's order_id/access.
                // 2026-06-06 — key on (user, course, BATCH) so assigning the
                // student to ANOTHER batch of the same course adds a SECOND
                // enrollment row (multi-batch) instead of moving/colliding.
                $enrollment = \Modules\Order\app\Models\Enrollment::firstOrNew([
                    'user_id'   => $user->id,
                    'course_id' => $batch->course_id,
                    'batch_id'  => $batch->id,
                ]);
                if (! $enrollment->exists) {
                    $enrollment->order_id   = null;
                    $enrollment->has_access = 1;
                }
                $enrollment->save();
            }
        }

        // Only email login credentials for genuinely new students.
        // An existing student who got linked already has their password
        // — emailing them a re-statement here would be weird (and we
        // don't have the plain-text password anyway).
        if (! $wasLinked) {
            $app_name = cache()->get('setting')?->app_name ?? config('app.name');
            $login_url = route('login');
            $contact_url = route('contact.index');
            $subject = "Welcome to {$app_name} - Your Login Information";

            $message = <<<EOD
            <p><strong>Welcome to {$app_name}!</strong></p><p>We are excited to have you onboard as a {$user->role}.</p><p>Below are your login credentials to access your account:</p><p><strong>Website URL:</strong> <a href="{$login_url}" target="_blank">Click here to log in</a></p><p><strong>Email:</strong> {$user->email}</p><p><strong>Password:</strong> {$request->password}</p>
            <p>If you have any questions or need assistance, feel free to <a href="{$contact_url}" target="_blank">contact us</a>.</p>
            EOD;

            (new MailSenderService)->sendMailToUserFromTrait($subject, $message, 'single_user', $user);
        }

        return redirect()->route('instructor.my-students.index')->with([
            'messege' => $wasLinked
                ? __('Student already existed — added to your roster')
                : __('Student Added'),
            'alert-type' => 'success',
        ]);
    }

    /**
     * Find a student row belonging to the current coach (or their staff's
     * coach). Aborts 404 on cross-coach access. Added 2026-05-12 as part
     * of the coach-panel IDOR sweep: editStudents/updateStudents/destroy
     * used to do raw User::find($id) which let any coach read/edit/delete
     * any user in the entire system by guessing an id.
     *
     * Scope: students added_by THIS coach OR whose coach_id is THIS coach.
     * Wrapped in a single inner closure so the OR doesn't leak past the
     * id constraint (the pre-fix code used ->whereOr(...) which left the
     * id constraint as a sibling and effectively scoped by added_by OR
     * coach_id alone).
     */
    private function findOwnedStudentOrFail(string|int $id): User
    {
        $coachId = userAuth()->role === 'instructor' ? userAuth()->id : userAuth()->coach_id;
        // 2026-06-01 (audit C2) — scope by the coach_student_links PIVOT, the
        // SAME source of truth myStudents() lists from. The read path used the
        // pivot while edit/update/destroy used the legacy added_by/coach_id
        // columns, so the set a coach could SEE differed from the set they
        // could EDIT/DELETE (and coach_id is NULL on students post-migration).
        $rosterIds = \App\Models\CoachStudentLink::studentIdsForCoach((int) $coachId);
        abort_unless(in_array((int) $id, $rosterIds, true), 404);

        return User::where('id', $id)->where('role', 'student')->firstOrFail();
    }

    public function editStudents(string $id)
    {
        $this->pageName = 'coach-students';
        $flag = checkPermission($this->pageName);

        if ($flag == 1) {
            $student = $this->findOwnedStudentOrFail($id);

            // 2026-06-06 — surface the student's assigned batch(es) so the coach
            // can verify them after add/edit. Scoped to THIS coach's courses so
            // another coach's batch never shows. Supports a student enrolled in
            // multiple courses/batches.
            $coachId = userAuth()->role === 'instructor' ? userAuth()->id : userAuth()->coach_id;
            $assignedBatches = \Modules\Order\app\Models\Enrollment::where('user_id', $student->id)
                ->whereNotNull('batch_id')
                ->whereHas('batch.course', function ($q) use ($coachId) {
                    $q->where('instructor_id', $coachId)->orWhere('added_by', $coachId);
                })
                ->with(['batch:id,title,course_id', 'batch.course:id,title'])
                ->get();

            return view('frontend.instructor-dashboard.my-students.edit', compact('student', 'assignedBatches'));
        } else {

            return view($this->admin_error_view);
        }
    }

    public function updateStudents(Request $request, $id)
    {
        // IDOR fix 2026-05-12 — was User::find($id) with no scope and no
        // permission check. Now both gated.
        $this->pageName = 'coach-students';
        $flag = checkPermission($this->pageName);
        if ($flag != 1) {
            return view($this->admin_error_view);
        }

        $request->validate([
            'name'   => ['required', 'string', 'max:255'],
            'email'  => ['required', 'email', 'max:255'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $student = $this->findOwnedStudentOrFail($id);

        // 2026-06-01 (audit H2) — name/email/status are GLOBAL user columns.
        // If this student is on more than one coach's roster, editing them here
        // would rename their shared account and (status=inactive) lock them out
        // of EVERY coach's courses. Only allow these global mutations when the
        // student belongs to THIS coach exclusively; otherwise the coach can
        // only remove them from their own roster (destroy → unlink).
        $coachId = userAuth()->role === 'instructor' ? userAuth()->id : userAuth()->coach_id;
        $linkedCoaches = \App\Models\CoachStudentLink::coachIdsForStudent((int) $student->id);
        if (count(array_unique($linkedCoaches)) > 1) {
            return redirect()->route('instructor.my-students.index')->with([
                'messege'    => __('This student is shared with other coaches, so their profile cannot be edited here. You can remove them from your roster instead.'),
                'alert-type' => 'error',
            ]);
        }

        $old = ['name' => $student->name, 'email' => $student->email, 'status' => $student->status];
        $student->name = $request->name;
        $student->email = $request->email;
        $student->status = $request->status;
        $student->save();

        // Enterprise H-A — audit student profile update by a coach.
        \App\Services\ActivityLogger::log(
            \App\Models\ActivityLog::UPDATED, 'student', $student,
            $old, ['name' => $student->name, 'email' => $student->email, 'status' => $student->status],
            'Updated student "' . $student->name . '"'
        );

        return redirect()->route('instructor.my-students.index')->with(['messege' => __('Student Updated'), 'alert-type' => 'success']);

    }

    public function destroy($id)
    {
        $this->pageName = 'coach-students';
        $methodName = request()->route()->getActionMethod();
        $flag = checkPermission($this->pageName, $methodName);

        if ($flag == 1) {
            // 2026-06-01 (audit C2) — UNLINK from THIS coach's roster (soft
            // remove the pivot row), never hard-delete the global User. A
            // student can be on many coaches' rosters (M2M); the old
            // $exist->delete() destroyed the SHARED account and cascade-removed
            // every OTHER coach's link too. Removing only the pivot leaves the
            // user (and other coaches' rosters) intact.
            $student = $this->findOwnedStudentOrFail($id);
            $coachId = userAuth()->role === 'instructor' ? userAuth()->id : userAuth()->coach_id;
            \App\Models\CoachStudentLink::unlink((int) $coachId, (int) $student->id);

            // Enterprise H-A — audit student removal from the coach roster.
            \App\Services\ActivityLogger::log(
                \App\Models\ActivityLog::DELETED, 'student', $student,
                ['student' => $student->name, 'coach_id' => $coachId], null,
                'Removed student "' . $student->name . '" from coach roster'
            );

            return redirect()->route('instructor.my-students.index')->with('success', __('Removed from your students'));
        } else {
            return view($this->admin_error_view);
        }
    }
}
