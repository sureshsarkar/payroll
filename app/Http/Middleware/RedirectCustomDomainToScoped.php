<?php

namespace App\Http\Middleware;

use App\Models\CoachLandingPage;
use App\Models\Course;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * White-Label G2 / G3 (2026-06-04, updated 2026-06-10).
 *
 * On a VERIFIED coach custom domain this middleware keeps the experience
 * tenant-isolated: it strips platform-wide catalog/blog, blocks competitor
 * course pages, and routes commerce (cart/checkout) into the coach-branded
 * surface.
 *
 * 2026-06-10 — the FULL student panel now renders on the coach domain.
 * Previously student/dashboard, student/my-courses and student/orders were
 * redirected to the thin /coach/{slug}/student/* surface (a 3-tile page).
 * Now that every student-panel controller scopes its queries to
 * courses.instructor_id = resolved_coach_id, those bare paths are left to
 * render the complete platform student panel — which inherits the coach's
 * brand via the brand-aware layout. So a student on photongears.io gets the
 * real dashboard (orders, live classes, fees, announcements, certificates …),
 * showing ONLY this coach's data. The student/* entries are therefore removed
 * from the redirect map below.
 *
 * Gating + safety:
 *   - Acts ONLY when resolved_coach_id is set (ResolveCoachByDomain stamps it
 *     for matched verified coach_domains hosts). The platform's own domain has
 *     no stamp → this middleware is a no-op there.
 *   - GET requests only (never interferes with form POSTs / API).
 *   - login / register are intentionally NOT redirected: the white-label
 *     student auth lives at /coach/{slug}/login, while the INSTITUTE OWNER
 *     still reaches their /instructor panel through the normal login.
 *   - No redirect loop: the targets ('coach/{slug}/...') are not in the map.
 *   - Query string is preserved (?bundle=, ?gift=, ?ref=, ...).
 */
class RedirectCustomDomainToScoped
{
    /** Commerce paths served at the CLEAN ROOT url on a coach domain (rendered
     *  in place — see handle(), 2026-06-10). */
    private const COMMERCE = ['cart', 'checkout'];

    /** Single-segment slugs never treated as a coach page (auth / functional). */
    private const RESERVED = [
        'login', 'register', 'logout', 'cart', 'checkout', 'dashboard',
        'courses', 'blog', 'set-currency', 'change-theme', 'all-coaches',
        // 2026-06-12 — password-reset flow must always fall through to the auth
        // routes on a coach domain (never be intercepted as a coach page),
        // otherwise "Forgot password?" 404s on custom websites.
        'forgot-password', 'reset-password',
    ];

    /** Platform public marketing pages that must NOT show platform content on a
     *  coach domain — fall back to the coach home when the coach lacks the page. */
    private const PLATFORM_MARKETING = [
        'about-us', 'contact', 'contact-us', 'privacy-policy', 'terms-and-conditions',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $coachId = (int) $request->attributes->get('resolved_coach_id');

        // NOTE: the /admin/* white-label block lives in ResolveCoachByDomain
        // (runs first, before the admin auth guard) — not here.

        if ($coachId > 0 && $request->isMethod('get')) {
            $path = trim($request->path(), '/');

            // (a) 2026-06-10 — commerce at CLEAN ROOT urls on a coach domain.
            // Render the coach-branded cart/checkout IN PLACE (no redirect → the
            // browser URL stays photongears.io/cart, /checkout). tenant_coach is
            // stamped from the server-resolved resolved_coach_id so the coach
            // controllers (built for the /coach/{slug} path surface) work
            // unchanged; their internal links use coachCommerceUrl() which also
            // emits clean root urls on a coach domain.
            if (in_array($path, self::COMMERCE, true)) {
                $slug  = CoachLandingPage::where('added_by', $coachId)->orderBy('id')->value('slug');
                $coach = \App\Models\User::find($coachId);
                if ($slug && $coach) {
                    $request->attributes->set('tenant_coach', $coach);
                    $controller = $path === 'cart'
                        ? \App\Http\Controllers\Frontend\Coach\CoachCartController::class
                        : \App\Http\Controllers\Frontend\Coach\CoachCheckoutController::class;
                    // The coach controller returns a View (cart/checkout) or a
                    // RedirectResponse (auth/empty/mixed). Pass redirects through;
                    // wrap a View into a Response to satisfy the return type.
                    $result = app($controller)->index($request, $slug);
                    return $result instanceof Response ? $result : response($result);
                }
            }

            // (a2) 2026-06-11 — AUTH at clean root urls on a coach domain.
            // Typing /login or /register on a coach domain must open the
            // COACH-BRANDED auth pages (CoachAuthController, coach master
            // layout), never the platform's login. GET only (this whole block
            // is GET-gated) — the coach forms POST to their own
            // /coach/{slug}/login|register routes. Dynamic per coach (slug
            // resolved from the landing page), no hardcoding.
            if (in_array($path, ['login', 'register'], true)) {
                $slug = CoachLandingPage::where('added_by', $coachId)->orderBy('id')->value('slug');
                if ($slug) {
                    return redirect()->to('/coach/' . $slug . '/' . $path);
                }
            }

            // (b) 2026-06-09 — content isolation (G2/G3). The platform's catalog
            // and the platform blog INDEX are platform-WIDE (every coach's
            // content); they must never render on a coach's branded domain.
            // 2026-06-23 — but /blog/{slug} is now the COACH'S OWN blog post
            // (CoachBlogPublicController resolves the post by the host's coach,
            // never the platform), so it is allowed through; only the bare
            // /courses and /blog listings bounce to the coach home.
            if ($path === 'courses' || $path === 'blog') {
                return redirect()->to('/');
            }

            // (b2) 2026-06-10 — White-label PAGE PRECEDENCE. The coach's OWN
            // marketing pages must win over the platform's same-slug public pages
            // (about-us, contact, privacy-policy, terms, and any custom page).
            // Otherwise e.g. /about-us matches the platform AboutPageController and
            // leaks the MBSGuru page; the coach's same-slug CoachPage (served by the
            // custom-domain Route::fallback) never gets a turn because that platform
            // route already matched. Single-segment GET; reserved auth/functional
            // slugs are never intercepted. Scales to every coach + every page.
            if ($path !== '' && ! str_contains($path, '/') && ! in_array($path, self::RESERVED, true)) {
                $hasPage = \App\Models\CoachPage::forCoach($coachId)->published()
                    ->where('slug', $path)->exists();
                if ($hasPage) {
                    return app(\App\Http\Controllers\Frontend\CoachSitePublicController::class)
                        ->showOnDomain($path);
                }
                // No matching coach page — but never leak the platform's own
                // marketing pages onto a coach domain; send those to the coach home.
                if (in_array($path, self::PLATFORM_MARKETING, true)) {
                    return redirect()->to('/');
                }
            }

            // (c) Course detail is linked from the coach site as /course/{slug},
            // so this coach's OWN courses must keep working — but a *competitor's*
            // course must not be viewable on this coach's domain. Allow only when
            // the course belongs to the resolved coach; otherwise → coach home.
            if (str_starts_with($path, 'course/')) {
                $slug = explode('/', substr($path, strlen('course/')))[0];
                if ($slug !== '') {
                    // 2026-06-12 — load the row (not just instructor_id), so we
                    // can tell "course doesn't exist" (let it 404 downstream)
                    // apart from "exists but NOT this coach's". A course that
                    // exists with a NULL/orphan owner is treated as not-this-
                    // coach → bounce (previously it slipped through, relying on
                    // CoursePageController to 404 — now the policy is consistent).
                    $course = Course::where('slug', $slug)->first(['instructor_id']);
                    if ($course && (int) $course->instructor_id !== $coachId) {
                        return redirect()->to('/');
                    }
                }
            }

            // (d) Course player /learning/{courseSlug}[/...] — same rule: only
            // this coach's own course may be learned on this domain. (Access is
            // already enrollment-gated, so this is isolation polish, not a data
            // gate.) Skip the id-keyed sub-routes (resource-download/quiz/...),
            // which aren't course-slug-addressed. 2026-06-12 — an existing
            // course with a NULL/orphan owner is treated as not-this-coach.
            if (str_starts_with($path, 'learning/')) {
                $seg = explode('/', substr($path, strlen('learning/')))[0];
                if ($seg !== '' && ! in_array($seg, ['resource-download', 'quiz', 'quiz-result'], true)) {
                    $course = Course::where('slug', $seg)->first(['instructor_id']);
                    if ($course && (int) $course->instructor_id !== $coachId) {
                        return redirect()->to('/');
                    }
                }
            }
        }

        return $next($request);
    }
}
