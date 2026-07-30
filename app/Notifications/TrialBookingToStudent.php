<?php

namespace App\Notifications;

use App\Models\CoachTrialEnquiry;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Confirmation email sent to the visitor (a guest — NOT a registered User) after
 * they book a trial session. Reuses the coach-branded email pipeline
 * (per-coach template + SMTP + brand) but is delivered mail-ONLY via an
 * on-demand notifiable: Notification::route('mail', $email)->notify(...).
 *
 * Because the notifiable is anonymous, all recipient-specific values come from
 * the constructor, never from $notifiable.
 */
class TrialBookingToStudent extends InAppNotification
{
    protected string $emailTemplate = 'notif_trial_booking_to_student';
    protected string $emailCtaLabel = '';

    public function __construct(
        private array $data,
        ?int $coachId = null
    ) {
        $this->coachId = $coachId ?: null;
        $this->title   = 'Your trial session is booked';
        $this->body    = 'Thank you for booking a trial session.';
        $this->url     = null;
    }

    /** Mail only — the visitor has no in-app bell / broadcast channel. */
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

    public static function fromEnquiry(CoachTrialEnquiry $e, string $coachName): self
    {
        return new self([
            'student_name'   => $e->name,
            'student_email'  => $e->email,
            'coach_name'     => $coachName,
            'plan_type'      => ucfirst((string) $e->plan_type),
            'course_type'    => ucfirst((string) $e->course_type),
            'time_slot'      => (string) $e->time_slot,
            'amount'         => (float) $e->price > 0 ? formatMoney((float) $e->price, $e->currency) : 'Free', // Phase 4.3: consistent ₹500.00
            'payment_status' => ucfirst((string) $e->payment_status),
        ], (int) $e->coach_id);
    }
}
