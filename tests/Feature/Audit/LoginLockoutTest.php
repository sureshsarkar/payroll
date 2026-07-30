<?php

namespace Tests\Feature\Audit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Enterprise #5 — cache-based account lockout after repeated failed logins.
 * No schema change; augments the route-level throttle:5,1.
 */
class LoginLockoutTest extends TestCase
{
    use DatabaseTransactions;

    public function test_lockout_mechanism_locks_after_five_hits(): void
    {
        $key = 'login:test-' . uniqid() . '|127.0.0.1';
        RateLimiter::clear($key);
        for ($i = 0; $i < 5; $i++) {
            $this->assertFalse(RateLimiter::tooManyAttempts($key, 5), "should not be locked at attempt $i");
            RateLimiter::hit($key, 900);
        }
        $this->assertTrue(RateLimiter::tooManyAttempts($key, 5), 'locked after 5 failures');
        $this->assertGreaterThan(0, RateLimiter::availableIn($key), 'cooldown reported');
        RateLimiter::clear($key);
        $this->assertFalse(RateLimiter::tooManyAttempts($key, 5), 'cleared on success');
    }

    public function test_both_login_controllers_wire_the_lockout(): void
    {
        foreach ([
            app_path('Http/Controllers/Auth/AuthenticatedSessionController.php'),
            app_path('Http/Controllers/Admin/Auth/AuthenticatedSessionController.php'),
        ] as $f) {
            $src = file_get_contents($f);
            $this->assertStringContainsString('RateLimiter::tooManyAttempts(', $src, "$f must check lockout");
            $this->assertStringContainsString('RateLimiter::hit(', $src, "$f must count failures");
            $this->assertStringContainsString('RateLimiter::clear(', $src, "$f must clear on success");
        }
    }

    public function test_web_login_locks_after_repeated_failures(): void
    {
        // Drive the controller directly so the cache (array driver) accumulates
        // hits within one context — the feature-test HTTP harness resets the
        // array cache between sub-requests, which would mask the (real,
        // persistent-cache) lockout. Production uses file/redis, so it works
        // end-to-end there.
        Cache::put('setting', (object) ['recaptcha_status' => 'inactive'], 600);
        $controller = new \App\Http\Controllers\Auth\AuthenticatedSessionController();
        $email = 'lockout-' . uniqid() . '@x.test';

        $locked = false;
        for ($i = 0; $i < 6; $i++) {
            $req = \Illuminate\Http\Request::create('/user-login', 'POST', ['email' => $email, 'password' => 'wrong-password']);
            try {
                $controller->store($req);
            } catch (\Illuminate\Validation\ValidationException $e) {
                $msg = implode(' ', $e->errors()['email'] ?? []);
                if (stripos($msg, 'too many') !== false) {
                    $locked = true;
                    break;
                }
            }
        }
        $this->assertTrue($locked, 'web login must lock out after repeated failed attempts');
    }
}
