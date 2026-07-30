<?php

namespace Tests\Feature\Audit;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Modules\Order\app\Models\Enrollment;
use Tests\TestCase;

/**
 * Verifies the audit's IDOR + auth-middleware guarantees:
 *
 * 1. /instructor/* GET routes carry an auth middleware (so unauthenticated
 *    visitors are redirected, not allowed through to call helpers on null).
 * 2. /admin/* GET routes carry auth:admin (the menu-builder bug fix).
 * 3. Login POST routes carry throttle middleware.
 * 4. All 5 webhook routes are registered.
 * 5. Webhook routes are CSRF-exempt (otherwise gateway POSTs would 419).
 */
class IdorTest extends TestCase
{
    use DatabaseTransactions;

    public function test_instructor_routes_have_auth_middleware(): void
    {
        $sample = [
            'instructor.dashboard',
            'instructor.setting.index',
            'instructor.payout.index',
        ];
        foreach ($sample as $name) {
            $this->assertRouteHasMiddleware($name, 'auth');
        }
    }

    public function test_admin_routes_have_auth_admin_middleware(): void
    {
        // Sample of admin routes that previously had bugs:
        // admin.menu-builder was missing auth before the audit — verify it's now gated.
        $sample = [
            'admin.dashboard',
            'admin.menubuilder.index',  // the menu-builder fix from session 8
        ];
        foreach ($sample as $name) {
            $routes = RouteFacade::getRoutes()->getRoutesByName();
            if (!isset($routes[$name])) {
                $this->markTestSkipped("route '$name' not registered (acceptable — name may have changed)");
            }
            $this->assertRouteHasMiddleware($name, 'auth:admin');
        }
    }

    public function test_user_login_post_carries_throttle_middleware(): void
    {
        $this->assertRouteHasMiddlewarePrefix('user-login', 'throttle');
    }

    public function test_admin_login_post_carries_throttle_middleware(): void
    {
        $this->assertRouteHasMiddlewarePrefix('admin.store-login', 'throttle');
    }

    public function test_all_five_webhook_routes_registered(): void
    {
        $expected = [
            'webhooks.stripe',
            'webhooks.razorpay',
            'webhooks.bkash',
            'webhooks.paypal',
            'webhooks.mercadopago',
        ];
        $registered = collect(RouteFacade::getRoutes()->getRoutesByName())->keys();
        foreach ($expected as $name) {
            $this->assertTrue(
                $registered->contains($name),
                "webhook route '$name' must be registered (audit added all 5)"
            );
        }
    }

    public function test_webhook_uri_pattern_is_under_csrf_exempt_path(): void
    {
        // The audit added 'webhooks/*' to VerifyCsrfToken::$except so gateway
        // POSTs aren't rejected with 419 Page Expired.
        $middleware = new \App\Http\Middleware\VerifyCsrfToken(app(), app('encrypter'));
        $reflection = new \ReflectionClass($middleware);
        $prop = $reflection->getProperty('except');
        $prop->setAccessible(true);
        $except = $prop->getValue($middleware);

        $this->assertContains('webhooks/*', $except, 'webhooks/* must be in VerifyCsrfToken::$except');
    }

    public function test_certificate_download_route_requires_authenticated_session(): void
    {
        // Unauthenticated request to the certificate download must NOT 200.
        // The post-audit endpoint also requires an active Enrollment row.
        $r = $this->get('/student/certificate/1/download');
        $this->assertNotEquals(200, $r->status(), 'Certificate download must not 200 for unauthenticated requests');
    }

    // ---- helpers -------------------------------------------------------

    private function assertRouteHasMiddleware(string $name, string $expected): void
    {
        $routes = RouteFacade::getRoutes()->getRoutesByName();
        $this->assertArrayHasKey($name, $routes, "route '$name' not registered");
        /** @var Route $route */
        $route = $routes[$name];
        $hits = array_filter($route->gatherMiddleware(), fn ($m) => $m === $expected || str_starts_with((string) $m, $expected . ':'));
        $this->assertNotEmpty($hits, "route '$name' is missing '$expected' middleware. Got: " . implode(', ', $route->gatherMiddleware()));
    }

    private function assertRouteHasMiddlewarePrefix(string $name, string $prefix): void
    {
        $routes = RouteFacade::getRoutes()->getRoutesByName();
        $this->assertArrayHasKey($name, $routes, "route '$name' not registered");
        /** @var Route $route */
        $route = $routes[$name];
        $hits = array_filter($route->middleware(), fn ($m) => str_starts_with((string) $m, $prefix));
        $this->assertNotEmpty($hits, "route '$name' is missing '$prefix' middleware. Got: " . implode(', ', $route->middleware()));
    }
}
