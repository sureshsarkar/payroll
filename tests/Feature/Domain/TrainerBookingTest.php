<?php

namespace Tests\Feature\Domain;

use App\Http\Controllers\Frontend\PricingEnquiryController;
use App\Models\CoachPage;
use App\Models\CoachPageSection;
use App\Models\CoachPricingEnquiry;
use App\Models\CoachPricingPayment;
use App\Models\CoachTrainer;
use App\Models\TrainerSessionPackage;
use App\Models\User;
use App\Services\Payment\PaymentGatewayResolverService;
use App\Services\PricingPaymentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Trainer "Book Personal Class Session" flow (2026-07-15, Phase 2).
 *
 * Reuses the pricing payment engine: the amount is re-derived server-side from
 * the trainer's session-package (never the posted price), the package is
 * double-tenant-gated (coach + trainer) and must be active, the enquiry is
 * discriminated by enquiry_type + linked FKs, a priced package redirects to
 * payment, retry never duplicates, and the shared paid-path transitions the
 * trainer enquiry to paid.
 */
class TrainerBookingTest extends TestCase
{
    use DatabaseTransactions;

    private function coach(): User
    {
        return User::find(DB::table('users')->insertGetId([
            'role' => 'instructor', 'name' => 'Coach', 'email' => 'c' . uniqid() . '@t.local',
            'password' => bcrypt('x'), 'status' => 'active', 'is_banned' => 'no', 'created_at' => now(), 'updated_at' => now(),
        ]));
    }

    private function trainer(User $coach, bool $active = true): CoachTrainer
    {
        return CoachTrainer::create([
            'coach_id' => $coach->id, 'name' => 'Virendra Kumar',
            'slug' => CoachTrainer::uniqueSlug($coach->id, 'Virendra Kumar-' . uniqid()),
            'is_active' => $active, 'sort_order' => 1,
        ]);
    }

    private function package(CoachTrainer $t, float $price = 7000, bool $active = true): TrainerSessionPackage
    {
        return TrainerSessionPackage::create([
            'trainer_id' => $t->id, 'coach_id' => $t->coach_id, 'name' => 'Starter',
            'sessions' => 5, 'validity_value' => 15, 'validity_unit' => 'days',
            'price' => $price, 'currency' => 'INR', 'is_active' => $active, 'sort_order' => 1,
        ]);
    }

    private function service(): PricingPaymentService
    {
        return app(PricingPaymentService::class);
    }

    /** POST the trainer-booking form as the host-resolved coach; returns the JSON array. */
    private function book(User $coach, CoachTrainer $t, TrainerSessionPackage $p, array $extra = []): array
    {
        $req = Request::create('/coach/trainer-booking', 'POST', array_merge([
            'trainer_id' => $t->id, 'package_id' => $p->id, 'price' => '1',   // posted price is a decoy
            'name' => 'Zoe', 'email' => 'zoe@t.local', 'mobile' => '7897897897',
            // Phase 5 — fuller required form.
            'plan_type' => 'Offline', 'course_type' => 'Individual Plan',
            'gender' => 'Female', 'height' => '170', 'weight' => '65', 'reason' => 'Fitness',
        ], $extra));
        $req->attributes->set('resolved_coach_id', $coach->id);
        $this->app->instance('request', $req);
        return app(PricingEnquiryController::class)->storeTrainerBooking($req)->getData(true);
    }

    // ── server-side amount authority + tenant/trainer gates ──────────────

    public function test_amount_comes_from_the_package_not_the_posted_price(): void
    {
        $coach = $this->coach();
        $t = $this->trainer($coach);
        $p = $this->package($t, 7000);

        $r = $this->service()->resolveTrainerPackageAmount($coach->id, $t->id, $p->id);
        $this->assertTrue($r['found']);
        $this->assertSame(7000.0, $r['amount']);
    }

    public function test_another_coachs_package_is_never_read(): void
    {
        $coachA = $this->coach();
        $tA = $this->trainer($coachA);
        $pA = $this->package($tA, 7000);
        $coachB = $this->coach();

        $r = $this->service()->resolveTrainerPackageAmount($coachB->id, $tA->id, $pA->id);
        $this->assertFalse($r['found']);
        $this->assertSame(0.0, $r['amount']);
    }

    public function test_package_of_a_different_trainer_is_rejected(): void
    {
        $coach = $this->coach();
        $t1 = $this->trainer($coach);
        $t2 = $this->trainer($coach);
        $p2 = $this->package($t2, 5000);

        // Ask for t1 but pass t2's package → trainer gate rejects it.
        $r = $this->service()->resolveTrainerPackageAmount($coach->id, $t1->id, $p2->id);
        $this->assertFalse($r['found']);
    }

    public function test_inactive_package_is_not_payable(): void
    {
        $coach = $this->coach();
        $t = $this->trainer($coach);
        $p = $this->package($t, 7000, active: false);

        $r = $this->service()->resolveTrainerPackageAmount($coach->id, $t->id, $p->id);
        $this->assertFalse($r['found']);
    }

    // ── enquiry creation ─────────────────────────────────────────────────

    public function test_booking_stores_discriminator_fks_and_snapshot(): void
    {
        $coach = $this->coach();
        $t = $this->trainer($coach);
        $p = $this->package($t, 7000);

        $this->book($coach, $t, $p, ['problem' => 'Back pain']);

        $e = CoachPricingEnquiry::forCoach($coach->id)->latest('id')->first();
        $this->assertSame(CoachPricingEnquiry::TYPE_TRAINER_SESSION, $e->enquiry_type);
        $this->assertSame((int) $t->id, (int) $e->trainer_ref_id);
        $this->assertSame((int) $p->id, (int) $e->trainer_package_id);
        $this->assertSame('Virendra Kumar', $e->category);     // trainer snapshot
        $this->assertSame($p->label(), (string) $e->time_period); // package label snapshot
        $this->assertSame('7000.00', (string) $e->plan_amount); // server price, NOT the posted 1
        // Phase 5 — fuller form fields captured.
        $this->assertSame('Individual Plan', $e->course_type);
        $this->assertSame('Female', $e->gender);
        $this->assertSame('Fitness', $e->reason);
        $this->assertSame('Offline', $e->details['plan_type']);
        $this->assertSame('170', $e->details['height']);
        $this->assertSame('Back pain', $e->details['problem_description']);
    }

    // ── payment gating ───────────────────────────────────────────────────

    public function test_priced_package_redirects_to_payment_with_discriminator(): void
    {
        $coach = $this->coach();
        $t = $this->trainer($coach);
        $p = $this->package($t, 7000);

        $stub = \Mockery::mock(PricingPaymentService::class, [app(PaymentGatewayResolverService::class)])->makePartial();
        $stub->shouldReceive('startPayment')->once()
            ->with(\Mockery::type(CoachPricingEnquiry::class), CoachPricingEnquiry::TYPE_TRAINER_SESSION)
            ->andReturnUsing(fn ($enq, $type) => [
                'ok' => true, 'mode' => 'payment', 'order_id' => 'order_STUB',
                'amount' => (int) round((float) $enq->plan_amount * 100),
            ]);
        $this->app->instance(PricingPaymentService::class, $stub);

        $json = $this->book($coach, $t, $p);
        $this->assertSame('payment', $json['mode']);
        $this->assertSame(700000, $json['amount']);
    }

    // ── retry without duplicate ──────────────────────────────────────────

    public function test_retry_reuses_the_same_enquiry(): void
    {
        $coach = $this->coach();
        $t = $this->trainer($coach);
        $p = $this->package($t, 7000);

        // No gateway configured → startPayment returns ok:false → lead, both complete.
        $this->book($coach, $t, $p);
        $this->book($coach, $t, $p);

        $count = CoachPricingEnquiry::forCoach($coach->id)
            ->where('enquiry_type', CoachPricingEnquiry::TYPE_TRAINER_SESSION)
            ->where('email', 'zoe@t.local')->where('trainer_package_id', $p->id)->count();
        $this->assertSame(1, $count, 'a repeat submit must reuse the enquiry, not duplicate it');
    }

    // ── shared paid-path (what the webhook + verify both call) ────────────

    public function test_mark_paid_transitions_the_trainer_enquiry_to_paid(): void
    {
        $coach = $this->coach();
        $t = $this->trainer($coach);
        $p = $this->package($t, 7000);

        $enquiry = CoachPricingEnquiry::create([
            'coach_id' => $coach->id, 'enquiry_type' => CoachPricingEnquiry::TYPE_TRAINER_SESSION,
            'trainer_ref_id' => $t->id, 'trainer_package_id' => $p->id,
            'category' => $t->name, 'time_period' => $p->label(),
            'name' => 'Zoe', 'email' => 'zoe@t.local', 'mobile' => '7897897897',
            'status' => CoachPricingEnquiry::STATUS_NEW, 'plan_amount' => 7000, 'currency' => 'INR',
            'payment_status' => CoachPricingEnquiry::PAY_PENDING,
        ]);
        $payment = CoachPricingPayment::create([
            'coach_id' => $coach->id, 'enquiry_id' => $enquiry->id, 'gateway' => 'razorpay',
            'gateway_order_id' => 'order_' . uniqid(), 'amount' => 7000, 'currency' => 'INR',
            'status' => CoachPricingPayment::STATUS_PENDING,
        ]);

        $fresh = $this->service()->markPaidLocked($payment, 'pay_TEST', 700000, ['x' => 1]);
        $this->assertTrue($fresh);
        $this->assertSame('paid', $enquiry->fresh()->payment_status);
        $this->assertSame('paid', $payment->fresh()->status);

        // Idempotent — a second call does not re-fire.
        $this->assertFalse($this->service()->markPaidLocked($payment->fresh(), 'pay_TEST', 700000, ['x' => 1]));
    }

    // ── website-builder section path (self-contained, no coach-panel entity) ──

    private function section(User $coach, array $packages): CoachPageSection
    {
        $page = CoachPage::create(['coach_id' => $coach->id, 'slug' => 'p-' . uniqid(), 'page_type' => 'custom', 'title' => 'P']);
        return CoachPageSection::create([
            'coach_page_id' => $page->id, 'section_type' => 'trainer_booking_v1',
            'content_json'  => ['trainer_name' => 'Mansi Rawat', 'currency' => 'INR', 'packages' => $packages,
                                'plan_types' => ['Offline'], 'course_types' => ['Individual Plan'], 'reasons' => ['Fitness']],
            'is_visible'    => true, 'sort_order' => 1,
        ]);
    }

    private function bookSection(User $coach, CoachPageSection $sec, int $index, array $extra = []): array
    {
        $req = Request::create('/coach/trainer-booking', 'POST', array_merge([
            'section_id' => $sec->id, 'package_id' => $index, 'price' => '1',   // posted price is a decoy
            'name' => 'Zoe', 'email' => 'zoe@t.local', 'mobile' => '7897897897',
            'plan_type' => 'Offline', 'course_type' => 'Individual Plan',
            'gender' => 'Female', 'height' => '170', 'weight' => '65', 'reason' => 'Fitness',
        ], $extra));
        $req->attributes->set('resolved_coach_id', $coach->id);
        $this->app->instance('request', $req);
        return app(PricingEnquiryController::class)->storeTrainerBooking($req)->getData(true);
    }

    public function test_section_booking_takes_amount_from_section_not_posted_price(): void
    {
        $coach = $this->coach();
        $sec = $this->section($coach, [
            ['label' => '5 Session Validity 10 days', 'price' => '6000'],
            ['label' => '10 Session Validity 25 days', 'price' => '10000'],
        ]);

        $this->bookSection($coach, $sec, 1, ['problem' => 'Back pain']);   // index 1 = ₹10000

        $e = CoachPricingEnquiry::forCoach($coach->id)->latest('id')->first();
        $this->assertSame(CoachPricingEnquiry::TYPE_TRAINER_SESSION, $e->enquiry_type);
        $this->assertSame('Mansi Rawat', $e->category);
        $this->assertSame('10 Session Validity 25 days', (string) $e->time_period);
        $this->assertSame('10000.00', (string) $e->plan_amount);       // server price, NOT the posted 1
        $this->assertNull($e->trainer_ref_id);                          // no coach-panel entity
        $this->assertSame((int) $sec->id, (int) $e->details['section_id']);
        $this->assertSame('Back pain', $e->details['problem_description']);
    }

    public function test_section_of_another_coach_cannot_be_booked(): void
    {
        $coachA = $this->coach();
        $sec = $this->section($coachA, [['label' => 'L', 'price' => '6000']]);
        $coachB = $this->coach();

        // coachB submits against coachA's section → tenant gate rejects, nothing charged.
        $json = $this->bookSection($coachB, $sec, 0);
        $this->assertFalse($json['ok'] ?? true);
        $this->assertSame(0, CoachPricingEnquiry::forCoach($coachB->id)
            ->where('enquiry_type', CoachPricingEnquiry::TYPE_TRAINER_SESSION)->count());
    }
}
