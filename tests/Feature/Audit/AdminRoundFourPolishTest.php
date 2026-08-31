<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

/**
 * Regression tripwire for the round-4 admin polish fixes (2026-05-12).
 *
 * Covers the bugs caught during deeper audit follow-on:
 *   - Smart page-title fallback: master_layout calls currentAdminPageTitle()
 *     so a new admin page automatically gets a sensible <h1> without per-view
 *     @section('page-title','…').
 *   - INVALID HTML: id="email exampleInputEmail" (two ids in one attribute)
 *     on three admin auth views — login, forgot-password, reset-password.
 *     The label for="email" worked by accident because browsers parse the
 *     first token. A future cleanup that "removes redundant ids" could pick
 *     the wrong one and silently break the for/id link.
 *   - Stale id="slug" on the Email input in edit_admin.blade.php (mirror of
 *     the create_admin.blade.php bug we already fixed).
 *   - aria-labels on icon-only buttons in five more list pages.
 */
class AdminRoundFourPolishTest extends TestCase
{
    /* ───────────────────────────────────────── smart page-title ─── */

    public function test_master_layout_uses_smart_page_title_helper(): void
    {
        $src = (string) file_get_contents(
            resource_path('views/admin/master_layout.blade.php')
        );

        $this->assertStringContainsString(
            'currentAdminPageTitle(',
            $src,
            'master_layout must call currentAdminPageTitle() so untouched pages still get a sensible title'
        );
    }

    public function test_current_admin_page_title_helper_is_defensive(): void
    {
        $this->assertTrue(
            function_exists('currentAdminPageTitle'),
            'currentAdminPageTitle() helper missing'
        );

        // Defensive on no-route (e.g. password reset view rendered before
        // route resolution completes). Must not throw.
        $title = currentAdminPageTitle();
        $this->assertIsString($title);
        $this->assertNotEmpty($title);

        // Explicit override wins.
        $this->assertSame(
            'Explicit Override', currentAdminPageTitle('Explicit Override'),
            'Explicit override must short-circuit — pages that set @section(\'page-title\') need to win over the auto-derivation'
        );
    }

    /* ────────────────────────────────────── invalid-id auth bugs ─── */

    public function test_auth_views_have_no_double_id_attribute(): void
    {
        // id="email exampleInputEmail" was the bug — two ids stuffed into
        // one attribute. Strip Blade comments before matching so our own
        // documentation reference to the prior bug doesn't trip the test.
        $files = [
            'views/admin/auth/login.blade.php',
            'views/admin/auth/forgot-password.blade.php',
            'views/admin/auth/reset-password.blade.php',
        ];
        foreach ($files as $rel) {
            $src      = (string) file_get_contents(resource_path($rel));
            $stripped = preg_replace('/\{\{--.*?--\}\}/s', '', $src);

            $this->assertDoesNotMatchRegularExpression(
                '/id="email\s+exampleInputEmail"/',
                $stripped,
                "{$rel} still has id=\"email exampleInputEmail\" — two ids stuffed into one attribute is invalid HTML; the label-for association works only by accident"
            );
            $this->assertDoesNotMatchRegularExpression(
                '/id="password\s+exampleInputPassword"/',
                $stripped,
                "{$rel} still has id=\"password exampleInputPassword\" — same invalid HTML pattern as email"
            );
        }
    }

    public function test_admin_edit_form_has_no_stale_slug_id(): void
    {
        $src = (string) file_get_contents(
            resource_path('views/admin/admin-list/edit_admin.blade.php')
        );

        // The pre-fix file had id="slug" on the Email input (copy-paste
        // from a slug field on some other form). Screen-reader users
        // heard "slug, edit text" when focusing the email field.
        $this->assertDoesNotMatchRegularExpression(
            '/id="slug"\s+class="form-control"\s+name="email"/',
            $src,
            'edit_admin still has id="slug" on the Email input — screen readers announce the wrong field name'
        );
    }

    /* ──────────────────────────────────── aria-labels rollout ─── */

    // LMS removal phase 2 (2026-08-27) — removed
    // test_remaining_list_pages_have_aria_labels_on_icon_buttons(). All four
    // pages it checked (coach landing pages, referrals, referral
    // commissions, coach memberships) are deleted along with the coach
    // business those admin screens managed.

    /* ───────────────────────────────────── 2FA challenge polish ─── */

    public function test_2fa_challenge_view_has_labeled_inputs(): void
    {
        $src = (string) file_get_contents(
            resource_path('views/admin/two-factor/challenge.blade.php')
        );

        // sr-only label for the 6-digit code field — was previously
        // unlabeled, screen readers announced just "edit text".
        $this->assertMatchesRegularExpression(
            '/<label for="tfac-code"\s+class="sr-only"/',
            $src,
            '2FA challenge code input must have a sr-only label — placeholder-only fields are inaccessible'
        );
        $this->assertMatchesRegularExpression(
            '/<label for="tfac-recovery"\s+class="sr-only"/',
            $src,
            '2FA challenge recovery-code input must have a sr-only label'
        );
    }
}
