<?php

namespace Tests\Feature\Domain;

use App\Http\Controllers\Frontend\PricingEnquiryController;
use App\Models\CoachLandingPage;
use App\Models\CoachPage;
use App\Models\CoachPageSection;
use App\Models\CoachPricingEnquiry;
use App\Models\User;
use App\Services\Payment\PaymentGatewayResolverService;
use App\Services\PricingPaymentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Classes & Schedules "Book a Session" flow (2026-07-14).
 *
 * Reuses the pricing payment engine: the amount is re-derived server-side from
 * the schedule_v1 section's `periods` config (never the posted price), the
 * enquiry stores class/slot/trainer + plan/course/period/body-metrics, a priced
 * period redirects to payment, an unpriced one stays a lead, retry never
 * duplicates the enquiry, and sections are strictly coach-scoped.
 */
class ScheduleBookingTest extends TestCase
{
    use DatabaseTransactions;

    private function coach(): User
    {
        return User::find(DB::table('users')->insertGetId([
            'role' => 'instructor', 'name' => 'Coach', 'email' => 'c' . uniqid() . '@t.local',
            'password' => bcrypt('x'), 'status' => 'active', 'is_banned' => 'no', 'created_at' => now(), 'updated_at' => now(),
        ]));
    }

    private function scheduleSection(User $coach, ?array $content = null): CoachPageSection
    {
        $content ??= [
            'title'        => 'Classes',
            'items'        => [['time' => '06:00 AM', 'title' => 'Morning Yoga', 'instructor' => 'Mansi Rawat']],
            'plan_types'   => ['Online', 'Offline'],
            'course_types' => ['Individual Plan', 'Couple Plan'],
            'periods'      => [['label' => '1 Month', 'price' => '2500'], ['label' => '2 Months', 'price' => '6000']],
            'reasons'      => ['Fitness', 'Problem'],
        ];
        $lp   = CoachLandingPage::create(['added_by' => $coach->id, 'website_name' => 'S', 'title' => 'S', 'slug' => 's' . uniqid()]);
        $page = CoachPage::create(['coach_id' => $coach->id, 'site_id' => $lp->id, 'slug' => 'p' . uniqid(), 'page_type' => 'home', 'title' => 'H']);

        return CoachPageSection::create([
            'coach_page_id' => $page->id, 'section_type' => 'schedule_v1', 'section_version' => 1,
            'content_json' => $content, 'sort_order' => 0, 'is_visible' => true,
        ]);
    }

    private function service(): PricingPaymentService
    {
        return app(PricingPaymentService::class);
    }

    /** POST the booking form as the host-resolved coach; returns the JSON array. */
    private function book(User $coach, array $extra = []): array
    {
        $req = Request::create('/coach/schedule-booking', 'POST', array_merge([
            'class_name' => 'Morning Yoga', 'class_time' => '06:00 AM', 'trainer' => 'Mansi Rawat',
            'schedule_id' => 'Morning Yoga', 'source_page' => 'Classes', 'source_button' => 'Book Now',
            'plan_type' => 'Online', 'course_type' => 'Individual Plan', 'price' => '999',
            'name' => 'Zoe', 'email' => 'zoe@t.local', 'mobile' => '7897897897',
        ], $extra));
        $req->attributes->set('resolved_coach_id', $coach->id);
        $this->app->instance('request', $req);
        return app(PricingEnquiryController::class)->storeScheduleBooking($req)->getData(true);
    }

    // ── server-side amount authority ─────────────────────────────────────

    public function test_amount_comes_from_the_section_period_not_the_posted_price(): void
    {
        $coach = $this->coach();
        $sec   = $this->scheduleSection($coach);

        $r = $this->service()->resolveScheduleAmount($coach->id, $sec->id, '2 Months');
        $this->assertTrue($r['found']);
        $this->assertSame(6000.0, $r['amount']);

        $r1 = $this->service()->resolveScheduleAmount($coach->id, $sec->id, '1 Month');
        $this->assertSame(2500.0, $r1['amount']);
    }

    public function test_unknown_period_resolves_to_zero(): void
    {
        $coach = $this->coach();
        $sec   = $this->scheduleSection($coach);
        $r = $this->service()->resolveScheduleAmount($coach->id, $sec->id, '9 Years');
        $this->assertFalse($r['found']);
        $this->assertSame(0.0, $r['amount']);
    }

    public function test_another_coachs_schedule_section_is_never_read(): void
    {
        $coachA = $this->coach();
        $secA   = $this->scheduleSection($coachA);
        $coachB = $this->coach();
        $r = $this->service()->resolveScheduleAmount($coachB->id, $secA->id, '2 Months');
        $this->assertFalse($r['found']);
        $this->assertSame(0.0, $r['amount']);
    }

    // ── enquiry creation ─────────────────────────────────────────────────

    public function test_booking_stores_class_slot_trainer_and_body_metrics(): void
    {
        $coach = $this->coach();
        $sec   = $this->scheduleSection($coach);

        $this->book($coach, ['section_id' => $sec->id, 'time_period' => '2 Months', 'reason' => 'Problem', 'problem' => 'Back pain', 'height' => '170', 'weight' => '65']);

        $e = CoachPricingEnquiry::forCoach($coach->id)->latest('id')->first();
        $this->assertSame('Morning Yoga', $e->schedule_id);
        $this->assertSame('06:00 AM', $e->time_slot);
        $this->assertSame('Mansi Rawat', $e->trainer_id);
        $this->assertSame('Online', $e->category);          // plan type
        $this->assertSame('Individual Plan', $e->course_type);
        $this->assertSame('2 Months', $e->time_period);
        $this->assertSame('6000.00', (string) $e->plan_amount);
        $this->assertSame('Morning Yoga', $e->details['class_name']);
        $this->assertSame('170', $e->details['height']);
        $this->assertSame('Back pain', $e->details['problem_description']);
    }

    // ── payment gating ───────────────────────────────────────────────────

    public function test_priced_period_redirects_to_payment(): void
    {
        $coach = $this->coach();
        $sec   = $this->scheduleSection($coach);

        // Stub only the live Razorpay order-create; gates run for real.
        $stub = \Mockery::mock(PricingPaymentService::class, [app(PaymentGatewayResolverService::class)])->makePartial();
        $stub->shouldReceive('startPayment')->once()->andReturnUsing(fn ($enq) => [
            'ok' => true, 'mode' => 'payment', 'order_id' => 'order_STUB',
            'amount' => (int) round((float) $enq->plan_amount * 100), 'verify_url' => 'x',
        ]);
        $this->app->instance(PricingPaymentService::class, $stub);

        $json = $this->book($coach, ['section_id' => $sec->id, 'time_period' => '2 Months']);
        $this->assertSame('payment', $json['mode']);
        $this->assertSame(600000, $json['amount']);
    }

    public function test_section_without_priced_periods_stays_a_lead(): void
    {
        $coach = $this->coach();
        // periods present but the picked one has no price → not found → lead.
        $sec = $this->scheduleSection($coach, [
            'items'   => [['time' => '06:00 AM', 'title' => 'Yoga', 'instructor' => 'M']],
            'periods' => [['label' => 'Free intro', 'price' => '']],
        ]);

        $json = $this->book($coach, ['section_id' => $sec->id, 'time_period' => 'Free intro']);
        $this->assertSame('lead', $json['mode']);
        $e = CoachPricingEnquiry::forCoach($coach->id)->latest('id')->first();
        $this->assertSame('unpaid', $e->payment_status);
        $this->assertNull($e->plan_amount);
    }

    // ── submit-time emails ───────────────────────────────────────────────

    public function test_submitting_emails_the_coach_and_the_student(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $coach = $this->coach();
        $sec   = $this->scheduleSection($coach);

        $this->book($coach, ['section_id' => $sec->id, 'time_period' => '2 Months']);

        \Illuminate\Support\Facades\Notification::assertSentTo($coach, \App\Notifications\BookingEnquiryToCoach::class);
        \Illuminate\Support\Facades\Notification::assertSentOnDemand(\App\Notifications\BookingEnquiryToStudent::class);
    }

    // ── retry without duplicate ──────────────────────────────────────────

    public function test_retry_reuses_the_same_enquiry(): void
    {
        $coach = $this->coach();
        $sec   = $this->scheduleSection($coach);

        // Force the lead path (no gateway) so both submits complete without network.
        $this->book($coach, ['section_id' => $sec->id, 'time_period' => '2 Months']);
        $this->book($coach, ['section_id' => $sec->id, 'time_period' => '2 Months']);

        $count = CoachPricingEnquiry::forCoach($coach->id)
            ->where('email', 'zoe@t.local')->where('schedule_id', 'Morning Yoga')->count();
        $this->assertSame(1, $count, 'a repeat submit must reuse the enquiry, not duplicate it');
    }
}
