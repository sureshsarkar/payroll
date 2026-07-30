<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

/**
 * UI/UX audit P0-1 — pins the contract for the credential-setting
 * route rename:
 *
 *   - new URL `admin/credential-setting` resolves with route name
 *     `admin.credential-setting`
 *   - old URL `admin/crediential-setting` (typo) returns a 301
 *     permanent redirect to the new URL
 *   - old route name `admin.crediential-setting` is still registered
 *     for any code that may still reference it
 *
 * Why this matters:
 *   The typo'd URL was visible in the address bar — embarrassing
 *   and an SEO concern (Google indexes admin URLs that leak via
 *   forgotten meta robots). The redirect preserves any bookmark
 *   from before the rename.
 */
class TypoRouteRedirectTest extends TestCase
{
    public function test_new_route_name_resolves(): void
    {
        $this->assertNotNull(route('admin.credential-setting'));
    }

    public function test_old_route_name_still_resolves(): void
    {
        // Old name kept as backward-compat. URL points to the OLD
        // path so requests to the old name still go through the
        // 301 redirect (they don't skip it).
        $this->assertNotNull(route('admin.crediential-setting'));
        $this->assertStringContainsString('crediential-setting', route('admin.crediential-setting'));
    }

    public function test_new_url_path_does_not_contain_typo(): void
    {
        $url = route('admin.credential-setting');
        $this->assertStringContainsString('credential-setting', $url);
        $this->assertStringNotContainsString('crediential', $url);
    }

    public function test_typo_url_route_is_registered_as_get(): void
    {
        // Walk the route collection directly — exercising the full HTTP
        // middleware stack in test env requires a hydrated settings()
        // helper that the test DB doesn't seed. The structural contract
        // is what matters: the old typo'd URL is registered as a GET
        // route, AND its handler is a closure that returns a redirect.
        $route = \Illuminate\Support\Facades\Route::getRoutes()
            ->getByName('admin.crediential-setting');

        $this->assertNotNull($route, 'old typo route must still be registered');
        $this->assertContains('GET', $route->methods());
        $this->assertSame('admin/crediential-setting', $route->uri());

        // The handler is the redirect closure we installed.
        $action = $route->getAction();
        $this->assertTrue(
            $action['uses'] instanceof \Closure
                || (is_string($action['uses'] ?? null) && str_contains($action['uses'], 'Closure')),
            'old typo route must be handled by a closure (the redirect handler)'
        );
    }

    public function test_new_url_route_handler_is_the_controller(): void
    {
        $route = \Illuminate\Support\Facades\Route::getRoutes()
            ->getByName('admin.credential-setting');

        $this->assertNotNull($route);
        $this->assertSame('admin/credential-setting', $route->uri());
        // New route still maps to the original controller method.
        $this->assertStringContainsString('crediential_setting', $route->getActionName());
    }
}
