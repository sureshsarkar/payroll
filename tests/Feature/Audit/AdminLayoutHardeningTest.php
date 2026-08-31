<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

/**
 * Regression tripwire for the HIGH-priority admin-layout audit fixes
 * (2026-05-12). Covers H1, H2/H3, H4, H5, H6, H7 — each represents a
 * subtle bug or maintenance pitfall that's easy to re-introduce by
 * tidying up the wrong file.
 *
 * Single test class because the fixes are tightly coupled to the same
 * three files (master_layout, javascripts partial, AppServiceProvider).
 */
class AdminLayoutHardeningTest extends TestCase
{
    private function masterLayout(): string
    {
        return (string) file_get_contents(
            resource_path('views/admin/master_layout.blade.php')
        );
    }

    /* ────────────────────────────────────────────────────── H1 ───── */

    public function test_page_title_is_yielded_not_hardcoded(): void
    {
        $src = $this->masterLayout();

        // The old hardcode read every page as "Dashboard". Block its return.
        $this->assertDoesNotMatchRegularExpression(
            '/<h1 class="page-title-new">\s*\{\{\s*__\(\'Dashboard\'\)\s*\}\}\s*<\/h1>/',
            $src,
            'master_layout still hardcodes "Dashboard" as the navbar title — every page reads "Dashboard" regardless of route. Use @yield(\'page-title\', \'Dashboard\').'
        );
        $this->assertStringContainsString(
            "yieldContent('page-title'",
            $src,
            'master_layout must yield the page-title section so pages can declare their own via @section(\'page-title\', …)'
        );
    }

    /* ────────────────────────────────────────────────── H2 + H3 ───── */

    public function test_flash_renderer_picks_up_both_messege_and_message(): void
    {
        $src = (string) file_get_contents(
            resource_path('views/admin/partials/javascripts.blade.php')
        );

        // Both the legacy typo key and the correctly-spelled key must be
        // read so neither convention silently swallows the flash.
        $this->assertStringContainsString(
            "session('messege')",
            $src,
            'javascripts partial must still read the legacy `messege` typo key for back-compat with existing controllers'
        );
        $this->assertStringContainsString(
            "session('message')",
            $src,
            'javascripts partial must ALSO read the correctly-spelled `message` key so new controllers using $msg => with(\'message\', …) actually render a toast'
        );

        // Bare top-level keys (`success` / `error` / etc.) used by some
        // controllers via redirect()->with('success', '…') must also flip
        // a toast — pre-fix they were silently swallowed.
        $this->assertStringContainsString(
            "'success', 'error', 'warning', 'info'",
            $src,
            'javascripts partial must check the bare top-level flash keys (success/error/warning/info) — without this, redirect()->with(\'success\', …) silently renders nothing'
        );
    }

    /* ────────────────────────────────────────────────────── H4 ───── */

    public function test_admin_can_directive_delegates_to_helper(): void
    {
        $src = (string) file_get_contents(
            app_path('Providers/AppServiceProvider.php')
        );

        $this->assertStringContainsString(
            "checkAdminHasPermission({\$permission})",
            $src,
            '@adminCan directive must delegate to checkAdminHasPermission() — calling ->user()->can() directly null-derefs if no admin is authenticated (e.g. layout shown during password reset)'
        );

        // The pre-fix implementation called ->user()->can() — which is the
        // exact null-deref vector.
        $this->assertDoesNotMatchRegularExpression(
            "/->user\(\)->can\(\{\\\$permission\}\)/",
            $src,
            '@adminCan directive must NOT call ->user()->can() directly — that crashes on a guest request'
        );
    }

    /* ────────────────────────────────────────────────────── H5 ───── */

    public function test_settings_sidebar_swap_uses_helper_not_hardcoded_list(): void
    {
        $src = $this->masterLayout();

        // The master layout used to inline an 18-route hardcoded list inside
        // request()->routeIs(…). Adding a new settings page silently used
        // the wrong sidebar until the layout itself was edited.
        $this->assertDoesNotMatchRegularExpression(
            "/request\(\)->routeIs\(\s*\n?\s*'admin\.general-setting'/",
            $src,
            "master_layout must not inline the request()->routeIs(...) call for settings-page detection — use isAdminSettingsRoute() so the list lives in one testable place"
        );
        $this->assertStringContainsString(
            'isAdminSettingsRoute()',
            $src,
            'master_layout must call isAdminSettingsRoute() to decide which sidebar to render'
        );

        // The helper must exist.
        $this->assertTrue(
            function_exists('isAdminSettingsRoute'),
            'isAdminSettingsRoute() helper missing — master_layout call will fatal'
        );
    }

    /* ────────────────────────────────────────────────────── H6 ───── */

    // LMS removal phase 2 (2026-08-27) — removed
    // test_dashboard_uses_shared_stat_card_partial(). resources/views/admin/
    // dashboard.blade.php is deleted: its 8 stat tiles (orders, courses,
    // pending approvals, etc.) all read LMS models. admin.dashboard now
    // redirects straight to admin.payroll.dashboard. The reusable
    // admin/partials/stat-card.blade.php partial itself is left in place —
    // it is generic, not LMS-specific — for whenever the payroll dashboard
    // grows stat tiles of its own.

    /* ────────────────────────────────────────────────────── H7 ───── */

    public function test_project_mode_meta_tag_is_gone(): void
    {
        $src = $this->masterLayout();

        // The pre-fix layout had:
        //   <meta name="mode" content="{{ env('PROJECT_MODE') ?? 'LIVE' }}">
        // exposing deployment posture to any HTML scraper.
        $this->assertDoesNotMatchRegularExpression(
            '/<meta\s+name="mode"\s+content="\{\{\s*env\(\'PROJECT_MODE\'\)/',
            $src,
            'master_layout still ships <meta name="mode" content="env(PROJECT_MODE)…"> — fingerprints deployment posture to any client'
        );
    }
}
