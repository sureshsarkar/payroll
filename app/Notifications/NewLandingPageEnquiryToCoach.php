<?php

namespace App\Notifications;

use App\Models\LandingPageEnquiry;

/**
 * Sent to a coach when a new lead lands via their published landing page
 * — either through the contact form (submit_landing_page) or the product
 * card form (submit_service_page). Delivered on:
 *
 *   - database channel: shows up in the bell with a link to the lead's
 *                       CRM detail page
 *   - broadcast channel: real-time toast for coaches currently logged in
 *   - mail channel:     only if the coach has an email address on file
 *
 * Phase 4 of the multi-template business website system (2026-05-12).
 * Before this notification existed, landing-page submissions emailed only
 * the platform admin and the coach learned about new leads by manually
 * refreshing the CRM page.
 */
class NewLandingPageEnquiryToCoach extends InAppNotification
{
    protected string $event = 'new_landing_lead';
    protected string $emailTemplate = 'notif_new_landing_lead';
    protected string $emailCtaLabel = 'Open lead in CRM';

    private LandingPageEnquiry $enquiry;

    /** Coach-facing: brand the email as the coach who owns the recipient. */
    protected function brandCoachId(object $notifiable): ?int
    {
        return $this->recipientCoachId($notifiable);
    }

    public function __construct(LandingPageEnquiry $enquiry)
    {
        $this->enquiry = $enquiry;

        $fullName = trim(($enquiry->first_name ?? '') . ' ' . ($enquiry->last_name ?? ''));
        $fullName = $fullName !== '' ? $fullName : 'Someone';

        // Title surfaces the business-vertical so a coach with multiple
        // landing pages can tell at a glance which one converted.
        $vertical = $enquiry->business_category ? " ({$enquiry->business_category})" : '';

        $this->title     = 'New lead' . $vertical . ' from your landing page';
        $this->body      = $fullName . ' is interested in "' . \Str::limit((string) ($enquiry->service ?? '—'), 50) . '"';
        $this->icon      = 'fa-bullseye';
        $this->iconColor = '#0d9488';
        $this->url       = route('instructor.landing-page-enquiry.show', $enquiry->id);
    }

    protected function placeholders(object $notifiable): array
    {
        return [
            'coach_name'    => $notifiable->name ?? '',
            'lead_name'     => trim(($this->enquiry->first_name ?? '') . ' ' . ($this->enquiry->last_name ?? '')),
            'lead_email'    => (string) ($this->enquiry->email ?? ''),
            'lead_phone'    => (string) ($this->enquiry->phone ?? ''),
            'service'       => (string) ($this->enquiry->service ?? ''),
            'message'       => (string) ($this->enquiry->message ?? ''),
            'vertical'      => (string) ($this->enquiry->business_category ?? ''),
            'crm_url'       => $this->url,
        ];
    }
}
