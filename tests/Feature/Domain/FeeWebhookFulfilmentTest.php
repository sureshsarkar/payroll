<?php

namespace Tests\Feature\Domain;

use App\Models\ActivityLog;
use App\Models\CourseBatch;
use App\Models\FeeDemand;
use App\Models\FeePayment;
use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Fee fulfilment via the Razorpay webhook (server-side safety net).
 *
 * Confirms the money-trail contract for FEE payments matches the
 * course-order path (PaymentFulfilmentService::markPaid):
 *   - payment.captured with notes.fee_demand_id flips the fee_payment
 *     row initiated -> paid and swaps the txn id to the pay_xxx id;
 *   - it writes exactly ONE audit row (action=payment_status_changed,
 *     module=fee);
 *   - it is IDEMPOTENT — Razorpay retries the webhook, and the retry
 *     must not double-pay or write a second audit row.
 */
class FeeWebhookFulfilmentTest extends TestCase
{
    use DatabaseTransactions;

    private const SECRET = 'razorpay_unit_test_secret';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.razorpay.webhook_secret', self::SECRET);
    }

    /** Build coach + batch + student + published demand + an initiated payment. */
    private function seedInitiatedPayment(string $rzpOrderId): FeePayment
    {
        $coach = User::factory()->create(['role' => 'instructor', 'coach_id' => null]);
        $student = User::factory()->create(['role' => 'student']);
        $courseId = DB::table('courses')->insertGetId([
            'title' => 'Fee course ' . uniqid(), 'slug' => 'fee-' . uniqid(),
            'instructor_id' => $coach->id, 'added_by' => $coach->id,
            'is_approved' => 'approved', 'status' => 'active',
            'type' => 'course', 'price' => 0, 'discount' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $batch = CourseBatch::create([
            'course_id' => $courseId, 'title' => 'Evening Batch',
            'start_date' => now(), 'end_date' => now()->addMonth(),
            'start_time' => '18:00:00', 'end_time' => '20:00:00',
            'capacity' => 30, 'days' => ['monday'], 'status' => 'active',
        ]);
        $demand = FeeDemand::create([
            'coach_id' => $coach->id, 'batch_id' => $batch->id,
            'title' => 'Term 1 Fee', 'amount' => 4999.99, 'status' => 'published',
            'created_by' => $coach->id, 'due_date' => now()->addWeek(),
        ]);

        return FeePayment::create([
            'fee_demand_id'  => $demand->id,
            'student_id'     => $student->id,
            'receipt_no'     => FeePayment::generateReceiptNo(),
            'amount'         => $demand->amount,
            'gateway'        => 'razorpay',
            'gateway_txn_id' => $rzpOrderId,   // order id saved at checkout()
            'status'         => 'initiated',
            'recorded_by'    => $student->id,
        ]);
    }

    /** POST a correctly-signed payment.captured webhook for the given payment. */
    private function postCapturedWebhook(FeePayment $payment, string $rzpOrderId, string $rzpPaymentId)
    {
        $payload = json_encode([
            'event'   => 'payment.captured',
            'payload' => ['payment' => ['entity' => [
                'id'       => $rzpPaymentId,
                'order_id' => $rzpOrderId,
                'amount'   => (int) round((float) $payment->amount * 100),
                'currency' => 'INR',
                'notes'    => [
                    'fee_demand_id' => (string) $payment->fee_demand_id,
                    'student_id'    => (string) $payment->student_id,
                ],
            ]]],
        ]);
        $sig = hash_hmac('sha256', $payload, self::SECRET);
        $req = Request::create('/webhooks/razorpay', 'POST', [], [], [], [
            'CONTENT_TYPE'              => 'application/json',
            'HTTP_X_RAZORPAY_SIGNATURE' => $sig,
        ], $payload);

        return app(Kernel::class)->handle($req);
    }

    public function test_webhook_marks_fee_paid_and_writes_one_audit_row(): void
    {
        $orderId   = 'order_TEST_' . uniqid();
        $paymentId = 'pay_TEST_' . uniqid();
        $payment   = $this->seedInitiatedPayment($orderId);

        $resp = $this->postCapturedWebhook($payment, $orderId, $paymentId);
        $this->assertSame(200, $resp->getStatusCode());

        $payment->refresh();
        $this->assertSame('paid', $payment->status, 'webhook must mark the fee payment paid');
        $this->assertNotNull($payment->paid_at, 'paid_at must be stamped');
        $this->assertSame($paymentId, $payment->gateway_txn_id,
            'txn id must swap order_xxx -> pay_xxx for ops lookup');

        $audits = ActivityLog::where('module', 'fee')
            ->where('action', ActivityLog::PAYMENT_STATUS_CHANGED)
            ->where('subject_id', $payment->id)
            ->get();
        $this->assertCount(1, $audits, 'fee fulfilment must write exactly one money-trail audit row');
        $this->assertSame('paid', $audits->first()->new_values['status'] ?? null);
    }

    public function test_webhook_is_idempotent_on_retry(): void
    {
        $orderId   = 'order_TEST_' . uniqid();
        $paymentId = 'pay_TEST_' . uniqid();
        $payment   = $this->seedInitiatedPayment($orderId);

        // First delivery.
        $this->postCapturedWebhook($payment, $orderId, $paymentId);
        // Razorpay retries (e.g. our 200 was slow to reach them).
        $resp2 = $this->postCapturedWebhook($payment, $orderId, $paymentId);
        $this->assertSame(200, $resp2->getStatusCode());

        $payment->refresh();
        $this->assertSame('paid', $payment->status);

        // No double-audit: still exactly one row after the retry.
        $count = ActivityLog::where('module', 'fee')
            ->where('action', ActivityLog::PAYMENT_STATUS_CHANGED)
            ->where('subject_id', $payment->id)
            ->count();
        $this->assertSame(1, $count, 'a retried webhook must NOT write a second audit row');

        // No duplicate paid FeePayment rows for this demand+student.
        $paidRows = FeePayment::where('fee_demand_id', $payment->fee_demand_id)
            ->where('student_id', $payment->student_id)
            ->where('status', 'paid')
            ->count();
        $this->assertSame(1, $paidRows, 'a retried webhook must NOT create a second paid payment');
    }
}
