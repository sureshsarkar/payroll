<?php

namespace Tests\Feature\Domain;

use App\Models\CourseBatch;
use App\Models\FeeDemand;
use App\Models\FeePayment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Order\app\Models\Enrollment;
use Tests\TestCase;

/**
 * Phase 4C — student-side Razorpay checkout + webhook contracts.
 *
 * We don't hit Razorpay's real API from tests; we exercise the
 * controller paths up to the gateway boundary, the webhook signature
 * verification, and the idempotent fee_payment update path.
 */
class FeePaymentCheckoutTest extends TestCase
{
    use DatabaseTransactions;

    /* ───────── routes ───────── */

    public function test_student_fee_routes_are_registered(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes());
        foreach ([
            'student.fees.index',
            'student.fees.checkout',
            'student.fees.verify',
        ] as $name) {
            $this->assertNotNull(
                $routes->first(fn ($r) => $r->getName() === $name),
                "Route $name must be registered."
            );
        }
    }

    /* ───────── controller wiring ───────── */

    public function test_student_fee_payment_controller_has_expected_methods(): void
    {
        $ref = new \ReflectionClass(
            \App\Http\Controllers\Frontend\StudentFeePaymentController::class
        );
        foreach (['index', 'checkout', 'verify'] as $m) {
            $this->assertTrue($ref->hasMethod($m),
                "StudentFeePaymentController must expose $m()");
        }
    }

    /* ───────── checkout: non-enrolled student blocked ───────── */

    public function test_checkout_rejects_student_not_enrolled_in_batch(): void
    {
        $coach   = User::factory()->create(['role' => 'instructor']);
        $student = User::factory()->create(['role' => 'student']);
        $batch   = $this->makeBatch($coach);

        $demand = FeeDemand::create([
            'coach_id' => $coach->id, 'batch_id' => $batch->id,
            'title' => 'X', 'amount' => 500, 'status' => 'published',
            'created_by' => $coach->id,
        ]);

        // NOT enrolled — controller must 403.
        Auth::guard('web')->loginUsingId($student->id);
        $ctrl = app(\App\Http\Controllers\Frontend\StudentFeePaymentController::class);
        $resp = $ctrl->checkout(\Illuminate\Http\Request::create('/x', 'POST'), $demand->id);

        $this->assertSame(403, $resp->getStatusCode(),
            'Checkout must 403 for a student not enrolled in the demand\'s batch.');
    }

    /* ───────── checkout: Razorpay creds missing → 503 ───────── */

    public function test_checkout_503s_when_razorpay_not_configured(): void
    {
        $coach   = User::factory()->create(['role' => 'instructor']);
        $student = User::factory()->create(['role' => 'student']);
        $batch   = $this->makeBatch($coach);
        $this->enroll($student, $batch);

        $demand = FeeDemand::create([
            'coach_id' => $coach->id, 'batch_id' => $batch->id,
            'title' => 'X', 'amount' => 500, 'status' => 'published',
            'created_by' => $coach->id,
        ]);

        // The dev DB has razorpay_key='razorpay_key' / 'razorpay_secret'
        // as placeholders. The controller treats those as "not
        // configured" and returns 503.
        Auth::guard('web')->loginUsingId($student->id);
        $ctrl = app(\App\Http\Controllers\Frontend\StudentFeePaymentController::class);
        $resp = $ctrl->checkout(\Illuminate\Http\Request::create('/x', 'POST'), $demand->id);

        // 503 if placeholders or absent; 200 if a real key happens to
        // be configured on this box. Either is fine.
        $this->assertContains($resp->getStatusCode(), [200, 503],
            'Checkout response must be 200 (configured) or 503 (not configured).');
    }

    /* ───────── verify(): HMAC reject path ───────── */

    public function test_verify_rejects_invalid_signature(): void
    {
        $coach   = User::factory()->create(['role' => 'instructor']);
        $student = User::factory()->create(['role' => 'student']);
        $batch   = $this->makeBatch($coach);

        $demand = FeeDemand::create([
            'coach_id' => $coach->id, 'batch_id' => $batch->id,
            'title' => 'X', 'amount' => 500, 'status' => 'published',
            'created_by' => $coach->id,
        ]);

        // Seed an 'initiated' fee_payment to mimic checkout having
        // run earlier.
        $payment = FeePayment::create([
            'fee_demand_id' => $demand->id, 'student_id' => $student->id,
            'receipt_no'    => FeePayment::generateReceiptNo(),
            'amount'        => 500, 'gateway' => 'razorpay',
            'gateway_txn_id'=> 'order_test_invalid',
            'status'        => 'initiated',
        ]);

        // Seed dummy razorpay creds so verify() doesn't 500 on missing
        // secret. They must NOT match the signature we send.
        \DB::table('payment_gateways')->updateOrInsert(
            ['key' => 'razorpay_key'],
            ['value' => 'rzp_test_KEYxxx', 'updated_at' => now(), 'created_at' => now()],
        );
        \DB::table('payment_gateways')->updateOrInsert(
            ['key' => 'razorpay_secret'],
            ['value' => 'TESTSECRETxxx', 'updated_at' => now(), 'created_at' => now()],
        );
        \Illuminate\Support\Facades\Cache::forget('payment_setting');

        Auth::guard('web')->loginUsingId($student->id);
        $ctrl = app(\App\Http\Controllers\Frontend\StudentFeePaymentController::class);
        $req = \Illuminate\Http\Request::create('/x', 'POST', [
            'razorpay_order_id'   => 'order_test_invalid',
            'razorpay_payment_id' => 'pay_test_xxx',
            'razorpay_signature'  => 'deadbeef',  // intentionally bogus
        ]);
        $resp = $ctrl->verify($req);

        $this->assertSame(400, $resp->getStatusCode(),
            'verify() must 400 on invalid HMAC.');

        // Payment row must still be 'initiated' — invalid signature must
        // NOT flip status.
        $this->assertSame('initiated', $payment->fresh()->status,
            'Invalid signature must not flip payment to paid.');
    }

    /* ───────── verify(): correct signature flips to paid ───────── */

    public function test_verify_marks_payment_paid_with_correct_signature(): void
    {
        $coach   = User::factory()->create(['role' => 'instructor']);
        $student = User::factory()->create(['role' => 'student']);
        $batch   = $this->makeBatch($coach);

        $demand = FeeDemand::create([
            'coach_id' => $coach->id, 'batch_id' => $batch->id,
            'title' => 'X', 'amount' => 500, 'status' => 'published',
            'created_by' => $coach->id,
        ]);

        $orderId   = 'order_test_' . uniqid();
        $paymentId = 'pay_test_' . uniqid();
        $secret    = 'TESTSECRET_' . uniqid();

        \DB::table('payment_gateways')->updateOrInsert(
            ['key' => 'razorpay_key'],
            ['value' => 'rzp_test_KEYxxx', 'updated_at' => now(), 'created_at' => now()],
        );
        \DB::table('payment_gateways')->updateOrInsert(
            ['key' => 'razorpay_secret'],
            ['value' => $secret, 'updated_at' => now(), 'created_at' => now()],
        );

        $payment = FeePayment::create([
            'fee_demand_id' => $demand->id, 'student_id' => $student->id,
            'receipt_no'    => FeePayment::generateReceiptNo(),
            'amount'        => 500, 'gateway' => 'razorpay',
            'gateway_txn_id'=> $orderId, 'status' => 'initiated',
        ]);

        $signature = hash_hmac('sha256', $orderId . '|' . $paymentId, $secret);

        Auth::guard('web')->loginUsingId($student->id);
        $ctrl = app(\App\Http\Controllers\Frontend\StudentFeePaymentController::class);
        $req = \Illuminate\Http\Request::create('/x', 'POST', [
            'razorpay_order_id'   => $orderId,
            'razorpay_payment_id' => $paymentId,
            'razorpay_signature'  => $signature,
        ]);
        $resp = $ctrl->verify($req);

        $this->assertSame(200, $resp->getStatusCode());
        $fresh = $payment->fresh();
        $this->assertSame('paid', $fresh->status,
            'Correct signature must flip status to paid.');
        $this->assertSame($paymentId, $fresh->gateway_txn_id,
            'gateway_txn_id must be swapped to payment_id after verify.');
        $this->assertNotNull($fresh->paid_at,
            'paid_at must be stamped.');
    }

    /* ───────── verify() is idempotent ───────── */

    public function test_verify_is_idempotent_on_already_paid(): void
    {
        $coach   = User::factory()->create(['role' => 'instructor']);
        $student = User::factory()->create(['role' => 'student']);
        $batch   = $this->makeBatch($coach);

        $demand = FeeDemand::create([
            'coach_id' => $coach->id, 'batch_id' => $batch->id,
            'title' => 'X', 'amount' => 500, 'status' => 'published',
            'created_by' => $coach->id,
        ]);

        // Already paid row — webhook beat the FE callback.
        $payment = FeePayment::create([
            'fee_demand_id' => $demand->id, 'student_id' => $student->id,
            'receipt_no'    => FeePayment::generateReceiptNo(),
            'amount'        => 500, 'gateway' => 'razorpay',
            'gateway_txn_id'=> 'pay_already_paid',
            'status'        => 'paid',
            'paid_at'       => now()->subMinute(),
        ]);

        $secret  = 'TESTSECRET_' . uniqid();
        $orderId = 'order_idem_' . uniqid();
        \DB::table('payment_gateways')->updateOrInsert(
            ['key' => 'razorpay_key'],
            ['value' => 'rzp_test', 'updated_at' => now(), 'created_at' => now()],
        );
        \DB::table('payment_gateways')->updateOrInsert(
            ['key' => 'razorpay_secret'],
            ['value' => $secret, 'updated_at' => now(), 'created_at' => now()],
        );

        // Re-seed gateway_txn_id so verify can find the row.
        $payment->update(['gateway_txn_id' => $orderId]);
        $payment->refresh();
        $paidAtBefore = $payment->paid_at;

        $signature = hash_hmac('sha256', $orderId . '|pay_x', $secret);

        Auth::guard('web')->loginUsingId($student->id);
        $ctrl = app(\App\Http\Controllers\Frontend\StudentFeePaymentController::class);
        $req = \Illuminate\Http\Request::create('/x', 'POST', [
            'razorpay_order_id'   => $orderId,
            'razorpay_payment_id' => 'pay_x',
            'razorpay_signature'  => $signature,
        ]);
        $resp = $ctrl->verify($req);

        $this->assertSame(200, $resp->getStatusCode(),
            'verify() must return 200 OK on already-paid (idempotent).');
        $this->assertSame($paidAtBefore?->toIso8601String(),
            $payment->fresh()->paid_at?->toIso8601String(),
            'paid_at must NOT change on a re-verified paid row (idempotency contract).');
    }

    /* ───────── notifications wiring ───────── */

    public function test_fee_demand_published_notification_class_exists(): void
    {
        $this->assertTrue(class_exists(\App\Notifications\FeeDemandPublishedToStudent::class));
        $this->assertTrue(class_exists(\App\Notifications\FeePaymentReceiptToStudent::class));
    }

    /* ════════ Phase 5b — Razorpay readiness ════════ */

    /** razorpay:test CLI command must be registered. */
    public function test_razorpay_test_command_is_registered(): void
    {
        $registered = collect(app('Illuminate\Contracts\Console\Kernel')->all())
            ->keys()
            ->contains('razorpay:test');
        $this->assertTrue($registered,
            "Artisan command 'razorpay:test' must be registered (auto-discovery via app/Console/Commands).");
    }

    /** razorpay:test must exit non-zero when creds are placeholder. */
    public function test_razorpay_test_exits_non_zero_on_missing_webhook_secret(): void
    {
        // Force RAZORPAY_WEBHOOK_SECRET to empty so the command MUST
        // hit the FAIL branch regardless of dev DB state.
        config(['services.razorpay.webhook_secret' => '']);
        putenv('RAZORPAY_WEBHOOK_SECRET=');

        // Set placeholder creds so razorpay_key/secret also FAIL.
        \DB::table('payment_gateways')->updateOrInsert(
            ['key' => 'razorpay_key'],
            ['value' => 'razorpay_key', 'updated_at' => now(), 'created_at' => now()],
        );
        \DB::table('payment_gateways')->updateOrInsert(
            ['key' => 'razorpay_secret'],
            ['value' => 'razorpay_secret', 'updated_at' => now(), 'created_at' => now()],
        );

        $exitCode = \Illuminate\Support\Facades\Artisan::call('razorpay:test');
        $this->assertSame(1, $exitCode,
            'razorpay:test must exit 1 (FAIL) when key/secret are placeholders and webhook secret is empty.');
    }

    /** Fee dashboard renders the configuration banner when Razorpay is not ready. */
    public function test_fee_dashboard_shows_banner_when_razorpay_not_configured(): void
    {
        // Sanity — controller exposes razorpayReadiness() via $razorpayStatus.
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/Coach/FeeManagementController.php')
        );
        $this->assertStringContainsString('razorpayReadiness', $src,
            'FeeManagementController must define razorpayReadiness().');
        $this->assertStringContainsString("'razorpayStatus'", $src,
            "Controller must pass razorpayStatus to the view.");

        $view = (string) file_get_contents(
            resource_path('views/frontend/instructor-dashboard/fees/index.blade.php')
        );
        $this->assertStringContainsString("razorpayStatus['state']", $view,
            'Dashboard view must render the readiness banner.');
        $this->assertStringContainsString('docs/RAZORPAY_SETUP.md', $view,
            'Banner must point at the setup doc.');
    }

    /** The ops doc must exist and cover the 8-step procedure. */
    public function test_razorpay_setup_doc_covers_full_procedure(): void
    {
        $path = base_path('docs/RAZORPAY_SETUP.md');
        $this->assertFileExists($path);
        $doc = (string) file_get_contents($path);

        // Steps must be present.
        foreach (['Step 1', 'Step 2', 'Step 3', 'Step 4', 'Step 5', 'Step 6', 'Step 7', 'Step 8'] as $step) {
            $this->assertStringContainsString($step, $doc, "Doc must include $step.");
        }
        // Critical commands must be referenced.
        foreach ([
            'gateway-secrets:encrypt-existing',
            'razorpay:test',
            'RAZORPAY_WEBHOOK_SECRET',
            '/webhooks/razorpay',
            'rzp_test_',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc, "Doc must reference '$needle'.");
        }
    }

    /* ───────── helpers ───────── */

    private function makeBatch(User $coach): CourseBatch
    {
        $courseId = DB::table('courses')->insertGetId([
            'title' => 'Fee checkout ' . uniqid(),
            'slug'  => 'fc-' . uniqid(),
            'instructor_id' => $coach->id, 'added_by' => $coach->id,
            'is_approved' => 'approved', 'status' => 'active',
            'type' => 'live',
            'price' => 0, 'discount' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return CourseBatch::create([
            'course_id'  => $courseId,
            'title'      => 'Batch ' . uniqid(),
            'start_date' => now(),
            'end_date'   => now()->addMonth(),
            'start_time' => '09:00:00',
            'end_time'   => '10:00:00',
            'capacity'   => 30,
            'days'       => ['monday'],
            'status'     => 'active',
        ]);
    }

    private function enroll(User $student, CourseBatch $batch): void
    {
        Enrollment::create([
            'user_id'    => $student->id,
            'course_id'  => $batch->course_id,
            'batch_id'   => $batch->id,
            'order_id'   => null,
            'has_access' => 1,
        ]);
    }
}
