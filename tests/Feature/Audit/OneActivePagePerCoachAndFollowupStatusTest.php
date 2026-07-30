<?php

namespace Tests\Feature\Audit;

use App\Models\CoachLandingPage;
use App\Models\LandingPageEnquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Regression tripwire — Phase 8 of the multi-template business website
 * system (2026-05-12). Three contracts locked:
 *
 *   1. One-active-page-per-coach: when a coach publishes a page (either
 *      via storeName on creation or publishWebsite on toggle), any other
 *      published pages they own get unpublished automatically. This keeps
 *      the subdomain routing deterministic — without it, two pages with
 *      different subdomains could both resolve and the coach has no clear
 *      way to know which one captured a specific lead.
 *   2. STATUS_FOLLOWUP exists as an enum value AND is rendered in the
 *      status dropdown. Without the enum, the spec's "Follow-up" status
 *      can never be set.
 *   3. Scheduling a future follow_up_at auto-flips the lead status to
 *      STATUS_FOLLOWUP from early-funnel statuses (new / contacted /
 *      published), but does NOT overwrite late-funnel decisions
 *      (qualified / proposal_sent / won / lost / spam).
 */
class OneActivePagePerCoachAndFollowupStatusTest extends TestCase
{
    use DatabaseTransactions;

    public function test_status_followup_enum_is_registered(): void
    {
        $opts = LandingPageEnquiry::statusOptions();

        $this->assertArrayHasKey(
            LandingPageEnquiry::STATUS_FOLLOWUP, $opts,
            "STATUS_FOLLOWUP missing from statusOptions() — spec mandates a Follow-up status"
        );
        $this->assertSame(
            'Follow-up', $opts[LandingPageEnquiry::STATUS_FOLLOWUP]['label'],
            "STATUS_FOLLOWUP label drifted from 'Follow-up' — spec uses that exact name"
        );

        // Must also be in the valid-status allowlist so the bulk-update +
        // status-update endpoints accept it as a target value.
        $this->assertContains(
            LandingPageEnquiry::STATUS_FOLLOWUP,
            LandingPageEnquiry::validStatuses(),
            "STATUS_FOLLOWUP missing from validStatuses() — coaches can't set the status via the UI"
        );
    }

    public function test_status_followup_sits_between_contacted_and_qualified(): void
    {
        // Order in statusOptions() drives the dropdown's funnel order.
        // Follow-up belongs after Contacted (the coach reached out) and
        // before Qualified (they confirmed fit). A drift here would
        // misrepresent the sales funnel in the dropdown.
        $keys = array_keys(LandingPageEnquiry::statusOptions());
        $contactedAt = array_search(LandingPageEnquiry::STATUS_CONTACTED, $keys, true);
        $followupAt  = array_search(LandingPageEnquiry::STATUS_FOLLOWUP,  $keys, true);
        $qualifiedAt = array_search(LandingPageEnquiry::STATUS_QUALIFIED, $keys, true);

        $this->assertNotFalse($contactedAt);
        $this->assertNotFalse($followupAt);
        $this->assertNotFalse($qualifiedAt);
        $this->assertGreaterThan($contactedAt, $followupAt, 'Follow-up must come after Contacted in the funnel order');
        $this->assertLessThan(   $qualifiedAt, $followupAt, 'Follow-up must come before Qualified in the funnel order');
    }

    public function test_setfollowup_auto_promotes_new_lead_to_followup(): void
    {
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/Coach/LandingPageEnquiryController.php')
        );

        $offset = strpos($src, 'function setFollowUp');
        $this->assertNotFalse($offset);
        $body = substr($src, $offset, 2500);

        // The auto-flip must check that the scheduled date is in the FUTURE
        // (historical follow-ups don't need a status change) AND that the
        // current status is in the early-funnel set.
        $this->assertStringContainsString(
            "isFuture()", $body,
            'setFollowUp() must only auto-promote when the scheduled date is in the future — past dates are just bookkeeping'
        );
        $this->assertStringContainsString(
            "STATUS_FOLLOWUP", $body,
            'setFollowUp() must set STATUS_FOLLOWUP — without this, scheduling a follow-up does nothing visible in the index'
        );
        // Must NOT overwrite late-funnel statuses. Test against the literal
        // late statuses listed in the controller's $allowed array.
        foreach (['qualified', 'proposal_sent', 'won', 'lost', 'spam'] as $latestatus) {
            $this->assertStringNotContainsString(
                "'{$latestatus}'", substr($body, 0, strpos($body, "STATUS_FOLLOWUP")),
                "setFollowUp() pre-status guard must not list '{$latestatus}' — that status reflects an explicit coach decision and follow-up scheduling shouldn't overwrite it"
            );
        }
    }

    public function test_storename_unpublishes_sibling_pages_for_same_coach(): void
    {
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/Coach/LandingPageController.php')
        );

        $offset = strpos($src, 'function storeName');
        $this->assertNotFalse($offset);
        $body = substr($src, $offset, 4000);

        // Before insert(), siblings must be set to is_published=0.
        $unpubPos = strpos($body, "->update(['is_published' => 0])");
        $createPos = strpos($body, 'CoachLandingPage::create(');
        $this->assertNotFalse($unpubPos,  'storeName() must unpublish sibling pages before activating the new one');
        $this->assertNotFalse($createPos, 'storeName() must still call CoachLandingPage::create() after the unpublish step');
        $this->assertLessThan(
            $createPos, $unpubPos,
            'Sibling unpublish must happen BEFORE the new page is created — otherwise the new page itself would get unpublished'
        );
    }

    public function test_publishwebsite_unpublishes_siblings_when_activating(): void
    {
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/Coach/LandingPageController.php')
        );

        $offset = strpos($src, 'function publishWebsite');
        $this->assertNotFalse($offset);
        $body = substr($src, $offset, 2500);

        // The sibling-unpublish only runs on activation (status=1) so a
        // coach hitting un-publish doesn't accidentally unpublish unrelated
        // rows.
        $this->assertMatchesRegularExpression(
            '/\(int\)\s*\$status\s*===\s*1/',
            $body,
            'publishWebsite() must guard the sibling-unpublish behind (int)$status === 1 — without it, unpublishing one page would also unpublish unrelated pages'
        );
        $this->assertStringContainsString(
            "->where('id', '!=', \$data->id)",
            $body,
            'publishWebsite() must exclude the page being published itself from the sibling-unpublish WHERE — otherwise the new page goes back to draft'
        );
    }
}
