<?php

namespace App\Notifications;

use Modules\Order\app\Models\Order;

/**
 * Sent to a buyer when their order's payment_status or status changes
 * (e.g. admin marks it paid, refunded, or canceled).
 */
class OrderStatusChangedToStudent extends InAppNotification
{
    protected string $event = 'order_status_changed';

    public function __construct(Order $order, string $previousStatus = '', string $previousPaymentStatus = '')
    {
        $invoice = (string) $order->invoice_id;
        $this->coachId = $order->primary_coach_id ? (int) $order->primary_coach_id : null;

        if ($order->payment_status === 'paid' && $order->status === 'completed') {
            $this->title = 'Payment confirmed for #' . $invoice;
            $this->body = 'Your enrollment is now active. Start learning anytime.';
            $this->icon = 'fa-circle-check';
            $this->iconColor = '#10b981';
        } elseif ($order->payment_status === 'refunded') {
            $this->title = 'Order #' . $invoice . ' refunded';
            $this->body = 'Your refund has been processed.';
            $this->icon = 'fa-rotate-left';
            $this->iconColor = '#f59e0b';
        } elseif (in_array($order->status, ['cancelled', 'failed']) || $order->payment_status === 'failed') {
            $this->title = 'Order #' . $invoice . ' ' . $order->status;
            $this->body = 'There was an issue with this order.';
            $this->icon = 'fa-circle-xmark';
            $this->iconColor = '#ef4444';
        } else {
            $this->title = 'Order #' . $invoice . ' updated';
            // 2026-07-09 fix (Phase 1.5): don't leak raw internal enum values
            // (e.g. "Status: processing / pending") to the student.
            $this->body = 'Your order has been updated. Open it to see the latest details.';
            $this->icon = 'fa-receipt';
            $this->iconColor = '';  // generic update → use the coach brand accent
        }

        $this->url = route('student.order.show', ['id' => $order->id]);
    }
}
