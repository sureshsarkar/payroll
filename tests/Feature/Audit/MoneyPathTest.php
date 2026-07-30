<?php

namespace Tests\Feature\Audit;

use App\Models\Course;
use App\Models\User;
use App\Services\PaymentFulfilmentService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Order\app\Models\Enrollment;
use Modules\Order\app\Models\Order;
use Tests\TestCase;

/**
 * Verifies the audit's money-path guarantees:
 *
 * 1. orders.transaction_id has a unique index — replay can't insert dupes.
 * 2. orders.invoice_id has a unique index.
 * 3. enrollments(user_id, course_id) is unique — same user can't enrol twice.
 * 4. PaymentFulfilmentService::markPaid is idempotent — second call is no-op.
 *
 * Each test uses DatabaseTransactions so changes are rolled back; nothing in
 * the dev database is permanently affected.
 */
class MoneyPathTest extends TestCase
{
    use DatabaseTransactions;

    public function test_orders_transaction_id_unique_constraint_present(): void
    {
        $idx = collect(\DB::select("SHOW INDEX FROM orders WHERE Non_unique=0 AND Column_name='transaction_id'"));
        $this->assertNotEmpty($idx, 'orders.transaction_id must have a unique index (audit migration 2026_05_05_140000)');
    }

    public function test_orders_invoice_id_unique_constraint_present(): void
    {
        $idx = collect(\DB::select("SHOW INDEX FROM orders WHERE Non_unique=0 AND Column_name='invoice_id'"));
        $this->assertNotEmpty($idx, 'orders.invoice_id must have a unique index (audit migration 2026_05_05_140000)');
    }

    public function test_enrollments_user_course_unique_constraint_present(): void
    {
        $idx = collect(\DB::select("SHOW INDEX FROM enrollments WHERE Non_unique=0 AND Key_name='enrollments_user_course_unique'"));
        $this->assertNotEmpty($idx, 'enrollments must have a unique index on (user_id, course_id)');
    }

    public function test_duplicate_transaction_id_is_rejected_by_database(): void
    {
        // Audit 2026-05-19 — replace "skip if empty CI DB" with a
        // factory fixture so the test actually runs against mbs_test.
        $user = User::first() ?? User::factory()->create(['role' => 'student']);

        $trxId = 'AUDIT-TEST-' . uniqid();

        // First insert succeeds
        $orderA = Order::create([
            'invoice_id'         => 'INV-' . uniqid('a'),
            'transaction_id'     => $trxId,
            'buyer_id'           => $user->id,
            'has_coupon'         => 0,
            'coupon_code'        => '',
            'coupon_discount_percent' => '',
            'coupon_discount_amount'  => 0,
            'payment_method'     => 'test',
            'payment_status'     => 'pending',
            'payable_amount'     => 100,
            'gateway_charge'     => 0,
            'payable_with_charge'=> 100,
            'paid_amount'        => 100,
            'payable_currency'   => 'INR',
            'conversion_rate'    => 1,
            'commission_rate'    => 0,
            'order_type'         => 'course',
        ]);
        $this->assertNotNull($orderA->id);

        // Second insert with same trxId must throw at the DB layer
        $this->expectException(QueryException::class);
        Order::create([
            'invoice_id'         => 'INV-' . uniqid('b'),
            'transaction_id'     => $trxId, // same!
            'buyer_id'           => $user->id,
            'has_coupon'         => 0,
            'coupon_code'        => '',
            'coupon_discount_percent' => '',
            'coupon_discount_amount'  => 0,
            'payment_method'     => 'test',
            'payment_status'     => 'pending',
            'payable_amount'     => 100,
            'gateway_charge'     => 0,
            'payable_with_charge'=> 100,
            'paid_amount'        => 100,
            'payable_currency'   => 'INR',
            'conversion_rate'    => 1,
            'commission_rate'    => 0,
            'order_type'         => 'course',
        ]);
    }

    public function test_duplicate_enrollment_for_same_user_and_course_rejected(): void
    {
        // Audit 2026-05-19 — create a guaranteed-fresh (user, course)
        // pair and a parent order, so the test runs reliably regardless
        // of pre-existing DB state.
        $user = User::factory()->create(['role' => 'student']);
        $coach = User::factory()->create(['role' => 'instructor']);
        $courseId = \DB::table('courses')->insertGetId([
            'title'         => 'Money path test '.uniqid(),
            'slug'          => 'money-path-test-'.uniqid(),
            'instructor_id' => $coach->id,
            'added_by'      => $coach->id,
            'is_approved'   => 'approved',
            'status'        => 'active',
            'price'         => 0, 'discount' => 0,
            'created_at'    => now(), 'updated_at' => now(),
        ]);
        $order = Order::create([
            'invoice_id'              => 'INV-' . uniqid('e'),
            'transaction_id'          => 'TRX-' . uniqid('e'),
            'buyer_id'                => $user->id,
            'has_coupon'              => 0,
            'coupon_code'             => '',
            'coupon_discount_percent' => '',
            'coupon_discount_amount'  => 0,
            'payment_method'          => 'test',
            'payment_status'          => 'pending',
            'payable_amount'          => 0,
            'gateway_charge'          => 0,
            'payable_with_charge'     => 0,
            'paid_amount'             => 0,
            'payable_currency'        => 'INR',
            'conversion_rate'         => 1,
            'commission_rate'         => 0,
            'order_type'              => 'course',
        ]);

        Enrollment::create([
            'user_id'    => $user->id,
            'course_id'  => $courseId,
            'order_id'   => $order->id,
            'has_access' => 1,
        ]);

        $this->expectException(QueryException::class);
        Enrollment::create([
            'user_id'    => $user->id,
            'course_id'  => $courseId,
            'order_id'   => $order->id,
            'has_access' => 1,
        ]);
    }

    public function test_payment_fulfilment_service_is_idempotent(): void
    {
        // Build an order in 'pending' state, then mark paid twice — second
        // call must be a no-op (return false) and not double-credit.
        // Audit 2026-05-19 — factory fixture so the test runs in any env.
        $user = User::factory()->create(['role' => 'student']);

        $order = Order::create([
            'invoice_id'              => 'INV-' . uniqid('idem'),
            'transaction_id'          => null,
            'buyer_id'                => $user->id,
            'has_coupon'              => 0,
            'coupon_code'             => '',
            'coupon_discount_percent' => '',
            'coupon_discount_amount'  => 0,
            'payment_method'          => 'test',
            'payment_status'          => 'pending',
            'payable_amount'          => 100,
            'gateway_charge'          => 0,
            'payable_with_charge'     => 100,
            'paid_amount'             => 100,
            'payable_currency'        => 'INR',
            'conversion_rate'         => 1,
            'commission_rate'         => 0,
            'order_type'              => 'course',
        ]);

        $svc = app(PaymentFulfilmentService::class);
        $trx = 'AUDIT-IDEM-' . uniqid();

        $first  = $svc->markPaid($order, $trx, ['gateway' => 'test']);
        $second = $svc->markPaid($order, $trx, ['gateway' => 'test']);

        $this->assertTrue($first, 'first markPaid should report processed=true');
        $this->assertFalse($second, 'second markPaid (replay) should report processed=false (already paid)');

        // Order should be paid exactly once
        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame($trx, $order->transaction_id);
    }
}
