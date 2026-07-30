<?php

namespace App\Http\Middleware;

use App\Support\TenantAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * White-label tenant access guard (request-time enforcement).
 *
 * Defence-in-depth companion to the login-time checks in the auth controllers.
 * Runs on every web request AFTER the session has started and the host-resolved
 * coach has been stamped (ResolveCoachByDomain). If an ALREADY-authenticated
 * user is sitting on a coach surface they do not belong to, their session is
 * invalidated immediately and they're sent back to that coach's branded home.
 *
 * This closes the "existing session" hole: even if a session was created before
 * this protection shipped (or via some other path), the very next request on a
 * foreign coach domain logs the user out. It NEVER touches the platform
 * (resolved_coach_id 0) or guests, and it never affects a user who legitimately
 * belongs to the coach.
 *
 * Keyed on resolved_coach_id (host-based custom domain / subdomain) — the real
 * white-label surface. The /coach/{slug} path surface gates at the controller
 * (CoachAuthController) since its tenant attribute is set by a later route
 * middleware.
 *
 * Registered in the 'web' middleware group (Kernel.php), after StartSession +
 * RedirectCustomDomainToScoped.
 */
class EnforceTenantAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $coachId = (int) ($request->attributes->get('resolved_coach_id') ?? 0);

        if ($coachId > 0) {
            // On a COACH surface: the authenticated user must belong to this
            // coach, else invalidate the session immediately.
            if (Auth::guard('web')->check()) {
                $user = Auth::guard('web')->user();

                if (! TenantAccess::userMayAccessCoach($user, $coachId)) {
                    Log::warning('tenant-access-denied — session invalidated', [
                        'user_id'        => $user->id ?? null,
                        'role'           => $user->role ?? null,
                        'resolved_coach' => $coachId,
                        'host'           => $request->getHost(),
                        'path'           => $request->path(),
                    ]);

                    Auth::guard('web')->logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    // Stay on the coach's brand — '/' renders the coach home on a
                    // resolved coach domain (HomePageController / middleware).
                    return redirect()->to('/')->with([
                        'messege'    => __('You are not authorized to access this website. Please sign in on the website where your account is registered.'),
                        'alert-type' => 'error',
                    ]);
                }
            }
        } elseif (Auth::guard('web')->check() && TenantAccess::isPlatformStudentArea($request)) {
            // On the BARE PLATFORM (mbsguru.com) student panel: a student who
            // belongs to a coach is confined to their coach's website — they may
            // not use the platform panel. Invalidate the platform session and
            // send them to their coach site to sign in. Platform-native students
            // (no coach link) and coaches/staff/admin are untouched (the helper
            // returns null for them).
            $user = Auth::guard('web')->user();
            $confineUrl = TenantAccess::confineUrlForStudent($user);

            if ($confineUrl) {
                Log::warning('platform-confinement — coach student redirected to coach site', [
                    'user_id'  => $user->id ?? null,
                    'path'     => $request->path(),
                    'redirect' => $confineUrl,
                ]);

                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->to($confineUrl)->with([
                    'messege'    => __('Please use your coach\'s website to sign in and access your dashboard.'),
                    'alert-type' => 'info',
                ]);
            }
        }

        return $next($request);
    }
}
