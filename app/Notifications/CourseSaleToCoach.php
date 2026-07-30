<?php

namespace App\Notifications;

use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\Order\app\Models\Order;
use Modules\Order\app\Models\OrderItem;

/**
 * Coach Course-Sale notification (doc item 5, 2026-06-30).
 *
 * Sent to the COACH who owns a course whenever a student successfully buys it —
 * fired from PaymentFulfilmentService::markPaid() AFTER the order is paid, the
 * status is completed and the enrollment is created (so it never fires on a
 * pending/failed/rolled-back order). Per-item dispatch keeps it tenant-safe: a
 * coach only ever receives sales for THEIR OWN course, never another coach's.
 *
 * Delivery rides the shared InAppNotification pipeline → coach-branded email via
 * the coach's verified SMTP (platform mailer fallback), bell entry, real-time
 * toast, notification-email logging, and a coach-editable template
 * (notif_course_sale_to_coach, falls back to the title/body if the row is
 * missing). brandCoachId() resolves the brand to the course owner.
 */
class CourseSaleToCoach extends InAppNotification
{
    protected string $event = 'course_sale_to_coach';
    protected string $emailTemplate = 'notif_course_sale_to_coach';
    protected string $emailCtaLabel = 'View order';

    private string $studentName;
    private string $courseTitle;
    private string $orderId;
    private string $purchasedAt;
    private string $amount;
    private string $paymentStatus;
    private string $orderUrl;
    private string $studentUrl;
    private string $coachName;

    public function __construct(Order $order, OrderItem $item, Course $course, ?User $student)
    {
        // Brand + recipient context resolve to the course OWNER (the coach).
        $this->coachId = ((int) ($course->instructor_id ?? 0)) ?: null;

        $this->studentName = (string) ($student?->name ?: __('A student'));
        $this->courseTitle = (string) ($course->title ?? '');
        $this->orderId     = (string) ($order->invoice_id ?: $order->id);

        try {
            $this->purchasedAt = Carbon::parse($order->updated_at ?? now())->format('d M Y, h:i A');
        } catch (\Throwable $e) {
            $this->purchasedAt = now()->format('d M Y, h:i A');
        }

        // Amount for THIS course (the line price), shown with the platform symbol
        // and the order's actual currency code so it's unambiguous in any context
        // (webhooks/cron have no session). No hardcoded currency.
        $icon = (string) (\Illuminate\Support\Facades\Cache::get('setting')?->currency_icon ?? '');
        $value = number_format((float) ($item->price ?? $order->paid_amount ?? 0), 2);
        $code  = (string) ($order->payable_currency ?? '');
        $this->amount = trim($icon . $value . ($code ? ' ' . $code : ''));

        $this->paymentStatus = ucfirst((string) ($order->payment_status ?: 'paid'));
        $this->coachName     = $this->coachId
            ? (string) (User::where('id', $this->coachId)->value('name') ?: '')
            : '';

        // Coach-panel deep links (fall back to home if a route is unavailable).
        try { $this->orderUrl   = route('instructor.my-sells.index'); }    catch (\Throwable $e) { $this->orderUrl = url('/'); }
        try { $this->studentUrl = route('instructor.my-students.index'); } catch (\Throwable $e) { $this->studentUrl = url('/'); }

        // Bell + fallback email content.
        $this->title     = __('New sale: :course', ['course' => Str::limit($this->courseTitle, 50)]);
        $this->body      = __(':student purchased ":course" — :amount.', [
            'student' => $this->studentName,
            'course'  => Str::limit($this->courseTitle, 50),
            'amount'  => $this->amount,
        ]);
        $this->icon      = 'fa-cart-shopping';
        $this->iconColor = '#16a34a';
        $this->url       = $this->orderUrl;
    }

    protected function placeholders(object $notifiable): array
    {
        return [
            'coach_name'     => $this->coachName ?: ($notifiable->name ?? ''),
            'student_name'   => $this->studentName,
            'course_title'   => $this->courseTitle,
            'order_id'       => $this->orderId,
            'purchased_at'   => $this->purchasedAt,
            'amount'         => $this->amount,
            'payment_status' => $this->paymentStatus,
            'order_url'      => $this->orderUrl,
            'student_url'    => $this->studentUrl,
        ];
    }
}
