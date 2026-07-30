<?php

namespace Tests\Feature\Audit;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Modules\Order\app\Models\Enrollment;
use Modules\Order\app\Models\Order;
use Modules\Order\app\Models\OrderItem;
use Tests\TestCase;

/**
 * 2026-06-01 — coach order payment-status toggle (InstructorDashboardController::mySellsupdate).
 *
 * Reported: after a coach set an order Paid→Completed (student gets access),
 * then back to Pending (access removed), re-marking it Paid+Completed did
 * NOT restore the student's access. Root cause: markPaid() creates the
 * enrollment with Enrollment::firstOrCreate(), which won't UPDATE an
 * existing row. Coupled financial bug: the Paid→Pending toggle never
 * reversed the coach's commission, so a later Pending→Paid double-credited.
 *
 * Pins the corrected behaviour across the full cycle (rolled back via
 * DatabaseTransactions — no wallet/enrollment data persists):
 *   Paid    → access granted,  +commission
 *   Pending → access revoked,  commission reversed (net 0)
 *   Paid    → access RESTORED, +commission (exactly once, not double)
 *   Refund  → access revoked,  commission reversed, status = 'declined'
 */
class CoachOrderTogglePaymentTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array{0:User,1:User,2:int,3:Order,4:OrderItem} */
    private function fixture(float $price = 100, int $commission = 10): array
    {
        $coach = User::factory()->create(['role' => 'instructor']);
        $student = User::factory()->create([
            'role' => 'student', 'coach_id' => $coach->id, 'added_by' => $coach->id,
        ]);
        $courseId = \DB::table('courses')->insertGetId([
            'title'         => 'Toggle test ' . uniqid(),
            'slug'          => 'toggle-test-' . uniqid(),
            'instructor_id' => $coach->id,
            'added_by'      => $coach->id,
            'is_approved'   => 'approved',
            'status'        => 'active',
            'price'         => $price, 'discount' => 0,
            'created_at'    => now(), 'updated_at' => now(),
        ]);
        $order = Order::create([
            'invoice_id'              => 'INV-' . uniqid('tg'),
            'transaction_id'          => 'COACH-' . uniqid(),
            'buyer_id'                => $student->id,
            'has_coupon'              => 0, 'coupon_code' => '',
            'coupon_discount_percent' => '', 'coupon_discount_amount' => 0,
            'payment_method'          => 'coach_manual',
            'payment_status'          => 'pending', 'status' => 'pending',
            'payable_amount'          => $price, 'gateway_charge' => 0, 'payable_with_charge' => $price,
            'paid_amount'             => $price, 'payable_currency' => 'INR', 'conversion_rate' => 1,
            'commission_rate'         => $commission, 'order_type' => 'course',
        ]);
        $oi = OrderItem::create([
            'order_id' => $order->id, 'course_id' => $courseId,
            'price' => $price, 'commission_rate' => $commission,
        ]);

        return [$coach, $student, $courseId, $order, $oi];
    }

    private function update(int $oiId, string $payment, string $orderStatus): void
    {
        app(\App\Http\Controllers\Frontend\InstructorDashboardController::class)
            ->mySellsupdate(
                Request::create('/x', 'POST', ['payment_status' => $payment, 'order_status' => $orderStatus]),
                $oiId
            );
    }

    private function access(int $studentId, int $courseId): int
    {
        return (int) (Enrollment::where('user_id', $studentId)->where('course_id', $courseId)->value('has_access') ?? 0);
    }

    private function wallet(int $coachId): float
    {
        return (float) User::find($coachId)->wallet_balance;
    }

    public function test_repay_after_pending_restores_access_without_double_credit(): void
    {
        [$coach, $student, $courseId, $order, $oi] = $this->fixture(100, 10);
        $this->actingAs($coach, 'web');

        $w0 = $this->wallet($coach->id);
        $credit = 90.0; // 100 − 10% commission

        $this->update($oi->id, 'paid', 'completed');
        $this->assertSame(1, $this->access($student->id, $courseId), 'Paid #1 must grant access');
        $this->assertEqualsWithDelta($w0 + $credit, $this->wallet($coach->id), 0.01, 'Paid #1 must credit commission');

        $this->update($oi->id, 'pending', 'pending');
        $this->assertSame(0, $this->access($student->id, $courseId), 'Pending must revoke access');
        $this->assertEqualsWithDelta($w0, $this->wallet($coach->id), 0.01, 'Pending must reverse the commission');

        $this->update($oi->id, 'paid', 'completed');
        $this->assertSame(1, $this->access($student->id, $courseId), 'Re-Paid must RESTORE access (the reported bug)');
        $this->assertEqualsWithDelta($w0 + $credit, $this->wallet($coach->id), 0.01, 'Re-Paid must credit exactly once (no double-credit)');
    }

    public function test_refund_revokes_access_reverses_commission_and_uses_valid_status(): void
    {
        [$coach, $student, $courseId, $order, $oi] = $this->fixture(100, 10);
        $this->actingAs($coach, 'web');

        $w0 = $this->wallet($coach->id);

        $this->update($oi->id, 'paid', 'completed');
        $this->update($oi->id, 'refunded', 'completed');

        $this->assertSame(0, $this->access($student->id, $courseId), 'Refund must revoke access');
        $this->assertEqualsWithDelta($w0, $this->wallet($coach->id), 0.01, 'Refund must reverse the commission');

        $row = \DB::table('orders')->where('id', $order->id)->first();
        // orders.status enum = pending|processing|completed|declined — it has
        // no 'cancelled', so the old hard-coded 'cancelled' coerced to ''.
        $this->assertSame('declined', $row->status, "Refund must set a VALID order status ('declined', not '')");
        $this->assertSame('refunded', $row->payment_status);
    }
}
