<?php

namespace App\Notifications;

use App\Models\CoachPricingEnquiry;

/**
 * Confirmation sent to the visitor (a guest — NOT a registered User) the moment
 * they submit a "Book a Session" enquiry, carrying the current payment status.
 * Coach-branded, mail-ONLY via an on-demand notifiable.
 */
class BookingEnquiryToStudent extends InAppNotification
{
    protected string $emailTemplate = 'notif_booking_enquiry_to_student';
    protected string $emailCtaLabel = '';

    public function __construct(
        private array $data,
        ?int $coachId = null
    ) {
        $this->coachId = $coachId ?: null;
        $this->title   = 'Your booking request is received';
        $this->body    = 'Thank you for your booking.';
        $this->url     = null;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    protected function brandCoachId(object $notifiable): ?int
    {
        return $this->coachId;
    }

    protected function placeholders(object $notifiable): array
    {
        return array_merge([
            'user_name'    => $this->data['student_name'] ?? '',
            'student_name' => $this->data['student_name'] ?? '',
        ], $this->data);
    }

    public static function fromEnquiry(CoachPricingEnquiry $e, string $coachName): self
    {
        $d = (array) ($e->details ?? []);

        return new self([
            'student_name'   => $e->name ?? '',
            'coach_name'     => $coachName,
            'class_name'     => (string) ($d['class_name'] ?? $e->schedule_id ?? ''),
            'slot'           => (string) ($e->time_slot ?? ''),
            'trainer'        => (string) ($e->trainer_id ?? ''),
            'plan_type'      => (string) ($e->category ?? ''),
            'course_type'    => (string) ($e->course_type ?? ''),
            'time_period'    => (string) ($e->time_period ?? ''),
            'amount'         => ! is_null($e->plan_amount) ? formatMoney((float) $e->plan_amount, $e->currency ?: 'INR') : '—',
            'payment_status' => ucfirst((string) ($e->payment_status ?: 'unpaid')),
        ], (int) $e->coach_id);
    }
}
