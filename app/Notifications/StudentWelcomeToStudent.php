<?php

namespace App\Notifications;

use App\Models\User;

/**
 * Feature 1 (2026-06-26) — welcome email sent to a student right after they
 * register through a coach's white-label custom website. Coach-branded: the
 * email uses the coach's tenant SMTP + branding (BrandedNotificationMail),
 * falling back to the platform default config when the coach has none. The
 * dispatch site wraps this in try/catch so a mail failure never breaks the
 * registration flow.
 */
class StudentWelcomeToStudent extends InAppNotification
{
    protected string $event = 'student_welcome';
    protected string $emailTemplate = 'notif_student_welcome';
    protected string $emailCtaLabel = 'Go to my account';

    private string $orgName;
    private string $loginUrl;

    /** Brand the email as the coach the student registered under. */
    public function __construct(User $coach, string $orgName, string $loginUrl)
    {
        $this->coachId  = (int) $coach->id ?: null;
        $this->orgName  = $orgName;
        $this->loginUrl = $loginUrl;
        $this->title    = 'Welcome to ' . $orgName;
        $this->body     = 'Your student account is ready. Log in to access your courses.';
        $this->icon     = 'fa-circle-check';
        $this->iconColor = '#16a34a';
        $this->url      = $loginUrl;
    }

    protected function placeholders(object $notifiable): array
    {
        return [
            'user_name'         => $notifiable->name ?? '',
            'student_name'      => $notifiable->name ?? '',
            'organization_name' => $this->orgName,
            'login_url'         => $this->loginUrl,
            'student_email'     => $notifiable->email ?? '',
        ];
    }
}
