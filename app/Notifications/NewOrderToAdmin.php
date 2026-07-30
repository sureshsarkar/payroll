<?php

namespace App\Notifications;

use Modules\Order\app\Models\Order;

/**
 * Sent to all admins when a new order is paid.
 */
class NewOrderToAdmin extends InAppNotification
{
    public function __construct(Order $order)
    {
        $buyerName = $order->user?->name ?? 'A customer';
        $amount = number_format((float) $order->paid_amount, 2);
        $currency = $order->payable_currency ?? 'INR';

        $this->title = '💰 New paid order: #' . $order->invoice_id;
        $this->body = $buyerName . ' paid ' . $currency . ' ' . $amount;
        $this->url = route('admin.order', ['id' => $order->id]);
        $this->icon = 'fa-cart-shopping';
        $this->iconColor = '#10b981';
    }
}
