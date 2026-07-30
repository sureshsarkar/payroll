<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

/**
 * Regression tripwire for the round-5 admin polish (2026-05-12).
 *
 * Locks in three follow-on contracts:
 *
 *   - template-performance.blade.php uses the shared sort-header partial
 *     (was hand-rolling a $sortHref/$sortCaret closure pair — works, but
 *     drifts from the rest of the admin if anyone tweaks the partial later).
 *   - referrals/settings.blade.php and membership/users/conversion.blade.php
 *     have label-for/id pairs on their date/number inputs.
 *   - admin/notifications/index.blade.php carries an aria-label on every
 *     notification row so screen-reader users can distinguish unread items
 *     from already-read ones without re-reading the title.
 */
class AdminRoundFivePolishTest extends TestCase
{
    public function test_template_performance_uses_shared_sort_header(): void
    {
        $src = (string) file_get_contents(
            resource_path('views/admin/landing-pages/template-performance.blade.php')
        );

        // The page must go through the shared partial, not its own
        // inline closure pair. Drift between the two had already started
        // (the partial added aria-sort while the inline version didn't).
        $count = substr_count($src, "admin.partials.sort-header");
        $this->assertGreaterThanOrEqual(
            5, $count,
            "template-performance.blade.php should use admin.partials.sort-header at least 5 times (one per sortable column). Found {$count}."
        );

        // The inline $sortHref / $sortCaret closures must be gone.
        $this->assertStringNotContainsString(
            '$sortHref = function',
            $src,
            'template-performance still defines its own $sortHref closure — should use the shared partial instead'
        );
    }

    public function test_referrals_settings_has_labeled_number_inputs(): void
    {
        $src = (string) file_get_contents(
            resource_path('views/admin/referrals/settings.blade.php')
        );

        // The three number inputs all need for/id pairs. The two switches
        // use label-wraps-input (valid HTML pattern, doesn't need for/id).
        foreach (['referral-reward-student', 'referral-reward-coach', 'referral-min-amount'] as $id) {
            $this->assertStringContainsString(
                "for=\"{$id}\"",
                $src,
                "referrals/settings.blade.php missing <label for=\"{$id}\"> — number input is unlabeled for screen readers"
            );
            $this->assertStringContainsString(
                "id=\"{$id}\"",
                $src,
                "referrals/settings.blade.php missing <input id=\"{$id}\"> — clicking the label can't focus the field"
            );
        }
    }

    public function test_conversion_report_date_inputs_are_labeled(): void
    {
        $src = (string) file_get_contents(
            resource_path('views/admin/membership/users/conversion.blade.php')
        );

        foreach (['conversion-from', 'conversion-to'] as $id) {
            $this->assertStringContainsString(
                "for=\"{$id}\"",
                $src,
                "conversion.blade.php date filter missing <label for=\"{$id}\">"
            );
        }
    }

    public function test_notifications_index_rows_have_aria_label_and_extracted_styles(): void
    {
        $src = (string) file_get_contents(
            resource_path('views/admin/notifications/index.blade.php')
        );

        // Aria-label on rows so screen readers announce unread state.
        $this->assertStringContainsString(
            'aria-label="{{ ($unread',
            $src,
            'notifications/index notification rows must carry aria-label that surfaces unread state — screen reader users have no other way to tell read from unread'
        );

        // Styles must live in @push('css'), not inline. The .nf-row class
        // is the marker that the extraction happened.
        $this->assertStringContainsString(
            '.nf-row',
            $src,
            "notifications/index should have a .nf-row CSS class — pre-fix the row layout was inline `style=` attributes"
        );
    }
}
