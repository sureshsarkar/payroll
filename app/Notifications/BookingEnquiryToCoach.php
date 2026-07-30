<?php

namespace App\Notifications;

use App\Models\CoachPricingEnquiry;

/**
 * Sent to the coach the moment a Classes & Schedules "Book a Session" enquiry is
 * submitted — carrying the current payment status (unpaid / pending / paid).
 * Coach-facing → coach-branded email + bell + broadcast (InAppNotification).
 */
class BookingEnquiryToCoach extends InAppNotification
{
    protected string $event         = 'booking_enquiry_to_coach';
    protected string $emailTemplate = 'notif_booking_enquiry_to_coach';
    protected string $emailCtaLabel = 'View enquiry';

    public function __construct(
        private array $data,
        ?int $coachId = null
    ) {
        $this->coachId   = $coachId ?: null;
        $this->title     = 'New booking: ' . ($data['student_name'] ?? '');
        $this->body      = trim(implode(' · ', array_filter([
            $data['class_name'] ?? null, $data['slot'] ?? null, $data['payment_status'] ?? null,
        ])));
        $this->icon      = 'fa-calendar-check';
        $this->iconColor = '#f97316';
        try {
            $this->url = route('instructor.pricing-enquiries.index');
        } catch (\Throwable $e) {
            $this->url = null;
        }
    }

    protected function brandCoachId(object $notifiable): ?int
    {
        return $this->coachId ?: $this->recipientCoachId($notifiable);
    }

    protected function placeholders(object $notifiable): array
    {
        return array_merge(['coach_name' => $notifiable->name ?? '', 'enquiries_url' => $this->url ?? ''], $this->data);
    }

    public static function fromEnquiry(CoachPricingEnquiry $e): self
    {
        $d = (array) ($e->details ?? []);

        return new self([
            'student_name'   => $e->name ?? '',
            'student_email'  => $e->email ?? '',
            'student_mobile' => $e->mobile ?? '',
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
