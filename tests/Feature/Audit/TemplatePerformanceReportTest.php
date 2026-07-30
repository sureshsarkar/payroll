<?php

namespace Tests\Feature\Audit;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Regression tripwire — Phase 7 of the multi-template business website
 * system (2026-05-12). Locks in the per-template performance report:
 *
 *   - The admin route is registered AND gated behind auth:admin + 2fa:admin.
 *   - The route is declared BEFORE the catch-all {id}/enquiries route so
 *     "templates-report" doesn't get parsed as an integer id and 404.
 *   - The controller method exists with the same permission check as the
 *     rest of the admin oversight surface.
 *   - The SQL aggregate uses a single GROUP BY query (no N+1).
 */
class TemplatePerformanceReportTest extends TestCase
{
    public function test_route_is_registered_and_admin_gated(): void
    {
        $this->assertTrue(
            Route::has('admin.coach-landing-pages.templates-report'),
            'admin.coach-landing-pages.templates-report missing — Phase 7 analytics unreachable'
        );

        $route = Route::getRoutes()->getByName('admin.coach-landing-pages.templates-report');
        $this->assertNotNull($route);

        $middleware = $route->gatherMiddleware();
        $this->assertContains(
            'auth:admin', $middleware,
            'templates-report must require auth:admin — public-facing analytics is a competitive-info leak'
        );
        $this->assertContains(
            '2fa:admin', $middleware,
            'templates-report must require 2fa:admin — matches the rest of the admin namespace'
        );
    }

    public function test_route_order_keeps_templates_report_above_id_pattern(): void
    {
        // The catch-all {id}/enquiries route, if declared first, swallows
        // GET coach-landing-pages/templates-report as id=templates-report
        // and silently 404s on the whereNumber('id') constraint. The
        // route file MUST declare templates-report first.
        $src = (string) file_get_contents(base_path('routes/admin.php'));

        $reportPos    = strpos($src, "'templates-report'");
        $enquiriesPos = strpos($src, "'{id}/enquiries'");
        $this->assertNotFalse($reportPos,    "Route literal 'templates-report' missing from routes/admin.php");
        $this->assertNotFalse($enquiriesPos, "Route literal '{id}/enquiries' missing from routes/admin.php");
        $this->assertLessThan(
            $enquiriesPos, $reportPos,
            'templates-report route must be declared BEFORE the {id}/enquiries route — otherwise the slug gets parsed as an integer id and 404s'
        );
    }

    public function test_controller_method_has_permission_check_and_single_query(): void
    {
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Admin/AdminLandingPageController.php')
        );

        $this->assertStringContainsString(
            'function templatePerformance',
            $src,
            'AdminLandingPageController::templatePerformance() missing'
        );

        $offset = strpos($src, 'function templatePerformance');
        $body = substr($src, $offset, 4000);

        $this->assertStringContainsString(
            "checkAdminHasPermissionAndThrowException('coach-landing-page.view')",
            $body,
            "templatePerformance() must call checkAdminHasPermissionAndThrowException — admin oversight surface is permission-gated"
        );

        // Single aggregate query — must NOT be a foreach over templates
        // counting per-template separately (classic N+1).
        $this->assertStringContainsString(
            'leftJoin',
            $body,
            'templatePerformance() must use joins to aggregate in one round-trip — N+1 over the template catalog would be slow at scale'
        );
        $this->assertStringContainsString(
            'groupBy',
            $body,
            'templatePerformance() must group on template_id to fold pages + enquiries into one row per template'
        );

        // 30-day window for the "recent activity" column.
        $this->assertMatchesRegularExpression(
            '/INTERVAL\s+30\s+DAY/',
            $body,
            'templatePerformance() must use a 30-day window for leads_30 — changing it requires updating the column header and this test'
        );
    }

    public function test_index_links_to_the_report(): void
    {
        // The index page (the admin entrypoint) must surface the report
        // so admins can find it without typing the URL.
        $src = (string) file_get_contents(
            resource_path('views/admin/landing-pages/index.blade.php')
        );
        $this->assertStringContainsString(
            "route('admin.coach-landing-pages.templates-report')",
            $src,
            'admin/landing-pages/index.blade.php must link to the templates-report route — otherwise nobody discovers the analytics page'
        );
    }
}
