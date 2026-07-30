<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

/**
 * Regression tripwire — Phase 4 of the multi-template business website system
 * (2026-05-12). Confirms that landing-page form submissions actually notify
 * the coach (bell + Pusher + email), not just the platform admin.
 *
 * Pre-Phase-4, both form-handlers emailed the platform-wide contact-message
 * receiver and stayed silent toward the coach who actually owns the lead.
 * Coaches found out about new leads by refreshing the CRM page.
 *
 * Two specific regression vectors guarded:
 *   1. The NewLandingPageEnquiryToCoach notification class existing and
 *      extending InAppNotification (so it gets database + broadcast + mail
 *      channels automatically).
 *   2. The two form-handlers actually dispatching that notification AFTER
 *      the enquiry row is created — a refactor that moves the notify() call
 *      before the row exists (or removes it entirely) would silently break
 *      coach-side alerts without breaking any other test.
 */
class NewLandingPageEnquiryNotificationTest extends TestCase
{
    public function test_notification_class_exists_and_extends_in_app_notification(): void
    {
        $path = app_path('Notifications/NewLandingPageEnquiryToCoach.php');
        $this->assertFileExists(
            $path,
            'NewLandingPageEnquiryToCoach notification missing — coaches will not be alerted to new leads'
        );

        $src = (string) file_get_contents($path);

        $this->assertMatchesRegularExpression(
            '/class\s+NewLandingPageEnquiryToCoach\s+extends\s+InAppNotification/',
            $src,
            'NewLandingPageEnquiryToCoach must extend InAppNotification — otherwise it bypasses the database+broadcast+mail channel plumbing'
        );

        // Event key used for preference filtering — without it, the user's
        // notification-preferences page can't toggle this notification off.
        $this->assertStringContainsString(
            "\$event = 'new_landing_lead'",
            $src,
            "Notification must declare \$event = 'new_landing_lead' so coaches can manage it in preferences"
        );

        // Title must include the business vertical when known — otherwise a
        // coach running 3 landing pages can't tell which one converted.
        $this->assertStringContainsString(
            '$enquiry->business_category',
            $src,
            'Notification title must surface business_category — coaches with multiple verticals need to disambiguate at a glance'
        );

        // URL must deep-link to the CRM detail page so the bell click goes
        // straight to the lead's record, not the index.
        $this->assertStringContainsString(
            "route('instructor.landing-page-enquiry.show'",
            $src,
            "Notification url must route to the CRM detail page for the lead — index-only links waste a click"
        );
    }

    public function test_submit_landing_page_dispatches_coach_notification(): void
    {
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/Coach/LandingPageController.php')
        );

        $offset = strpos($src, 'function submit_landing_page');
        $this->assertNotFalse($offset, 'submit_landing_page method missing');
        // Window widened 2026-05-25 — coach marketing website audit
        // added defensive name-splitting + page_id/section_id capture +
        // custom_fields handling INSIDE submit_landing_page, pushing the
        // ->notify() call past the previous 6000-char window.
        // 8000 gives headroom for the next few inserts before this
        // needs revisiting.
        $body = substr($src, $offset, 8000);

        // The handler must call ->notify(new NewLandingPageEnquiryToCoach(...))
        // AFTER the enquiry row is created. Order matters because the
        // notification carries the enquiry ID into the CRM deep-link URL.
        $createPos = strpos($body, 'LandingPageEnquiry::create(');
        $notifyPos = strpos($body, 'NewLandingPageEnquiryToCoach(');
        $this->assertNotFalse(
            $createPos,
            'submit_landing_page must still call LandingPageEnquiry::create() — guard against accidental refactor'
        );
        $this->assertNotFalse(
            $notifyPos,
            'submit_landing_page must dispatch NewLandingPageEnquiryToCoach — coaches need to know about new leads'
        );
        $this->assertGreaterThan(
            $createPos, $notifyPos,
            'NewLandingPageEnquiryToCoach must be dispatched AFTER LandingPageEnquiry::create() — notifying before the row exists yields a null id in the deep-link URL'
        );

        // Notification failure must NOT block the visitor's form submission.
        $this->assertMatchesRegularExpression(
            '/try\s*\{[^}]*NewLandingPageEnquiryToCoach[^}]*\}\s*catch\s*\(\\\\?Throwable/s',
            $body,
            'NewLandingPageEnquiryToCoach must be wrapped in try/catch — a notification failure must never break the public form'
        );
    }

    public function test_submit_service_page_dispatches_coach_notification(): void
    {
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/Coach/LandingPageController.php')
        );

        $offset = strpos($src, 'function submit_service_page');
        $this->assertNotFalse($offset, 'submit_service_page method missing');
        $body = substr($src, $offset, 6000);

        $this->assertStringContainsString(
            'NewLandingPageEnquiryToCoach(',
            $body,
            'submit_service_page must also dispatch the coach notification — product-form leads matter too'
        );

        $createPos = strpos($body, 'LandingPageEnquiry::create(');
        $notifyPos = strpos($body, 'NewLandingPageEnquiryToCoach(');
        $this->assertGreaterThan(
            $createPos, $notifyPos,
            'submit_service_page: notification must be dispatched after enquiry creation (same deep-link reason as the landing-page handler)'
        );
    }
}
