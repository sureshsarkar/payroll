<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

/**
 * Regression tripwire for the round-5 admin polish (2026-05-12).
 *
 * Originally locked in three follow-on contracts:
 *
 *   - template-performance.blade.php uses the shared sort-header partial
 *   - referrals/settings.blade.php and membership/users/conversion.blade.php
 *     have label-for/id pairs on their date/number inputs.
 *   - admin/notifications/index.blade.php carries an aria-label on every
 *     notification row so screen-reader users can distinguish unread items
 *     from already-read ones without re-reading the title.
 *
 * LMS removal phase 2 (2026-08-27) — removed the first two contracts.
 * admin/landing-pages/template-performance.blade.php,
 * admin/referrals/settings.blade.php and
 * admin/membership/users/conversion.blade.php are all deleted along with
 * coach landing pages, the referral wallet system and coach memberships.
 * Only the notifications contract survives — it isn't LMS-specific.
 */
class AdminRoundFivePolishTest extends TestCase
{
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
