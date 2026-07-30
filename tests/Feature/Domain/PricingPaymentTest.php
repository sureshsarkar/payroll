<?php

namespace Tests\Feature\Domain;

use App\Http\Controllers\Frontend\PricingEnquiryController;
use App\Models\CoachLandingPage;
use App\Models\CoachPage;
use App\Models\CoachPageSection;
use App\Models\CoachPricingEnquiry;
use App\Models\CoachPricingPayment;
use App\Models\User;
use App\Services\Payment\PaymentGatewayResolverService;
use App\Services\PricingPaymentService;
use App\Notifications\PricingBookingPaidToCoach;
use App\Notifications\PricingBookingReceiptToStudent;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Pricing & Plans booking → payment (2026-07-13).
 *
 * Covers: server-side amount authority (posted price is never trusted), the
 * opt-in gate + free-plan lead-only behaviour, idempotent + amount-reconciled
 * mark-paid, cancel/abandon keeping the enquiry, retry-after-failure, the
 * HMAC-verified webhook branch, and strict tenant isolation.
 *
 * Live Razorpay order-create + signature verify need real keys, so those exact
 * network calls are exercised by manual/live UAT (same as the trial flow); the
 * security-critical money logic (markPaidLocked / webhook / gating) is fully
 * unit/integration tested here without the network.
 */
class PricingPaymentTest extends TestCase
{
    use DatabaseTransactions;

    private function coach(): User
    {
        return User::find(DB::table('users')->insertGetId([
            'role' => 'instructor', 'name' => 'Coach', 'email' => 'c' . uniqid() . '@t.local',
            'password' => bcrypt('x'), 'status' => 'active', 'is_banned' => 'no',
            'created_at' => now(), 'updated_at' => now(),
        ]));
    }

    /** Build a real pricing_plans_v1 section owned by $coach. */
    private function section(User $coach, ?array $categories = null): CoachPageSection
    {
        $categories ??= [[
            'name' => 'Online Class', 'mode' => 'booking',
            'individual_periods' => [
                ['label' => '3 Months', 'price' => '6000'],
                ['label' => 'Free Trial', 'price' => 'Free'],
            ],
            'couple_periods' => [
                ['label' => '3 Months', 'price' => '9,600'],
            ],
        ]];

        $lp = CoachLandingPage::create([
            'added_by' => $coach->id, 'website_name' => 'Site', 'title' => 'Site', 'slug' => 's' . uniqid(),
        ]);
        $page = CoachPage::create([
            'coach_id' => $coach->id, 'site_id' => $lp->id, 'slug' => 'p' . uniqid(),
            'page_type' => 'home', 'title' => 'Home',
        ]);

        return CoachPageSection::create([
            'coach_page_id'   => $page->id,
            'section_type'    => 'pricing_plans_v1',
            'section_version' => 1,
            'content_json'    => ['categories' => $categories],
            'sort_order'      => 0,
            'is_visible'      => true,
        ]);
    }

    private function service(): PricingPaymentService
    {
        return app(PricingPaymentService::class);
    }

    private function pendingPayment(User $coach, CoachPricingEnquiry $enq, float $amount, string $order = 'order_X'): CoachPricingPayment
    {
        return CoachPricingPayment::create([
            'coach_id' => $coach->id, 'enquiry_id' => $enq->id, 'gateway' => 'razorpay',
            'gateway_order_id' => $order, 'amount' => $amount, 'currency' => 'INR',
            'status' => CoachPricingPayment::STATUS_PENDING,
        ]);
    }

    // ── Server-side amount authority ─────────────────────────────────────

    public function test_amount_is_resolved_server_side_and_ignores_posted_price(): void
    {
        $coach = $this->coach();
        $sec   = $this->section($coach);

        // resolvePlanAmount takes NO price argument — it can only read the stored
        // section, so a tampered client price is structurally impossible to honour.
        $r = $this->service()->resolvePlanAmount($coach->id, $sec->id, 'Online Class', 'individual', '3 Months');
        $this->assertTrue($r['found']);
        $this->assertSame(6000.0, $r['amount']);
        $this->assertSame('INR', $r['currency']);

        // Indian-format couple price with a comma is parsed correctly.
        $rc = $this->service()->resolvePlanAmount($coach->id, $sec->id, 'Online Class', 'couple', '3 Months');
        $this->assertSame(9600.0, $rc['amount']);
    }

    public function test_free_or_unmatched_plan_resolves_to_zero(): void
    {
        $coach = $this->coach();
        $sec   = $this->section($coach);

        $free = $this->service()->resolvePlanAmount($coach->id, $sec->id, 'Online Class', 'individual', 'Free Trial');
        $this->assertFalse($free['found']);
        $this->assertSame(0.0, $free['amount']);

        $noPeriod = $this->service()->resolvePlanAmount($coach->id, $sec->id, 'Online Class', 'individual', '99 Years');
        $this->assertFalse($noPeriod['found']);

        $noCat = $this->service()->resolvePlanAmount($coach->id, $sec->id, 'Nonexistent', 'individual', '3 Months');
        $this->assertFalse($noCat['found']);
    }

    public function test_section_from_another_coach_is_never_read(): void
    {
        $coachA = $this->coach();
        $secA   = $this->section($coachA);           // has the 6000 plan
        $coachB = $this->coach();                     // no sections of their own

        // coachB posting coachA's section id must NOT resolve coachA's price.
        $r = $this->service()->resolvePlanAmount($coachB->id, $secA->id, 'Online Class', 'individual', '3 Months');
        $this->assertFalse($r['found']);
        $this->assertSame(0.0, $r['amount']);
    }

    // ── Opt-in gate via the controller ───────────────────────────────────

    private function storeReq(User $coach, array $extra = []): array
    {
        $req = Request::create('/coach/pricing-enquiry', 'POST', array_merge([
            'category' => 'Online Class', 'course_type' => 'individual', 'time_period' => '3 Months',
            'price' => '1', 'name' => 'Visitor', 'email' => 'v@t.local', 'mobile' => '9990001111',
        ], $extra));
        $req->attributes->set('resolved_coach_id', $coach->id);   // host-resolved coach
        // resolveCoachId() reads the container's current request() — bind ours.
        $this->app->instance('request', $req);
        $resp = app(PricingEnquiryController::class)->store($req, $this->service());
        return $resp->getData(true);
    }

    /** A priced plan ALWAYS redirects to payment (no opt-in gate). */
    public function test_priced_plan_redirects_to_payment(): void
    {
        $coach = $this->coach();
        $sec   = $this->section($coach);   // Online Class · individual · 3 Months → 6000

        // Stub only the live Razorpay order-create; resolvePlanAmount runs for real,
        // proving the controller redirects to payment purely because it is priced.
        $stub = \Mockery::mock(PricingPaymentService::class, [app(PaymentGatewayResolverService::class)])->makePartial();
        $stub->shouldReceive('startPayment')->once()->andReturnUsing(function ($enq) {
            return ['ok' => true, 'mode' => 'payment', 'gateway' => 'razorpay',
                    'order_id' => 'order_STUB', 'amount' => (int) round((float) $enq->plan_amount * 100),
                    'verify_url' => 'http://x/verify'];
        });
        $this->app->instance(PricingPaymentService::class, $stub);

        $req = Request::create('/coach/pricing-enquiry', 'POST', [
            'section_id' => $sec->id, 'category' => 'Online Class', 'course_type' => 'individual',
            'time_period' => '3 Months', 'price' => '1', 'name' => 'V', 'email' => 'v@t.local', 'mobile' => '9990001111',
        ]);
        $req->attributes->set('resolved_coach_id', $coach->id);
        $this->app->instance('request', $req);

        $json = app(PricingEnquiryController::class)->store($req)->getData(true);

        $this->assertSame('payment', $json['mode'], 'a priced plan must redirect to payment');
        $this->assertSame('order_STUB', $json['order_id']);
        $this->assertSame(600000, $json['amount'], 'amount is the server-resolved 6000, in paise');

        // Enquiry is created FIRST, as Unpaid, before the redirect.
        $enq = CoachPricingEnquiry::forCoach($coach->id)->latest('id')->first();
        $this->assertSame('unpaid', $enq->payment_status);
        $this->assertSame('6000.00', (string) $enq->plan_amount);
    }

    /** No gateway anywhere (coach nor platform) → keep the visitor moving: save the lead. */
    public function test_priced_plan_without_a_gateway_falls_back_to_lead(): void
    {
        $coach = $this->coach();
        $sec   = $this->section($coach);

        $stub = \Mockery::mock(PricingPaymentService::class, [app(PaymentGatewayResolverService::class)])->makePartial();
        $stub->shouldReceive('startPayment')->once()->andReturn(['ok' => false, 'message' => 'no gateway']);
        $this->app->instance(PricingPaymentService::class, $stub);

        $req = Request::create('/coach/pricing-enquiry', 'POST', [
            'section_id' => $sec->id, 'category' => 'Online Class', 'course_type' => 'individual',
            'time_period' => '3 Months', 'price' => '1', 'name' => 'V', 'email' => 'v@t.local', 'mobile' => '9990001111',
        ]);
        $req->attributes->set('resolved_coach_id', $coach->id);
        $this->app->instance('request', $req);

        $json = app(PricingEnquiryController::class)->store($req)->getData(true);

        $this->assertSame('lead', $json['mode'], 'no gateway → graceful lead fallback');
        $enq = CoachPricingEnquiry::forCoach($coach->id)->latest('id')->first();
        $this->assertSame('unpaid', $enq->payment_status);
        $this->assertSame('6000.00', (string) $enq->plan_amount);
    }

    public function test_free_plan_stays_lead(): void
    {
        $coach = $this->coach();
        $sec   = $this->section($coach);

        $json = $this->storeReq($coach, ['section_id' => $sec->id, 'time_period' => 'Free Trial']);

        $this->assertSame('lead', $json['mode']);
        $enq = CoachPricingEnquiry::forCoach($coach->id)->latest('id')->first();
        $this->assertSame('unpaid', $enq->payment_status);
        $this->assertNull($enq->plan_amount);
        $this->assertSame(0, CoachPricingPayment::forCoach($coach->id)->count());
    }

    // ── Mark-paid: idempotent + amount-reconciled ────────────────────────

    public function test_mark_paid_is_idempotent_and_updates_the_enquiry(): void
    {
        $coach = $this->coach();
        $enq   = CoachPricingEnquiry::create([
            'coach_id' => $coach->id, 'category' => 'Online Class', 'plan_amount' => 6000,
            'currency' => 'INR', 'payment_status' => 'pending', 'name' => 'V', 'email' => 'v@t.local', 'mobile' => '9', 'status' => 'new',
        ]);
        $pay = $this->pendingPayment($coach, $enq, 6000);

        $first  = $this->service()->markPaidLocked($pay, 'pay_1', 600000, ['id' => 'pay_1']);
        $second = $this->service()->markPaidLocked($pay->fresh(), 'pay_1', 600000, ['id' => 'pay_1']);

        $this->assertTrue($first);
        $this->assertFalse($second, 'a second mark-paid must be a no-op (idempotent)');
        $this->assertSame('paid', $pay->fresh()->status);
        $this->assertSame('pay_1', $pay->fresh()->transaction_id);
        $this->assertSame('paid', $enq->fresh()->payment_status);
        $this->assertSame('6000.00', (string) $enq->fresh()->paid_amount);
    }

    public function test_coach_and_student_are_emailed_once_on_a_paid_booking(): void
    {
        Notification::fake();
        $coach = $this->coach();
        $enq   = CoachPricingEnquiry::create([
            'coach_id' => $coach->id, 'category' => 'Online', 'course_type' => 'individual',
            'time_period' => '3 Month', 'plan_amount' => 4800, 'currency' => 'INR',
            'payment_status' => 'pending', 'name' => 'Zoe', 'email' => 'zoe@t.local', 'mobile' => '9', 'status' => 'new',
        ]);
        $pay = $this->pendingPayment($coach, $enq, 4800);

        $this->service()->markPaidLocked($pay, 'pay_1', 480000, ['id' => 'pay_1']);
        // A duplicate mark-paid (double callback / webhook race) must NOT re-notify.
        $this->service()->markPaidLocked($pay->fresh(), 'pay_1', 480000, ['id' => 'pay_1']);

        // Coach: branded email + bell, exactly once.
        Notification::assertSentToTimes($coach, PricingBookingPaidToCoach::class, 1);
        // Student: coach-branded paid receipt to the guest's email (on-demand).
        Notification::assertSentOnDemand(PricingBookingReceiptToStudent::class, function ($notification, $channels, $notifiable) {
            return in_array('mail', $channels, true)
                && ($notifiable->routes['mail'] ?? null) === 'zoe@t.local';
        });
    }

    public function test_underpayment_is_rejected(): void
    {
        $coach = $this->coach();
        $enq   = CoachPricingEnquiry::create([
            'coach_id' => $coach->id, 'plan_amount' => 6000, 'currency' => 'INR',
            'payment_status' => 'pending', 'name' => 'V', 'email' => 'v@t.local', 'mobile' => '9', 'status' => 'new',
        ]);
        $pay = $this->pendingPayment($coach, $enq, 6000);

        $this->expectException(\RuntimeException::class);
        // captured 5000.00 < expected 6000.00 → must throw, never mark paid.
        $this->service()->markPaidLocked($pay, 'pay_x', 500000, []);
    }

    public function test_cancel_keeps_the_enquiry_and_flags_it_cancelled(): void
    {
        $coach = $this->coach();
        $enq   = CoachPricingEnquiry::create([
            'coach_id' => $coach->id, 'plan_amount' => 6000, 'currency' => 'INR',
            'payment_status' => 'pending', 'name' => 'V', 'email' => 'v@t.local', 'mobile' => '9', 'status' => 'new',
        ]);
        $pay = $this->pendingPayment($coach, $enq, 6000);

        $res = $this->service()->markCancelled($pay->id, $coach->id);

        $this->assertTrue($res['ok']);
        $this->assertSame('cancelled', $pay->fresh()->status);
        $this->assertSame('cancelled', $enq->fresh()->payment_status);
        $this->assertDatabaseHas('coach_pricing_enquiries', ['id' => $enq->id]); // enquiry kept
    }

    public function test_retry_after_a_failed_payment_can_succeed(): void
    {
        $coach = $this->coach();
        $enq   = CoachPricingEnquiry::create([
            'coach_id' => $coach->id, 'plan_amount' => 6000, 'currency' => 'INR',
            'payment_status' => 'failed', 'name' => 'V', 'email' => 'v@t.local', 'mobile' => '9', 'status' => 'new',
        ]);
        // First attempt failed.
        CoachPricingPayment::create([
            'coach_id' => $coach->id, 'enquiry_id' => $enq->id, 'gateway' => 'razorpay',
            'gateway_order_id' => 'order_fail', 'amount' => 6000, 'currency' => 'INR',
            'status' => CoachPricingPayment::STATUS_FAILED,
        ]);
        // Retry — a fresh pending order.
        $retry = $this->pendingPayment($coach, $enq, 6000, 'order_retry');

        $ok = $this->service()->markPaidLocked($retry, 'pay_retry', 600000, []);

        $this->assertTrue($ok);
        $this->assertSame('paid', $retry->fresh()->status);
        $this->assertSame('paid', $enq->fresh()->payment_status);
    }

    // ── Webhook (HMAC-verified) ──────────────────────────────────────────

    public function test_webhook_marks_pricing_payment_paid_with_a_valid_signature(): void
    {
        config(['services.razorpay.webhook_secret' => 'whsec_test']);
        $coach = $this->coach();
        $enq   = CoachPricingEnquiry::create([
            'coach_id' => $coach->id, 'plan_amount' => 6000, 'currency' => 'INR',
            'payment_status' => 'pending', 'name' => 'V', 'email' => 'v@t.local', 'mobile' => '9', 'status' => 'new',
        ]);
        $pay = $this->pendingPayment($coach, $enq, 6000, 'order_wh');

        $payload = json_encode(['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => [
            'id' => 'pay_wh', 'order_id' => 'order_wh', 'amount' => 600000, 'currency' => 'INR',
            'notes' => ['type' => 'pricing_plan', 'coach_id' => $coach->id, 'enquiry_id' => $enq->id],
        ]]]]);
        $sig = hash_hmac('sha256', $payload, 'whsec_test');

        // Kernel-direct dispatch — TestCase::call() snapshots config before the
        // test's config() set takes effect (see WebhookSignatureTest).
        $req = \Illuminate\Http\Request::create('/webhooks/razorpay', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_SIGNATURE' => $sig,
        ], $payload);
        $resp = app(\Illuminate\Contracts\Http\Kernel::class)->handle($req);

        $this->assertSame(200, $resp->getStatusCode(), 'body: ' . substr((string) $resp->getContent(), 0, 120));
        $this->assertSame('paid', $pay->fresh()->status);
        $this->assertSame('paid', $enq->fresh()->payment_status);
    }

    public function test_webhook_rejects_an_invalid_signature(): void
    {
        config(['services.razorpay.webhook_secret' => 'whsec_test']);
        $coach = $this->coach();
        $enq   = CoachPricingEnquiry::create([
            'coach_id' => $coach->id, 'plan_amount' => 6000, 'currency' => 'INR',
            'payment_status' => 'pending', 'name' => 'V', 'email' => 'v@t.local', 'mobile' => '9', 'status' => 'new',
        ]);
        $pay = $this->pendingPayment($coach, $enq, 6000, 'order_bad');

        $payload = json_encode(['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => [
            'id' => 'pay_bad', 'order_id' => 'order_bad', 'amount' => 600000,
            'notes' => ['type' => 'pricing_plan', 'coach_id' => $coach->id, 'enquiry_id' => $enq->id],
        ]]]]);

        $req = \Illuminate\Http\Request::create('/webhooks/razorpay', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_SIGNATURE' => 'deadbeef',
        ], $payload);
        $resp = app(\Illuminate\Contracts\Http\Kernel::class)->handle($req);

        $this->assertContains($resp->getStatusCode(), [400, 401, 403, 422]);
        $this->assertSame('pending', $pay->fresh()->status, 'an unverified webhook must never mark paid');
    }

    // ── Tenant isolation ─────────────────────────────────────────────────

    public function test_one_coach_cannot_cancel_another_coachs_payment(): void
    {
        $coachA = $this->coach();
        $coachB = $this->coach();
        $enqA   = CoachPricingEnquiry::create([
            'coach_id' => $coachA->id, 'plan_amount' => 6000, 'currency' => 'INR',
            'payment_status' => 'pending', 'name' => 'V', 'email' => 'v@t.local', 'mobile' => '9', 'status' => 'new',
        ]);
        $payA = $this->pendingPayment($coachA, $enqA, 6000, 'order_A');

        // coachB tries to cancel coachA's payment → scoped away, no-op.
        $this->service()->markCancelled($payA->id, $coachB->id);
        $this->assertSame('pending', $payA->fresh()->status, 'cross-coach cancel must not touch the row');

        // And the scope itself hides it.
        $this->assertNull(CoachPricingPayment::forCoach($coachB->id)->find($payA->id));
        $this->assertNotNull(CoachPricingPayment::forCoach($coachA->id)->find($payA->id));
    }
}
