<?php

namespace App\Notifications;

use App\Models\User;

/**
 * Feature 2 (2026-06-26) — notifies a coach when a new student registers
 * through their white-label custom website. Coach-facing, so the email is
 * branded as the recipient coach themselves (recipientCoachId) and routed
 * through their tenant SMTP with platform fallback. Dispatched non-blocking
 * so a mail failure never affects the student's registration response.
 */
class NewStudentRegisteredToCoach extends InAppNotification
{
    protected string $event = 'new_student_registered';
    protected string $emailTemplate = 'notif_new_student_registered';
    protected string $emailCtaLabel = 'View my students';

    private User $student;
    private string $orgName;
    private string $registeredAt;

    /** Coach-facing: brand the email as the coach who owns the recipient. */
    protected function brandCoachId(object $notifiable): ?int
    {
        return $this->recipientCoachId($notifiable);
    }

    public function __construct(User $student, string $orgName, string $registeredAt)
    {
        $this->student      = $student;
        $this->orgName      = $orgName;
        $this->registeredAt = $registeredAt;
        $this->title        = 'New student registered';
        $this->body         = $student->name . ' just registered on your website.';
        $this->icon         = 'fa-user-plus';
        $this->iconColor    = '';  // use the coach brand accent
        $this->url          = route('instructor.my-students.index');
    }

    protected function placeholders(object $notifiable): array
    {
        return [
            'user_name'         => $notifiable->name ?? '',
            'coach_name'        => $notifiable->name ?? '',
            'student_name'      => (string) $this->student->name,
            'student_email'     => (string) $this->student->email,
            'registered_at'     => $this->registeredAt,
            'organization_name' => $this->orgName,
            'students_url'      => $this->url,
        ];
    }
}
