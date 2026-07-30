<?php

namespace App\Notifications;

/**
 * Central registry of every notification email template + its placeholders.
 * Drives both the runtime substitution in InAppNotification::toMail() and the
 * admin "edit template" UI's variable-help table.
 */
class NotificationEmailTemplates
{
    /**
     * Returns: [
     *   'notif_course_approved' => [
     *     'label' => '...',
     *     'placeholders' => [
     *       'user_name' => 'Recipient (coach) name',
     *       ...
     *     ],
     *   ],
     *   ...
     * ]
     */
    public static function all(): array
    {
        return [
            // 2026-07-09 (audit Phase 6.6) — registry entries added so these
            // templates show placeholder help in the editor (they render fine
            // regardless; this drives the coach/admin editor UI only).
            'notif_payment_failed_student' => [
                'label' => 'Payment failed (to student)',
                'placeholders' => [
                    'user_name' => 'Student name',
                    'order_id'  => 'Order / invoice id',
                    'amount'    => 'Amount attempted',
                    'retry_url' => 'Link to retry the order',
                ],
            ],
            'notif_payment_failed_coach' => [
                'label' => 'Payment failed (to coach)',
                'placeholders' => [
                    'coach_name'   => 'Coach name',
                    'student_name' => 'Customer name',
                    'order_id'     => 'Order / invoice id',
                    'amount'       => 'Amount attempted',
                    'order_url'    => 'Coach orders page URL',
                ],
            ],
            'notif_course_sale_to_coach' => [
                'label' => 'Course sale (to coach)',
                'placeholders' => [
                    'coach_name'     => 'Coach name',
                    'student_name'   => 'Student who purchased',
                    'course_title'   => 'Course title',
                    'order_id'       => 'Order / invoice id',
                    'purchased_at'   => 'Purchase date & time',
                    'amount'         => 'Sale amount',
                    'payment_status' => 'Payment status',
                    'order_url'      => 'Coach orders URL',
                    'student_url'    => 'Coach students URL',
                ],
            ],
            'notif_live_class_started' => [
                'label' => 'Live class started (to student)',
                'placeholders' => [
                    'user_name'    => 'Student name',
                    'class_title'  => 'Live class title',
                    'course_title' => 'Course title',
                    'batch_name'   => 'Batch name',
                    'coach_name'   => 'Coach name',
                    'start_time'   => 'Start time',
                ],
            ],
            'notif_trial_booking_to_student' => [
                'label' => 'Trial booking confirmation (to student)',
                'placeholders' => [
                    'student_name'   => 'Student name',
                    'coach_name'     => 'Coach name',
                    'plan_type'      => 'Plan type',
                    'course_type'    => 'Course type',
                    'time_slot'      => 'Chosen time slot',
                    'amount'         => 'Amount',
                    'payment_status' => 'Payment status',
                ],
            ],
            'notif_trial_booking_to_coach' => [
                'label' => 'Trial booking (to coach)',
                'placeholders' => [
                    'student_name'   => 'Student name',
                    'student_email'  => 'Student email',
                    'student_mobile' => 'Student mobile',
                    'plan_type'      => 'Plan type',
                    'course_type'    => 'Course type',
                    'time_slot'      => 'Chosen time slot',
                    'reason'         => 'Reason / notes',
                    'amount'         => 'Amount',
                    'payment_status' => 'Payment status',
                ],
            ],
            'notif_trial_student_welcome' => [
                'label' => 'Trial student welcome + credentials',
                'placeholders' => [
                    'student_name'      => 'Student name',
                    'organization_name' => 'Coach / academy name',
                    'login_url'         => 'Login URL',
                    'student_email'     => 'Login email',
                    'temp_password'     => 'Temporary password',
                    'time_slot'         => 'Chosen time slot',
                    'amount'            => 'Amount',
                    'payment_status'    => 'Payment status',
                    'support_email'     => 'Coach support email',
                ],
            ],
            'notif_course_approved' => [
                'label' => 'Course approved',
                'placeholders' => [
                    'user_name'     => 'Coach name',
                    'course_title'  => 'Course title',
                    'dashboard_url' => 'Coach courses URL',
                ],
            ],
            'notif_course_rejected' => [
                'label' => 'Course rejected / sent back',
                'placeholders' => [
                    'user_name'     => 'Coach name',
                    'course_title'  => 'Course title',
                    'status'        => 'New approval status (rejected / pending)',
                    'dashboard_url' => 'Coach courses URL',
                ],
            ],
            'notif_new_enrollment' => [
                'label' => 'New enrollment (to coach)',
                'placeholders' => [
                    'coach_name'    => 'Coach name',
                    'student_name'  => 'Student who enrolled',
                    'course_title'  => 'Course they enrolled in',
                    'sales_url'     => 'Coach sales page URL',
                ],
            ],
            'notif_course_completed' => [
                'label' => 'Course completed (to student)',
                'placeholders' => [
                    'user_name'    => 'Student name',
                    'course_title' => 'Course title',
                    'courses_url'  => 'Enrolled courses page URL',
                ],
            ],
            'notif_quiz_result' => [
                'label' => 'Quiz result published',
                'placeholders' => [
                    'user_name'  => 'Student name',
                    'quiz_title' => 'Quiz title',
                    'score'      => 'Score the student earned',
                    'total'      => 'Total possible marks',
                    'status'     => 'Pass / Fail',
                    'result_url' => 'Quiz result page URL',
                ],
            ],
            'notif_live_class_scheduled' => [
                'label' => 'Live class scheduled',
                'placeholders' => [
                    'user_name'    => 'Student name',
                    'course_title' => 'Course title',
                    'batch_name'   => 'Batch name',
                    'lesson_title' => 'Live class title',
                    'start_time'   => 'Formatted start time',
                    'coach_name'   => 'Coach name',
                    'join_url'     => 'Live class join URL',
                ],
            ],
            'notif_live_class_starting_soon' => [
                'label' => 'Live class starting soon',
                'placeholders' => [
                    'user_name'    => 'Student name',
                    'class_title'  => 'Live class title',
                    'course_title' => 'Course title',
                    'batch_name'   => 'Batch name',
                    'coach_name'   => 'Coach / instructor name',
                    'starts_at'    => 'Class date & time',
                    'minutes'      => 'Minutes until start',
                    'join_url'     => 'Live class join URL (meeting link)',
                ],
            ],
            'notif_student_welcome' => [
                'label' => 'Student welcome (custom-website registration)',
                'placeholders' => [
                    'student_name'      => 'Student name',
                    'organization_name' => 'Coach / organization name',
                    'student_email'     => 'Registered email address',
                    'login_url'         => 'Coach-branded login URL',
                ],
            ],
            'notif_new_student_registered' => [
                'label' => 'New student registered (to coach)',
                'placeholders' => [
                    'coach_name'        => 'Coach name',
                    'student_name'      => 'Student who registered',
                    'student_email'     => 'Student email',
                    'registered_at'     => 'Registration date & time',
                    'organization_name' => 'Coach / organization name',
                    'students_url'      => 'Coach students dashboard URL',
                ],
            ],
            'notif_refund_approved' => [
                'label' => 'Refund approved (to student)',
                'placeholders' => [
                    'user_name'     => 'Student name',
                    'refund_amount' => 'Refunded amount with currency',
                    'order_id'      => 'Order invoice id',
                ],
            ],
            'notif_refund_rejected' => [
                'label' => 'Refund rejected (to student)',
                'placeholders' => [
                    'user_name'    => 'Student name',
                    'order_id'     => 'Order invoice id',
                    'reason'       => 'Admin-supplied subject line',
                    'description'  => 'Admin-supplied description',
                ],
            ],
            'notif_new_refund_request' => [
                'label' => 'New refund request (to admin)',
                'placeholders' => [
                    'student_name' => 'Student who submitted the request',
                    'order_id'     => 'Order invoice id',
                ],
            ],
            'notif_withdrawal_approved' => [
                'label' => 'Payout approved (to coach)',
                'placeholders' => [
                    'user_name' => 'Coach name',
                    'amount'    => 'Payout amount with currency',
                ],
            ],
            'notif_withdrawal_rejected' => [
                'label' => 'Payout rejected (to coach)',
                'placeholders' => [
                    'user_name' => 'Coach name',
                    'amount'    => 'Payout amount with currency',
                ],
            ],
            'notif_new_withdrawal_request' => [
                'label' => 'New payout request (to admin)',
                'placeholders' => [
                    'coach_name' => 'Coach who requested the payout',
                    'amount'     => 'Payout amount with currency',
                ],
            ],
            'notif_payment_due' => [
                'label' => 'Payment due reminder',
                'placeholders' => [
                    'user_name' => 'Student name',
                    'order_id'  => 'Order invoice id',
                    'days_open' => 'Days the order has been pending',
                ],
            ],
            'notif_referral_reward' => [
                'label' => 'Referral reward earned',
                'placeholders' => [
                    'user_name'      => 'Referrer name (recipient)',
                    'referred_name'  => 'Name of the user who activated their membership',
                    'reward_amount'  => 'Reward amount (currency-formatted)',
                ],
            ],
            'notif_membership_activated' => [
                'label' => 'Membership activated',
                'placeholders' => [
                    'user_name'    => 'Member name',
                    'plan_name'    => 'Plan they activated',
                    'expires_at'   => 'Expiry date or "Lifetime"',
                    'cash_paid'    => 'Cash portion paid (currency-formatted)',
                    'wallet_used'  => 'Wallet credit applied (currency-formatted)',
                ],
            ],
            'notif_trial_expiring' => [
                'label' => 'Trial expiring (to coach)',
                'placeholders' => [
                    'user_name'  => 'Coach name',
                    'days_left'  => 'Whole-number days remaining (0 = today)',
                    'expires_at' => 'Trial expiry date (formatted)',
                ],
            ],
            'notif_coach_trial_welcome' => [
                'label' => 'Coach trial welcome',
                'placeholders' => [
                    'user_name'  => 'Coach name',
                    'trial_days' => 'Length of the trial in days',
                    'expires_at' => 'Trial expiry date (formatted)',
                ],
            ],

            // 2026-06-26 — coach-branded templates that were missing a registry
            // entry (so they had no placeholder help + couldn't be coach-edited).
            'notif_batch_announcement' => [
                'label' => 'Batch announcement (to student)',
                'placeholders' => [
                    'user_name'        => 'Student name',
                    'course_title'     => 'Course title',
                    'batch_name'       => 'Batch name (blank if course-wide)',
                    'batch_start_time' => 'Batch start time',
                    'batch_end_time'   => 'Batch end time',
                    'coach_name'       => 'Coach / instructor name',
                    'title'            => 'Announcement title',
                    'message'          => 'Announcement text',
                ],
            ],
            'notif_fee_demand_published' => [
                'label' => 'Fee due (to student)',
                'placeholders' => [
                    'user_name'   => 'Student name',
                    'fee_title'   => 'Fee title',
                    'amount'      => 'Amount due (currency-formatted)',
                    'batch_title' => 'Batch name',
                    'due_date'    => 'Due date (or "No specific due date")',
                ],
            ],
            'notif_fee_payment_receipt' => [
                'label' => 'Fee payment receipt (to student)',
                'placeholders' => [
                    'user_name'  => 'Student name',
                    'amount'     => 'Amount paid (currency-formatted)',
                    'receipt_no' => 'Receipt number',
                    'fee_title'  => 'Fee title',
                    'paid_at'    => 'Paid-on date & time',
                    'gateway'    => 'Payment method',
                ],
            ],
            'notif_new_landing_lead' => [
                'label' => 'New website lead (to coach)',
                'placeholders' => [
                    'coach_name' => 'Coach name',
                    'lead_name'  => 'Lead full name',
                    'lead_email' => 'Lead email',
                    'lead_phone' => 'Lead phone',
                    'service'    => 'Service / interest',
                    'message'    => 'Lead message',
                    'vertical'   => 'Business category',
                ],
            ],
            'notif_low_attendance_alert' => [
                'label' => 'Low attendance alert (to coach)',
                'placeholders' => [
                    'user_name'      => 'Coach name',
                    'batch_title'    => 'Batch name',
                    'attended'       => 'Number of students who attended',
                    'total_students' => 'Total students in the batch',
                    'percent'        => 'Attendance percentage',
                ],
            ],
        ];
    }

    public static function placeholdersFor(string $name): array
    {
        return self::all()[$name]['placeholders'] ?? [];
    }

    /**
     * The subset of templates a COACH may customise for their own white-label
     * site. Only emails sent in a coach's tenant context are listed — platform/
     * admin-scoped templates (membership, trial, payouts, admin alerts) are
     * excluded because a coach override would never apply to them. Intersected
     * with all() so a key that ever gets removed can't break the editor.
     *
     * @return array<string, array{label:string, placeholders:array}>
     */
    public static function coachEditable(): array
    {
        $keys = [
            // Student-facing (coach-branded)
            'notif_student_welcome',
            'notif_course_completed',
            'notif_live_class_scheduled',
            'notif_live_class_starting_soon',
            'notif_batch_announcement',
            'notif_quiz_result',
            'notif_fee_demand_published',
            'notif_fee_payment_receipt',
            'notif_payment_due',
            // Coach-facing (coach-branded)
            'notif_new_student_registered',
            'notif_new_enrollment',
            'notif_new_landing_lead',
            'notif_low_attendance_alert',
            'notif_withdrawal_approved',
            'notif_withdrawal_rejected',
        ];
        $all = self::all();
        $out = [];
        foreach ($keys as $k) {
            if (isset($all[$k])) {
                $out[$k] = $all[$k];
            }
        }
        return $out;
    }
}
