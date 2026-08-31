<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Modules\GlobalSetting\app\Models\MarketingSetting;
use Modules\GlobalSetting\app\Models\SeoSetting;
use Modules\GlobalSetting\app\Models\Setting;

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
            $setting = (object) ['timezone' => config('app.timezone')];
            $marketing_setting = (object) [];
            $seo_setting = (object) [];
        }

        /** Share settings to all views */
        View::composer('*', function ($view) use ($setting, $marketing_setting, $seo_setting) {
            // $brand is the platform brand (name, logo, favicon, colors,
            // support email/phone). Every view receives it via this composer;
            // blades use $brand->name / $brand->logoUrl() / $brand->primaryColor
            // instead of reaching into cache('setting') directly.
            //
            // Wrapped in try/catch so a server whose settings table hasn't been
            // migrated yet still renders, falling back to the same platform
            // brand built straight from whatever settings we do have.
            try {
                $brand = app(\App\Services\BrandResolver::class)->current();
            } catch (\Throwable $e) {
                $brand = \App\Services\Brand::platform(
                    $setting ? (array) $setting : []
                );
            }

            // LMS removal phase 2 (2026-08-27) — the shared payload used to
            // carry six coach/student counters plus a five-key sidebarBadges
            // array, all recomputed (cached 60s per guard+role+user) on EVERY
            // view render from courses / live classes / orders / enquiries /
            // fee demands / batches. Those tables are gone. The keys are still
            // published as zeros so any straggler blade that reads one renders
            // a harmless 0 instead of throwing on an undefined variable.
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
                'sidebarBadges'            => [
                    'announcement_drafts' => 0, 'enquiries_new' => 0,
                    'fees_overdue' => 0, 'orders_pending' => 0,
                    'batches_active' => 0,
                ],
            ];

            $view->with($shared);
        });
        /* LMS removal phase 2 (2026-08-27) — removed the coach global-footer
         * composer that ran on frontend.layouts.master (the layout every HR
         * and payroll page renders through). It resolved a coach from the
         * request/route and rendered their CoachSiteFooter section. There are
         * no coach sites, and frontend.coach-site.layouts.master is gone. */

        // set timezone
        date_default_timezone_set($setting->timezone ?? config('app.timezone'));

        /** Register custom blade directives */
        $this->registerBladeDirectives();

        // Use Bootstrap 4 pagination
        Paginator::useBootstrapFour();

        // LMS removal phase 2 (2026-08-27) — dropped the DEFAULT_HOMEPAGE
        // constant. It named the active storefront theme (main / online /
        // university / language / kindergarten / business) and only ever
        // selected which marketing homepage to render. There is no storefront.
    }

    /*
     * LMS removal phase 2 (2026-08-27) — eleven sidebar-count helpers lived
     * here and ran, cached for 60s, on EVERY authenticated view render:
     * coach course / live-class / order / student counts, the landing-page
     * slug, and the announcement-draft / enquiry / overdue-fee /
     * pending-order / active-batch badges. Every one queried a table that
     * Phase 2 drops.
     */

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

        // LMS removal phase 2 (2026-08-27) — dropped @theme/@endtheme, which
        // branched on DEFAULT_HOMEPAGE to pick a storefront theme's markup.
    }
}
