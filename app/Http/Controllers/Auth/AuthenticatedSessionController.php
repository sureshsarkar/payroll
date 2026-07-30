<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\CustomRecaptcha;
use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        // echo userAuth()->role;die;
        // if (Auth::check()) {
        //     if (userAuth()->role != 'student') {
        //         return redirect()->route('instructor.dashboard');
        //     } else {
        //         return redirect()->route('student.dashboard');
        //     }
        // }

        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(Request $request): RedirectResponse
    {
        $setting = Cache::get('setting');

        $rules = [
            'email' => 'required|email',
            'password' => 'required',
            'g-recaptcha-response' => $setting->recaptcha_status == 'active' ? ['required', new CustomRecaptcha] : 'nullable',
        ];

        $customMessages = [
            'email.required' => __('Email is required'),
            'password.required' => __('Password is required'),
            'g-recaptcha-response.required' => __('Please complete the recaptcha to submit the form'),
        ];
        $this->validate($request, $rules, $customMessages);

        // Enterprise #5 — persistent account lockout after repeated failed
        // logins. Cache-based (RateLimiter) so it needs NO schema change, and
        // augments the route-level throttle:5,1. Keyed by email+IP: an attacker
        // can't lock every account from one IP, and a victim isn't trivially
        // locked out from a single hostile IP. 5 failures => 15-minute cooldown.
        $throttleKey = 'login:' . Str::lower((string) $request->input('email')) . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'email' => __('Too many failed login attempts. Please try again in :minutes minute(s).', [
                    'minutes' => max(1, (int) ceil($seconds / 60)),
                ]),
            ]);
        }

        $credential = [
            'email' => $request->email,
            'password' => $request->password,
        ];

        $user = User::where('email', $request->email)->first();

        // Check if user exists and password match
        if (! $user || ! Hash::check($request->password, $user->password)) {
            RateLimiter::hit($throttleKey, 900); // count this failure (15-min decay)
            $notification = __('Invalid credentials please check your email and password');
            throw ValidationException::withMessages(['email' => $notification]);
        }

        // Check if user active
        if ($user->status != UserStatus::ACTIVE->value) {
            $notification = __('Inactive account');
            throw ValidationException::withMessages(['email' => $notification]);
        }

        // Check if user is banned
        if ($user->is_banned == UserStatus::BANNED->value) {
            $notification = __('Your account has been banned');
            $notification = ['messege' => $notification, 'alert-type' => 'error'];

            return redirect()->back()->with($notification);
        }

        // Check if email is verified
        if (! $user->email_verified_at) {
            $notification = __('Please verify your email');
            $notification = ['messege' => $notification, 'alert-type' => 'error'];

            return redirect()->back()->with($notification);
        }

        // TENANT ISOLATION (2026-06-16) — if this login is happening on a coach
        // white-label surface (resolved by ResolveCoachByDomain), the user must
        // belong to that coach. On the platform host the surface id is 0 and
        // this is a no-op. Defence-in-depth: RedirectCustomDomainToScoped sends
        // /login on a coach domain to /coach/{slug}/login, but we also guard the
        // platform form directly. See \App\Support\TenantAccess.
        $surfaceCoachId = \App\Support\TenantAccess::surfaceCoachId($request);
        if ($surfaceCoachId > 0 && ! \App\Support\TenantAccess::userMayAccessCoach($user, $surfaceCoachId)) {
            RateLimiter::hit($throttleKey, 900);
            throw ValidationException::withMessages([
                'email' => __('These credentials are not authorized for this website.'),
            ]);
        }

        // PLATFORM CONFINEMENT (2026-06-16) — on the bare platform host, a student
        // who belongs to a coach is confined to their coach's website. Redirect
        // them there WITHOUT creating a platform session. Platform-native
        // students (no coach link) fall through and sign in normally.
        if ($surfaceCoachId === 0 && ($user->role ?? '') === 'student') {
            $confineUrl = \App\Support\TenantAccess::confineUrlForStudent($user);
            if ($confineUrl) {
                RateLimiter::clear($throttleKey);
                return redirect()->to($confineUrl)->with([
                    'messege'    => __('Please sign in on your coach\'s website to access your dashboard.'),
                    'alert-type' => 'info',
                ]);
            }
        }

        // Authenticate user. Reject the attempt result rather than silently
        // continuing — earlier this ignored the return value, so an
        // attempt() that ever returned false (rate-limit, race) would still
        // proceed past the auth gate.
        if (!Auth::guard('web')->attempt($credential, $request->remember)) {
            RateLimiter::hit($throttleKey, 900); // count this failure toward lockout
            throw ValidationException::withMessages([
                'email' => __('Invalid credentials please check your email and password'),
            ]);
        }

        // Successful login — clear the failed-attempt counter for this key.
        RateLimiter::clear($throttleKey);

        // Session-fixation defense: rotate the session ID after privilege
        // change. Without this, an attacker who gets the victim to use a
        // pre-set session cookie keeps the same ID after victim logs in,
        // so they're now sitting in the victim's authenticated session.
        $request->session()->regenerate();

        // Clear stale 2FA flag — a fresh login means a fresh challenge.
        $request->session()->forget('two_factor.passed_at.web');

        // If 2FA is enabled on this user, send them to the challenge before
        // anything else (cart sync, dashboard redirect, etc.).
        $loggedUser = Auth::guard('web')->user();
        if ($loggedUser && method_exists($loggedUser, 'hasTwoFactorEnabled') && $loggedUser->hasTwoFactorEnabled()) {
            return redirect()->route('web.2fa.challenge');
        }

        // session cart to database
        $cart_count = Cart::content()->count();
        sessionCartToDatabase();

        // Redirect user to dashboard based on role
        $notification = __('Logged in successfully.');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        $intendedUrl = session()->get('url.intended');
        if ($intendedUrl && \Str::contains($intendedUrl, '/admin')) {
            if ($user->role == 'instructor') {
                return redirect()->route('instructor.dashboard');
            }

            return redirect()->route('student.dashboard');
        }

        // 2026-06-01 (audit [1]) — simplified from
        // `role === 'instructor' || role !== 'student'` (the first clause was
        // redundant: any instructor already satisfies `!== 'student'`) plus a
        // confusing elseif with no guaranteed return. Behaviour is identical:
        // a student lands on their dashboard (or cart if they have items);
        // every non-student role (instructor + coach staff) uses the
        // instructor panel.
        if ($user->role === 'student') {
            if ($cart_count > 0) {
                return redirect()->route('cart');
            }

            return redirect()->route('student.dashboard');
        }

        return redirect()->route('instructor.dashboard');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        // 2026-06-11 — White-label logout. Capture the coach context BEFORE
        // invalidating the session. ResolveCoachByDomain stamps
        // resolved_coach_id on the request when the host is a coach domain
        // (custom or subdomain); on the platform host it's 0/null. The global
        // logout button (student panel sidebar + public header) posts here, so
        // without this a student/coach who logs out ON a coach domain was
        // bounced to the PLATFORM login page — leaking MBSGuru chrome onto the
        // coach's brand. Keep them on-brand: send them to the coach's home.
        $coachId = (int) ($request->attributes->get('resolved_coach_id') ?? 0);

        Auth::guard('web')->logout();

        // Standard Laravel logout sequence:
        //  - invalidate(): destroy the session row in storage so a stolen
        //    cookie is no longer valid (the previous code only cleared the
        //    auth, leaving the session itself alive — an attacker who
        //    captured the cookie pre-logout could keep using it)
        //  - regenerateToken(): rotate CSRF, so any token captured before
        //    logout can't be reused on a future re-login.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $notification = ['messege' => __('Logged out successfully.'), 'alert-type' => 'success'];

        // On a coach domain → return to THAT coach's branded home (dynamic,
        // slug resolved from the coach's landing page; no hardcoding). Falls
        // back to the domain root (the middleware renders the coach home there)
        // if the slug can't be resolved, so we never leak the platform page.
        if ($coachId > 0) {
            $slug = \App\Models\CoachLandingPage::where('added_by', $coachId)->value('slug');
            if ($slug) {
                return redirect()->route('coach.site.path', ['site_slug' => $slug])->with($notification);
            }

            return redirect()->to('/')->with($notification);
        }

        return redirect()->route('login')->with($notification);
    }
}
