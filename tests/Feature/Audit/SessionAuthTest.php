<?php

namespace Tests\Feature\Audit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Verifies the web/admin auth flow's session-fixation + token-rotation
 * guarantees:
 *
 *  - Login attempt result is consumed (was: ignored)
 *  - session()->regenerate() runs after every privilege-elevating event
 *    (login, 2FA challenge pass, recovery-code use, 2FA confirm)
 *  - destroy() invalidates the session row + rotates CSRF (was: only
 *    cleared auth, leaving the session itself alive)
 *  - Web forgot-password no longer reveals registered emails (parity
 *    with the API fix)
 *  - Web reset-password enforces the expiry column + bumps min:4 → min:8
 *    + revokes all sessions/tokens (matches API fix)
 *  - Cookie flags in config/session.php are http_only + secure-by-default
 */
class SessionAuthTest extends TestCase
{
    use DatabaseTransactions;

    public function test_web_login_regenerates_session_id(): void
    {
        $src = file_get_contents(app_path('Http/Controllers/Auth/AuthenticatedSessionController.php'));
        $this->assertStringContainsString('$request->session()->regenerate();', $src,
            'web login store() must call $request->session()->regenerate() after Auth::attempt — without it, an attacker who fixes the session cookie keeps the same session ID after victim logs in');
    }

    public function test_web_login_consumes_attempt_return_value(): void
    {
        $src = file_get_contents(app_path('Http/Controllers/Auth/AuthenticatedSessionController.php'));
        $this->assertStringContainsString("if (!Auth::guard('web')->attempt", $src,
            "web login must check Auth::attempt() return value rather than ignore it");
    }

    public function test_admin_login_regenerates_session_id(): void
    {
        $src = file_get_contents(app_path('Http/Controllers/Admin/Auth/AuthenticatedSessionController.php'));
        $this->assertStringContainsString('$request->session()->regenerate();', $src,
            'admin login store() must call regenerate() after Auth::attempt');
    }

    public function test_web_logout_invalidates_session_and_rotates_csrf(): void
    {
        $src = file_get_contents(app_path('Http/Controllers/Auth/AuthenticatedSessionController.php'));
        $this->assertStringContainsString('$request->session()->invalidate();', $src,
            'web logout must invalidate the session row in storage');
        $this->assertStringContainsString('$request->session()->regenerateToken();', $src,
            'web logout must rotate the CSRF token');
    }

    public function test_admin_logout_invalidates_session_and_rotates_csrf(): void
    {
        $src = file_get_contents(app_path('Http/Controllers/Admin/Auth/AuthenticatedSessionController.php'));
        $this->assertStringContainsString('$request->session()->invalidate();', $src);
        $this->assertStringContainsString('$request->session()->regenerateToken();', $src);
    }

    public function test_2fa_pass_regenerates_session(): void
    {
        // Both guards must rotate the session ID after a successful TOTP
        // challenge, recovery-code use, and initial confirm — these are all
        // privilege-elevating events.
        foreach (['Frontend', 'Admin'] as $ns) {
            $src = file_get_contents(app_path("Http/Controllers/$ns/TwoFactorController.php"));
            // Expect 3 regenerate calls per controller: confirm, verifyChallenge, useRecovery.
            $count = substr_count($src, '$request->session()->regenerate();');
            $this->assertGreaterThanOrEqual(3, $count,
                "$ns/TwoFactorController must call session()->regenerate() in confirm + verifyChallenge + useRecovery (found $count)");
        }
    }

    public function test_web_forgot_password_does_not_leak_email_registration(): void
    {
        $src = file_get_contents(app_path('Http/Controllers/Auth/PasswordResetLinkController.php'));
        $this->assertStringNotContainsString('Email does not exist', $src,
            'web forgot-password must not reveal whether an email is registered (enumeration leak)');
        $this->assertStringContainsString('If that email is registered', $src);
        $this->assertStringContainsString('forget_password_token_expires_at', $src,
            'forgot-password must stamp expires_at on token issue');
    }

    public function test_web_reset_password_enforces_expiry_and_bumps_min_length(): void
    {
        $src = file_get_contents(app_path('Http/Controllers/Auth/NewPasswordController.php'));
        $this->assertStringContainsString("'required|min:8|confirmed'", $src,
            'web reset-password must enforce min:8 (was min:4)');
        $this->assertStringContainsString('forget_password_token_expires_at', $src,
            'web reset-password must consult the expiry column');
        $this->assertStringContainsString('PersonalAccessToken::where', $src,
            'web reset-password must revoke Sanctum tokens on success');
    }

    public function test_session_cookie_flags_are_set_correctly(): void
    {
        $this->assertTrue((bool) config('session.http_only'),
            'session.http_only must be true so JS cannot read the session cookie');
        $this->assertNotEmpty(config('session.same_site'),
            'session.same_site should be set (lax or strict)');

        // Validate the config-file SOURCE, not the runtime-resolved value.
        // Local dev .env can legitimately set SESSION_SECURE_COOKIE=false
        // for HTTP development, but the config default must still be `true`
        // so prod (where the env var is unset / true) gets the secure flag.
        $cfg = file_get_contents(config_path('session.php'));
        $this->assertMatchesRegularExpression(
            "/'secure'\s*=>\s*env\(\s*'SESSION_SECURE_COOKIE'\s*,\s*true\s*\)/",
            $cfg,
            "config/session.php 'secure' default must be `env('SESSION_SECURE_COOKIE', true)` — anything else risks shipping cookies over HTTP in prod"
        );
    }
}
