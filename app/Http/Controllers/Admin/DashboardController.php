<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Modules\Blog\app\Models\Blog;
use Modules\ContactMessage\app\Models\ContactMessage;
use Modules\Language\app\Models\Language;
use Modules\Order\app\Models\Order;
use App\Services\FinancialReportingService;

class DashboardController extends Controller
{
    public function dashboard(Request $request)
    {
        // FT-IDOR-24 fix (2026-05-28) — was no permission gate. Every
        // admin role in 2026_05_18_120000_seed_additional_admin_roles
        // is granted `dashboard.view` at minimum (it's the floor every
        // role design assumed), and the AdminRolePermissionTest
        // explicitly asserts "each role has dashboard view at minimum".
        // Adding the gate here makes the convention explicit at the
        // call site so future role additions that omit dashboard.view
        // fail fast instead of silently exposing platform-wide
        // earnings aggregates to unauthorised sub-admins.
        checkAdminHasPermissionAndThrowException('dashboard.view');

        // remove intended url from session
        $request->session()->forget('url');

        // Earnings aggregates pushed to SQL — was loading the entire paid-orders
        // table into PHP for a per-row foreach (OOM at scale, slow always).
        // Cached for 60 s because the dashboard is heavy and refreshes are noisy.
        // Also computes the previous-period equivalents so the dashboard's
        // trend badges can show real deltas instead of hardcoded 12.5%.
        // AUD-002 (Release 1) — platform commission via the authoritative
        // FinancialReportingService. Was ((payable_amount + gateway_charge) -
        // coupon_discount_amount) * rate, which double-subtracted the coupon
        // (payable_amount is ALREADY post-coupon) and folded the gateway charge
        // into the commission base. Correct base = payable_amount * rate/100,
        // which equals SUM(OrderItem::commissionAmount) — the coach wallet basis.
        $fin = new FinancialReportingService();

        // AUD-028 — currencies must never be summed together. Determine the deployment's
        // primary currency and whether paid orders span more than one currency. The
        // scalar KPI cards below show the PRIMARY currency's figure (scoped when the
        // deployment is multi-currency); the full per-currency split is exposed
        // separately in $data['commission_by_currency'] and rendered as its own panel.
        // Cache keys embed the currency signature so a change in the currency mix never
        // serves a stale cross-currency figure.
        $primaryCurrency = $fin->primaryCurrency();
        $isMultiCurrency = $fin->isMultiCurrency();
        $earningsCurrency = $isMultiCurrency ? $primaryCurrency : null; // single-currency: unfiltered (keeps legacy NULL-currency rows counted)
        $currencySig = $primaryCurrency . ':' . ($isMultiCurrency ? 'multi' : 'single');

        $earnings = Cache::remember('admin.dashboard.earnings:' . $currencySig, 60, function () use ($fin, $earningsCurrency) {
            $now    = Carbon::now();
            $expr   = $fin->orderCommissionExpr();
            $base   = $fin->paidOrders()->where('commission_rate', '>', 0);
            if ($earningsCurrency !== null) {
                $base->whereRaw('UPPER(TRIM(payable_currency)) = ?', [$earningsCurrency]);
            }

            $sumBetween = fn ($from, $to) => (float) (clone $base)
                ->whereBetween('created_at', [$from, $to])
                ->selectRaw("SUM($expr) s")->value('s');

            return [
                'total'      => (float) (clone $base)->selectRaw("SUM($expr) s")->value('s'),
                'this_year'  => (float) (clone $base)->whereYear('created_at', $now->year)->selectRaw("SUM($expr) s")->value('s'),
                'this_month' => (float) (clone $base)->whereYear('created_at', $now->year)->whereMonth('created_at', $now->month)->selectRaw("SUM($expr) s")->value('s'),
                'today'      => (float) (clone $base)->whereDate('created_at', $now->toDateString())->selectRaw("SUM($expr) s")->value('s'),

                // Previous-period equivalents for trend deltas.
                'total_prev'      => $sumBetween($now->copy()->subMonths(2)->startOfMonth(), $now->copy()->subMonth()->endOfMonth()),
                'this_year_prev'  => $sumBetween($now->copy()->subYear()->startOfYear(), $now->copy()->subYear()->endOfDay()->subDays(1)),
                'this_month_prev' => $sumBetween($now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth()),
                'today_prev'      => $sumBetween($now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()),
            ];
        });
        $totalEarnings      = $earnings['total'];
        $thisYearsEarnings  = $earnings['this_year'];
        $thisMonthsEarnings = $earnings['this_month'];
        $todaysEarnings     = $earnings['today'];

        $dataCal = [];
        $start = Carbon::now()->startOfMonth();
        $end = Carbon::now()->endOfMonth();
        $first_date = $start->toDateString();
        $lastDayofMonth = $end->toDateString();
        // pre($lastDayofMonth);die;

        if ($request->filled('year') && $request->filled('month')) {
            $year = $request->input('year');
            $month = $request->input('month');

            $start = Carbon::createFromDate($year, $month, 1)->startOfMonth();
            $end = $start->copy()->endOfMonth();
        } elseif ($request->filled('year')) {
            $year = $request->input('year');

            $start = Carbon::createFromDate($year, 1, 1)->startOfYear();
            $end = $start->copy()->endOfYear();
        }

        // Audit fix F (2026-05-12) — these three queries used to run on every
        // dashboard hit:
        //   - The monthly-chart aggregate (selectRaw + groupBy)
        //   - Order::orderBy('asc')->first()  for oldestYear
        //   - Order::orderBy('desc')->first() for latestYear
        // Now wrapped in a per-window cache keyed on (year, month). 60 s TTL
        // matches the other dashboard caches. Year-range query is also cached
        // separately because its result doesn't depend on the filter window.
        // AUD-028 — the sales trend is a single-currency series (you cannot plot ₹ and $
        // on one axis). Scope to the primary currency when the deployment is
        // multi-currency; the currency signature is part of the cache key.
        $cacheKey = sprintf('admin.dashboard.chart:%s:%s:%s', $currencySig, $start->format('Y-m-d'), $end->format('Y-m-d'));
        $chart = Cache::remember($cacheKey, 60, function () use ($start, $end, $earningsCurrency) {
            // AUD-011 — sales trend = NET revenue (post-coupon, pre-tax) =
            // SUM(payable_amount). Was (payable_amount + gateway_charge) - coupon,
            // which double-subtracted the coupon and folded in the gateway charge.
            $dataItems = Order::selectRaw('DATE(created_at) as date, SUM(payable_amount) as net_revenue')
                ->where('payment_status', 'paid')
                ->when($earningsCurrency !== null, fn ($q) => $q->whereRaw('UPPER(TRIM(payable_currency)) = ?', [$earningsCurrency]))
                ->whereBetween('created_at', [$start, $end])
                ->groupBy('date')
                ->get();

            $dates = [];
            $cursor = $start->copy();
            while ($cursor <= $end) {
                $dates[] = $cursor->toDateString();
                $cursor->addDay();
            }
            $dataCal = array_fill_keys($dates, 0);
            foreach ($dataItems as $item) {
                $dataCal[$item->date] = (float) $item->net_revenue;
            }
            return array_values($dataCal);
        });

        $data = [];
        $data['monthly_data'] = json_encode($chart);

        // Approved design's default view — 12 monthly bars. Deliberately built on
        // the SAME basis as the daily series above (AUD-011: net revenue =
        // SUM(payable_amount) over paid orders, scoped to the primary currency),
        // so the two views can never disagree with each other.
        $data['revenue_12m'] = Cache::remember(
            'admin.dashboard.rev12:' . $currencySig,
            60,
            function () use ($earningsCurrency) {
                $from = Carbon::now()->startOfMonth()->subMonths(11);

                $rows = Order::selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, SUM(payable_amount) as net_revenue")
                    ->where('payment_status', 'paid')
                    ->when($earningsCurrency !== null,
                        fn ($q) => $q->whereRaw('UPPER(TRIM(payable_currency)) = ?', [$earningsCurrency]))
                    ->where('created_at', '>=', $from)
                    ->groupBy('ym')
                    ->pluck('net_revenue', 'ym');

                $labels = [];
                $values = [];
                for ($i = 0; $i < 12; $i++) {
                    $month    = $from->copy()->addMonths($i);
                    $labels[] = $month->format('M');
                    $values[] = (float) ($rows[$month->format('Y-m')] ?? 0);
                }

                return ['labels' => $labels, 'values' => $values];
            }
        );

        // Year range — cached separately because it doesn't depend on the
        // filter window. min()/max() are O(1) with an index on created_at;
        // the prior orderBy()->first() was the same plan but ran twice.
        $yearRange = Cache::remember('admin.dashboard.year-range', 60, function () {
            $oldest = Order::min('created_at');
            $latest = Order::max('created_at');
            return [
                'oldest' => $oldest ? Carbon::parse($oldest)->year : Carbon::now()->year,
                'latest' => $latest ? Carbon::parse($latest)->year : Carbon::now()->year,
            ];
        });
        $data['oldestYear'] = $yearRange['oldest'];
        $data['latestYear'] = $yearRange['latest'];
        $counts = Cache::remember('admin.dashboard.counts', 60, function () {
            $now = Carbon::now();
            $thirtyAgo = $now->copy()->subDays(30);
            return [
                'total_orders'         => Order::count(),
                'total_pending_orders' => Order::where('status', 'pending')->count(),
                'total_course'         => Course::count(),
                'total_pending_course' => Course::where(['status' => 'pending', 'is_approved' => 'approved'])->count(),
                'pending_courses'      => Course::where('is_approved', 'pending')->count(),
                'pending_blogs'        => Blog::where('status', 0)->count(),

                // Snapshot of the same counts 30 days ago — used for trend deltas.
                'total_orders_prev'         => Order::where('created_at', '<', $thirtyAgo)->count(),
                'total_pending_orders_prev' => Order::where('status', 'pending')->where('created_at', '<', $thirtyAgo)->count(),
                'total_course_prev'         => Course::where('created_at', '<', $thirtyAgo)->count(),
                'total_pending_course_prev' => Course::where(['status' => 'pending', 'is_approved' => 'approved'])->where('created_at', '<', $thirtyAgo)->count(),
            ];
        });
        $data = array_merge($data, $counts);
        $data['total_earning']       = $totalEarnings;
        $data['this_months_earning'] = $thisMonthsEarnings;
        $data['todays_earning']      = $todaysEarnings;
        $data['this_years_earning']  = $thisYearsEarnings;

        // AUD-028 — currency metadata + the authoritative per-currency commission split.
        // The scalar cards above are the primary currency's figure; the blade renders
        // them with formatMoney($value, $primary_currency) (session-rate independent —
        // NEVER the currency() helper, which multiplies by the live session rate) and
        // shows the full grouped breakdown so mixed currencies are never combined.
        $data['primary_currency']   = $primaryCurrency;
        $data['is_multi_currency']  = $isMultiCurrency;
        $data['commission_by_currency'] = Cache::remember('admin.dashboard.by-currency:' . $currencySig, 60, fn () => $fin->commissionByCurrency());
        // Display symbol for the primary currency (for the chart axis/tooltip) — resolved
        // once from the currency table, independent of the viewer's session currency.
        $primaryRow = allCurrencies()->firstWhere('currency_code', $primaryCurrency);
        $data['primary_currency_symbol'] = $primaryRow->currency_icon ?? '';

        // Trend deltas for each stat card. Returns ['pct' => float, 'dir' => 'up|down|flat'].
        // Flat/N/A when previous value is zero — division by zero would otherwise
        // produce infinity or false trends.
        $delta = function ($current, $previous): array {
            if ($previous <= 0) {
                return ['pct' => null, 'dir' => 'flat'];
            }
            $pct = (($current - $previous) / $previous) * 100;
            return [
                'pct' => round(abs($pct), 1),
                'dir' => $pct > 0 ? 'up' : ($pct < 0 ? 'down' : 'flat'),
            ];
        };
        $data['trend'] = [
            'total_orders'         => $delta($counts['total_orders'],         $counts['total_orders_prev']),
            'total_pending_orders' => $delta($counts['total_pending_orders'], $counts['total_pending_orders_prev']),
            'total_course'         => $delta($counts['total_course'],         $counts['total_course_prev']),
            'total_pending_course' => $delta($counts['total_pending_course'], $counts['total_pending_course_prev']),
            'total_earning'        => $delta($earnings['total'],      $earnings['total_prev']),
            'this_years_earning'   => $delta($earnings['this_year'],  $earnings['this_year_prev']),
            'this_months_earning'  => $delta($earnings['this_month'], $earnings['this_month_prev']),
            'todays_earning'       => $delta($earnings['today'],      $earnings['today_prev']),
        ];
        $data['recent_courses']  = Course::with('instructor:id,name')->latest()->limit(5)->get();
        // 2026-06-25 (Phase 7) — eager-load translation+author; the blade reads
        // $blog->translation->title and $blog->author->name per row (was 2 lazy
        // queries × 5 rows = 10 N+1).
        $data['recent_blogs']    = Blog::with(['translation', 'author:id,name'])->latest()->limit(5)->get();
        $data['recent_contacts'] = ContactMessage::latest()->limit(5)->get();

        // Membership + referral health metrics — cached 60 s.
        $health = Cache::remember('admin.dashboard.health', 60, function () {
            return [
                'membership' => [
                    'active_total'     => \App\Models\UserMembership::where('status', 'active')->where('payment_status', 'paid')->count(),
                    'active_paid'      => \App\Models\UserMembership::where('status', 'active')->where('payment_status', 'paid')->where('payment_method', '!=', 'trial')->count(),
                    'trials_active'    => \App\Models\UserMembership::where('status', 'active')->where('payment_method', 'trial')->count(),
                    'trials_ending_3d' => \App\Models\UserMembership::where('status', 'active')->where('payment_method', 'trial')
                        ->whereBetween('expires_at', [now(), now()->addDays(3)])->count(),
                    'pending_payment'  => \App\Models\UserMembership::where('payment_status', 'pending')->count(),
                    'expired_30d'      => \App\Models\UserMembership::where('status', 'expired')->where('expires_at', '>=', now()->subDays(30))->count(),
                    'cash_this_month'  => (float) \App\Models\UserMembership::where('payment_status', 'paid')
                        ->whereBetween('started_at', [now()->startOfMonth(), now()])
                        ->sum('price_paid'),
                ],
                'referral' => [
                    'total'              => \App\Models\Referral::count(),
                    'pending'            => \App\Models\Referral::where('status', 'pending')->count(),
                    'rewarded_this_month'=> \App\Models\Referral::where('status', 'rewarded')
                        ->whereBetween('rewarded_at', [now()->startOfMonth(), now()])->count(),
                    'wallet_outstanding' => (float) \DB::table('users')->sum('referral_wallet_balance'),
                    'rewards_this_month' => (float) \App\Models\Referral::where('status', 'rewarded')
                        ->whereBetween('rewarded_at', [now()->startOfMonth(), now()])
                        ->sum('reward_amount'),
                ],
            ];
        });
        $data['membership'] = $health['membership'];
        $data['referral']   = $health['referral'];

        // Coaches / institutes — multi-tenant overview for the super-admin
        // (Phase 2, 2026-06-25). Platform-wide counts; cached 60s like the
        // other dashboard aggregates. Coaches = users.role 'instructor'.
        $data['coaches'] = Cache::remember('admin.dashboard.coaches', 60, function () {
            $coach = \App\Models\User::where('role', 'instructor');
            return [
                'total'          => (clone $coach)->count(),
                'active'         => (clone $coach)->where('status', 'active')->count(),
                'inactive'       => (clone $coach)->where('status', '!=', 'active')->count(),
                'new_30d'        => (clone $coach)->where('created_at', '>=', now()->subDays(30))->count(),
                'total_students' => \App\Models\User::where('role', 'student')->count(),
                'domains_total'  => \App\Models\CoachDomain::count(),
                'domains_active' => \App\Models\CoachDomain::where('status', 'active')->count(),
            ];
        });

        // 2026-07-20 — "Top coaches" panel. The RANKING is a cheap grouped count;
        // every money figure comes from the certified
        // FinancialReportingService::coachLifetime(), so the platform-commission
        // formula is never re-derived here (see the AUD-002/003 commission bugs).
        $data['top_coaches'] = Cache::remember('admin.dashboard.top-coaches:' . $currencySig, 60, function () use ($fin) {
            $rows = \DB::table('order_items as oi')
                ->join('orders as o', 'o.id', '=', 'oi.order_id')
                ->join('courses as c', 'c.id', '=', 'oi.course_id')
                ->where('o.payment_status', 'paid')
                ->whereNotNull('c.instructor_id')
                ->selectRaw('c.instructor_id as coach_id, COUNT(DISTINCT o.id) as orders')
                ->groupBy('c.instructor_id')
                ->orderByDesc('orders')
                ->limit(5)
                ->get();

            $out = [];
            foreach ($rows as $row) {
                $coachId = (int) $row->coach_id;
                $user = \App\Models\User::select('id', 'name')->find($coachId);
                if (! $user) {
                    continue;
                }
                $life = $fin->coachLifetime($coachId);
                $out[] = [
                    'id'         => $coachId,
                    'name'       => $user->name,
                    'orders'     => (int) $row->orders,
                    'students'   => count((array) \App\Models\CoachStudentLink::studentIdsForCoach($coachId)),
                    'commission' => (float) ($life['platform_commission'] ?? 0),
                ];
            }
            return $out;
        });

        // 2026-07-20 — System status. Only values we can actually read are shown;
        // "last backup" / "disk usage" have no reliable source in this app, so they
        // are deliberately NOT displayed rather than reported as a guess.
        $data['system'] = [
            'queue'        => config('queue.default'),
            'failed_jobs'  => \Illuminate\Support\Facades\Schema::hasTable('failed_jobs')
                ? (int) \DB::table('failed_jobs')->count() : null,
            'pending_jobs' => \Illuminate\Support\Facades\Schema::hasTable('jobs')
                ? (int) \DB::table('jobs')->count() : null,
        ];

        // Pending coach payouts — surfaced as a KPI on the operator dashboard.
        $data['pending_withdraws'] = Cache::remember('admin.dashboard.pending-withdraws', 60, fn () =>
            (int) \Modules\PaymentWithdraw\app\Models\WithdrawRequest::where('status', 'pending')->count());

        // Audit 2026-05-19 phase 3 — operator strip.
        // The previous dashboard surfaced 8 "totals" cards (lifetime
        // numbers) at the top. Operators care about TODAY first: how
        // much money came in, how many new orders, what needs my
        // attention right now. Render that as the hero strip so the
        // first thing an admin sees on landing is actionable.
        $data['operator'] = Cache::remember('admin.dashboard.operator:' . $currencySig, 60, function () use ($earnings, $primaryCurrency, $health) {
            $now      = Carbon::now();
            $todayStr = $now->toDateString();
            $yesterStr = $now->copy()->subDay()->toDateString();

            $newOrdersToday    = Order::whereDate('created_at', $todayStr)->count();
            $newOrdersYester   = Order::whereDate('created_at', $yesterStr)->count();
            $newSignupsToday   = \DB::table('users')->whereDate('created_at', $todayStr)->count();
            $newSignupsYester  = \DB::table('users')->whereDate('created_at', $yesterStr)->count();
            $liveClassesToday  = \DB::table('course_live_classes')
                ->whereRaw('LEFT(start_time, 10) = ?', [$todayStr])
                ->count();

            // Action items — things that need an admin to look at.
            // AUD-002 — the pending-payment action alert must exclude failed /
            // cancelled / declined payments (they are not "awaiting payment").
            $pendingOrdersOld = Order::where('status', 'pending')
                ->whereNotIn('payment_status', ['failed', 'cancelled', 'declined'])
                ->where('created_at', '<', $now->copy()->subHours(24))
                ->count();
            $coursesAwaitingApproval = Course::where('is_approved', 'pending')->count();
            // ContactMessage doesn't carry a read/seen column in this
            // schema — surface total inbox count instead, with a
            // 7-day window so we don't badge ancient unread items.
            $contactsRecent = \Modules\ContactMessage\app\Models\ContactMessage::query()
                ->where('created_at', '>=', $now->copy()->subDays(7))
                ->count();
            $membershipPending = \App\Models\UserMembership::where('payment_status', 'pending')->count();

            // Delta helper — symmetric pct, "—" when previous was 0.
            $pctDelta = function ($cur, $prev): ?float {
                if ($prev <= 0) return null;
                return round((($cur - $prev) / $prev) * 100, 1);
            };

            return [
                'date'              => $todayStr,
                'currency'          => $primaryCurrency,   // AUD-028 — render with formatMoney, not session-rate currency()
                'revenue_today'     => (float) $earnings['today'],
                'revenue_delta_pct' => $pctDelta($earnings['today'], $earnings['today_prev']),
                'new_orders_today'  => $newOrdersToday,
                'new_orders_delta_pct' => $pctDelta($newOrdersToday, $newOrdersYester),
                'new_signups_today' => $newSignupsToday,
                'new_signups_delta_pct' => $pctDelta($newSignupsToday, $newSignupsYester),
                'live_classes_today'=> $liveClassesToday,

                // Action items — sorted by urgency, only render if > 0.
                'action_items' => array_values(array_filter([
                    // 'label' carries no count — the card renders the number as its
                    // own large figure, so repeating it read as "35 35 pending…".
                    // 'cta' names the destination instead of a bare "Review →".
                    $pendingOrdersOld > 0 ? [
                        'count'    => $pendingOrdersOld,
                        'label'    => __('Payments pending > 24h'),
                        'cta'      => __('Review payouts'),
                        'route'    => 'admin.orders',
                        'severity' => 'high',
                        'icon'     => 'fa-credit-card',
                    ] : null,
                    $coursesAwaitingApproval > 0 ? [
                        'count'    => $coursesAwaitingApproval,
                        'label'    => __('Courses awaiting approval'),
                        'cta'      => __('Review courses'),
                        'route'    => 'admin.courses.index',
                        'severity' => 'medium',
                        'icon'     => 'fa-graduation-cap',
                    ] : null,
                    $membershipPending > 0 ? [
                        'count'    => $membershipPending,
                        'label'    => __('Membership payment pending'),
                        'cta'      => __('Review'),
                        'route'    => 'admin.user-memberships.index',
                        'severity' => 'medium',
                        'icon'     => 'fa-shield-alt',
                    ] : null,
                    ($referralPending = (int) ($health['referral']['pending'] ?? 0)) > 0 ? [
                        'count'    => $referralPending,
                        'label'    => __('Referrals to verify'),
                        'cta'      => __('Review'),
                        'route'    => 'admin.referrals.index',
                        'severity' => 'medium',
                        'icon'     => 'fa-user-plus',
                    ] : null,
                    $contactsRecent > 0 ? [
                        'count'    => $contactsRecent,
                        'label'    => __('New contact messages this week'),
                        'cta'      => __('Review'),
                        'route'    => 'admin.contact-messages',
                        'severity' => 'low',
                        'icon'     => 'fa-envelope',
                    ] : null,
                ])),
            ];
        });

        return view('admin.dashboard', compact('data'));
    }

    public function setLanguage()
    {
        Cache::forget('getSocialLinks');

        $lang = Language::whereCode(request('code'))->first();

        if (session()->has('lang')) {
            session()->forget('lang');
            session()->forget('text_direction');
        }
        if ($lang) {
            session()->put('lang', $lang->code);
            session()->put('text_direction', $lang->direction);

            $notification = __('Language Changed Successfully');
            $notification = ['messege' => $notification, 'alert-type' => 'success'];

            return redirect()->back()->with($notification);
        }

        session()->put('lang', config('app.locale'));

        $notification = __('Language Changed Successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->back()->with($notification);
    }
}
