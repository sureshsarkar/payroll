<?php

namespace Tests\Feature\Audit;

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\MembershipPlanController;
use App\Http\Controllers\Admin\RolesController;
use Tests\TestCase;

/**
 * Regression tripwire for the second-round admin polish fixes
 * (2026-05-12). Covers M2, M3, M6, M7-leftover, M13, and the
 * adminSearchRouteList memoization. Each guards a change that would be
 * easy to silently undo by a future refactor:
 *
 *   - M6 (sortable headers): the three list controllers must keep their
 *     SORTABLE allowlist + the three views must keep using the shared
 *     sort-header partial. Building ORDER BY from raw request input is
 *     a SQL-injection vector — the allowlist is the guard.
 *   - M3 (search hint): the search input must carry an aria-label so
 *     screen-reader users discover it; without it the box is invisible
 *     to non-sighted nav.
 *   - M13 (noscript fallback): nav-level language/currency selectors
 *     must remain usable when JS is disabled.
 *   - M7-leftover: profile-edit form labels must associate with inputs.
 *   - adminSearchRouteList: must memoize per-request to avoid
 *     re-building ~80 route URLs every partial render.
 */
class AdminListSortAndPolishTest extends TestCase
{
    /* ─────────────────────────────────────────────────────── M6 ── */

    public function test_admin_controller_has_sortable_allowlist(): void
    {
        $refl = new \ReflectionClass(AdminController::class);
        $this->assertTrue(
            $refl->hasConstant('SORTABLE'),
            'AdminController::SORTABLE allowlist missing — list page either has no sort, or worse, builds ORDER BY from raw request input'
        );

        $sortable = $refl->getConstant('SORTABLE');
        $this->assertIsArray($sortable);
        foreach (['name', 'email', 'status'] as $key) {
            $this->assertArrayHasKey(
                $key, $sortable,
                "AdminController::SORTABLE missing '{$key}' — sort link in the view would silently fall back to the default column"
            );
        }
    }

    public function test_roles_controller_has_sortable_allowlist(): void
    {
        $refl = new \ReflectionClass(RolesController::class);
        $this->assertTrue($refl->hasConstant('SORTABLE'), 'RolesController::SORTABLE missing');
        $sortable = $refl->getConstant('SORTABLE');
        // 'permissions' maps to permissions_count (withCount()) — must NOT be
        // re-exposed as a raw column name in the URL.
        $this->assertSame(
            'permissions_count', $sortable['permissions'] ?? null,
            "RolesController::SORTABLE['permissions'] must map to permissions_count (the withCount column)"
        );
    }

    public function test_membership_plan_controller_has_sortable_allowlist(): void
    {
        $refl = new \ReflectionClass(MembershipPlanController::class);
        $this->assertTrue($refl->hasConstant('SORTABLE'), 'MembershipPlanController::SORTABLE missing');
    }

    public function test_three_list_views_use_shared_sort_header_partial(): void
    {
        $files = [
            'views/admin/admin-list/admin.blade.php',
            'views/admin/roles/index.blade.php',
            'views/admin/membership/plans/index.blade.php',
        ];
        foreach ($files as $rel) {
            $src = (string) file_get_contents(resource_path($rel));
            $this->assertStringContainsString(
                "admin.partials.sort-header",
                $src,
                "{$rel} must use the shared admin.partials.sort-header partial — hand-rolling sort links across pages re-introduces drift"
            );
        }
    }

    public function test_sort_header_partial_preserves_existing_query_state(): void
    {
        // Without array_merge(request()->query(), …) the sort link wipes
        // the user's active filters every time they click a column header.
        $src = (string) file_get_contents(
            resource_path('views/admin/partials/sort-header.blade.php')
        );
        $this->assertMatchesRegularExpression(
            '/array_merge\(\s*request\(\)->query\(\)\s*,/',
            $src,
            'sort-header partial must merge with request()->query() so sorting preserves the active filters'
        );
        $this->assertStringContainsString(
            'aria-sort=',
            $src,
            'sort-header partial must emit aria-sort so screen readers announce the current sort direction'
        );
    }

    /* ─────────────────────────────────────────────────────── M3 ── */

    public function test_navbar_search_box_is_accessible_and_discoverable(): void
    {
        $src = (string) file_get_contents(
            resource_path('views/admin/master_layout.blade.php')
        );

        // Icon prefix makes the box obviously a search field.
        $this->assertMatchesRegularExpression(
            '/<i class="fas fa-search"[^>]*aria-hidden/',
            $src,
            'navbar search box must show a magnifying-glass icon — placeholder-only fields are easy to miss'
        );
        // Accessible label so screen-reader users can find it.
        $this->assertStringContainsString(
            'aria-label="{{ __(\'Search admin menu\')',
            $src,
            'navbar search input must carry an aria-label — screen readers otherwise read "edit text" with no clue what the box does'
        );
        $this->assertStringContainsString(
            'for="search_menu"',
            $src,
            'navbar search input must have a (sr-only) <label for="search_menu"> association'
        );
    }

    /* ────────────────────────────────────────────────────── M13 ── */

    public function test_nav_selectors_have_noscript_fallback(): void
    {
        $src = (string) file_get_contents(
            resource_path('views/admin/master_layout.blade.php')
        );
        $count = substr_count($src, '<noscript>');
        $this->assertGreaterThanOrEqual(
            2, $count,
            "Expected ≥ 2 <noscript> fallbacks in master_layout (language + currency selectors). Found {$count}. Without these, JS-disabled users can pick an option but no submit fires."
        );
    }

    /* ──────────────────────────────────────────── M7-leftover ─── */

    public function test_profile_edit_form_associates_labels(): void
    {
        $src = (string) file_get_contents(
            resource_path('views/admin/profile/edit_profile.blade.php')
        );

        // Every label must have for=, every input must have id=, and the
        // pairs must match. We test the contract loosely: count for= vs
        // count of inputs we expect (image, name, email, bio, current,
        // new, confirm = 7 fields).
        $forCount = substr_count($src, 'for="profile-');
        $this->assertGreaterThanOrEqual(
            6, $forCount,
            "Profile edit form should have ≥ 6 label-for/input-id pairs (profile-name, -email, -bio, -current-password, -new-password, -password-confirmation). Found {$forCount}."
        );

        // alt="image" placeholder was meaningless — must be replaced.
        // Strip Blade comments before matching, otherwise our own
        // documentation reference to the prior bug counts as a hit.
        $stripped = preg_replace('/\{\{--.*?--\}\}/s', '', $src);
        $this->assertStringNotContainsString(
            'alt="image"', $stripped,
            'Profile edit still uses alt="image" — screen readers gain nothing from that. Use the admin name.'
        );
    }

    /* ─────────────────────────────────────────────── Low / memo ── */

    public function test_admin_search_route_list_memoizes_per_request(): void
    {
        $src = (string) file_get_contents(app_path('Helpers/helper.php'));

        $offset = strpos($src, 'function adminSearchRouteList');
        $this->assertNotFalse($offset);
        $body = substr($src, $offset, 1200);

        $this->assertStringContainsString(
            'static $_memo',
            $body,
            'adminSearchRouteList() must memoize via a static — without it, every partial render rebuilds ~80 route() URLs'
        );
        $this->assertStringContainsString(
            'if ($_memo !== null) {',
            $body,
            'adminSearchRouteList() must short-circuit when the static is already populated'
        );
    }
}
