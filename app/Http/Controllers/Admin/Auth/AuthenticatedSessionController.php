<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Models\Admin;
use Illuminate\View\View;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Rules\CustomRecaptcha;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthenticatedSessionController extends Controller
{
    public function __construct()
    {
        $this->middleware('guest:admin')->except('destroy');
    }

    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('admin.auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(Request $request)
    {
        $rules = [
            'email' => 'required|email',
            'password' => 'required',
        ];

        $customMessages = [
            'email.required' => __('Email is required'),
            'password.required' => __('Password is required'),
        ];
        $this->validate($request, $rules, $customMessages);

        // Enterprise #5 — account lockout after repeated failed admin logins
        // (cache-based, no schema change; augments route throttle:5,1).
        $throttleKey = 'admin-login:' . Str::lower((string) $request->input('email')) . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return redirect()->back()->with([
                'messege'    => __('Too many failed login attempts. Please try again in :minutes minute(s).', [
                    'minutes' => max(1, (int) ceil($seconds / 60)),
                ]),
                'alert-type' => 'error',
            ]);
        }

        $credential = [
            'email' => $request->email,
            'password' => $request->password,
        ];

        $admin = Admin::where('email', $request->email)->first();

        $invalid = ['messege' => __('Invalid credentials'), 'alert-type' => 'error'];

        if (!$admin || !Hash::check($request->password, $admin->password)) {
            RateLimiter::hit($throttleKey, 900);
            return redirect()->back()->with($invalid);
        }

        if ($admin->status !== 'active') {
            return redirect()->back()->with(['messege' => __('Inactive account'), 'alert-type' => 'error']);
        }

        // FT-AUTH-2 fix (2026-05-27) — parity with web login gate.
        // Admin guard previously only checked status='active' — an admin
        // marked is_banned='yes' but still active could log in. The web
        // controller (AuthenticatedSessionController) has had banned +
        // email-verified gates for months; admin missed them. Use the
        // same gates here so admin entry-point is at least as tight as
        // the user-facing one.
        if (($admin->is_banned ?? 'no') === 'yes') {
            return redirect()->back()->with([
                'messege'    => __('Your account has been banned. Please contact support.'),
                'alert-type' => 'error',
            ]);
        }
        // Admins are platform operators — they MUST be email-verified.
        // Only enforce if the column exists on the model (Admin schema
        // varies between deployments; some legacy installs lack
        // email_verified_at on admins). Defensive null check avoids
        // SQL-level failures on older schemas.
        if (\Schema::hasColumn('admins', 'email_verified_at')
            && empty($admin->email_verified_at)) {
            return redirect()->back()->with([
                'messege'    => __('Please verify your email before logging in.'),
                'alert-type' => 'error',
            ]);
        }

        if (!Auth::guard('admin')->attempt($credential, $request->remember)) {
            RateLimiter::hit($throttleKey, 900);
            return redirect()->back()->with($invalid);
        }

        // Successful login — clear the failed-attempt counter for this key.
        RateLimiter::clear($throttleKey);

        // Session-fixation defense (admin guard): rotate the session ID
        // after privilege change. See web AuthenticatedSessionController
        // for the threat model.
        $request->session()->regenerate();

        $request->session()->forget('two_factor.passed_at.admin');

        $loggedAdmin = Auth::guard('admin')->user();
        if ($loggedAdmin && method_exists($loggedAdmin, 'hasTwoFactorEnabled') && $loggedAdmin->hasTwoFactorEnabled()) {
            return redirect()->route('admin.2fa.challenge');
        }

        return redirect()->route('admin.dashboard')->with([
            'messege' => __('Logged in successfully.'),
            'alert-type' => 'success',
        ]);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();

        // Invalidate the session row in storage and rotate CSRF — see web
        // AuthenticatedSessionController::destroy() for the threat model.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $notification = __('Logged out successfully.');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->route('admin.login')->with($notification);
    }
}
