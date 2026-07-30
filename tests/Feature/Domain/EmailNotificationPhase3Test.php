<?php

namespace Tests\Feature\Domain;

use App\Models\User;
use App\Notifications\PaymentFailedToCoach;
use App\Notifications\PaymentFailedToStudent;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\Order\app\Models\Order;
use Tests\TestCase;

/**
 * 2026-07-09 — Email/Notification audit, Phase 3 (Missing Communications).
 * Covers the new payment-failure notifications (3.1) — previously a failed
 * payment notified NO ONE.
 */
class EmailNotificationPhase3Test extends TestCase
{
    use DatabaseTransactions;

    private function seedInr(): void
    {
        DB::table('multi_currencies')->updateOrInsert(['currency_code' => 'INR'],
            ['currency_icon' => '₹', 'currency_position' => 'before_price', 'currency_rate' => 1, 'is_default' => 'yes']);
        Cache::forget('allCurrencies');
    }

    private function order(User $buyer, User $coach): Order
    {
        return (new Order())->forceFill([
            'id' => 777, 'invoice_id' => 'INV-9', 'buyer_id' => $buyer->id,
            'primary_coach_id' => $coach->id, 'paid_amount' => 500, 'payable_currency' => 'INR',
            'payment_status' => 'pending',
        ]);
    }

    public function test_payment_failed_student_notification_is_coach_branded_and_informative(): void
    {
        $this->seedInr();
        $coach = User::factory()->create(['role' => 'instructor', 'coach_id' => null]);
        $buyer = User::factory()->create(['role' => 'student', 'coach_id' => $coach->id, 'name' => 'Asha']);

        $n = new PaymentFailedToStudent($this->order($buyer, $coach), 500, 'INR');

        $row = $n->toDatabase($buyer);
        $this->assertStringContainsString('#INV-9', $row['body']);
        $this->assertStringContainsString('/order-details/777', $row['url']);

        $ref = new \ReflectionMethod($n, 'placeholders');
        $ref->setAccessible(true);
        $ph = $ref->invoke($n, $buyer);
        $this->assertSame('₹500.00', $ph['amount'], 'amount formatted session-independently');
        $this->assertSame('INV-9', $ph['order_id']);

        // Branded as the OWNING coach (student sees the coach's brand).
        $bref = new \ReflectionMethod($n, 'brandCoachId');
        $bref->setAccessible(true);
        $this->assertSame($coach->id, $bref->invoke($n, $buyer));
    }

    public function test_payment_failed_coach_notification_names_customer_and_amount(): void
    {
        $this->seedInr();
        $coach = User::factory()->create(['role' => 'instructor', 'coach_id' => null, 'name' => 'CoachX']);
        $buyer = User::factory()->create(['role' => 'student', 'coach_id' => $coach->id, 'name' => 'Bob Buyer']);

        $n = new PaymentFailedToCoach($this->order($buyer, $coach), 500, 'INR', $buyer);
        $row = $n->toDatabase($coach);
        $this->assertStringContainsString('#INV-9', $row['body']);
        $this->assertStringContainsString('Bob Buyer', $row['body']);
        $this->assertStringContainsString('₹500.00', $row['body']);
    }

    public function test_payment_failure_dedup_guard_fires_once_per_order(): void
    {
        Cache::flush();
        $this->assertTrue(PaymentFailedToStudent::shouldNotify(4242), 'first failure notifies');
        $this->assertFalse(PaymentFailedToStudent::shouldNotify(4242), 'subsequent retries deduped');
        $this->assertTrue(PaymentFailedToStudent::shouldNotify(9999), 'a different order still notifies');
    }

    public function test_both_parties_are_notified_on_payment_failure(): void
    {
        Notification::fake();
        $this->seedInr();
        $coach = User::factory()->create(['role' => 'instructor', 'coach_id' => null]);
        $buyer = User::factory()->create(['role' => 'student', 'coach_id' => $coach->id]);
        $order = $this->order($buyer, $coach);

        // Mirror the webhook dispatch.
        $buyer->notify(new PaymentFailedToStudent($order, 500, 'INR'));
        $coach->notify(new PaymentFailedToCoach($order, 500, 'INR', $buyer));

        Notification::assertSentTo($buyer, PaymentFailedToStudent::class);
        Notification::assertSentTo($coach, PaymentFailedToCoach::class);
        // The coach does NOT receive the student's notification and vice-versa.
        Notification::assertNotSentTo($coach, PaymentFailedToStudent::class);
        Notification::assertNotSentTo($buyer, PaymentFailedToCoach::class);
    }
}
