<?php

namespace App\Notifications;

use App\Models\LandingPageEnquiry;

/**
 * Reminder to the staff member (or coach) responsible for a lead when its
 * scheduled follow-up time arrives. White-label: branded as the COACH who
 * owns the lead ($enquiry->coach_id), never the platform.
 */
class LeadFollowUpReminderToStaff extends InAppNotification
{
    protected string $event = 'lead_follow_up';

    private LandingPageEnquiry $enquiry;

    public function __construct(LandingPageEnquiry $enquiry)
    {
        $this->enquiry = $enquiry;
        $this->coachId = $enquiry->coach_id ? (int) $enquiry->coach_id : null;

        $leadName = trim(($enquiry->first_name ?? '') . ' ' . ($enquiry->last_name ?? ''));
        $leadName = $leadName !== '' ? $leadName : 'a lead';
        $service  = trim((string) ($enquiry->service ?? ''));

        $this->title     = 'Follow-up due: ' . $leadName;
        $this->body      = 'It\'s time to follow up with ' . $leadName
                          . ($service !== '' ? ' about ' . \Str::limit($service, 50) : '')
                          . '. Open the lead to log your call.';
        $this->icon      = 'fa-bell';
        $this->iconColor = '#0ea5e9';

        try {
            $this->url = route('instructor.landing-page-enquiry.show', $enquiry->id);
        } catch (\Throwable $e) {
            $this->url = null;
        }
    }
}
