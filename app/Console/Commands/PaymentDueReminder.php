<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\PaymentDueReminderToStudent;
use Illuminate\Console\Command;
use Modules\Order\app\Models\Order;

/**
 * Daily reminder to students who placed an order but haven't paid yet.
 *
 * Pings on day 1, 3, and 7 after order creation, then stops.
 * Idempotent within the same day via a per-(order, day) cache flag, so this
 * is safe to re-run.
 */
class PaymentDueReminder extends Command
{
    protected $signature = 'notify:payment-due';

    protected $description = 'Remind students with pending unpaid orders to complete payment.';

    public function handle(): int
    {
        $reminderDays = [1, 3, 7];

        // Pull orders that are still unpaid AND placed within the last 7 days.
        $pending = Order::where('payment_status', 'pending')
            ->where('status', 'pending')
            ->where('created_at', '>=', now()->subDays(8))
            ->get();

        $sent = 0;
        foreach ($pending as $order) {
            $daysOpen = (int) $order->created_at->diffInDays(now());
            if (!in_array($daysOpen, $reminderDays, true)) continue;

            $flag = 'payment_due_reminder_' . $order->id . '_d' . $daysOpen;
            if (\Cache::has($flag)) continue;
            \Cache::put($flag, 1, now()->addDays(2));

            $student = User::find($order->buyer_id);
            if (!$student) continue;

            try {
                $student->notify(new PaymentDueReminderToStudent($order, $daysOpen));
                $sent++;
            } catch (\Throwable $e) {
                \Log::warning('Payment due reminder failed for order ' . $order->id . ': ' . $e->getMessage());
            }
        }

        $this->info("Payment-due reminders sent: {$sent}");
        return self::SUCCESS;
    }
}
