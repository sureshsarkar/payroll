<?php

namespace App\Notifications;

use App\Models\LandingPageEnquiry;

/**
 * Sent to the staff member (or coach) a lead is assigned to, so they know to
 * follow up. White-label: branded as the COACH who owns the lead
 * ($enquiry->coach_id), never the platform — a coach's staff must see the
 * coach's brand, not MBSGuru.
 */
class LeadAssignedToStaff extends InAppNotification
{
    protected string $event = 'lead_assigned';

    private LandingPageEnquiry $enquiry;

    public function __construct(LandingPageEnquiry $enquiry)
    {
        $this->enquiry = $enquiry;
        // Brand the email as the coach that owns this lead (tenant-safe).
        $this->coachId = $enquiry->coach_id ? (int) $enquiry->coach_id : null;

        $leadName = trim(($enquiry->first_name ?? '') . ' ' . ($enquiry->last_name ?? ''));
        $leadName = $leadName !== '' ? $leadName : 'A new lead';
        $service  = trim((string) ($enquiry->service ?? ''));

        $this->title     = 'New lead assigned to you';
        $this->body      = $leadName . ($service !== '' ? ' — ' . \Str::limit($service, 60) : '')
                          . '. Open the lead to follow up.';
        $this->icon      = 'fa-user-plus';
        $this->iconColor = '#0d9488';

        try {
            $this->url = route('instructor.landing-page-enquiry.show', $enquiry->id);
        } catch (\Throwable $e) {
            $this->url = null;
        }
    }
}
