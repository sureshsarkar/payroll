<?php

namespace App\Support;

use App\Models\CoachDomain;
use App\Models\CoachLandingPage;
use App\Models\CoachStudentLink;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Single source of truth for white-label tenant access at the AUTHENTICATION
 * layer.
 *
 * Background: every coach gets a white-label website (custom domain, subdomain,
 * or /coach/{slug} surface). Students are GLOBAL `users` rows; a student's coach
 * membership is the many-to-many `coach_student_links` pivot (a student can
 * belong to several coaches via added/purchase/invite). `users.coach_id` is
 * ALWAYS NULL for students (a migration explicitly clears it — that column is
 * only for coach STAFF).
 *
 * Until now, login authenticated by email+password GLOBALLY and never checked
 * the coach context, so Coach A's student could log in on Coach B's site. Data
 * queries are scoped by resolved_coach_id (so they'd see an empty dashboard),
 * but the session itself was created — a tenant-isolation breach.
 *
 * This class centralises the rule so every entry point (coach login, platform
 * login, social login, API login, the request-time guard) decides the same way.
 * It is fully dynamic for ALL coaches — it never hardcodes a coach id/slug/
 * domain. See [[global-multi-coach-rule]].
 */
class TenantAccess
{
    /**
     * May $user hold an authenticated session under coach context $coachId?
     *
     *   $coachId <= 0 → platform context (no coach surface). Ungated — the
     *                   platform behaves exactly as before.
     *   $coachId  > 0 → a coach surface (custom domain / subdomain / /coach/{slug}).
     *
     * Rules (mirror InstructorMiddleware's coach/staff definitions):
     *   - admin                              → allowed (admin paths are blocked
     *                                           on coach domains separately)
     *   - real coach (instructor, coach_id
     *     NULL)                              → only their OWN coach id
     *   - coach staff (coach_id set, not a
     *     student/admin)                     → only their owning coach
     *   - student                            → only coaches they are actively
     *                                           linked to (coach_student_links)
     *   - anything else                      → denied
     */
    public static function userMayAccessCoach(?User $user, int $coachId): bool
    {
        if ($coachId <= 0) {
            return true; // platform — no tenant gating
        }
        if (! $user) {
            return false;
        }

        $role = (string) ($user->role ?? '');

        if ($role === 'admin') {
            return true;
        }

        // Real coach — a top-level instructor account (coach_id NULL). May only
        // hold a session on their OWN white-label surface.
        if ($role === 'instructor' && empty($user->coach_id)) {
            return (int) $user->id === $coachId;
        }

        // Coach staff — carries coach_id and is not a student/admin. Bound to
        // the coach they work for.
        if (! empty($user->coach_id) && ! in_array($role, ['student', 'admin'], true)) {
            return (int) $user->coach_id === $coachId;
        }

        // Student — must be actively linked to this coach in coach_student_links.
        if ($role === 'student') {
            return in_array(
                $coachId,
                CoachStudentLink::coachIdsForStudent((int) $user->id),
                true
            );
        }

        return false;
    }

    /**
     * The coach context for the actual SURFACE being viewed in a web request:
     * the host-resolved coach (custom domain / subdomain, stamped by
     * ResolveCoachByDomain) first, else the /coach/{slug} tenant (stamped by
     * TenantContext). Returns 0 on the platform.
     *
     * NOTE: deliberately does NOT fall back to the sticky session
     * `tenant_coach_id` — that survives gateway hops and would over-gate plain
     * platform pages. Login attribution uses the session elsewhere; the gate
     * keys only on the real surface.
     */
    public static function surfaceCoachId(Request $request): int
    {
        $resolved = (int) ($request->attributes->get('resolved_coach_id') ?? 0);
        if ($resolved > 0) {
            return $resolved;
        }

        return (int) ($request->attributes->get('tenant_coach_id') ?? 0);
    }

    /**
     * Coach context for the API (stateless — the web domain-resolver middleware
     * does not run). Resolves directly from the request host against
     * coach_domains. Returns 0 for the platform/api host (no change there).
     */
    public static function apiSurfaceCoachId(Request $request): int
    {
        return (int) (CoachDomain::coachIdForHost($request->getHost()) ?? 0);
    }

    /**
     * Coach context for the CHECKOUT/commerce flow. Unlike surfaceCoachId() this
     * DOES include the sticky session `tenant_coach_id` — CoachCheckoutController
     * stamps it and it must survive the payment-gateway hop so coupon scoping and
     * the order-creation tenant block see the coach surface. Returns 0 on the
     * bare platform. Order of trust: host-resolved → /coach/{slug} attr → session.
     */
    public static function checkoutCoachId(Request $request): int
    {
        $resolved = (int) ($request->attributes->get('resolved_coach_id') ?? 0);
        if ($resolved > 0) {
            return $resolved;
        }

        $attr = (int) ($request->attributes->get('tenant_coach_id') ?? 0);
        if ($attr > 0) {
            return $attr;
        }

        return $request->hasSession() ? (int) $request->session()->get('tenant_coach_id', 0) : 0;
    }

    /**
     * PLATFORM CONFINEMENT (2026-06-16) — a student who belongs to at least one
     * coach must NOT use the bare platform (mbsguru.com); they are confined to
     * their coach's website. This returns the URL to send such a student to (so
     * they sign in there), or NULL if the student is not coach-bound (a
     * platform-native student — leave them on the platform) or no coach surface
     * can be resolved (fail open — never trap a user).
     *
     * Resolution: their primary coach's verified custom domain / subdomain →
     * https://host/login; else the /coach/{slug}/login surface on the platform
     * host. Multi-coach students are sent to their first active coach.
     */
    public static function confineUrlForStudent(User $student): ?string
    {
        if ((string) ($student->role ?? '') !== 'student') {
            return null; // coaches/staff/admin are never confined
        }

        $coachIds = CoachStudentLink::coachIdsForStudent((int) $student->id);
        if (empty($coachIds)) {
            return null; // platform-native student — allowed on the platform
        }

        $coachId = (int) $coachIds[0];

        $host = CoachDomain::query()
            ->where('coach_id', $coachId)
            ->whereNotNull('verified_at')
            ->where('status', '!=', 'suspended')
            ->orderByDesc('is_primary')
            ->value('hostname');
        if ($host) {
            return 'https://' . $host . '/login';
        }

        // No own domain yet → the coach's branded surface on the platform host.
        $slug = CoachLandingPage::where('added_by', $coachId)->value('slug');
        if ($slug) {
            return url('/coach/' . $slug . '/login');
        }

        return null; // can't resolve a coach surface — don't trap the student
    }

    /**
     * Is this request hitting the BARE PLATFORM student panel (not the
     * /coach/{slug}/... coach surface, not public/auth pages)? Used to confine
     * coach-bound students to their coach site without touching anything else.
     */
    public static function isPlatformStudentArea(Request $request): bool
    {
        $path = trim($request->path(), '/');

        if (str_starts_with($path, 'coach/')) {
            return false; // a coach-branded surface, even on the platform host
        }

        return $path === 'student' || str_starts_with($path, 'student/');
    }

    /**
     * The effective coach id of the AUTHENTICATED user (web guard) — used by the
     * coach PANEL where the tenant is the logged-in coach, not the domain. A real
     * coach resolves to their own id; coach staff to their owning coach; students/
     * admins/guests to 0. Mirrors InstructorMiddleware's coach/staff definition.
     */
    public static function authCoachId(): int
    {
        $u = auth('web')->user();
        if (! $u) {
            return 0;
        }

        $role = (string) ($u->role ?? '');

        if ($role === 'instructor' && empty($u->coach_id)) {
            return (int) $u->id;
        }
        if (! empty($u->coach_id) && ! in_array($role, ['student', 'admin'], true)) {
            return (int) $u->coach_id;
        }

        return 0;
    }

    /**
     * Auto-attribute a brand-new student to the coach surface they signed up /
     * social-authed on, so a fresh visitor on Coach B's site becomes Coach B's
     * student. No-op on the platform (coachId 0) or for non-students.
     * Idempotent (CoachStudentLink::link).
     */
    public static function autoLinkIfStudent(User $user, int $coachId, string $source = 'invite'): void
    {
        if ($coachId <= 0 || (string) ($user->role ?? '') !== 'student') {
            return;
        }

        try {
            CoachStudentLink::link($coachId, (int) $user->id, $source);
        } catch (\Throwable $e) {
            Log::warning('tenant-autolink-failed', [
                'coach_id'   => $coachId,
                'student_id' => $user->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
