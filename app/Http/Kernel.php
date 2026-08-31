<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array<int, class-string|string>
     */
    protected $middleware = [
        // \App\Http\Middleware\TrustHosts::class,
        \App\Http\Middleware\TrustProxies::class,
        \Illuminate\Http\Middleware\HandleCors::class,
        \App\Http\Middleware\PreventRequestsDuringMaintenance::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        // LMS removal phase 2 (2026-08-27) — ApiVersionAlias rewrote
        // `/api/v1/*` → `/api/*` for the mobile LMS client. There is no mobile
        // client and no /api route left.
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array<string, array<int, class-string|string>>
     */
    protected $middlewareGroups = [
        'web' => [
            // LMS removal phase 2 (2026-08-27) — four white-label middlewares
            // ran on every single web request and are gone with the coach
            // sites: ResolveCoachByDomain (host → coach_domains lookup),
            // RedirectCustomDomainToScoped, EnforceTenantAccess and
            // CaptureSiteAttribution (?ref=coach_site/utm_* → Order source).
            // TrackReferralCookie went with the referral wallet.
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\DemoModeMiddleware::class,
            \App\Http\Middleware\SetLocaleMiddleware::class,
            \App\Http\Middleware\XssSanitization::class,
            // SECURITY (audit 2026-05-22) — sets Cache-Control: no-store
            // on every authenticated response so browsers / proxies / CDNs
            // can never serve User A's HTML to User B. The middleware
            // self-detects auth state and only stamps the headers when a
            // user is logged in, so public pages still cache normally.
            // This was the confirmed root cause of "wrong user data
            // appearing" reports.
            \App\Http\Middleware\NoStoreAuthenticated::class,
            // P1-4 (2026-05-29) — Content Security Policy. Generates a
            // per-request nonce + stamps Content-Security-Policy-Report-Only
            // by default (set CSP_ENFORCE=true to switch to enforcing).
            // Skips JSON/file responses automatically.
            \App\Http\Middleware\ContentSecurityPolicy::class,
        ],

        'api' => [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class . ':api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            'json.only',
            // Same no-store guarantee for sanctum API responses
            \App\Http\Middleware\NoStoreAuthenticated::class,
        ],
    ];

    /**
     * The application's middleware aliases.
     *
     * Aliases may be used instead of class names to conveniently assign middleware to routes and groups.
     *
     * @var array<string, class-string|string>
     */
    protected $middlewareAliases = [
        'auth' => \App\Http\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'auth.session' => \Illuminate\Session\Middleware\AuthenticateSession::class,
        'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
        'precognitive' => \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
        'signed' => \App\Http\Middleware\ValidateSignature::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        'translation' => \App\Http\Middleware\SetLocaleMiddleware::class,
        'maintenance.mode' => \App\Http\Middleware\MaintenanceModeMiddleware::class,
        'json.only' => \App\Http\Middleware\API\EnforceJsonMiddleware::class,
        'studentrole' => \App\Http\Middleware\StudentroleMiddleware::class,
        'instructorrole' => \App\Http\Middleware\InstructorMiddleware::class,
        // Multi-tenant: resolves + binds the active Company for HR/Employee requests.
        'companycontext' => \Modules\Company\app\Http\Middleware\EnsureCompanyContext::class,
        '2fa' => \App\Http\Middleware\EnsureTwoFactorChallenged::class,
        // LMS removal phase 2 (2026-08-27) — dropped aliases whose middleware
        // gated only LMS/coach surfaces and which no surviving route uses:
        // api.instructor, payment.api, abilities (mobile API), track.referral,
        // requires.membership + requires.enterprise (coach subscription gates),
        // permission (coach-staff permission slugs), zoom.live.headers and
        // tenant.context (/coach/{slug} white-label surface).
    ];
}
