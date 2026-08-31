<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\TwoFactorAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 2FA controller for the web guard (coaches, staff, students).
 *
 * Setup:
 *   GET  /2fa/setup
 *   POST /2fa/enable, /2fa/confirm, /2fa/disable, /2fa/regenerate
 *
 * Challenge:
 *   GET  /2fa/challenge
 *   POST /2fa/challenge/verify, /2fa/challenge/recovery
 *
 * After a successful challenge, sends users to their role-specific dashboard.
 */
class TwoFactorController extends Controller
{
    public function __construct(private TwoFactorAuthService $tfa) {}

    private function dashboardRouteFor($user): string
    {
        // LMS removal phase 2 (2026-08-27) — the LMS dashboards these named
        // are gone; land users on the HR / employee dashboards instead.
        $isCoach = $user->role === 'instructor' || !empty($user->coach_id);
        return $isCoach ? 'hr.overview' : 'employee.overview';
    }

    /* =================================== Setup =================================== */

    public function showSetup(Request $request): View
    {
        $user = $request->user();
        $secret = $user->two_factor_secret;
        $hasPending = !is_null($secret) && is_null($user->two_factor_confirmed_at);
        $isEnabled = $user->hasTwoFactorEnabled();

        $qrSvg = null;
        if ($hasPending) {
            $otpauth = $this->tfa->otpauthUrl($user->email, config('app.name', 'MBS'), $secret);
            $qrSvg = $this->tfa->qrSvg($otpauth, 220);
        }

        return view('frontend.two-factor.setup', [
            'user'          => $user,
            'isEnabled'     => $isEnabled,
            'hasPending'    => $hasPending,
            'qrSvg'         => $qrSvg,
            'secret'        => $secret,
            'recoveryCodes' => $user->two_factor_recovery_codes ?? [],
        ]);
    }

    public function enable(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user->hasTwoFactorEnabled()) {
            return back()->with(['messege' => __('2FA is already active. Disable it first to re-enroll.'), 'alert-type' => 'info']);
        }

        $user->two_factor_secret = $this->tfa->generateSecret();
        $user->two_factor_recovery_codes = $this->tfa->generateRecoveryCodes();
        $user->two_factor_enabled_at = now();
        $user->two_factor_confirmed_at = null;
        $user->save();

        return redirect()->route('web.2fa.setup')->with([
            'messege'    => __('Scan the QR code with your authenticator app, then enter a code below to confirm.'),
            'alert-type' => 'info',
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (!$user->two_factor_secret) {
            return redirect()->route('web.2fa.setup');
        }

        // Codes are exactly 6 digits. Length-bound the input so the rate
        // limiter doesn't get hammered by 10MB-string DoS payloads.
        $request->validate(['code' => ['required', 'string', 'size:6']]);

        if (!$this->tfa->verifyCode($user->two_factor_secret, $request->code)) {
            return back()->withErrors(['code' => __('Invalid code. Try again — make sure your phone clock is in sync.')]);
        }

        $user->two_factor_confirmed_at = now();
        $user->save();

        // First successful 2FA enrollment elevates the user from
        // "logged in" to "logged in + 2FA verified". Rotate the session
        // ID so any token captured during the pre-2FA window can't be
        // replayed against the now-elevated session.
        $request->session()->regenerate();
        session(['two_factor.passed_at.web' => now()->toIso8601String()]);

        return redirect()->route('web.2fa.setup')->with([
            'messege'    => __('🎉 Two-factor authentication enabled. Save your recovery codes somewhere safe.'),
            'alert-type' => 'success',
        ]);
    }

    public function disable(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->two_factor_secret = null;
        $user->two_factor_recovery_codes = null;
        $user->two_factor_enabled_at = null;
        $user->two_factor_confirmed_at = null;
        $user->save();

        session()->forget('two_factor.passed_at.web');

        return redirect()->route('web.2fa.setup')->with([
            'messege'    => __('Two-factor authentication has been disabled. Re-enroll any time.'),
            'alert-type' => 'success',
        ]);
    }

    public function regenerateRecovery(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (!$user->hasTwoFactorEnabled()) {
            return back();
        }
        $user->two_factor_recovery_codes = $this->tfa->generateRecoveryCodes();
        $user->save();

        return back()->with([
            'messege'    => __('New recovery codes generated. The old codes are no longer valid.'),
            'alert-type' => 'success',
        ]);
    }

    /* =================================== Challenge =================================== */

    public function showChallenge(): View
    {
        return view('frontend.two-factor.challenge');
    }

    public function verifyChallenge(Request $request): RedirectResponse
    {
        // Codes are exactly 6 digits. Length-bound the input so the rate
        // limiter doesn't get hammered by 10MB-string DoS payloads.
        $request->validate(['code' => ['required', 'string', 'size:6']]);
        $user = auth('web')->user();
        if (!$user || !$user->hasTwoFactorEnabled()) {
            return redirect()->route($this->dashboardRouteFor($user ?? (object) ['role' => 'student']));
        }

        if (!$this->tfa->verifyCode($user->two_factor_secret, $request->code)) {
            return back()->withErrors(['code' => __('Invalid code.')]);
        }

        // Privilege escalation (logged-in → fully-authenticated). Rotate the
        // session ID so a token captured during the pre-2FA window can't be
        // replayed against the elevated session.
        $request->session()->regenerate();
        session(['two_factor.passed_at.web' => now()->toIso8601String()]);
        return redirect()->intended(route($this->dashboardRouteFor($user)));
    }

    public function useRecovery(Request $request): RedirectResponse
    {
        // Recovery codes are XXXXX-XXXXX (11 chars). Bound the input length.
        $request->validate(['recovery_code' => ['required', 'string', 'max:32']]);
        $user = auth('web')->user();
        if (!$user || !$user->hasTwoFactorEnabled()) {
            return redirect()->route($this->dashboardRouteFor($user ?? (object) ['role' => 'student']));
        }

        if (!$this->tfa->consumeRecoveryCode($user, $request->recovery_code)) {
            return back()->withErrors(['recovery_code' => __('Invalid recovery code.')]);
        }
        $user->save();

        $request->session()->regenerate();
        session(['two_factor.passed_at.web' => now()->toIso8601String()]);
        return redirect()->route($this->dashboardRouteFor($user))->with([
            'messege'    => __('Recovery code accepted. Consider regenerating your codes now.'),
            'alert-type' => 'warning',
        ]);
    }
}
