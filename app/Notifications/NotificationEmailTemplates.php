<?php

namespace App\Notifications;

/**
 * Central registry of every notification email template + its placeholders.
 * Drives both the runtime substitution in InAppNotification::toMail() and the
 * admin "edit template" UI's variable-help table.
 *
 * LMS removal phase 2 (2026-08-31) — emptied. All 32 registered templates
 * (course-approved/rejected, quiz-result, live-class-scheduled/starting-soon,
 * enrollment, trial-booking/welcome, referral-reward, membership-activated,
 * withdrawal-approved/rejected, fee-demand/payment, landing-lead,
 * low-attendance-alert, batch-announcement, ...) belonged to notification
 * classes deleted along with the LMS. `Modules/GlobalSetting`'s email-config
 * editor UI still calls all()/coachEditable() to render the variable-help
 * table, so the methods stay — they just have nothing to register yet.
 */
class NotificationEmailTemplates
{
    /**
     * Returns: [
     *   'notif_key' => [
     *     'label' => '...',
     *     'placeholders' => ['var_name' => 'description', ...],
     *   ],
     *   ...
     * ]
     */
    public static function all(): array
    {
        return [];
    }

    public static function placeholdersFor(string $name): array
    {
        return self::all()[$name]['placeholders'] ?? [];
    }

    /**
     * The subset of templates a tenant (formerly: coach) may customise for
     * their own branded emails. Empty until an HR-side template needs a
     * per-company override.
     *
     * @return array<string, array{label:string, placeholders:array}>
     */
    public static function coachEditable(): array
    {
        return [];
    }
}
