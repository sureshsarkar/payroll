<?php

namespace App\Notifications;

use Modules\Order\app\Models\Order;

/**
 * Sent by the schedule:notify:payment-due command when an order is still
 * pending payment after a configurable grace period.
 */
class PaymentDueReminderToStudent extends InAppNotification
{
    protected string $event = 'payment_due_reminder';
    protected string $emailTemplate = 'notif_payment_due';
    protected string $emailCtaLabel = 'Complete payment';

    private Order $order;
    private int $daysOpen;

    public function __construct(Order $order, int $daysOpen)
    {
        $this->order = $order;
        $this->coachId = $order->primary_coach_id ? (int) $order->primary_coach_id : null;
        $this->daysOpen = $daysOpen;
        $this->title = 'Payment pending — order #' . $order->invoice_id;
        $this->body = 'Your order has been waiting for payment for ' . $daysOpen . ' day' . ($daysOpen === 1 ? '' : 's')
            . '. Complete payment to activate your enrollment.';
        $this->icon = 'fa-hourglass-half';
        $this->iconColor = '#f59e0b';
        $this->url = route('student.order.show', ['id' => $order->id]);
    }

    protected function placeholders(object $notifiable): array
    {
        return [
            'user_name' => $notifiable->name ?? '',
            'order_id'  => (string) $this->order->invoice_id,
            'days_open' => (string) $this->daysOpen,
        ];
    }
}
