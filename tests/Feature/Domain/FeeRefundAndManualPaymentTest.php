<?php

namespace Tests\Feature\Domain;

use App\Http\Controllers\Frontend\Coach\FeeManagementController;
use App\Models\ActivityLog;
use App\Models\CourseBatch;
use App\Models\FeeDemand;
use App\Models\FeePayment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Order\app\Models\Enrollment;
use Tests\TestCase;

/**
 * Coach-side fee money-trail: manual (offline) payment recording and refunds.
 *
 *  - A coach can record a CASH payment for a batch member who has NOT paid
 *    yet (has_access=0) — that's the normal case (cash before access). The
 *    old has_access=1 gate wrongly blocked it (same bug class as the student
 *    visibility fix).
 *  - Both recording a manual payment and issuing a refund write a money-trail
 *    audit row (action=payment_status_changed, module=fee), matching the
 *    course-order audit path.
 */
class FeeRefundAndManualPaymentTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array{0:User,1:CourseBatch,2:FeeDemand,3:User} coach, batch, demand, enrolled student */
    private function seedScenario(int $hasAccess = 0): array
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
        Enrollment::create([
            'user_id' => $student->id, 'course_id' => $courseId,
            'batch_id' => $batch->id, 'has_access' => $hasAccess,
        ]);

        return [$coach, $batch, $demand, $student];
    }

    public function test_coach_can_record_cash_payment_for_unpaid_member_and_it_is_audited(): void
    {
        [$coach, , $demand, $student] = $this->seedScenario(hasAccess: 0); // the bug scenario
        Auth::guard('web')->loginUsingId($coach->id);

        $req = Request::create('/instructor/fees/' . $demand->id . '/record', 'POST', [
            'student_id' => $student->id,
            'amount'     => 4999.99,
            'gateway'    => 'cash',
            'note'       => 'Paid at front desk',
        ]);
        app(FeeManagementController::class)->recordPayment($req, $demand->id);

        $payment = FeePayment::where('fee_demand_id', $demand->id)
            ->where('student_id', $student->id)->first();
        $this->assertNotNull($payment, 'cash payment must be recorded for an unpaid (has_access=0) batch member');
        $this->assertSame('paid', $payment->status);
        $this->assertSame('cash', $payment->gateway);

        $audit = ActivityLog::where('module', 'fee')
            ->where('action', ActivityLog::PAYMENT_STATUS_CHANGED)
            ->where('subject_id', $payment->id)->first();
        $this->assertNotNull($audit, 'recording a manual payment must write a money-trail audit row');
        $this->assertSame('paid', $audit->new_values['status'] ?? null);
    }

    public function test_refund_flips_status_and_writes_audit_row(): void
    {
        [$coach, , $demand, $student] = $this->seedScenario(hasAccess: 1);
        Auth::guard('web')->loginUsingId($coach->id);

        // A manual (non-razorpay) paid payment so refund skips the gateway leg.
        $payment = FeePayment::create([
            'fee_demand_id' => $demand->id, 'student_id' => $student->id,
            'receipt_no' => FeePayment::generateReceiptNo(), 'amount' => 4999.99,
            'gateway' => 'cash', 'status' => 'paid', 'paid_at' => now(),
            'recorded_by' => $coach->id,
        ]);

        app(FeeManagementController::class)->refundPayment($payment->id);

        $payment->refresh();
        $this->assertSame('refunded', $payment->status);
        $this->assertNotNull($payment->refunded_at);

        $audit = ActivityLog::where('module', 'fee')
            ->where('action', ActivityLog::PAYMENT_STATUS_CHANGED)
            ->where('subject_id', $payment->id)->first();
        $this->assertNotNull($audit, 'a refund must write a money-trail audit row');
        $this->assertSame('paid', $audit->old_values['status'] ?? null);
        $this->assertSame('refunded', $audit->new_values['status'] ?? null);
    }

    public function test_only_paid_payments_can_be_refunded(): void
    {
        [$coach, , $demand, $student] = $this->seedScenario(hasAccess: 1);
        Auth::guard('web')->loginUsingId($coach->id);

        $payment = FeePayment::create([
            'fee_demand_id' => $demand->id, 'student_id' => $student->id,
            'receipt_no' => FeePayment::generateReceiptNo(), 'amount' => 4999.99,
            'gateway' => 'razorpay', 'gateway_txn_id' => 'pay_x', 'status' => 'initiated',
            'recorded_by' => $coach->id,
        ]);

        app(FeeManagementController::class)->refundPayment($payment->id);

        $payment->refresh();
        $this->assertSame('initiated', $payment->status, 'a non-paid payment must not be refundable');
        $this->assertSame(0, ActivityLog::where('module', 'fee')
            ->where('subject_id', $payment->id)->count(),
            'a rejected refund must not write an audit row');
    }
}
