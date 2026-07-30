<?php

namespace App\Providers;

use App\Enums\ThemeList;
use App\Models\CoachLandingPage;
use App\Models\Course;
use App\Models\CourseChapterLesson;
use App\Models\CourseLiveClass;
use App\Models\User;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Modules\GlobalSetting\app\Models\MarketingSetting;
use Modules\GlobalSetting\app\Models\SeoSetting;
use Modules\GlobalSetting\app\Models\Setting;
use Modules\Order\app\Models\Enrollment;
use Modules\Order\app\Models\OrderItem;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 2026-06-22 — delivery observability: log every mail-channel
        // notification (sent/failed) into notification_email_logs.
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Notifications\Events\NotificationSent::class,
            [\App\Listeners\LogNotificationEmail::class, 'sent']
        );
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Notifications\Events\NotificationFailed::class,
            [\App\Listeners\LogNotificationEmail::class, 'failed']
        );

        // 2026-07-09 (Phase 5.3) — universal outbound-mail trail so the legacy
        // Mailables (credential/order/QnA/live-class) are auditable too, not just
        // notifications. Toggle with MAIL_LOG_SENDS (default on).
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Mail\Events\MessageSent::class,
            [\App\Listeners\LogMailSent::class, 'handle']
        );

        // 2026-06-02 — when the site is configured for HTTPS (APP_URL=https://…)
        // force every generated URL (asset()/route()/url()) to https. This
        // prevents mixed-content (an http:// asset on an https:// page), which
        // browsers block and which can stop the Zoom SDK / page JS from loading
        // — and live classes need a secure (https) context to work at all.
        // SAFE on local: while APP_URL stays http://localhost this is a no-op.
        // NOTE: if you serve HTTPS behind a proxy / Cloudflare / load balancer,
        // also set TrustProxies::$proxies (e.g. '*') so the forwarded https
        // scheme is detected, otherwise Laravel still thinks the request is http.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        try {
            /** Cache settings (with secret values transparently decrypted) */
            $setting = Cache::rememberForever('setting', function () {
                $raw = Setting::pluck('value', 'key')->all();
                return (object) \App\Support\SecretSettings::decryptArray($raw);
            });
            // echo "<pre>",print_r($setting);
            // exit;
            $marketing_setting = Cache::rememberForever('marketing_setting', fn () => (object) MarketingSetting::pluck('value', 'key')->all());
            $seo_setting = Cache::rememberForever('seo_setting', fn () => (object) SeoSetting::all()->groupBy('page_name')->mapWithKeys(function ($group, $pageName) {
                return [$pageName => $group->first()];
            }));

            if ($setting) {
                set_wasabi_config();
                set_aws_config();
            }
        } catch (\Throwable $th) {
            info($th);
            $setting = (object) ['timezone' => config('app.timezone'), 'site_theme' => ThemeList::MAIN->value];
            $marketing_setting = (object) [];
            $seo_setting = (object) [];
        }

        /** Share settings to all views */
        View::composer('*', function ($view) use ($setting, $marketing_setting, $seo_setting) {
            // 2026-05-21 — per-coach white-label.
            // $brand is the COMPOSED brand (coach overrides + platform
            // defaults + sane fallbacks). Every view receives it via
            // this composer; blades can use $brand->name / $brand->logoUrl()
            // / $brand->primaryColor instead of cache('setting')->*
            // for any field a coach should be able to override.
            //
            // Wrapped in try/catch so a fresh deploy without the
            // coach_brand_settings table (e.g. on a pre-migrate server)
            // still renders — falls back to platform brand.
            try {
                $brand = app(\App\Services\BrandResolver::class)->current();
            } catch (\Throwable $e) {
                $brand = \App\Services\Brand::platform(
                    $setting ? (array) $setting : []
                );
            }

            $shared = [
                'setting' => $setting,
                'marketing_setting' => $marketing_setting,
                'seo_setting' => $seo_setting,
                'brand' => $brand,
                'totalCoachUpcomingLive'   => 0,
                'totalCouachCourses'       => 0,
                'totalCoachOrders'         => 0,
                'totalCoachStudents'       => 0,
                'landingPageDomain'        => '',
                'totalStudentUpcomingLive' => 0,
            ];

            if (auth()->check()) {
                // SECURITY (2026-05-22) — Cache key MUST include the guard
                // name and the user's role + email hash, otherwise admin
                // id=5 and instructor id=5 (different users from different
                // tables) collide and one sees the other's sidebar counts
                // for 60s. Audit-confirmed root cause of "user A sees user
                // B's data" reports.
              try {
                // 2026-06-16 — HARDENING. This composer runs on EVERY view
                // (View::composer('*')). If ANY sidebar-count query throws (a
                // partial deploy, a stale OPcache, a missing column…), an
                // unguarded exception here blanks the ENTIRE site. Wrap it so the
                // page always renders with safe zero badges instead.
                $guard = Auth::getDefaultDriver();              // 'web' | 'admin' | …
                $authUser = auth()->user();
                $uid  = $authUser->id ?? 0;
                $role = $authUser->role ?? 'guest';
                // Email-hash adds a final tie-breaker that survives any
                // future guard/role reshuffles. 8 chars of sha256 is
                // sufficient — collision domain is per-(guard, id, role).
                $emailKey = substr(sha1((string) ($authUser->email ?? '')), 0, 8);
                $counts = Cache::remember("layout_counts:{$guard}:{$role}:{$uid}:{$emailKey}", 60, function () {
                    return [
                        'totalCoachUpcomingLive'   => $this->getCoachLiveClassCount(),
                        'totalCouachCourses'       => $this->getCoachTotalCourseCount(),
                        'totalCoachOrders'         => $this->getCoachTotalOrderCount(),
                        'totalCoachStudents'       => $this->getCoachTotalStudentCount(),
                        'landingPageDomain'        => $this->getCoachLandingPageSlug(),
                        'totalStudentUpcomingLive' => $this->getStudentLiveClassCount(),
                        // 2026-05-20 — sidebar attention badges. Each
                        // returns 0 when there's nothing to flag; sidebar
                        // auto-hides badges that read 0.
                        'sidebarBadges'            => [
                            'announcement_drafts' => $this->getCoachAnnouncementDraftCount(),
                            'enquiries_new'       => $this->getCoachEnquiriesNewCount(),
                            'fees_overdue'        => $this->getCoachFeesOverdueCount(),
                            'orders_pending'      => $this->getCoachOrdersPendingCount(),
                            'batches_active'      => $this->getCoachActiveBatchCount(),
                        ],
                    ];
                });
                $shared = array_merge($shared, $counts);
              } catch (\Throwable $e) {
                \Log::warning('layout sidebar counts failed (degraded to zeros): ' . $e->getMessage());
                $shared['sidebarBadges'] = [
                    'announcement_drafts' => 0, 'enquiries_new' => 0,
                    'fees_overdue' => 0, 'orders_pending' => 0, 'batches_active' => 0,
                ];
              }
            } else {
                // Unauth path — empty badges so the view doesn't have to
                // null-check.
                $shared['sidebarBadges'] = [
                    'announcement_drafts' => 0, 'enquiries_new' => 0,
                    'fees_overdue' => 0, 'orders_pending' => 0,
                    'batches_active' => 0,
                ];
            }

            $view->with($shared);
        });

        // 2026-06-18 — GLOBAL FOOTER on EVERY coach-site page (login / register /
        // cart / checkout / etc.), not only the marketing pages rendered by
        // CoachSitePublicController. Those other pages @extends the coach-site
        // master layout but don't append the footer, so they used to fall back
        // to the generic config footer. Resolve the coach by SURFACE and inject
        // the rendered global footer so it shows consistently everywhere. Pages
        // that already carry their own footer (hasFooterSection) are untouched.
        View::composer(['frontend.coach-site.layouts.master', 'frontend.layouts.master'], function ($view) {
            $data = $view->getData();
            if (! empty($data['hasFooterSection']) || ! empty($data['globalFooterHtml'])) {
                return;
            }
            try {
                $coachId = (int) (request()->attributes->get('resolved_coach_id')
                    ?: request()->attributes->get('tenant_coach_id')
                    ?: (request()->hasSession() ? request()->session()->get('tenant_coach_id') : 0));
                // Path surface (/coach/{slug}/login|cart|checkout|…) may not carry
                // a stamp on every route — resolve straight from the route slug.
                if ($coachId <= 0) {
                    $slug = request()->route('coachSlug') ?? request()->route('site_slug');
                    if ($slug) {
                        $coachId = (int) (\App\Models\CoachLandingPage::where('slug', $slug)->value('added_by') ?? 0);
                    }
                }
                if ($coachId <= 0 && ! empty($data['page']?->coach_id)) {
                    $coachId = (int) $data['page']->coach_id;
                }
                if ($coachId <= 0) {
                    return;
                }
                $footer = \App\Models\CoachSiteFooter::where('coach_id', $coachId)
                    ->where('is_enabled', true)->first();
                if (! $footer || empty($footer->content_json)) {
                    return;
                }
                $section = new \App\Models\CoachPageSection([
                    'section_type'    => 'footer_v1',
                    'section_version' => $footer->section_version ?: 'v1',
                    'content_json'    => $footer->content_json,
                    'is_visible'      => true,
                ]);
                // renderOne() requires a real CoachPage — other pages (login/cart)
                // may carry an unrelated stdClass `$page`, so fall back to a stub.
                $page = (($data['page'] ?? null) instanceof \App\Models\CoachPage)
                    ? $data['page']
                    : new \App\Models\CoachPage(['coach_id' => $coachId, 'slug' => 'home', 'page_type' => 'home']);
                $section->setRelation('page', $page);
                $coach = \App\Models\User::find($coachId);
                $view->with('globalFooterHtml',
                    app(\App\Services\Site\SectionRenderer::class)->renderOne($section, $page, $coach));
            } catch (\Throwable $e) {
                // Footer resolution must never blank a page.
            }
        });

        // set timezone
        date_default_timezone_set($setting->timezone ?? config('app.timezone'));

        /** Register custom blade directives */
        $this->registerBladeDirectives();

        // Use Bootstrap 4 pagination
        Paginator::useBootstrapFour();

        // Define default homepage based on site_theme from setting, with fallback.
        // Guarded so artisan config:cache (which boots providers twice) doesn't redefine it.
        if (!defined('DEFAULT_HOMEPAGE')) {
            define('DEFAULT_HOMEPAGE', $setting?->site_theme ?? ThemeList::MAIN->value);
        }

        // $this->totalUpcomingLive;

    }

   




    protected function getCoachTotalCourseCount()
    {
        if (! auth()->check()) {
            return 0;
        }
        $user = userAuth();
        if (! $user) {
            return 0;
        }
        $coachId = $user->id;
        $branchIds = User::where('parent_coach_id', userAuth()->id)
            ->pluck('id')
            ->toArray();

        return Course::where(function ($query) use ($coachId, $branchIds) {
            $query->where('added_by', $coachId)
                // BUGFIX (audit 2026-05-22) — `orWhere('branch_id', $branchIds)`
                // with $branchIds = [1,2,3] silently coerces the array to a
                // string, producing `branch_id = '[1,2,3]'` which never
                // matches anything. Use orWhereIn for array values.
                // Also guard against empty array → SQL error.
                ->when(!empty($branchIds), function ($q) use ($branchIds) {
                    $q->orWhereIn('branch_id', $branchIds);
                })
                ->orWhere('instructor_id', $coachId);
        })->count();
    }
 // Get Coach Today's Live Classes Count
    protected function getCoachLiveClassCount()
    {
        if (! auth()->check()) {
            return 0;
        }

        $coachId = userAuth()?->id;

        // Get branch IDs created by coach
        $branchIds = User::where('parent_coach_id', $coachId)
            ->pluck('id')
            ->toArray();

        // 2026-06-23 — the sidebar pill is labelled "LIVE" (pulsing dot), so it
        // must count classes that are GENUINELY RUNNING right now, not every
        // class scheduled for today (which over-counted: a class at 4:50 PM
        // showed as "LIVE" at 11 AM). This mirrors the list badge and
        // LiveMeetingGuard::coachHasRunningClass — started, not over, not
        // ended/cancelled. Window-bounded (started within the last 12h) so the
        // computed isOver()/hasStarted() filter only touches a tiny row set.
        $rows = CourseLiveClass::where(function ($query) use ($coachId, $branchIds) {
            $query->whereHas('lesson', function ($q) use ($coachId) {
                $q->where('instructor_id', $coachId);
            });
            if (! empty($branchIds)) {
                $query->orWhereIn('branch_id', $branchIds);
            }
        })
            ->whereNull('ended_at')
            ->whereNull('cancelled_at')
            ->whereNotNull('start_time')
            ->where('start_time', '<=', now())                 // already started
            ->where('start_time', '>=', now()->subHours(12))   // bound the set
            ->get();

        return $rows->filter(fn ($lc) => $lc->hasStarted() && ! $lc->isOver())->count();
    }
    
    protected function getCoachTotalOrderCount()
    {
        if (! auth()->check()) {
            return 0;
        }
        $user = userAuth();
        if (! $user) {
            return 0;
        }
        $userId = $user->id;

        return OrderItem::join('courses', 'order_items.course_id', '=', 'courses.id')
            ->where(function ($query) use ($userId) {
                $query->where('courses.added_by', $userId)->orWhere('courses.instructor_id', $userId);
            })
            ->count();
    }

    protected function getCoachTotalStudentCount()
    {
        if (! auth()->check()) {
            return 0;
        }
        $user = userAuth();
        if (! $user) {
            return 0;
        }
        // 2026-05-21 — multi-coach: count from the pivot table.
        // Replaces the old (added_by OR branch OR coach_id) query
        // which underreported coaches who acquired students via
        // course purchase. The pivot is the single source of truth
        // for "this student is on my roster".
        try {
            return count(\App\Models\CoachStudentLink::studentIdsForCoach((int) $user->id));
        } catch (\Throwable $e) {
            // Pivot table not yet migrated on this install — graceful
            // degradation so the sidebar still renders.
            return 0;
        }
    }

    protected function getCoachLandingPageSlug()
    {
        if (! auth()->check()) {
            return 0;
        }

        $user = userAuth();
        if (! $user) {
            return 0;
        }
        $userId = $user->id;

        return CoachLandingPage::where('added_by', $userId)->where('is_published', 1)->value('subdomain') ?? '';
    }

    /* ────────────────────────────────────────────────────────────────
       2026-05-20 — Sidebar attention badge counts.
       Each returns the number of items that need the coach's attention.
       Wrapped in try/catch + degrades to 0 so a missing table never
       breaks the layout (deferred-module installs).
       ──────────────────────────────────────────────────────────────── */

    protected function getCoachAnnouncementDraftCount(): int
    {
        try {
            if (!auth()->check()) return 0;
            $coachId = userAuth()?->role === 'instructor' ? userAuth()->id : (userAuth()->coach_id ?? userAuth()->id);
            return (int) \DB::table('announcements')
                ->where('instructor_id', $coachId)
                ->where('status', 'inactive')   // 'inactive' = draft in the legacy enum
                ->count();
        } catch (\Throwable $e) { return 0; }
    }

    protected function getCoachEnquiriesNewCount(): int
    {
        try {
            if (!auth()->check()) return 0;
            $uid = (int) (userAuth()->id ?? 0);
            $coachId = userAuth()?->role === 'instructor' ? userAuth()->id : (userAuth()->coach_id ?? userAuth()->id);

            $q = \DB::table('landing_page_enquiries')
                ->where(function ($qq) use ($coachId) {
                    $qq->where('coach_id', $coachId)->orWhere('added_by', $coachId);
                })
                // The Enquiries list treats legacy 'published' as 'new'; match it.
                ->whereIn('status', ['new', 'published']);

            // 2026-07-10 (New Changes for UI #8.1) — staff enquiry visibility is
            // now driven by explicit ASSIGNMENT, so the sidebar badge matches the
            // module: a staff member counts only enquiries assigned to them; a
            // real coach (role='instructor') counts all their tenant's new leads.
            if (userAuth()?->role !== 'instructor') {
                $q->where('assigned_to', $uid);
            }

            return (int) $q->count();
        } catch (\Throwable $e) { return 0; }
    }

    protected function getCoachFeesOverdueCount(): int
    {
        try {
            if (!auth()->check() || !\Schema::hasTable('fee_demands')) return 0;
            $coachId = userAuth()?->role === 'instructor' ? userAuth()->id : (userAuth()->coach_id ?? userAuth()->id);
            return (int) \DB::table('fee_demands')
                ->where('coach_id', $coachId)
                ->where('status', 'published')
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', now()->toDateString())
                ->count();
        } catch (\Throwable $e) { return 0; }
    }

    protected function getCoachOrdersPendingCount(): int
    {
        try {
            if (!auth()->check()) return 0;
            $coachId = userAuth()?->role === 'instructor' ? userAuth()->id : (userAuth()->coach_id ?? userAuth()->id);
            // Pending orders that include at least one of this coach's
            // courses. Distinct so a multi-item order counts once.
            return (int) \DB::table('orders as o')
                ->join('order_items as oi', 'oi.order_id', '=', 'o.id')
                ->join('courses as c', 'c.id', '=', 'oi.course_id')
                ->where('o.status', 'pending')
                ->where(function ($q) use ($coachId) {
                    $q->where('c.instructor_id', $coachId)->orWhere('c.added_by', $coachId);
                })
                ->distinct('o.id')->count('o.id');
        } catch (\Throwable $e) { return 0; }
    }

    protected function getCoachActiveBatchCount(): int
    {
        try {
            if (!auth()->check() || !\Schema::hasTable('course_batches')) return 0;
            $uid = (int) (userAuth()->id ?? 0);
            $coachId = userAuth()?->role === 'instructor' ? userAuth()->id : (userAuth()->coach_id ?? userAuth()->id);

            $q = \DB::table('course_batches as b')
                ->join('courses as c', 'c.id', '=', 'b.course_id')
                ->where('b.status', 'active')
                ->where(function ($qq) use ($coachId) {
                    $qq->where('c.instructor_id', $coachId)->orWhere('c.added_by', $coachId);
                });

            // 2026-07-10 (Staff Panel) — for a staff/teacher, count only the batches
            // they can actually see in the Batch module: assigned to them OR under a
            // course they authored (mirrors InstructorCourseController::batchesIndex).
            // A coach → assignedBatchIdsFor returns null → no narrowing.
            $assigned = \App\Models\TeacherBatchAssignment::assignedBatchIdsFor($uid);
            if ($assigned !== null) {
                $q->where(function ($qq) use ($assigned, $uid) {
                    $qq->whereIn('b.id', $assigned ?: [0])
                       ->orWhere('c.added_by', $uid);
                });
            }

            return (int) $q->count();
        } catch (\Throwable $e) { return 0; }
    }

    //  student upcomming live class
    protected function getStudentLiveClassCount()
    {
        if (! auth()->check()) {
            return 0;
        }

        $user = userAuth();
        if (! $user) {
            return 0;
        }
        $userId = $user->id;

        // 2026-06-16 — the sidebar count MUST match the Live Classes list exactly
        // (reported: list showed 1, badge showed "2 LIVE"). Use the same shared
        // scope (tenant + has_access + batch pairing + cancelled filter) instead
        // of a separate, looser query.
        $tenantCoachId = (int) request()->attributes->get('resolved_coach_id');

        $count = CourseLiveClass::visibleToStudent($userId, $tenantCoachId)->count();

        // 2026-07-03 — also count the student's active 1:1 instant meetings so the
        // sidebar pill matches the unified Live Classes list.
        try {
            $count += \App\Models\InstantMeeting::visibleToStudent($userId, $tenantCoachId)->count();
        } catch (\Throwable $e) {
        }

        return $count;
    }

    protected function registerBladeDirectives()
    {
        // Audit fix H4 (2026-05-12) — delegate to checkAdminHasPermission()
        // so the directive and the helper return identical results. The
        // prior implementation called ->user()->can() directly, which
        // null-derefs if no admin is authenticated (e.g. layout shown
        // during password reset). The helper guards for that.
        Blade::directive('adminCan', function ($permission) {
            return "<?php if(checkAdminHasPermission({$permission})): ?>";
        });

        Blade::directive('endadminCan', function () {
            return '<?php endif; ?>';
        });

        // Blade directive for checking the current theme
        Blade::directive('theme', function ($themes) {
            return "<?php if(in_array(DEFAULT_HOMEPAGE, {$themes})): ?>";
        });

        Blade::directive('endtheme', function () {
            return '<?php endif; ?>';
        });
    }
}
