<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

/**
 * Regression guard for IDOR (Insecure Direct Object Reference) on the
 * coach panel.
 *
 * The coach panel exposes per-coach resources (courses, lessons, payouts,
 * landing pages, staff, etc.). Every controller method that accepts a
 * resource ID parameter must verify the resource belongs to the calling
 * coach (`instructor_id == userAuth()->id` OR `coach_id == userAuth()->id`
 * for staff users, typically via $this->effectiveCoachId()).
 *
 * 2026-05-01 audit swept this. The spot-check on 2026-05-06 confirmed
 * the pattern still holds:
 *   - InstructorPayoutController::destroy() — WithdrawRequest::where('id',...)
 *                                             ->where(['user_id'=>$coachId,...])
 *   - CoachAnalyticsController              — courses scoped by added_by/instructor_id
 *   - Coach\LiveClassController             — abort_unless instructor_id == userAuth->id
 *   - Coach\LandingPageController           — explicit instructor/coach scoping
 *
 * This test enforces the property going forward: every coach-panel
 * controller must contain at least one of the canonical ownership-check
 * primitives. If a new file is added without one, CI fails — forcing
 * the author to add an explicit ownership check (or document why the
 * controller doesn't need one).
 */
class CoachPanelIdorTest extends TestCase
{
    /**
     * Files that legitimately lack an ownership-check primitive — typically
     * because they ONLY render forms / lookups for the current authenticated
     * coach (no resource-by-ID parameters at all).
     *
     * Adding to this list requires a per-file review: confirm there is no
     * route accepting an ID for that controller.
     *
     * @var array<int, string>
     */
    private const EXEMPT_FILES = [
        // CoachBillingController (2026-06-24): read-only. Its only method is
        // index(), which shows the ACTING coach's own plan + billing resolved
        // via $this->coachId() (self for a coach, coach_id for staff). No
        // resource-id parameter is ever accepted, so there is no IDOR surface.
        'app/Http/Controllers/Frontend/Coach/CoachBillingController.php',

        // CoachStaffPermissionController: read-only on the coach side per
        // 2026-05-01 audit. Permissions live in a global table; only
        // `index` and `show` are exposed in the coach routes group, no
        // create/update/delete. The IDOR risk requires a write path.
        'app/Http/Controllers/Frontend/Coach/CoachStaffPermissionController.php',

        // CoachBrandSettingController: edit() and update() operate ONLY
        // on userAuth()'s own brand row (resolved via
        // CoachBrandSetting::firstOrCreateForCoach((int) userAuth()->id)).
        // No method takes a resource ID parameter, so there's no id-
        // substitution surface a coach could use to read or mutate
        // another coach's brand. Reviewed 2026-05-21.
        'app/Http/Controllers/Frontend/Coach/CoachBrandSettingController.php',

        // OnboardingController: ALL methods resolve the acting coach
        // via userAuth() in the `coachOrAbort()` helper. No URL
        // parameters carry a coach id; the only id param is theme_id
        // which is platform-shared (no IDOR surface). applyTheme()
        // dispatches a job with $coach->id from userAuth(). Reviewed
        // 2026-05-25 (Theme System phase 3).
        'app/Http/Controllers/Frontend/Coach/OnboardingController.php',

        // CoachCartController: serves the STUDENT-FACING coach cart page,
        // not a coach-panel admin surface. The {coachSlug} URL parameter
        // resolves to a coach via the TenantContext middleware (which
        // checks coach_domains + landing-page slugs — both server-owned).
        // The controller queries the LOGGED-IN STUDENT's carts table and
        // never trusts coachSlug to scope writes. Reviewed 2026-05-26
        // (white-label Phase 2).
        'app/Http/Controllers/Frontend/Coach/CoachCartController.php',

        // CoachCheckoutController: same shape as CoachCartController.
        // Reads userAuth()->cart_total and posts gateway forms keyed to
        // the order_id stored server-side. No coach-id is taken from the
        // URL except via the TenantContext middleware. Reviewed 2026-05-26.
        'app/Http/Controllers/Frontend/Coach/CoachCheckoutController.php',

        // CoachAuthController: student-facing login/register. The
        // {coachSlug} param ONLY drives view branding + the
        // CoachStudentLink row created at registration. CoachStudentLink::
        // link($coachId, $studentId) is idempotent; the worst-case abuse
        // is a student linking themselves to a coach whose URL they
        // visited — which is exactly the intended behavior of the
        // white-label flow. Reviewed 2026-05-26.
        'app/Http/Controllers/Frontend/Coach/CoachAuthController.php',
    ];

    /**
     * Substrings that count as a valid ownership-check primitive.
     * If a controller's source contains AT LEAST ONE of these, it's
     * presumed to be performing some form of per-coach scoping.
     *
     * @var array<int, string>
     */
    private const OWNERSHIP_PRIMITIVES = [
        'effectiveCoachId(',                  // helper used across most coach controllers
        // Newer controllers (2026-07) scope reads/writes through the model's
        // `scopeForCoach($q, $coachId)` query scope — `Model::forCoach($coachId)
        // ->findOrFail()` is exactly the coach-ownership gate this test checks.
        // Matches both static (::forCoach) and instance (->forCoach) calls.
        'forCoach(',
        "where('instructor_id', \$coachId",   // common direct scope
        "where('instructor_id', userAuth()",  // direct user-id scope
        "where('coach_id', \$coachId",
        "where('coach_id', userAuth()",
        // Variant with explicit (int) cast — preferred style for
        // newer controllers (defends against accidentally passing
        // a string id from request input). Added 2026-05-21 along
        // with CoachDomainController which uses this pattern.
        "where('coach_id', (int) userAuth()",
        "where('user_id', \$coachId",         // payouts use user_id
        "where('user_id', userAuth()",
        "where('user_id', auth('web')",       // common alternative to userAuth()
        "where('added_by', \$coachId",        // resources owned via added_by
        "where('added_by', userAuth()",
        'instructor_id == userAuth()',         // abort_unless / similar
        'instructor_id === userAuth()',
        '$coachId == userAuth()',
        // updateOrCreate / firstOrCreate first-arg scoping
        "'instructor_id' => userAuth()->id",
        "'user_id' => userAuth()->id",
        "'instructor_id' => \$coachId",
        // abort_if pattern with auth('web')->user()->id
        "instructor_id != auth('web')->user()->id",
        "instructor_id !== auth('web')->user()->id",
    ];

    public function test_every_coach_panel_controller_has_ownership_check_primitive(): void
    {
        $candidates = array_merge(
            glob(app_path('Http/Controllers/Frontend/Coach/*.php')) ?: [],
            glob(app_path('Http/Controllers/Frontend/Instructor*.php')) ?: []
        );

        $missing = [];
        foreach ($candidates as $abs) {
            $rel = str_replace([base_path() . DIRECTORY_SEPARATOR, '\\'], ['', '/'], $abs);
            if (in_array($rel, self::EXEMPT_FILES, true)) continue;

            $body = (string) file_get_contents($abs);
            $found = false;
            foreach (self::OWNERSHIP_PRIMITIVES as $needle) {
                if (str_contains($body, $needle)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $missing[] = $rel;
            }
        }

        $this->assertEmpty(
            $missing,
            "Coach-panel controllers without an obvious ownership-check primitive.\n" .
            "Each one must either:\n" .
            "  (a) contain at least one of the OWNERSHIP_PRIMITIVES (effectiveCoachId(),\n" .
            "      where('instructor_id', \$coachId), etc.) — see CoachPanelIdorTest::OWNERSHIP_PRIMITIVES\n" .
            "  (b) be added to EXEMPT_FILES with a per-file review confirming no\n" .
            "      controller method accepts a resource ID parameter\n\n" .
            "Files: " . implode(', ', $missing)
        );
    }
}
