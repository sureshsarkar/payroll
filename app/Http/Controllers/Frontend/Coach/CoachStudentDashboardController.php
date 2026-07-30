<?php

namespace App\Http\Controllers\Frontend\Coach;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Frontend\Coach\Traits\BuildsCoachSiteContext;
use App\Models\CourseProgress;
use App\Services\BrandResolver;
use Illuminate\Http\Request;
use Modules\Order\app\Models\Enrollment;
use Modules\Order\app\Models\Order;

/**
 * Coach-scoped student dashboard.
 *
 * Same data as the platform StudentDashboardController, BUT filtered to
 * only show courses + orders belonging to THIS coach. So a student who
 * bought from Coach A and Coach B will see:
 *   /coach/coach-a/student/my-courses → only Coach A's courses
 *   /coach/coach-b/student/my-courses → only Coach B's courses
 *   /student/my-courses (platform)    → ALL their courses (unchanged)
 *
 * Tenant isolation: every query is scoped via courses.instructor_id =
 * tenant_coach_id. The platform views are reused as-is so we don't fork
 * the dashboard UX.
 */
class CoachStudentDashboardController extends Controller
{
    use BuildsCoachSiteContext;

    /**
     * 2026-06-01 (audit [11]) — Tag each order with a coach-scoped amount.
     *
     * `orderItems` is already eager-loaded constrained to this coach's
     * courses, so its row count tells us how many items belong to this coach,
     * while `order_items_count` (withCount, un-scoped) is the order's total.
     * When they differ, the order spans multiple coaches and its
     * order-level paid_amount must NOT be shown here (it includes the other
     * coach's revenue) — we expose this coach's subtotal instead. Single-coach
     * orders keep showing the exact paid_amount as before.
     */
    private function scopeOrderAmounts($orders): void
    {
        foreach ($orders as $o) {
            $coachItems = $o->orderItems->count();
            $allItems   = (int) ($o->order_items_count ?? $coachItems);
            $o->is_multi_coach    = $allItems > $coachItems;
            $o->coach_items_total = (float) $o->orderItems->sum('price');
            // Amount to display: coach subtotal for mixed orders, else the
            // real paid_amount (preserves coupon/charge-adjusted figure).
            $o->display_amount = $o->is_multi_coach ? $o->coach_items_total : $o->paid_amount;
        }
    }

    public function dashboard(Request $request, string $coachSlug)
    {
        $coach = $request->attributes->get('tenant_coach');
        if (! $coach) {
            return redirect()->route('student.dashboard');
        }

        $userId  = userAuth()->id;
        $coachId = (int) $coach->id;

        $stats = [
            'totalEnrolledCourses' => Enrollment::where('user_id', $userId)
                ->whereHas('course', fn($q) => $q->where('instructor_id', $coachId))
                ->count(),
            'totalOrders' => Order::where('buyer_id', $userId)
                ->whereHas('orderItems.course', fn($q) => $q->where('instructor_id', $coachId))
                ->count(),
        ];

        $orders = Order::withCount('orderItems')
            ->with(['orderItems' => fn($q) => $q
                ->whereHas('course', fn($c) => $c->where('instructor_id', $coachId))])
            ->where('buyer_id', $userId)
            ->whereHas('orderItems.course', fn($q) => $q->where('instructor_id', $coachId))
            ->orderByDesc('id')
            ->take(10)
            ->get();
        $this->scopeOrderAmounts($orders);

        // "Resume learning" — only consider this coach's courses
        $resume = CourseProgress::where('user_id', $userId)
            ->where('current', 1)
            ->whereHas('course', fn($q) => $q->where('instructor_id', $coachId))
            ->with(['course' => fn($q) => $q->withTrashed()])
            ->orderByDesc('id')
            ->first();

        $brand = app(BrandResolver::class)->forCoach($coachId);

        return view('frontend.coach-site.pages.student-dashboard', [
            'coachSlug'        => $coachSlug,
            'coach'            => $coach,
            'brand'            => $brand,
            'page'             => $this->syntheticPage($coach, 'student-dashboard', __('My dashboard')),
            'siteNav'          => $this->siteNavFor($coach, $coachSlug),
            'hasFooterSection' => false,
            'bodyHtml'         => null,
            'stats'            => $stats,
            'orders'           => $orders,
            'resume'           => $resume,
        ]);
    }

    public function myCourses(Request $request, string $coachSlug)
    {
        $coach = $request->attributes->get('tenant_coach');
        if (! $coach) {
            // 2026-06-25 (route audit) — was route('enrolled-courses'), which is
            // NOT a registered name (the real one is 'student.enrolled-courses')
            // → this null-coach fallback threw RouteNotFoundException (500).
            return redirect()->route('student.enrolled-courses');
        }

        $coachId = (int) $coach->id;
        $enrolls = Enrollment::with(['course' => fn($q) => $q->withTrashed()])
            ->where(['user_id' => userAuth()->id, 'has_access' => 1])
            ->whereHas('course', fn($q) => $q->where('instructor_id', $coachId))
            ->orderByDesc('id')
            ->paginate(9);

        $brand = app(BrandResolver::class)->forCoach($coachId);

        return view('frontend.coach-site.pages.student-courses', [
            'coachSlug'        => $coachSlug,
            'coach'            => $coach,
            'brand'            => $brand,
            'page'             => $this->syntheticPage($coach, 'student-courses', __('My courses')),
            'siteNav'          => $this->siteNavFor($coach, $coachSlug),
            'hasFooterSection' => false,
            'bodyHtml'         => null,
            'enrolls'          => $enrolls,
        ]);
    }

    public function orders(Request $request, string $coachSlug)
    {
        $coach = $request->attributes->get('tenant_coach');
        if (! $coach) {
            // 2026-06-25 (route audit) — was route('student-orders'), an
            // unregistered name (real one is 'student.orders.index') → 500.
            return redirect()->route('student.orders.index');
        }

        $coachId = (int) $coach->id;
        // 2026-06-01 (audit [11]) — white-label isolation: only hydrate THIS
        // coach's order items, so a cross-coach order never renders another
        // coach's course titles (or its combined total) on this coach's site.
        $orders = Order::withCount('orderItems') // all-coach item count, for mixed detection
            ->with(['orderItems' => fn($q) => $q
                ->whereHas('course', fn($c) => $c->where('instructor_id', $coachId))
                ->with(['course' => fn($c) => $c->withTrashed()])])
            ->where('buyer_id', userAuth()->id)
            ->whereHas('orderItems.course', fn($q) => $q->where('instructor_id', $coachId))
            ->orderByDesc('id')
            ->paginate(15);

        $this->scopeOrderAmounts($orders->getCollection());

        $brand = app(BrandResolver::class)->forCoach($coachId);

        return view('frontend.coach-site.pages.student-orders', [
            'coachSlug'        => $coachSlug,
            'coach'            => $coach,
            'brand'            => $brand,
            'page'             => $this->syntheticPage($coach, 'student-orders', __('My orders')),
            'siteNav'          => $this->siteNavFor($coach, $coachSlug),
            'hasFooterSection' => false,
            'bodyHtml'         => null,
            'orders'           => $orders,
        ]);
    }
}
