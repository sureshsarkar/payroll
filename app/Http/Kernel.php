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
        // 2026-05-22 — API version alias. Rewrites `/api/v1/*` → `/api/*`
        // BEFORE the router matches, so mobile clients can pin a version
        // handle without us duplicating every route. Must be in the
        // global stack (not the api middleware group) because it has to
        // run before route matching.
        \App\Http\Middleware\ApiVersionAlias::class,
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array<string, array<int, class-string|string>>
     */
    protected $middlewareGroups = [
        'web' => [
            // 2026-05-21 P2 — per-coach white-label.
            // Resolves the request's host header against coach_domains
            // and stamps the matched coach id on the request. Runs
            // FIRST in the web stack so the brand is correct on the
            // very first byte rendered — including the login page,
            // landing page, anonymous course catalog. No side-effects
            // for hosts that don't match (platform root, admin host).
            \App\Http\Middleware\ResolveCoachByDomain::class,

            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            // White-Label G2/G3 (2026-06-04) — on a verified coach custom domain,
            // redirect bare student/commerce paths to the tenant-isolated
            // /coach/{slug}/... surface (no-op on the platform domain). Runs
            // after StartSession + SubstituteBindings so the redirect + session
            // are ready; GET-only so it never touches form POSTs.
            \App\Http\Middleware\RedirectCustomDomainToScoped::class,
            // White-label tenant access guard (2026-06-16). Runs after the
            // session is started + the coach domain is resolved: if an already
            // authenticated user is on a coach surface they don't belong to,
            // their session is invalidated immediately (defence-in-depth for the
            // login-time checks). No-op on the platform + for guests.
            \App\Http\Middleware\EnforceTenantAccess::class,
            \App\Http\Middleware\DemoModeMiddleware::class,
            \App\Http\Middleware\SetLocaleMiddleware::class,
            \App\Http\Middleware\XssSanitization::class,
            \App\Http\Middleware\TrackReferralCookie::class,
            // SECURITY (audit 2026-05-22) — sets Cache-Control: no-store
            // on every authenticated response so browsers / proxies / CDNs
            // can never serve User A's HTML to User B. The middleware
            // self-detects auth state and only stamps the headers when a
            // user is logged in, so public pages still cache normally.
            // This was the confirmed root cause of "wrong user data
            // appearing" reports.
            \App\Http\Middleware\NoStoreAuthenticated::class,
            // Coach Marketing Website (audit 2026-05-25) — captures
            // ?ref=coach_site&utm_* query params into the session so the
            // next Order created in this session inherits source attribution.
            \App\Http\Middleware\CaptureSiteAttribution::class,
            // P1-4 (2026-05-29) — Content Security Policy. Generates a
            // per-request nonce + stamps Content-Security-Policy-Report-Only
            // by default (set CSP_ENFORCE=true to switch to enforcing).
            // Runs AFTER ResolveCoachByDomain so coach-specific analytics
            // allowlists (GTM/GA4/Meta Pixel/Hotjar) can be added to the
            // policy. Skips JSON/file responses automatically.
            \App\Http\Middleware\ContentSecurityPolicy::class,
        ],

        'api' => [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class . ':api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\API\AppDemoModeMiddleware::class,
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
        'role' => \App\Http\Middleware\Role::class,
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
        'payment.api' => \App\Http\Middleware\API\HeaderBearerTokenSet::class,
        'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
        // 'subscription' alias removed — superseded by 'requires.membership'.
        // The SubscriptionHistory model still exists for legacy data; the
        // RequiresMembership middleware reads it as a fallback for users with
        // pre-existing rows. The old gating middleware itself is no longer used.
        'studentrole' => \App\Http\Middleware\StudentroleMiddleware::class,
        'instructorrole' => \App\Http\Middleware\InstructorMiddleware::class,
        // 2026-06-09 (security audit) — JSON role gate for the coach API.
        'api.instructor' => \App\Http\Middleware\ApiInstructorMiddleware::class,
        '2fa' => \App\Http\Middleware\EnsureTwoFactorChallenged::class,
        'track.referral' => \App\Http\Middleware\TrackReferralCookie::class,
        'requires.membership' => \App\Http\Middleware\RequiresMembership::class,
        // Coach-panel granular permission gate (2026-07-04) — 'permission:slug[,slug2]'.
        // Real coach passes; staff must hold at least one slug (override>role>deny).
        'permission' => \App\Http\Middleware\CoachPermission::class,
        // Coach-specific payment gateway feature (2026-06-29) — Enterprise-only gate.
        'requires.enterprise' => \App\Http\Middleware\RequiresEnterpriseMembership::class,
        'zoom.live.headers' => \App\Http\Middleware\ZoomLiveClassHeaders::class,
        // White-label tenant resolver (2026-05-26). Used by the
        // /coach/{coachSlug}/... route group to keep students inside a
        // coach's branded experience for cart/checkout/auth/dashboard.
        'tenant.context' => \App\Http\Middleware\TenantContext::class,
    ];
}
