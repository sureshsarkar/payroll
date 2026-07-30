<?php

namespace App\Notifications;

use App\Models\CoachStaff;
use App\Models\User;
use App\Notifications\Concerns\BrandedNotificationMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to a newly-added coach staff member with their login email +
 * the temporary password the coach assigned at /instructor/coach-staff/create.
 *
 * Reported in bug-doc 2026-05-26 (C10): staff add saved the row but
 * never sent a welcome email, so the new staff had no idea their
 * account existed.
 */
class CoachStaffWelcomeToStaff extends Notification
{
    use Queueable;
    use BrandedNotificationMail;

    public function __construct(
        protected CoachStaff $staff,
        protected string $plaintextPassword,
        protected ?User $coach = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $coachName = $this->coach?->name ?: 'Your coach';
        $roleName  = (string) ($this->staff->role ?? 'staff');
        $coachId   = $this->coach?->id ?: ($notifiable->coach_id ?? null);

        $bodyHtml = '<p>' . e(__(':coach has added you as a member of their team.', ['coach' => $coachName])) . '</p>'
                  . '<p><strong>' . e(__('Your login email')) . ':</strong> ' . e($notifiable->email ?? '') . '<br>'
                  . '<strong>' . e(__('Temporary password')) . ':</strong> ' . e($this->plaintextPassword) . '</p>'
                  . '<p>' . e(__('Please change this password after your first login.')) . '</p>';

        return $this->buildBrandedMail(
            $notifiable,
            $coachId ? (int) $coachId : null,
            __(':coach added you as :role', ['coach' => $coachName, 'role' => $roleName]),
            [
                'title'         => __('You have been added to the team'),
                'bodyHtml'      => $bodyHtml,
                'url'           => url('/login'),
                'icon'          => 'fa-user-plus',
                'iconColor'     => '#3b82f6',
                'recipientName' => $notifiable->name ?? '',
                'ctaLabel'      => __('Log in'),
            ]
        );
    }
}
