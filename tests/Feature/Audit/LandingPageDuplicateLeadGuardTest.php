<?php

namespace Tests\Feature\Audit;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Regression tripwire — Phase 5 of the multi-template business website
 * system (2026-05-12). Locks down two pieces of admin/back-pressure
 * infrastructure that are easy to silently regress:
 *
 *   1. Duplicate-lead guard on the two public form-handlers. A bot or
 *      enthusiastic visitor hitting the form 50× in a minute would
 *      otherwise flood the coach's CRM with identical rows. The guard
 *      collapses duplicates within a 7-day window into one primary row
 *      with notes — preserving the data but keeping the inbox clean.
 *   2. Admin landing-page oversight routes existing and reachable from
 *      the admin namespace. Without them, the admin can't see cross-
 *      coach landing-page activity or troubleshoot lead-attribution
 *      problems without DB access.
 */
class LandingPageDuplicateLeadGuardTest extends TestCase
{
    public function test_find_recent_duplicate_helper_exists_with_7day_window(): void
    {
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/Coach/LandingPageController.php')
        );

        $this->assertStringContainsString(
            'function findRecentDuplicate',
            $src,
            'findRecentDuplicate() helper missing — public form-handlers will allow bot floods'
        );

        // The 7-day window is the contract — shortening it would let
        // resubmitters slip through, lengthening it would discard genuine
        // returning leads. Either change needs a test update too.
        $this->assertMatchesRegularExpression(
            '/now\(\)->subDays\(\s*7\s*\)/',
            $src,
            'Duplicate window must be 7 days — changing it requires updating this test and the docstring'
        );

        // The guard must exclude prior spam rows from its lookup, otherwise
        // a scripted bot would reset the window each submission.
        $this->assertStringContainsString(
            "STATUS_SPAM",
            $src,
            'Duplicate guard must exclude STATUS_SPAM rows from the lookup — bots would otherwise reset the window'
        );
    }

    public function test_both_form_handlers_apply_the_duplicate_guard(): void
    {
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/Coach/LandingPageController.php')
        );

        // submit_landing_page
        $offset = strpos($src, 'function submit_landing_page');
        $this->assertNotFalse($offset, 'submit_landing_page method missing');
        $body = substr($src, $offset, 6000);
        $this->assertStringContainsString(
            'findRecentDuplicate(',
            $body,
            'submit_landing_page must call findRecentDuplicate() before persisting the lead'
        );

        // submit_service_page
        $offset2 = strpos($src, 'function submit_service_page');
        $this->assertNotFalse($offset2, 'submit_service_page method missing');
        $body2 = substr($src, $offset2, 6000);
        $this->assertStringContainsString(
            'findRecentDuplicate(',
            $body2,
            'submit_service_page must call findRecentDuplicate() — service-form leads can also flood'
        );

        // Duplicate rows must be marked STATUS_SPAM, not silently dropped.
        // Dropping would destroy a real submission; STATUS_SPAM preserves
        // it while keeping the coach's inbox clean.
        $this->assertMatchesRegularExpression(
            '/\$duplicate\s*\?\s*\\\\?App\\\\?Models\\\\?LandingPageEnquiry::STATUS_SPAM/',
            $body,
            'submit_landing_page must mark duplicates with STATUS_SPAM (not drop them) — dropping destroys real data'
        );
    }

    public function test_duplicates_dont_re_notify_the_coach(): void
    {
        // A duplicate within 7 days means the coach already knows about
        // this lead. Re-notifying them would feel like spam from the
        // coach's perspective, undoing the value of the dedup guard.
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/Coach/LandingPageController.php')
        );

        $offset = strpos($src, 'function submit_landing_page');
        // Window widened 2026-05-25 — coach marketing website audit
        // expanded submit_landing_page with page_id/section_id capture
        // + smart name-splitting, pushing the notify() call past 6000c.
        $body = substr($src, $offset, 8000);

        // The "if ($duplicate) return early" branch must come BEFORE the
        // notify() call. If it doesn't, duplicates would still fire the
        // coach notification.
        $dupReturn = strpos($body, 'recordDuplicateNote(');
        $notify    = strpos($body, 'NewLandingPageEnquiryToCoach(');
        $this->assertNotFalse($dupReturn, 'recordDuplicateNote() must be called from submit_landing_page');
        $this->assertNotFalse($notify, 'NewLandingPageEnquiryToCoach must still be dispatched for non-duplicate leads');
        $this->assertLessThan(
            $notify, $dupReturn,
            'recordDuplicateNote() must run BEFORE the notify() call so the early-return short-circuits notifications for duplicates'
        );
    }

    public function test_admin_oversight_routes_are_registered(): void
    {
        // Both admin routes must resolve via the route name registry. If
        // someone renames the controller or accidentally drops the route
        // group, the URL would 404 silently and the audit catches it.
        $this->assertTrue(
            Route::has('admin.coach-landing-pages.index'),
            'admin.coach-landing-pages.index route missing — admin cannot see cross-coach landing-page activity'
        );
        $this->assertTrue(
            Route::has('admin.coach-landing-pages.enquiries'),
            'admin.coach-landing-pages.enquiries route missing — admin cannot drill into a page\'s leads'
        );

        // Confirm both routes are behind auth:admin + 2fa middleware so
        // they can't be hit by a logged-in coach or anonymous visitor.
        $route = Route::getRoutes()->getByName('admin.coach-landing-pages.index');
        $this->assertNotNull($route);
        $middleware = $route->gatherMiddleware();
        $this->assertContains(
            'auth:admin', $middleware,
            'admin.coach-landing-pages.index must require auth:admin — cross-coach data is admin-only'
        );
        $this->assertContains(
            '2fa:admin', $middleware,
            'admin.coach-landing-pages.index must require 2fa:admin — matches the rest of the admin namespace'
        );
    }

    public function test_admin_controller_exists_with_expected_methods(): void
    {
        $cls = \App\Http\Controllers\Admin\AdminLandingPageController::class;
        $this->assertTrue(
            class_exists($cls),
            'AdminLandingPageController missing — admin oversight pages cannot resolve'
        );
        $this->assertTrue(
            method_exists($cls, 'index'),
            "{$cls}::index() method missing"
        );
        $this->assertTrue(
            method_exists($cls, 'enquiries'),
            "{$cls}::enquiries() method missing — drill-down to per-page leads is unavailable"
        );
    }
}
