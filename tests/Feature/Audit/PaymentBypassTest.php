<?php

namespace Tests\Feature\Audit;

use App\Models\CourseBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Modules\BasicPayment\app\Http\Controllers\PaymentController;
use Modules\BasicPayment\app\Services\PaymentMethodService;
use Modules\Order\app\Models\Order;
use Modules\Order\app\Models\OrderItem;
use Tests\TestCase;

/**
 * Audit C3/C4 - payment bypass.
 *
 * payment_success() previously marked an order paid + granted enrollment off
 * nothing but session('order'). A user could start checkout (which creates a
 * PENDING order and stores it in session), get redirected to the gateway,
 * then navigate straight to /payment-success WITHOUT paying - and walk away
 * enrolled for free.
 *
 * The fix requires a gateway-verified transaction reference
 * (after_success_transaction, set only AFTER each gateway handler verifies the
 * charge server-side) before fulfilling an online order, and routes fulfilment
 * through the canonical PaymentFulfilmentService::markPaid().
 */
class PaymentBypassTest extends TestCase
{
    use DatabaseTransactions;

    private int $courseId;
    private ?int $batchId = null;
    private User $coach;

    /**
     * Build a PaymentController WITHOUT running its constructor. The real
     * constructor boots every payment module's gateway service (Razorpay,
     * Bkash, ...), each of which reads settings rows not seeded in the test
     * DB. payment_success() only needs $this->paymentService for its
     * ::BANK_PAYMENT / ::OFFLINE_PAYMENT constants, so we inject the service
     * CLASS NAME - `$this->paymentService::BANK_PAYMENT` resolves the constant
     * off a class-name string just as well as off an instance.
     */
    private function controller(): PaymentController
    {
        $ref = new \ReflectionClass(PaymentController::class);
        $controller = $ref->newInstanceWithoutConstructor();
        $prop = $ref->getProperty('paymentService');
        $prop->setAccessible(true);
        $prop->setValue($controller, PaymentMethodService::class);
        return $controller;
    }

    private function makeOrder(User $student, string $method, float $price = 100, float $rate = 10): Order
    {
        $this->coach = User::factory()->create(['role' => 'instructor', 'coach_id' => null, 'wallet_balance' => 0]);
        $this->courseId = DB::table('courses')->insertGetId([
            'title' => 'Pay course ' . uniqid(), 'slug' => 'pay-' . uniqid(),
            'instructor_id' => $this->coach->id, 'added_by' => $this->coach->id,
            'is_approved' => 'approved', 'status' => 'active',
            'type' => 'course', 'price' => $price, 'discount' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->batchId = CourseBatch::create([
            'course_id' => $this->courseId, 'title' => 'B', 'status' => 'active', 'capacity' => 30,
        ])->id;

        $order = Order::create([
            'invoice_id' => 'PB-' . uniqid(), 'buyer_id' => $student->id,
            'status' => 'pending', 'payment_status' => 'pending', 'payment_method' => $method,
            'payable_amount' => $price, 'paid_amount' => $price, 'commission_rate' => $rate,
            'payable_currency' => 'INR', 'order_type' => 'course',
        ]);
        OrderItem::create([
            'order_id' => $order->id, 'course_id' => $this->courseId, 'price' => $price,
            'commission_rate' => $rate, 'batch_id' => $this->batchId,
        ]);
        return $order;
    }

    public function test_direct_hit_without_verified_transaction_is_blocked(): void
    {
        Mail::fake();
        $student = User::factory()->create(['role' => 'student']);
        $order = $this->makeOrder($student, 'stripe');

        $this->actingAs($student);
        // Attacker reaches /payment-success with the session order but NO
        // gateway-verified transaction reference.
        session(['order' => $order]); // no after_success_transaction

        $resp = $this->controller()->payment_success();

        $this->assertInstanceOf(RedirectResponse::class, $resp);
        $this->assertStringContainsString('payment-failed', $resp->getTargetUrl());

        // Order must NOT be fulfilled.
        $this->assertSame('pending', $order->fresh()->payment_status, 'order must stay pending - no free access');
        $this->assertDatabaseMissing('enrollments', [
            'user_id' => $student->id, 'course_id' => $this->courseId,
        ]);
        $this->assertEqualsWithDelta(0.0, (float) $this->coach->fresh()->wallet_balance, 0.001,
            'no wallet credit on a bypassed payment');
    }

    public function test_verified_transaction_fulfils_via_markpaid(): void
    {
        Mail::fake();
        $student = User::factory()->create(['role' => 'student']);
        $order = $this->makeOrder($student, 'stripe');

        $this->actingAs($student);
        session([
            'order' => $order,
            'after_success_transaction' => 'tx_verified_' . uniqid(),
            'payment_details' => ['gateway' => 'stripe'],
        ]);

        $this->controller()->payment_success();

        $fresh = $order->fresh();
        $this->assertSame('paid', $fresh->payment_status, 'verified payment marks the order paid');
        $this->assertSame('completed', $fresh->status);

        // Enrollment created WITH batch_id (markPaid propagates it - the
        // inline redirect path used to drop it).
        $this->assertDatabaseHas('enrollments', [
            'user_id' => $student->id, 'course_id' => $this->courseId,
            'has_access' => 1, 'batch_id' => $this->batchId,
        ]);
        // Coach credited per item (100 - 10% = 90).
        $this->assertEqualsWithDelta(90.0, (float) $this->coach->fresh()->wallet_balance, 0.001);
    }

    public function test_bank_offline_order_recorded_pending_not_fulfilled(): void
    {
        Mail::fake();
        $student = User::factory()->create(['role' => 'student']);
        $order = $this->makeOrder($student, 'offline');

        $this->actingAs($student);
        session([
            'order' => $order,
            'after_success_transaction' => 'offline_txn_' . uniqid(),
            'payment_details' => 'receipt.png',
        ]);

        $this->controller()->payment_success();

        $this->assertSame('pending', $order->fresh()->payment_status,
            'bank/offline stays pending until admin approval');
        $this->assertDatabaseMissing('enrollments', [
            'user_id' => $student->id, 'course_id' => $this->courseId,
        ]);
        $this->assertEqualsWithDelta(0.0, (float) $this->coach->fresh()->wallet_balance, 0.001,
            'no wallet credit until the bank receipt is approved');
    }
}
