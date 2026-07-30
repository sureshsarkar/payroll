<?php

namespace App\Notifications;

use App\Models\CoachPricingPayment;

/**
 * Paid receipt sent to the visitor (a guest — NOT a registered User) after their
 * Pricing & Plans booking payment succeeds. Reuses the coach-branded email
 * pipeline (per-coach template + SMTP + brand) but is delivered mail-ONLY via an
 * on-demand notifiable: Notification::route('mail', $email)->notify(...).
 *
 * Because the notifiable is anonymous, every recipient value comes from the
 * constructor, never from $notifiable.
 */
class PricingBookingReceiptToStudent extends InAppNotification
{
    protected string $emailTemplate = 'notif_pricing_booking_receipt_to_student';
    protected string $emailCtaLabel = '';

    public function __construct(
        private array $data,
        ?int $coachId = null
    ) {
        $this->coachId = $coachId ?: null;
        $this->title   = 'Your payment is confirmed';
        $this->body    = 'Thank you — your booking payment was received.';
        $this->url     = null;
    }

    /** Mail only — the guest has no in-app bell / broadcast channel. */
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
        // $notifiable is anonymous → source everything from constructor data.
        return array_merge([
            'user_name'    => $this->data['student_name'] ?? '',
            'student_name' => $this->data['student_name'] ?? '',
        ], $this->data);
    }

    public static function fromPayment(CoachPricingPayment $p, string $coachName): self
    {
        $e = $p->enquiry;

        return new self([
            'student_name'   => $e->name ?? '',
            'student_email'  => $e->email ?? '',
            'coach_name'     => $coachName,
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
