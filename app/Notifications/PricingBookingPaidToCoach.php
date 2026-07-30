<?php

namespace App\Notifications;

use App\Models\CoachPricingPayment;

/**
 * Sent to the coach when a Pricing & Plans booking is PAID (signature- or
 * webhook-verified). Coach-facing → coach-branded email + bell + broadcast,
 * through the standard InAppNotification pipeline (per-coach SMTP, brand, footer).
 */
class PricingBookingPaidToCoach extends InAppNotification
{
    protected string $event         = 'pricing_booking_paid_to_coach';
    protected string $emailTemplate = 'notif_pricing_booking_paid_to_coach';
    protected string $emailCtaLabel = 'View enquiry';

    public function __construct(
        private array $data,
        ?int $coachId = null
    ) {
        $this->coachId   = $coachId ?: null;
        $this->title     = 'Payment received: ' . ($data['student_name'] ?? '') . ' — ' . ($data['amount'] ?? '');
        $this->body      = trim(implode(' · ', array_filter([
            $data['category'] ?? null,
            $data['course_type'] ?? null,
            $data['time_period'] ?? null,
        ])));
        $this->icon      = 'fa-circle-check';
        $this->iconColor = '#16a34a';
        try {
            $this->url = route('instructor.pricing-enquiries.index');
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
            'coach_name'    => $notifiable->name ?? '',
            'enquiries_url' => $this->url ?? '',
        ], $this->data);
    }

    public static function fromPayment(CoachPricingPayment $p): self
    {
        $e = $p->enquiry;

        return new self([
            'student_name'   => $e->name ?? '',
            'student_email'  => $e->email ?? '',
            'student_mobile' => $e->mobile ?? '',
            'category'       => (string) ($e->category ?? ''),
            'course_type'    => ucfirst((string) ($e->course_type ?? '')),
            'time_period'    => (string) ($e->time_period ?? ''),
            'amount'         => formatMoney((float) $p->amount, $p->currency ?: 'INR'),
            'gateway'        => ucfirst((string) $p->gateway),
            'transaction_id' => (string) ($p->transaction_id ?? ''),
            'paid_at'        => optional($p->paid_at)->format('d M Y, h:i A') ?? '',
        ], (int) $p->coach_id);
    }
}
