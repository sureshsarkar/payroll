<?php

namespace Tests\Feature\Audit;

use App\Models\Course;
use App\Models\User;
use App\Services\PaymentFulfilmentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\Order\app\Models\Order;
use Modules\Order\app\Models\OrderItem;
use Tests\TestCase;

/**
 * Audit finding H9 — per-item / per-coach commission.
 *
 * Before: PaymentFulfilmentService::markPaid() computed the payout from the
 * WHOLE order's paid_amount ONCE and credited that full amount to EACH item's
 * instructor — so a 2-coach order credited every coach the entire order total
 * (N items => N× over-credit). The refund paths already debited per-item, so
 * charge and refund were asymmetric.
 *
 * After: every wallet credit/debit goes through OrderItem::coachPayout(),
 * which is per-item and reads the item's captured (per-coach) commission_rate,
 * falling back to the order rate. Charge and refund therefore read the SAME
 * value and are always symmetric.
 */
class CommissionPayoutTest extends TestCase
{
    use DatabaseTransactions;

    private function makeCoach(): User
    {
        return User::factory()->create(['role' => 'instructor', 'coach_id' => null, 'wallet_balance' => 0]);
    }

    private function makeCourse(int $coachId, float $price): int
    {
        return DB::table('courses')->insertGetId([
            'title' => 'Comm course ' . uniqid(), 'slug' => 'comm-' . uniqid(),
            'instructor_id' => $coachId, 'added_by' => $coachId,
            'is_approved' => 'approved', 'status' => 'active',
            'type' => 'course', 'price' => $price, 'discount' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /* ---------- helper unit ---------- */

    public function test_coach_payout_helper_uses_item_rate_then_falls_back(): void
    {
        $item = new OrderItem(['price' => 100, 'commission_rate' => 10]);
        $this->assertEqualsWithDelta(90.0, $item->coachPayout(99), 0.001,
            'item rate (10%) must win over the order rate');

        $legacy = new OrderItem(['price' => 100, 'commission_rate' => null]);
        $this->assertEqualsWithDelta(75.0, $legacy->coachPayout(25), 0.001,
            'a null item rate must fall back to the order rate (25%)');

        // Clamp out-of-range rates.
        $weird = new OrderItem(['price' => 100, 'commission_rate' => 250]);
        $this->assertEqualsWithDelta(0.0, $weird->coachPayout(0), 0.001, 'rate clamps to <= 100%');
    }

    /* ---------- the headline fix: markPaid is per-item ---------- */

    public function test_markpaid_credits_each_coach_their_own_item_not_the_order_total(): void
    {
        $coachA = $this->makeCoach();
        $coachB = $this->makeCoach();
        $courseA = $this->makeCourse($coachA->id, 100);
        $courseB = $this->makeCourse($coachB->id, 100);
        $student = User::factory()->create(['role' => 'student']);

        // 2-coach order, total 200, 10% commission, captured per item.
        $order = Order::create([
            'invoice_id' => 'H9-' . uniqid(), 'transaction_id' => 'H9TX-' . uniqid(),
            'buyer_id' => $student->id, 'status' => 'pending', 'payment_status' => 'pending',
            'payable_amount' => 200, 'paid_amount' => 200, 'commission_rate' => 10,
            'payment_method' => 'test', 'order_type' => 'course',
        ]);
        OrderItem::create(['order_id' => $order->id, 'course_id' => $courseA, 'price' => 100, 'commission_rate' => 10]);
        OrderItem::create(['order_id' => $order->id, 'course_id' => $courseB, 'price' => 100, 'commission_rate' => 10]);

        app(PaymentFulfilmentService::class)->markPaid($order, $order->transaction_id, 'test');

        // Each coach gets 100 - 10% = 90 (NOT the whole-order 200*0.9 = 180).
        $this->assertEqualsWithDelta(90.0, (float) $coachA->fresh()->wallet_balance, 0.001,
            'coach A must be credited only their own item payout (90), not the order total');
        $this->assertEqualsWithDelta(90.0, (float) $coachB->fresh()->wallet_balance, 0.001,
            'coach B must be credited only their own item payout (90)');
    }

    public function test_markpaid_honours_per_coach_item_rate_over_order_rate(): void
    {
        $coach = $this->makeCoach();
        $courseId = $this->makeCourse($coach->id, 100);
        $student = User::factory()->create(['role' => 'student']);

        // Order rate 10, but THIS coach negotiated 50% (captured on the item).
        $order = Order::create([
            'invoice_id' => 'H9-' . uniqid(), 'transaction_id' => 'H9TX-' . uniqid(),
            'buyer_id' => $student->id, 'status' => 'pending', 'payment_status' => 'pending',
            'payable_amount' => 100, 'paid_amount' => 100, 'commission_rate' => 10,
            'payment_method' => 'test', 'order_type' => 'course',
        ]);
        OrderItem::create(['order_id' => $order->id, 'course_id' => $courseId, 'price' => 100, 'commission_rate' => 50]);

        app(PaymentFulfilmentService::class)->markPaid($order, $order->transaction_id, 'test');

        $this->assertEqualsWithDelta(50.0, (float) $coach->fresh()->wallet_balance, 0.001,
            'payout must use the per-item 50% rate (=> 50), not the order 10% rate (=> 90)');
    }

    public function test_charge_and_refund_are_symmetric_per_item(): void
    {
        // Symmetry by construction: both paths call OrderItem::coachPayout()
        // with the same persisted rate, so credit == debit for each item.
        $coach = $this->makeCoach();
        $courseId = $this->makeCourse($coach->id, 120);
        $student = User::factory()->create(['role' => 'student']);

        $order = Order::create([
            'invoice_id' => 'H9-' . uniqid(), 'transaction_id' => 'H9TX-' . uniqid(),
            'buyer_id' => $student->id, 'status' => 'pending', 'payment_status' => 'pending',
            'payable_amount' => 120, 'paid_amount' => 120, 'commission_rate' => 15,
            'payment_method' => 'test', 'order_type' => 'course',
        ]);
        $item = OrderItem::create(['order_id' => $order->id, 'course_id' => $courseId, 'price' => 120, 'commission_rate' => 15]);

        app(PaymentFulfilmentService::class)->markPaid($order, $order->transaction_id, 'test');
        $afterCharge = (float) $coach->fresh()->wallet_balance;
        $this->assertEqualsWithDelta(102.0, $afterCharge, 0.001, '120 - 15% = 102 credited');

        // Simulate the refund debit exactly as every refund path does.
        $coach->fresh()->decrement('wallet_balance', $item->coachPayout((float) $order->commission_rate));

        $this->assertEqualsWithDelta(0.0, (float) $coach->fresh()->wallet_balance, 0.001,
            'refund debit must exactly reverse the charge credit (symmetry)');
    }
}
