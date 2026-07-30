<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

/**
 * UI/UX audit P1-12 — pin the toast notification wiring.
 *
 * Investigation: a toastr-based notification system is already
 * wired into both admin and frontend chrome via prior audit fix
 * H2/H3 (commits ~2026-05-12). The audit noted that successful
 * actions are followed by a flash-banner pattern which "feels
 * jarring vs modern apps". Moving to true SPA toast-after-AJAX
 * requires rewriting controllers to return JSON + adding JS form
 * handlers — multi-week work. The CURRENT toastr-on-page-load
 * pattern IS the toast system; just emitted server-side.
 *
 * This test pins the current wiring so it can't silently regress.
 *
 * What's covered:
 *   1. toastr library is loaded in both admin and frontend chrome
 *   2. Both chromes handle the legacy session key 'messege' (typo)
 *      and the new 'message' key — fallback chain
 *   3. Alert types (info/success/warning/error) all branch correctly
 */
class ToastNotificationWiringTest extends TestCase
{
    public function test_frontend_chrome_loads_toastr(): void
    {
        $contents = file_get_contents(
            resource_path('views/frontend/layouts/scripts.blade.php')
        );
        $this->assertStringContainsString('toastr.min.js', $contents,
            'Frontend chrome must load toastr.min.js for flash notifications');
        $this->assertStringContainsString('toastr.success', $contents,
            'Frontend chrome must wire toastr.success for the success branch');
        $this->assertStringContainsString('toastr.error', $contents,
            'Frontend chrome must wire toastr.error');
    }

    public function test_admin_chrome_loads_toastr(): void
    {
        $contents = file_get_contents(
            resource_path('views/admin/partials/javascripts.blade.php')
        );
        $this->assertStringContainsString('toastr.min.js', $contents,
            'Admin chrome must load toastr.min.js for flash notifications');
        $this->assertStringContainsString('toastr.success', $contents);
        $this->assertStringContainsString('toastr.error', $contents);
    }

    public function test_admin_chrome_handles_both_legacy_messege_and_modern_message_keys(): void
    {
        // Audit fix H2/H3 (2026-05-12) added fallback to handle the
        // typo'd `messege` key the codebase historically used PLUS
        // the corrected `message` key. Both must remain wired.
        $contents = file_get_contents(
            resource_path('views/admin/partials/javascripts.blade.php')
        );
        $this->assertStringContainsString("session('messege')", $contents);
        $this->assertStringContainsString("session('message')", $contents);
    }

    public function test_both_chromes_handle_all_four_alert_types(): void
    {
        foreach ([
            resource_path('views/frontend/layouts/scripts.blade.php'),
            resource_path('views/admin/partials/javascripts.blade.php'),
        ] as $path) {
            $contents = file_get_contents($path);
            foreach (['info', 'success', 'warning', 'error'] as $type) {
                $this->assertStringContainsString("toastr.$type", $contents,
                    basename($path) . " must handle alert-type '$type'");
            }
        }
    }

    public function test_toastr_position_is_bottom_right(): void
    {
        $contents = file_get_contents(
            resource_path('views/frontend/layouts/scripts.blade.php')
        );
        $this->assertStringContainsString("toast-bottom-right", $contents,
            'Toast position must be bottom-right (modern SaaS convention; '
            . 'top-right would conflict with header chrome on mobile)');
    }
}
