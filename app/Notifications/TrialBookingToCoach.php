<?php

namespace App\Notifications;

use App\Models\CoachTrialEnquiry;

/**
 * Sent to the coach when a visitor books a trial session on their website.
 * Coach-facing → email (coach-branded) + bell + broadcast (the "system
 * notification"). Delivered through the standard InAppNotification pipeline.
 */
class TrialBookingToCoach extends InAppNotification
{
    protected string $event         = 'trial_booking_to_coach';
    protected string $emailTemplate = 'notif_trial_booking_to_coach';
    protected string $emailCtaLabel = 'View enquiry';

    public function __construct(
        private array $data,
        ?int $coachId = null
    ) {
        $this->coachId   = $coachId ?: null;
        $this->title     = 'New trial booking: ' . ($data['student_name'] ?? '');
        $this->body      = ($data['plan_type'] ?? '') . ' · ' . ($data['course_type'] ?? '') . ' · ' . ($data['payment_status'] ?? '');
        $this->icon      = 'fa-calendar-check';
        $this->iconColor = '#0ea5e9';
        try {
            $this->url = route('instructor.trial-sessions.enquiries.index');
        } catch (\Throwable $e) {
            $this->url = null;
        }
    }

    /** Coach-/staff-facing → brand as the recipient's coach. */
    protected function brandCoachId(object $notifiable): ?int
    {
        return $this->coachId ?: $this->recipientCoachId($notifiable);
    }

    protected function placeholders(object $notifiable): array
    {
        return array_merge([
            'coach_name'     => $notifiable->name ?? '',
            'enquiries_url'  => $this->url ?? '',
        ], $this->data);
    }

    public static function fromEnquiry(CoachTrialEnquiry $e): self
    {
        return new self([
            'student_name'   => $e->name,
            'student_email'  => $e->email,
            'student_mobile' => $e->mobile,
            'plan_type'      => ucfirst((string) $e->plan_type),
            'course_type'    => ucfirst((string) $e->course_type),
            'time_slot'      => (string) $e->time_slot,
            'reason'         => ucfirst((string) $e->reason),
            'amount'         => (float) $e->price > 0 ? formatMoney((float) $e->price, $e->currency) : 'Free', // Phase 4.3: consistent ₹500.00
            'payment_status' => ucfirst((string) $e->payment_status),
        ], (int) $e->coach_id);
    }
}
