<?php

namespace Tests\Feature\Audit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Verifies the Sanctum / API audit guarantees:
 *
 *  - Auth-bootstrap routes carry a `throttle:` middleware (login/register/
 *    forget-password were unthrottled before — bruteforce + spam exposure).
 *  - Sanctum `expiration` config is set (was null = never-expire).
 *  - Register endpoint restricts `role` to public-registerable values
 *    (was `string|max:20` — anyone could POST role=admin to self-elevate).
 *  - Register/reset enforce password length ≥ 8 (was 4).
 *  - Forget-password no longer reveals whether an email is registered.
 *  - Tokens are issued with role-scoped abilities, not wildcard.
 *  - users.forget_password_token_expires_at column exists (TTL backfill).
 *  - HeaderBearerTokenSet middleware sets Cache-Control: no-store on
 *    responses so download URLs aren't cached by intermediaries.
 */
class SanctumApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_api_login_route_carries_throttle(): void
    {
        $this->assertRouteHasMiddlewarePrefix('api.patient-login', 'throttle');
    }

    public function test_api_register_route_carries_throttle(): void
    {
        $this->assertRouteHasMiddlewarePrefix('api.register', 'throttle');
    }

    public function test_api_forget_password_carries_throttle(): void
    {
        $this->assertRouteHasMiddlewarePrefix('api.forget-password', 'throttle');
    }

    public function test_api_reset_password_carries_throttle(): void
    {
        $this->assertRouteHasMiddlewarePrefix('api.reset-password', 'throttle');
    }

    public function test_sanctum_token_expiration_is_set(): void
    {
        $exp = config('sanctum.expiration');
        $this->assertNotNull(
            $exp,
            'sanctum.expiration must not be null — null means tokens never expire, ' .
            'so a stolen device-level bearer grants account access in perpetuity'
        );
        $this->assertGreaterThan(0, (int) $exp);
    }

    public function test_register_restricts_role_to_public_values(): void
    {
        // Static check: the validator must use `in:student,instructor` on
        // role — an open string|max:20 rule lets POST role=admin self-elevate.
        $src = file_get_contents(app_path('Http/Controllers/API/AuthenticatedController.php'));
        $this->assertStringContainsString(
            "'in:student,instructor'",
            $src,
            "register() must restrict 'role' to in:student,instructor (was string|max:20 — open self-elevation)"
        );
    }

    public function test_password_minimum_length_is_eight_or_greater(): void
    {
        $src = file_get_contents(app_path('Http/Controllers/API/AuthenticatedController.php'));
        $this->assertStringNotContainsString("'min:4'", $src,
            'API register/reset must not accept 4-char passwords');
        // Two callsites: register + resetPassword
        $this->assertSame(2, substr_count($src, "'min:8'"),
            "expected 'min:8' in both register() and resetPassword(), found different count");
    }

    public function test_forget_password_returns_uniform_response(): void
    {
        // Static check: the controller must not return "Email does not exist"
        // when a lookup fails. Tested at source level because hitting the
        // endpoint requires a working mailer in the test env.
        $src = file_get_contents(app_path('Http/Controllers/API/AuthenticatedController.php'));
        $this->assertStringNotContainsString('Email does not exist', $src,
            'forgetPassword must not reveal whether the email is registered (enumeration leak)');
        $this->assertStringContainsString('If that email is registered', $src,
            'forgetPassword should always return the uniform "if registered" message');
    }

    public function test_login_issues_role_scoped_token(): void
    {
        $src = file_get_contents(app_path('Http/Controllers/API/AuthenticatedController.php'));
        $this->assertStringNotContainsString("createToken('student', ['*'])", $src,
            "login() must not issue tokens with wildcard ['*'] abilities or hardcoded 'student' name");
        $this->assertStringContainsString("'role:' . \$user->role", $src,
            'login() must scope token abilities to the user role');
    }

    public function test_users_table_has_forget_password_token_expiry_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn('users', 'forget_password_token_expires_at'),
            'users.forget_password_token_expires_at must exist (TTL on reset tokens)'
        );
    }

    public function test_reset_password_revokes_existing_tokens(): void
    {
        $src = file_get_contents(app_path('Http/Controllers/API/AuthenticatedController.php'));
        $this->assertStringContainsString('PersonalAccessToken::', $src);
        $this->assertStringContainsString('->delete();', $src);
        $this->assertStringContainsString('forget_password_token_expires_at', $src,
            'resetPassword must consult the expiry column');
    }

    public function test_header_bearer_token_set_response_is_no_store(): void
    {
        // Round-trip the middleware in isolation to confirm Cache-Control.
        $mw = new \App\Http\Middleware\API\HeaderBearerTokenSet();
        $req = \Illuminate\Http\Request::create(
            '/api/download-invoice/INV-1?bearer_token=fake-test-token-not-real',
            'GET'
        );
        $response = $mw->handle($req, fn ($r) => new \Illuminate\Http\Response('ok'));

        $this->assertSame(200, $response->getStatusCode());
        $cache = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', (string) $cache);
    }

    public function test_header_bearer_token_set_rejects_when_no_token(): void
    {
        $mw = new \App\Http\Middleware\API\HeaderBearerTokenSet();
        $req = \Illuminate\Http\Request::create('/api/download-invoice/X', 'GET');
        $response = $mw->handle($req, fn ($r) => new \Illuminate\Http\Response('should not reach'));
        $this->assertSame(401, $response->getStatusCode());
    }

    private function assertRouteHasMiddlewarePrefix(string $name, string $prefix): void
    {
        $routes = RouteFacade::getRoutes()->getRoutesByName();
        $this->assertArrayHasKey($name, $routes, "route '$name' not registered");
        /** @var Route $route */
        $route = $routes[$name];
        $hits = array_filter($route->gatherMiddleware(), fn ($m) => str_starts_with((string) $m, $prefix));
        $this->assertNotEmpty(
            $hits,
            "route '$name' is missing '$prefix' middleware. Got: " . implode(', ', $route->gatherMiddleware())
        );
    }
}
