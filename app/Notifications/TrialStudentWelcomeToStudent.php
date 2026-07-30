<?php

namespace App\Notifications;

use App\Models\CoachDomain;
use App\Models\CoachTrialEnquiry;
use App\Models\User;

/**
 * Welcome email for a student account auto-created after a successful trial
 * payment (2026-07-03). Carries the ONE-TIME temporary password + trial/payment
 * details. Coach-branded (white-label) with a coach-domain login link. Only ever
 * sent for a NEWLY created account — existing students get the plain booking
 * confirmation instead (no credentials).
 */
class TrialStudentWelcomeToStudent extends InAppNotification
{
    protected string $event         = 'trial_student_welcome';
    protected string $emailTemplate = 'notif_trial_student_welcome';
    protected string $emailCtaLabel = 'Log in to my account';

    private string $coachName;
    private string $orgName;
    private string $loginUrl;
    private string $tempPassword;
    private string $timeSlot;
    private string $amount;
    private string $paymentStatus;
    private string $supportEmail;

    public function __construct(
        User $coach,
        string $orgName,
        string $loginUrl,
        string $tempPassword,
        array $trial = []
    ) {
        $this->coachId       = (int) $coach->id ?: null;
        $this->coachName     = (string) ($coach->name ?? '');
        $this->orgName       = $orgName ?: (string) ($coach->name ?? config('app.name'));
        $this->loginUrl      = $loginUrl;
        $this->tempPassword  = $tempPassword;
        $this->timeSlot      = (string) ($trial['time_slot'] ?? '');
        $this->amount        = (string) ($trial['amount'] ?? '');
        $this->paymentStatus = (string) ($trial['payment_status'] ?? '');
        $this->supportEmail  = (string) ($trial['support_email'] ?? '');

        $this->title     = __('Welcome to :org', ['org' => $this->orgName]);
        $this->body      = __('Your student account is ready. Your trial session is confirmed — log in to get started.');
        $this->icon      = 'fa-circle-check';
        $this->iconColor = '#16a34a';
        $this->url       = $loginUrl;
    }

    protected function placeholders(object $notifiable): array
    {
        return [
            'user_name'         => $notifiable->name ?? '',
            'student_name'      => $notifiable->name ?? '',
            'coach_name'        => $this->coachName,
            'organization_name' => $this->orgName,
            'login_url'         => $this->loginUrl,
            'student_email'     => $notifiable->email ?? '',
            'temp_password'     => $this->tempPassword,
            'time_slot'         => $this->timeSlot,
            'amount'            => $this->amount,
            'payment_status'    => $this->paymentStatus,
            'support_email'     => $this->supportEmail,
        ];
    }

    /**
     * Build from a trial enquiry (resolves coach + coach-domain login URL).
     * $plainPassword is used once here and never persisted.
     */
    public static function fromEnquiry(CoachTrialEnquiry $enquiry, User $coach, string $orgName, string $plainPassword): self
    {
        $host = CoachDomain::primaryHostFor((int) $coach->id);
        $base = $host ? ('https://' . $host) : rtrim((string) config('app.url'), '/');
        $loginUrl = $base . '/login';

        return new self($coach, $orgName, $loginUrl, $plainPassword, [
            'time_slot'      => (string) ($enquiry->time_slot ?? ''),
            'amount'         => (float) $enquiry->price > 0 ? formatMoney((float) $enquiry->price, $enquiry->currency) : (string) __('Free'), // Phase 4.3: consistent ₹500.00
            'payment_status' => ucfirst((string) $enquiry->payment_status),
            'support_email'  => (string) ($coach->email ?? ''),
        ]);
    }
}
