<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\TwoFactorAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin 2FA — setup (enrollment) flow + challenge (post-login) flow.
 *
 * Setup:    /admin/2fa/setup        GET   show QR + recovery codes
 *           /admin/2fa/enable       POST  generate fresh secret + codes
 *           /admin/2fa/confirm      POST  verify a code from authenticator → mark confirmed
 *           /admin/2fa/disable      POST  remove all 2FA columns
 *           /admin/2fa/regenerate   POST  generate fresh recovery codes
 *
 * Challenge: /admin/2fa/challenge          GET   show form
 *            /admin/2fa/challenge/verify   POST  6-digit TOTP code
 *            /admin/2fa/challenge/recovery POST  one-time recovery code
 */
class TwoFactorController extends Controller
{
    public function __construct(private TwoFactorAuthService $tfa) {}

    /* ============================================================ Setup =====================*/

    public function showSetup(Request $request): View
    {
        $admin = $request->user('admin');
        $secret = $admin->two_factor_secret;
        $hasPending = !is_null($secret) && is_null($admin->two_factor_confirmed_at);
        $isEnabled = $admin->hasTwoFactorEnabled();

        $qrSvg = null;
        $otpauth = null;
        if ($hasPending) {
            $otpauth = $this->tfa->otpauthUrl($admin->email, config('app.name', 'MBS'), $secret);
            $qrSvg = $this->tfa->qrSvg($otpauth, 220);
        }

        return view('admin.two-factor.setup', [
            'admin'           => $admin,
            'isEnabled'       => $isEnabled,
            'hasPending'      => $hasPending,
            'qrSvg'           => $qrSvg,
            'secret'          => $secret,
            'recoveryCodes'   => $admin->two_factor_recovery_codes ?? [],
        ]);
    }

    public function enable(Request $request): RedirectResponse
    {
        $admin = $request->user('admin');
        if ($admin->hasTwoFactorEnabled()) {
            return back()->with(['messege' => __('2FA is already active. Disable it first to re-enroll.'), 'alert-type' => 'info']);
        }

        $admin->two_factor_secret = $this->tfa->generateSecret();
        $admin->two_factor_recovery_codes = $this->tfa->generateRecoveryCodes();
        $admin->two_factor_enabled_at = now();
        $admin->two_factor_confirmed_at = null;
        $admin->save();

        return redirect()->route('admin.2fa.setup')->with([
            'messege'    => __('Scan the QR code with your authenticator app, then enter a code below to confirm.'),
            'alert-type' => 'info',
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $admin = $request->user('admin');
        if (!$admin->two_factor_secret) {
            return redirect()->route('admin.2fa.setup');
        }

        $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        if (!$this->tfa->verifyCode($admin->two_factor_secret, $request->code)) {
            return back()->withErrors(['code' => __('Invalid code. Try again — make sure your phone clock is in sync.')]);
        }

        $admin->two_factor_confirmed_at = now();
        $admin->save();

        // Mark this session as 2FA-passed so the admin doesn't get bounced to challenge.
        $request->session()->regenerate();
        session(['two_factor.passed_at.admin' => now()->toIso8601String()]);

        return redirect()->route('admin.2fa.setup')->with([
            'messege'    => __('🎉 Two-factor authentication enabled. Save your recovery codes somewhere safe.'),
            'alert-type' => 'success',
        ]);
    }

    public function disable(Request $request): RedirectResponse
    {
        $admin = $request->user('admin');
        $admin->two_factor_secret = null;
        $admin->two_factor_recovery_codes = null;
        $admin->two_factor_enabled_at = null;
        $admin->two_factor_confirmed_at = null;
        $admin->save();

        session()->forget('two_factor.passed_at.admin');

        return redirect()->route('admin.2fa.setup')->with([
            'messege'    => __('Two-factor authentication has been disabled. Re-enroll any time.'),
            'alert-type' => 'success',
        ]);
    }

    public function regenerateRecovery(Request $request): RedirectResponse
    {
        $admin = $request->user('admin');
        if (!$admin->hasTwoFactorEnabled()) {
            return back();
        }
        $admin->two_factor_recovery_codes = $this->tfa->generateRecoveryCodes();
        $admin->save();

        return back()->with([
            'messege'    => __('New recovery codes generated. The old codes are no longer valid.'),
            'alert-type' => 'success',
        ]);
    }

    /* ============================================================ Challenge =================*/

    public function showChallenge(): View
    {
        return view('admin.two-factor.challenge');
    }

    public function verifyChallenge(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'size:6']]);
        $admin = auth('admin')->user();
        if (!$admin || !$admin->hasTwoFactorEnabled()) {
            return redirect()->route('admin.dashboard');
        }

        if (!$this->tfa->verifyCode($admin->two_factor_secret, $request->code)) {
            return back()->withErrors(['code' => __('Invalid code.')]);
        }

        $request->session()->regenerate();
        session(['two_factor.passed_at.admin' => now()->toIso8601String()]);
        return redirect()->intended(route('admin.dashboard'));
    }

    public function useRecovery(Request $request): RedirectResponse
    {
        $request->validate(['recovery_code' => ['required', 'string', 'max:32']]);
        $admin = auth('admin')->user();
        if (!$admin || !$admin->hasTwoFactorEnabled()) {
            return redirect()->route('admin.dashboard');
        }

        if (!$this->tfa->consumeRecoveryCode($admin, $request->recovery_code)) {
            return back()->withErrors(['recovery_code' => __('Invalid recovery code.')]);
        }
        $admin->save(); // persist the consumed-codes array

        $request->session()->regenerate();
        session(['two_factor.passed_at.admin' => now()->toIso8601String()]);
        return redirect()->route('admin.dashboard')->with([
            'messege'    => __('Recovery code accepted. Consider regenerating your codes now.'),
            'alert-type' => 'warning',
        ]);
    }
}
