<?php

namespace App\Notifications;

use App\Models\Course;
use Modules\Order\app\Models\OrderItem;

/**
 * Sent to a coach when one of their courses is sold (order moves to paid + completed).
 */
class CourseSoldToCoach extends InAppNotification
{
    protected string $event = 'course_sold';

    /** Coach-facing: brand the email as the coach who owns the recipient. */
    protected function brandCoachId(object $notifiable): ?int
    {
        return $this->recipientCoachId($notifiable);
    }

    public function __construct(OrderItem $item, Course $course)
    {
        $buyerName = (string) ($item->order->user->name ?? 'A student');
        $price = number_format((float) $item->price, 2);
        $currency = (string) ($item->order->payable_currency ?? 'INR');

        $this->title = '🎉 ' . $course->title . ' just sold!';
        $this->body = $buyerName . ' enrolled — ' . $currency . ' ' . $price;
        $this->url = route('instructor.my-sells.show', ['id' => $item->id]);
        $this->icon = 'fa-cart-shopping';
        $this->iconColor = '#10b981';
    }
}
