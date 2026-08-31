<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\CustomRecaptcha;
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

        // LMS removal phase 2 (2026-08-27) — dropped two white-label login
        // guards (App\Support\TenantAccess): the tenant-isolation check that
        // rejected credentials not belonging to the coach whose domain the
        // login form was served on, and the platform-confinement redirect that
        // bounced a coach's student to their coach's own site rather than
        // creating a platform session. There is a single surface now; company
        // scoping is enforced by the `companycontext` middleware after login.

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

        // LMS removal phase 2 (2026-08-27) — dropped the guest-cart merge
        // (Cart::content() + sessionCartToDatabase()) that ran on every login.
        // There is no cart.

        // Redirect user to dashboard based on role
        $notification = __('Logged in successfully.');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        // LMS→HR conversion — this install has no course/LMS features any more,
        // so the post-login landing page is the HR/Employee dashboard. The old
        // instructor.dashboard / student.dashboard routes no longer exist.
        $intendedUrl = session()->get('url.intended');
        if ($intendedUrl && \Str::contains($intendedUrl, '/admin')) {
            if ($user->role == 'instructor') {
                return redirect()->route('hr.overview');
            }

            return redirect()->route('employee.overview');
        }

        // 2026-06-01 (audit [1]) — simplified from
        // `role === 'instructor' || role !== 'student'` (the first clause was
        // redundant: any instructor already satisfies `!== 'student'`) plus a
        // confusing elseif with no guaranteed return. An employee (role
        // 'student') lands on their own dashboard; every other role
        // (instructor + staff) uses the HR panel.
        if ($user->role === 'student') {
            return redirect()->route('employee.overview');
        }

        return redirect()->route('hr.overview');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        // LMS removal phase 2 (2026-08-27) — dropped the white-label logout
        // branch. It captured resolved_coach_id before invalidating the session
        // and bounced the user back to that coach's branded home so logging out
        // on a coach domain never showed platform chrome. There are no coach
        // domains; everyone returns to the single login page.
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

        return redirect()->route('login')->with($notification);
    }
}
