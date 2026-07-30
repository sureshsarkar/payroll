<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseReview;
use App\Models\TeacherBatchAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Modules\Order\app\Models\Enrollment;
use Modules\Order\app\Models\Order;
use Modules\Order\app\Models\OrderItem;
use Modules\PaymentWithdraw\app\Models\WithdrawRequest;

/**
 * Coach analytics dashboard.
 *
 * Query strategy: scope every aggregate to courses the coach owns
 * (added_by = coach OR instructor_id = coach), then aggregate over
 * the order_items joined to (paid + completed) orders.
 *
 * Date range filter: default last 30 days, switchable via ?range=...
 */
class CoachAnalyticsController extends Controller
{
    public function index(Request $request): View
    {
        $u = userAuth();
        $coachId = $u->role === 'instructor' ? $u->id : $u->coach_id;

        // Range: 7, 30, 90, 365 days (default 30) OR a custom From/To date range
        // (2026-07-10 — Staff Panel changes doc: Analytics custom date filter).
        $range = (int) $request->query('range', 30);
        if (!in_array($range, [7, 30, 90, 365])) {
            $range = 30;
        }

        $from = (string) $request->query('from', '');
        $to   = (string) $request->query('to', '');
        $isCustom = false;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            try {
                $rangeStart = Carbon::parse($from)->startOfDay();
                $rangeEnd   = Carbon::parse($to)->endOfDay();
                if ($rangeEnd->lessThan($rangeStart)) {   // tolerate a reversed range
                    [$rangeStart, $rangeEnd] = [$rangeEnd->copy()->startOfDay(), $rangeStart->copy()->endOfDay()];
                }
                $rangeStart = $rangeStart->max(Carbon::now()->subDays(366)->startOfDay()); // cap the daily-bucket loop
                $rangeDays  = $rangeStart->diffInDays($rangeEnd) + 1;
                $from = $rangeStart->toDateString();
                $to   = $rangeEnd->toDateString();
                $isCustom = true;
            } catch (\Throwable $e) {
                $isCustom = false;
            }
        }
        if (! $isCustom) {
            $rangeStart = Carbon::now()->subDays($range)->startOfDay();
            $rangeEnd   = Carbon::now()->endOfDay();
            $rangeDays  = $range;
            $from = '';
            $to   = '';
        }
        $previousStart = (clone $rangeStart)->subDays($rangeDays);

        // 2026-05-20 P6 — Teacher panel. Resolved outside the cache so the
        // cache key can include a fingerprint of the assigned-batch set.
        $teacherBatches = TeacherBatchAssignment::assignedBatchIdsFor((int) $u->id);
        $teacherFp = $teacherBatches === null
            ? 'coach'
            : 'tb:' . substr(sha1(implode(',', $teacherBatches)), 0, 12);

        // PERFORMANCE (audit 2026-05-22) — wrap data-gathering in 60s cache.
        // The original index() ran 25-50+ uncached queries per request
        // (per-course findOrFail, count, avg in the coursePerformance map;
        // per-course completion-funnel iteration; 4 separate large aggregate
        // queries). For a coach with 10 courses this peaked at 50+ queries
        // per dashboard load. Now first hit fills the 60s cache, subsequent
        // hits are a single Redis/DB lookup.
        //
        // Cache key includes: coach id + role + range + teacher-batch
        // fingerprint. A teacher with the same coach but different batch
        // assignments gets a different cache entry — no cross-leakage.
        // A new sale doesn't invalidate the cache but waits at most 60s.
        $cacheKey = "coach.analytics:{$coachId}:role:{$u->role}:r{$range}:c{$from}_{$to}:{$teacherFp}";

        $data = Cache::remember($cacheKey, 60, function () use ($coachId, $range, $rangeStart, $rangeEnd, $rangeDays, $previousStart, $teacherBatches, $u) {
            // All course IDs owned by this coach.
            $courseIds = Course::where(function ($q) use ($coachId) {
                $q->where('added_by', $coachId)->orWhere('instructor_id', $coachId);
            })->pluck('id');

            // Teacher panel — narrow to courses with at least one assigned batch.
            if ($teacherBatches !== null) {
                $allowedCourseIds = \DB::table('course_batches')
                    ->whereIn('id', $teacherBatches ?: [0])
                    ->pluck('course_id')->unique()->map(fn ($v) => (int) $v)->all();
                $courseIds = $courseIds->intersect($allowedCourseIds)->values();
            }

            // === Headline metrics =====================================================
        $paidItemsBase = OrderItem::whereIn('course_id', $courseIds)
            ->whereHas('order', fn($q) => $q->where('payment_status', 'paid'));

        $revenueCurrent = (clone $paidItemsBase)
            ->whereHas('order', fn($q) => $q->where('payment_status', 'paid')->whereBetween('created_at', [$rangeStart, $rangeEnd]))
            ->sum('order_items.price');

        $revenuePrevious = (clone $paidItemsBase)
            ->whereHas('order', fn($q) => $q->where('payment_status', 'paid')
                ->whereBetween('created_at', [$previousStart, $rangeStart]))
            ->sum('order_items.price');

        $revenueDelta = $revenuePrevious > 0
            ? round((($revenueCurrent - $revenuePrevious) / $revenuePrevious) * 100, 1)
            : ($revenueCurrent > 0 ? 100.0 : 0.0);

        $salesCount = (clone $paidItemsBase)
            ->whereHas('order', fn($q) => $q->where('payment_status', 'paid')->whereBetween('created_at', [$rangeStart, $rangeEnd]))
            ->count();

        $uniqueStudents = Enrollment::whereIn('course_id', $courseIds)
            ->where('has_access', 1)
            ->distinct('user_id')
            ->count('user_id');

        $avgRating = CourseReview::whereIn('course_id', $courseIds)->avg('rating');
        $reviewsCount = CourseReview::whereIn('course_id', $courseIds)->count();

        $walletBalance = (float) ($u->wallet_balance ?? 0);

        $pendingPayout = WithdrawRequest::where('user_id', $coachId)->where('status', 'pending')->sum('withdraw_amount');

        // === Revenue chart: $range days, daily buckets ===========================
        $rawDaily = OrderItem::whereIn('course_id', $courseIds)
            ->whereHas('order', fn($q) => $q->where('payment_status', 'paid'))
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.created_at', [$rangeStart, $rangeEnd])
            ->select(
                DB::raw('DATE(orders.created_at) as bucket'),
                DB::raw('SUM(order_items.price) as revenue'),
                DB::raw('COUNT(*) as sales')
            )
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->pluck('revenue', 'bucket')
            ->toArray();

        // Fill gaps so the chart has every day across the (default or custom) window.
        $chartLabels = [];
        $chartValues = [];
        $cursor = (clone $rangeStart);
        while ($cursor->lte($rangeEnd)) {
            $d = $cursor->toDateString();
            $chartLabels[] = $rangeDays <= 31
                ? $cursor->format('M j')
                : $cursor->format('M');
            $chartValues[] = (float) ($rawDaily[$d] ?? 0);
            $cursor->addDay();
        }

        // === Top 5 courses by revenue ============================================
        $top5Rows = OrderItem::whereIn('course_id', $courseIds)
            ->whereHas('order', fn($q) => $q->where('payment_status', 'paid')->whereBetween('created_at', [$rangeStart, $rangeEnd]))
            ->select('course_id', DB::raw('SUM(price) as revenue'), DB::raw('COUNT(*) as sales'))
            ->groupBy('course_id')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get();
        // F45 — fetch the (≤5) courses in one query instead of per-row find().
        $top5Courses = Course::withTrashed()->whereIn('id', $top5Rows->pluck('course_id'))->get()->keyBy('id');
        $topCourses = $top5Rows->map(function ($row) use ($top5Courses) {
            $row->course = $top5Courses->get($row->course_id);
            return $row;
        });

        // === Rating distribution =================================================
        $ratingDist = CourseReview::whereIn('course_id', $courseIds)
            ->select('rating', DB::raw('COUNT(*) as count'))
            ->groupBy('rating')
            ->pluck('count', 'rating')
            ->toArray();
        $ratingChart = [];
        for ($i = 5; $i >= 1; $i--) {
            $ratingChart[$i] = (int) ($ratingDist[$i] ?? 0);
        }

        // === Recent sales (last 10) ==============================================
        $recentSales = OrderItem::whereIn('course_id', $courseIds)
            ->whereHas('order', fn($q) => $q->where('payment_status', 'paid'))
            ->with(['order.user:id,name,email', 'course:id,title,slug,thumbnail'])
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        /* ==================== ADVANCED METRICS ==================== */

        // === Average Order Value (AOV) ===========================================
        $aov = $salesCount > 0 ? round($revenueCurrent / $salesCount, 2) : 0;

        // === Refund rate (refunded orders / total order items) ===================
        $refundedCount = (clone $paidItemsBase)
            ->whereHas('order', fn($q) => $q->where('payment_status', 'refunded')->whereBetween('created_at', [$rangeStart, $rangeEnd]))
            ->count();
        $refundRate = $salesCount > 0 ? round(($refundedCount / max(1, $salesCount + $refundedCount)) * 100, 1) : 0;

        // === Per-course performance breakdown ====================================
        $perfRows = OrderItem::whereIn('course_id', $courseIds)
            ->whereHas('order', fn($q) => $q->where('payment_status', 'paid'))
            ->select(
                'course_id',
                DB::raw('SUM(price) as revenue'),
                DB::raw('COUNT(*) as sales'),
                DB::raw('COUNT(DISTINCT order_id) as orders'),
                DB::raw('AVG(price) as avg_price')
            )
            ->groupBy('course_id')
            ->orderByDesc('revenue')
            ->get();

        // F45 (audit 2026-06-26) — batch the per-course aggregates (was 5 queries
        // PER course inside ->map()). One grouped query each, keyed by course_id.
        $perfIds      = $perfRows->pluck('course_id')->all();
        $perfCourses  = Course::withTrashed()->whereIn('id', $perfIds)->get()->keyBy('id');
        $perfStudents = Enrollment::whereIn('course_id', $perfIds)->where('has_access', 1)
            ->select('course_id', DB::raw('COUNT(DISTINCT user_id) as c'))->groupBy('course_id')->pluck('c', 'course_id');
        $perfReviews  = CourseReview::whereIn('course_id', $perfIds)
            ->select('course_id', DB::raw('COUNT(*) as cnt'), DB::raw('AVG(rating) as avg_r'))->groupBy('course_id')->get()->keyBy('course_id');
        $perfLessons  = \App\Models\CourseChapterItem::query()
            ->join('course_chapters', 'course_chapters.id', '=', 'course_chapter_items.chapter_id')
            ->whereIn('course_chapters.course_id', $perfIds)
            ->select('course_chapters.course_id as cid', DB::raw('COUNT(*) as c'))
            ->groupBy('course_chapters.course_id')->pluck('c', 'cid');

        $coursePerformance = $perfRows->map(function ($row) use ($perfCourses, $perfStudents, $perfReviews, $perfLessons) {
            $row->course        = $perfCourses->get($row->course_id);
            $row->students      = (int) ($perfStudents[$row->course_id] ?? 0);
            $rev                = $perfReviews->get($row->course_id);
            $row->reviews_count = (int) ($rev->cnt ?? 0);
            $row->avg_rating    = round((float) ($rev->avg_r ?? 0), 1);
            $row->total_lessons = (int) ($perfLessons[$row->course_id] ?? 0);
            return $row;
        });

        // === Sales heatmap: day-of-week (0=Sun, 6=Sat) × hour-of-day (0-23) ======
        $heatmapRaw = OrderItem::whereIn('course_id', $courseIds)
            ->whereHas('order', fn($q) => $q->where('payment_status', 'paid'))
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.created_at', '>=', Carbon::now()->subDays(90)->startOfDay())  // 90-day window for meaningful heatmap
            ->select(
                DB::raw('DAYOFWEEK(orders.created_at) - 1 as dow'),  // 0..6
                DB::raw('HOUR(orders.created_at) as hr'),
                DB::raw('COUNT(*) as cnt')
            )
            ->groupBy('dow', 'hr')
            ->get();
        $heatmap = array_fill(0, 7, array_fill(0, 24, 0));
        $heatmapMax = 0;
        foreach ($heatmapRaw as $row) {
            $heatmap[(int) $row->dow][(int) $row->hr] = (int) $row->cnt;
            if ($row->cnt > $heatmapMax) $heatmapMax = (int) $row->cnt;
        }

        // === Top customers by lifetime spend =====================================
        $topCustomers = Order::where('payment_status', 'paid')
            ->whereHas('orderItems', fn($q) => $q->whereIn('course_id', $courseIds))
            ->select('buyer_id', DB::raw('SUM(paid_amount) as lifetime'), DB::raw('COUNT(*) as orders'), DB::raw('MAX(created_at) as last_order'))
            ->groupBy('buyer_id')
            ->orderByDesc('lifetime')
            ->limit(8)
            ->with(['user:id,name,email,image'])
            ->get();

        // === Revenue by payment method (donut chart) =============================
        $byPaymentMethod = Order::where('payment_status', 'paid')
            ->whereHas('orderItems', fn($q) => $q->whereIn('course_id', $courseIds))
            ->whereBetween('created_at', [$rangeStart, $rangeEnd])
            ->select('payment_method', DB::raw('SUM(paid_amount) as total'))
            ->groupBy('payment_method')
            ->orderByDesc('total')
            ->get();
        $paymentLabels = $byPaymentMethod->pluck('payment_method')->map(fn($m) => $m ?: 'Other')->toArray();
        $paymentValues = $byPaymentMethod->pluck('total')->map(fn($v) => (float) $v)->toArray();

        // === Course-completion funnel (across all coach courses) =================
        $totalEnrolled = Enrollment::whereIn('course_id', $courseIds)->where('has_access', 1)->count();
        $startedCount = \App\Models\CourseProgress::whereIn('course_id', $courseIds)
            ->where('watched', 1)
            ->distinct('user_id', 'course_id')
            ->count(DB::raw('CONCAT(user_id, "_", course_id)'));

        // Approximate "completed" = students with progress count >= 80% of course
        // lectures. F45 (audit 2026-06-26) — batched: lesson-count per course +
        // watched-count per (user,course) in TWO grouped queries instead of two
        // queries PER course, then the per-course 80% threshold is applied in PHP.
        $lessonCountAll = \App\Models\CourseChapterItem::query()
            ->join('course_chapters', 'course_chapters.id', '=', 'course_chapter_items.chapter_id')
            ->whereIn('course_chapters.course_id', $courseIds)
            ->select('course_chapters.course_id as cid', DB::raw('COUNT(*) as c'))
            ->groupBy('course_chapters.course_id')->pluck('c', 'cid');
        $watchedPerUserCourse = \App\Models\CourseProgress::whereIn('course_id', $courseIds)
            ->where('watched', 1)
            ->select('course_id', 'user_id', DB::raw('COUNT(*) as wc'))
            ->groupBy('course_id', 'user_id')->get();
        $completedCount = 0;
        foreach ($watchedPerUserCourse as $r) {
            $total = (int) ($lessonCountAll[$r->course_id] ?? 0);
            if ($total === 0) continue;
            if ((int) $r->wc >= max(1, (int) floor($total * 0.8))) {
                $completedCount++;
            }
        }
        $reviewedCount = CourseReview::whereIn('course_id', $courseIds)->distinct('user_id')->count('user_id');

        $funnel = [
            ['label' => 'Enrolled',  'count' => $totalEnrolled,  'color' => '#5751e1'],
            ['label' => 'Started',   'count' => $startedCount,   'color' => '#3b82f6'],
            ['label' => 'Completed', 'count' => $completedCount, 'color' => '#10b981'],
            ['label' => 'Reviewed',  'count' => $reviewedCount,  'color' => '#f59e0b'],
        ];
        $funnelMax = max(array_column($funnel, 'count')) ?: 1;

            return [
                'range'           => $range,
                'revenueCurrent'  => $revenueCurrent,
                'revenuePrevious' => $revenuePrevious,
                'revenueDelta'    => $revenueDelta,
                'salesCount'      => $salesCount,
                'uniqueStudents'  => $uniqueStudents,
                'avgRating'       => $avgRating ? round($avgRating, 1) : 0,
                'reviewsCount'    => $reviewsCount,
                'walletBalance'   => $walletBalance,
                'pendingPayout'   => $pendingPayout,
                'chartLabels'     => $chartLabels,
                'chartValues'     => $chartValues,
                // Advanced
                'aov'                => $aov,
                'refundRate'         => $refundRate,
                'refundedCount'      => $refundedCount,
                'coursePerformance'  => $coursePerformance,
                'heatmap'            => $heatmap,
                'heatmapMax'         => $heatmapMax,
                'topCustomers'       => $topCustomers,
                'paymentLabels'      => $paymentLabels,
                'paymentValues'      => $paymentValues,
                'funnel'             => $funnel,
                'funnelMax'          => $funnelMax,
                'topCourses'         => $topCourses,
                'ratingChart'        => $ratingChart,
                'recentSales'        => $recentSales,
            ];
        });

        // Request-derived (not cached-data) so the UI can reflect the chosen filter.
        $data['from']     = $from;
        $data['to']       = $to;
        $data['isCustom'] = $isCustom;

        return view('frontend.instructor-dashboard.analytics.index', $data);
    }
}
