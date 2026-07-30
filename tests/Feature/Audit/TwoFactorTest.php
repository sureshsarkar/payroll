<?php

namespace Tests\Feature\Audit;

use App\Services\TwoFactorAuthService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

/**
 * Verifies the 2FA audit guarantees:
 *
 * - Every mutating 2FA endpoint (challenge verify/recovery, enable/confirm/
 *   disable/regenerate) carries a throttle middleware. Pre-audit these were
 *   wide open — 6-digit TOTP has only 10^6 codes, so an attacker with the
 *   password could bruteforce online.
 * - Code/recovery_code inputs are length-bounded (no 10MB-string DoS).
 * - TwoFactorAuthService still rejects non-6-digit codes.
 * - Recovery-code consumption removes the used code (single-use).
 */
class TwoFactorTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_2fa_challenge_verify_is_throttled(): void
    {
        $this->assertRouteHasMiddlewarePrefix('admin.2fa.challenge.verify', 'throttle');
    }

    public function test_admin_2fa_challenge_recovery_is_throttled(): void
    {
        $this->assertRouteHasMiddlewarePrefix('admin.2fa.challenge.recovery', 'throttle');
    }

    public function test_web_2fa_challenge_verify_is_throttled(): void
    {
        $this->assertRouteHasMiddlewarePrefix('web.2fa.challenge.verify', 'throttle');
    }

    public function test_web_2fa_challenge_recovery_is_throttled(): void
    {
        $this->assertRouteHasMiddlewarePrefix('web.2fa.challenge.recovery', 'throttle');
    }

    public function test_2fa_management_endpoints_are_throttled(): void
    {
        // Both guards' enable/confirm/disable/regenerate need throttling
        // — confirm takes a TOTP code (bruteforceable), and disable/
        // regenerate would let a hijacked session lock out the real owner.
        $managed = [
            'admin.2fa.enable', 'admin.2fa.confirm', 'admin.2fa.disable', 'admin.2fa.regenerate',
            'web.2fa.enable',   'web.2fa.confirm',   'web.2fa.disable',   'web.2fa.regenerate',
        ];
        foreach ($managed as $name) {
            $this->assertRouteHasMiddlewarePrefix($name, 'throttle');
        }
    }

    public function test_totp_input_is_bounded_to_six_chars(): void
    {
        // Static check: both controllers must use size:6 on the code field
        // so an attacker can't submit megabyte payloads to clog the limiter.
        foreach (['Frontend', 'Admin'] as $ns) {
            $src = file_get_contents(app_path("Http/Controllers/$ns/TwoFactorController.php"));
            $this->assertStringContainsString("'code' => ['required', 'string', 'size:6']", $src,
                "$ns/TwoFactorController must bound 'code' input length to 6 chars");
            $this->assertStringContainsString("'recovery_code' => ['required', 'string', 'max:32']", $src,
                "$ns/TwoFactorController must bound 'recovery_code' input length");
        }
    }

    public function test_service_rejects_non_six_digit_codes(): void
    {
        $svc = new TwoFactorAuthService();
        $secret = $svc->generateSecret();

        $this->assertFalse($svc->verifyCode($secret, ''),     'empty code must fail');
        $this->assertFalse($svc->verifyCode($secret, '12345'),  '5-digit code must fail');
        $this->assertFalse($svc->verifyCode($secret, '1234567'),'7-digit code must fail');
        $this->assertFalse($svc->verifyCode($secret, 'abcdef'), 'non-digit code must fail');
        $this->assertFalse($svc->verifyCode($secret, 'a23456'), 'partial-digit code must fail');
    }

    public function test_recovery_code_is_single_use(): void
    {
        $svc = new TwoFactorAuthService();
        $codes = $svc->generateRecoveryCodes(3);
        $this->assertCount(3, $codes);

        // Mock user object that just exposes the array property.
        $user = new class {
            public array $two_factor_recovery_codes = [];
        };
        $user->two_factor_recovery_codes = $codes;

        $first = $codes[0];
        $this->assertTrue($svc->consumeRecoveryCode($user, $first),
            'first use of a recovery code must succeed');
        $this->assertCount(2, $user->two_factor_recovery_codes,
            'consumed code must be removed from the list');
        $this->assertFalse($svc->consumeRecoveryCode($user, $first),
            're-using a consumed code must fail (single-use guarantee)');
    }

    public function test_recovery_codes_use_unambiguous_alphabet(): void
    {
        $svc = new TwoFactorAuthService();
        $codes = $svc->generateRecoveryCodes(20);
        $combined = implode('', $codes);

        // Visually-ambiguous chars must NOT appear (0/O, 1/I/l).
        foreach (['0', 'o', '1', 'i', 'l'] as $bad) {
            $this->assertStringNotContainsString($bad, $combined,
                "recovery alphabet must not include '$bad' (visual ambiguity)");
        }
    }

    public function test_recovery_code_format_xxxxx_dash_xxxxx(): void
    {
        $svc = new TwoFactorAuthService();
        foreach ($svc->generateRecoveryCodes(8) as $code) {
            $this->assertMatchesRegularExpression('/^[a-z0-9]{5}-[a-z0-9]{5}$/', $code);
        }
    }

    private function assertRouteHasMiddlewarePrefix(string $name, string $prefix): void
    {
        $routes = RouteFacade::getRoutes()->getRoutesByName();
        $this->assertArrayHasKey($name, $routes, "route '$name' not registered");
        /** @var Route $route */
        $route = $routes[$name];
        $hits = array_filter($route->gatherMiddleware(), fn ($m) => str_starts_with((string) $m, $prefix));
        $this->assertNotEmpty($hits, "route '$name' is missing '$prefix' middleware. Got: " . implode(', ', $route->gatherMiddleware()));
    }
}
