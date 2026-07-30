<?php

namespace Tests\Feature\Domain;

use App\Http\Controllers\Frontend\Coach\OfflinePaymentController;
use App\Models\CoachBrandSetting;
use App\Models\CoachStudentLink;
use App\Models\Course;
use App\Models\OfflinePayment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Order\app\Models\Enrollment;
use Modules\Order\app\Models\Order;
use Tests\TestCase;

/**
 * 2026-07-11 — Offline Payment (Phase 1).
 * Record-only offline course-purchase: enrolls the student, marks the order
 * paid WITHOUT any gateway, is strictly tenant-scoped, and honours the
 * per-coach approval gate.
 */
class OfflinePaymentTest extends TestCase
{
    use DatabaseTransactions;

    private function coach(): User
    {
        return User::factory()->create(['role' => 'instructor', 'coach_id' => null]);
    }

    private function studentFor(User $coach): User
    {
        $s = User::factory()->create(['role' => 'student', 'added_by' => $coach->id]);
        CoachStudentLink::link($coach->id, $s->id);
        return $s;
    }

    private function course(User $coach): Course
    {
        $c = (new Course())->forceFill([
            'instructor_id' => $coach->id, 'added_by' => $coach->id,
            'title' => 'C ' . uniqid(), 'slug' => 'c-' . uniqid(),
            'price' => 5000, 'discount' => 0, 'status' => 'active',
            'is_approved' => 'approved', 'type' => 'live', 'coach_soft_delete' => 0,
        ]);
        $c->save();
        return $c;
    }

    private function recordCourse(User $coach, array $overrides = []): void
    {
        Auth::guard('web')->loginUsingId($coach->id);
        $req = Request::create('/instructor/offline-payments/course', 'POST', array_merge([
            'amount' => 5000, 'method' => 'cash', 'status' => 'paid', 'paid_at' => date('Y-m-d'),
        ], $overrides));
        app(OfflinePaymentController::class)->storeCourse($req);
    }

    public function test_offline_course_purchase_enrolls_and_marks_paid_without_gateway(): void
    {
        $coach = $this->coach();
        $student = $this->studentFor($coach);
        $course = $this->course($coach);

        $this->recordCourse($coach, ['student_id' => $student->id, 'course_id' => $course->id]);

        $op = OfflinePayment::forCoach($coach->id)->first();
        $this->assertNotNull($op, 'an offline_payments row is written');
        $this->assertSame(OfflinePayment::APPROVAL_AUTO, $op->approval_status);
        $this->assertNotNull($op->receipt_no);

        $order = Order::find($op->order_id);
        $this->assertNotNull($order);
        $this->assertSame('paid', $order->payment_status, 'order marked paid');
        $this->assertStringStartsWith('offline_', (string) $order->payment_method);
        $this->assertStringStartsWith('OFFLINE-', (string) $order->transaction_id, 'no gateway txn');

        $this->assertTrue(
            Enrollment::where('user_id', $student->id)->where('course_id', $course->id)->where('has_access', 1)->exists(),
            'student is enrolled with access'
        );
    }

    public function test_roster_student_joined_via_purchase_can_be_recorded(): void
    {
        // Repro of "Selected student is not yours": a student on the coach's
        // roster via the CoachStudentLink pivot (e.g. joined by purchase) but
        // whose users.added_by points elsewhere. The dropdown lists them, so the
        // recorder must accept them too.
        $coach = $this->coach();
        $otherCoach = $this->coach();
        $student = User::factory()->create(['role' => 'student', 'added_by' => $otherCoach->id]);
        CoachStudentLink::link($coach->id, $student->id, 'purchase'); // on THIS coach's roster
        $course = $this->course($coach);

        $this->recordCourse($coach, ['student_id' => $student->id, 'course_id' => $course->id]);

        $op = OfflinePayment::forCoach($coach->id)->first();
        $this->assertNotNull($op, 'a roster (purchase-linked) student can be recorded even if not added_by this coach');
        $this->assertTrue(Enrollment::where('user_id', $student->id)->where('course_id', $course->id)->where('has_access', 1)->exists());
    }

    public function test_partial_payment_records_but_does_not_enroll(): void
    {
        $coach = $this->coach();
        $student = $this->studentFor($coach);
        $course = $this->course($coach);

        $this->recordCourse($coach, ['student_id' => $student->id, 'course_id' => $course->id, 'status' => 'partial']);

        $op = OfflinePayment::forCoach($coach->id)->first();
        $this->assertSame('partial', $op->status);
        $order = Order::find($op->order_id);
        $this->assertSame('pending', $order->payment_status, 'partial does not mark paid');
        $this->assertFalse(
            Enrollment::where('user_id', $student->id)->where('course_id', $course->id)->where('has_access', 1)->exists()
        );
    }

    public function test_approval_gate_holds_payment_until_approved(): void
    {
        $coach = $this->coach();
        CoachBrandSetting::firstOrCreateForCoach($coach->id)->forceFill(['offline_payment_needs_approval' => true])->save();
        $student = $this->studentFor($coach);
        $course = $this->course($coach);

        $this->recordCourse($coach, ['student_id' => $student->id, 'course_id' => $course->id]);

        $op = OfflinePayment::forCoach($coach->id)->first();
        $this->assertSame(OfflinePayment::APPROVAL_PENDING, $op->approval_status);
        $this->assertSame('pending', Order::find($op->order_id)->payment_status, 'not paid until approved');
        $this->assertFalse(Enrollment::where('user_id', $student->id)->where('has_access', 1)->exists());

        // Approve → now applied.
        Auth::guard('web')->loginUsingId($coach->id);
        app(OfflinePaymentController::class)->approve($op->id);

        $this->assertSame('paid', Order::find($op->order_id)->payment_status);
        $this->assertTrue(Enrollment::where('user_id', $student->id)->where('course_id', $course->id)->where('has_access', 1)->exists());
    }

    public function test_cancel_reverses_the_effect(): void
    {
        $coach = $this->coach();
        $student = $this->studentFor($coach);
        $course = $this->course($coach);
        $this->recordCourse($coach, ['student_id' => $student->id, 'course_id' => $course->id]);
        $op = OfflinePayment::forCoach($coach->id)->first();

        Auth::guard('web')->loginUsingId($coach->id);
        app(OfflinePaymentController::class)->cancel(Request::create('/', 'POST', ['reason' => 'test']), $op->id);

        $op->refresh();
        $this->assertNotNull($op->cancelled_at);
        $this->assertSame('refunded', Order::find($op->order_id)->payment_status);
        $this->assertFalse(
            Enrollment::where('user_id', $student->id)->where('course_id', $course->id)->where('has_access', 1)->exists(),
            'access revoked on cancel'
        );
    }

    public function test_offline_fee_payment_settles_and_links_the_ledger(): void
    {
        $coach = $this->coach();
        $student = $this->studentFor($coach);
        $course = $this->course($coach);
        $batch = \App\Models\CourseBatch::query()->forceCreate([
            'course_id' => $course->id, 'title' => 'B', 'status' => 'active',
            'start_date' => now(), 'end_date' => now()->addMonth(),
            'start_time' => '10:00', 'end_time' => '11:00', 'days' => ['Mon'], 'capacity' => 10,
        ]);
        Enrollment::create([
            'user_id' => $student->id, 'course_id' => $course->id,
            'batch_id' => $batch->id, 'has_access' => 0,
        ]);
        $demand = \App\Models\FeeDemand::query()->forceCreate([
            'coach_id' => $coach->id, 'batch_id' => $batch->id,
            'title' => 'July', 'amount' => 2000, 'status' => 'published', 'created_by' => $coach->id,
        ]);

        Auth::guard('web')->loginUsingId($coach->id);
        $req = Request::create('/', 'POST', [
            'student_id' => $student->id, 'amount' => 2000, 'gateway' => 'upi',
            'reference_no' => 'UPI-1', 'paid_at' => date('Y-m-d'),
        ]);
        app(\App\Http\Controllers\Frontend\Coach\FeeManagementController::class)->recordPayment($req, $demand->id);

        // Unified ledger row (source=fee) linked to the created fee_payment.
        $op = OfflinePayment::forCoach($coach->id)->where('source_type', 'fee')->first();
        $this->assertNotNull($op, 'fee offline payment writes the unified ledger');
        $this->assertNotNull($op->fee_payment_id);

        $fp = \App\Models\FeePayment::find($op->fee_payment_id);
        $this->assertNotNull($fp);
        $this->assertSame('paid', $fp->status, 'fee_payment created paid (no gateway)');
        $this->assertSame('upi', $fp->gateway);
    }

    /* ───────── Phase 2 ───────── */

    public function test_mark_existing_pending_order_paid_offline_enrolls(): void
    {
        $coach = $this->coach();
        $student = $this->studentFor($coach);
        $course = $this->course($coach);

        $order = Order::create([
            'invoice_id' => 'INV' . uniqid(), 'buyer_id' => $student->id, 'seller_id' => $coach->id,
            'payment_method' => 'coach_manual', 'payment_status' => 'pending', 'status' => 'pending',
            'payable_amount' => 5000, 'paid_amount' => 5000, 'payable_with_charge' => 5000,
            'taxable_amount' => 5000, 'tax_amount' => 0, 'gateway_charge' => 0,
            'commission_rate' => 0, 'payable_currency' => 'INR', 'conversion_rate' => 1,
            'order_type' => 'course', 'transaction_id' => 'T' . uniqid(),
        ]);
        Order::find($order->id)->orderItems()->create([
            'course_id' => $course->id, 'price' => 5000, 'commission_rate' => 0,
        ]);

        Auth::guard('web')->loginUsingId($coach->id);
        app(OfflinePaymentController::class)->recordForOrder(
            Request::create('/', 'POST', ['method' => 'upi', 'reference_no' => 'UTR-9']),
            $order->id
        );

        $this->assertSame('paid', Order::find($order->id)->payment_status);
        $this->assertTrue(Enrollment::where('user_id', $student->id)->where('course_id', $course->id)->where('has_access', 1)->exists());
        $op = OfflinePayment::forCoach($coach->id)->where('source_type', 'order')->where('order_id', $order->id)->first();
        $this->assertNotNull($op);
        $this->assertSame('upi', $op->method);
    }

    public function test_cancel_claws_back_the_wallet_credit(): void
    {
        $coach = $this->coach();
        $student = $this->studentFor($coach);
        $course = $this->course($coach);

        $before = (float) $coach->fresh()->wallet_balance;
        $this->recordCourse($coach, ['student_id' => $student->id, 'course_id' => $course->id]);
        $op = OfflinePayment::forCoach($coach->id)->first();

        $afterRecord = (float) $coach->fresh()->wallet_balance;
        $this->assertGreaterThan($before, $afterRecord, 'coach wallet credited on paid offline sale');

        Auth::guard('web')->loginUsingId($coach->id);
        app(OfflinePaymentController::class)->cancel(Request::create('/', 'POST', ['reason' => 'x']), $op->id);

        $this->assertEqualsWithDelta($before, (float) $coach->fresh()->wallet_balance, 0.01, 'wallet clawed back on cancel');
        $this->assertStringContainsString('wallet_reversed', (string) Order::find($op->order_id)->payment_details);
    }

    public function test_offline_trial_payment_provisions_student_and_settles(): void
    {
        $coach = $this->coach();
        $enquiry = \App\Models\CoachTrialEnquiry::query()->forceCreate([
            'coach_id' => $coach->id, 'name' => 'Lead', 'email' => 'lead' . uniqid() . '@x.test',
            'mobile' => '9990001234', 'price' => 999, 'currency' => 'INR',
            'status' => 'pending', 'payment_status' => 'unpaid', 'plan_type' => 'offline',
        ]);

        Auth::guard('web')->loginUsingId($coach->id);
        app(OfflinePaymentController::class)->recordForTrial(
            Request::create('/', 'POST', ['method' => 'cash']),
            $enquiry->id
        );

        $enquiry->refresh();
        $this->assertSame('paid', $enquiry->payment_status, 'trial marked paid');
        $this->assertNotNull($enquiry->student_id, 'student provisioned');

        $op = OfflinePayment::forCoach($coach->id)->where('source_type', 'trial')->first();
        $this->assertNotNull($op);
        $this->assertSame((int) $enquiry->student_id, (int) $op->student_id);

        $pay = \App\Models\CoachTrialPayment::where('enquiry_id', $enquiry->id)->first();
        $this->assertNotNull($pay);
        $this->assertSame('paid', $pay->status);
    }

    /* ───────── branded receipt email ───────── */

    private function receiptEmailedAudit(int $opId): bool
    {
        return \App\Models\ActivityLog::where('subject_type', OfflinePayment::class)
            ->where('subject_id', $opId)
            ->where('action', 'offline_payment_receipt_emailed')
            ->exists();
    }

    public function test_recording_emails_the_branded_receipt_to_the_student(): void
    {
        $coach = $this->coach();
        $student = $this->studentFor($coach); // has a factory email
        $course = $this->course($coach);

        $this->recordCourse($coach, ['student_id' => $student->id, 'course_id' => $course->id]);

        $op = OfflinePayment::forCoach($coach->id)->first();
        $this->assertTrue($this->receiptEmailedAudit($op->id), 'a branded receipt email is sent + audited on record');
    }

    public function test_receipt_email_can_be_turned_off_per_coach(): void
    {
        $coach = $this->coach();
        CoachBrandSetting::firstOrCreateForCoach($coach->id)->forceFill(['offline_payment_email_receipt' => false])->save();
        $student = $this->studentFor($coach);
        $course = $this->course($coach);

        $this->recordCourse($coach, ['student_id' => $student->id, 'course_id' => $course->id]);

        $op = OfflinePayment::forCoach($coach->id)->first();
        $this->assertFalse($this->receiptEmailedAudit($op->id), 'no receipt email when the coach disabled it');
    }

    /* ───────── tax on receipts + collections report ───────── */

    private function enableTax(User $coach, float $rate = 18, string $name = 'GST'): void
    {
        \App\Models\TaxProfile::query()->forceCreate([
            'coach_id' => $coach->id, 'is_enabled' => 1, 'mode' => 'inclusive', 'legal_name' => 'Acme',
        ]);
        \App\Models\TaxRate::query()->forceCreate([
            'coach_id' => $coach->id, 'name' => $name, 'rate' => $rate, 'is_default' => 1, 'status' => 'active',
        ]);
    }

    public function test_tax_is_split_inclusively_when_coach_has_a_tax_profile(): void
    {
        $coach = $this->coach();
        $this->enableTax($coach, 18, 'GST');
        $student = $this->studentFor($coach);
        $course = $this->course($coach);

        $this->recordCourse($coach, ['student_id' => $student->id, 'course_id' => $course->id, 'amount' => 5000]);

        $op = OfflinePayment::forCoach($coach->id)->first();
        $this->assertEqualsWithDelta(4237.29, (float) $op->base_amount, 0.02, 'base = gross / 1.18');
        $this->assertEqualsWithDelta(762.71, (float) $op->tax_amount, 0.02, 'tax = gross - base');
        $this->assertEquals(18.0, (float) $op->tax_rate);
        $this->assertSame('GST', $op->tax_label);
    }

    public function test_no_tax_stored_when_coach_has_no_tax_profile(): void
    {
        $coach = $this->coach();
        $student = $this->studentFor($coach);
        $course = $this->course($coach);

        $this->recordCourse($coach, ['student_id' => $student->id, 'course_id' => $course->id]);

        $op = OfflinePayment::forCoach($coach->id)->first();
        $this->assertNull($op->tax_amount);
        $this->assertNull($op->base_amount);
    }

    public function test_collections_summary_totals_the_range(): void
    {
        $coach = $this->coach();
        $s1 = $this->studentFor($coach);
        $s2 = $this->studentFor($coach);
        $c1 = $this->course($coach);
        $c2 = $this->course($coach);
        $this->recordCourse($coach, ['student_id' => $s1->id, 'course_id' => $c1->id, 'amount' => 5000]);
        $this->recordCourse($coach, ['student_id' => $s2->id, 'course_id' => $c2->id, 'amount' => 3000]);

        Auth::guard('web')->loginUsingId($coach->id);
        $data = app(OfflinePaymentController::class)->index(Request::create('/', 'GET'))->getData();

        $this->assertSame(2, $data['summary']['count']);
        $this->assertEqualsWithDelta(8000.0, (float) $data['summary']['total'], 0.01);
    }

    public function test_receipt_pdf_downloads_a_pdf(): void
    {
        $coach = $this->coach();
        $student = $this->studentFor($coach);
        $course = $this->course($coach);
        $this->recordCourse($coach, ['student_id' => $student->id, 'course_id' => $course->id]);
        $op = OfflinePayment::forCoach($coach->id)->first();

        Auth::guard('web')->loginUsingId($coach->id);
        $resp = app(OfflinePaymentController::class)->receiptPdf($op->id);

        $this->assertSame(200, $resp->getStatusCode());
        $this->assertStringContainsString('application/pdf', (string) $resp->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', substr((string) $resp->getContent(), 0, 8));
    }

    public function test_csv_export_streams_the_ledger(): void
    {
        $coach = $this->coach();
        $student = $this->studentFor($coach);
        $course = $this->course($coach);
        $this->recordCourse($coach, ['student_id' => $student->id, 'course_id' => $course->id]);
        $op = OfflinePayment::forCoach($coach->id)->first();

        Auth::guard('web')->loginUsingId($coach->id);
        $resp = app(OfflinePaymentController::class)->exportCsv(Request::create('/', 'GET'));

        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\StreamedResponse::class, $resp);
        ob_start();
        $resp->sendContent();
        $csv = ob_get_clean();
        $this->assertStringContainsString('Receipt', $csv);
        $this->assertStringContainsString($op->receipt_no, $csv);
    }

    /* ───────── follow-ups ───────── */

    public function test_settings_persist_enabled_methods_and_approval(): void
    {
        $coach = $this->coach();
        Auth::guard('web')->loginUsingId($coach->id);

        app(OfflinePaymentController::class)->updateSettings(
            Request::create('/', 'POST', ['methods' => ['upi', 'cheque'], 'needs_approval' => '1'])
        );

        $this->assertEqualsCanonicalizing(['upi', 'cheque'], OfflinePayment::enabledMethodsForCoach($coach->id));
        $this->assertTrue((bool) CoachBrandSetting::firstOrCreateForCoach($coach->id)->offline_payment_needs_approval);
    }

    public function test_disabled_method_is_rejected_by_validation(): void
    {
        $coach = $this->coach();
        $student = $this->studentFor($coach);
        $course = $this->course($coach);
        CoachBrandSetting::firstOrCreateForCoach($coach->id)->forceFill(['offline_payment_methods' => ['upi']])->save();

        Auth::guard('web')->loginUsingId($coach->id);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(OfflinePaymentController::class)->storeCourse(
            Request::create('/', 'POST', [
                'student_id' => $student->id, 'course_id' => $course->id,
                'amount' => 1000, 'method' => 'cash', 'status' => 'paid', // cash is disabled
            ])
        );
    }

    public function test_tenant_isolation_coach_cannot_touch_another_coachs_payment(): void
    {
        $coachA = $this->coach();
        $studentA = $this->studentFor($coachA);
        $courseA = $this->course($coachA);
        $this->recordCourse($coachA, ['student_id' => $studentA->id, 'course_id' => $courseA->id]);
        $opA = OfflinePayment::forCoach($coachA->id)->first();

        $coachB = $this->coach();

        // Coach B's scoped query can't see it.
        $this->assertNull(OfflinePayment::forCoach($coachB->id)->find($opA->id));

        // And the controller (which resolves via forCoach) 404s for coach B.
        Auth::guard('web')->loginUsingId($coachB->id);
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(OfflinePaymentController::class)->cancel(Request::create('/', 'POST'), $opA->id);
    }
}
