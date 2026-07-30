<?php

namespace Tests\Feature\Domain;

use App\Http\Controllers\Frontend\Coach\CoachTrialSessionController;
use App\Http\Controllers\Frontend\TrialSessionController;
use App\Models\CoachTrialEnquiry;
use App\Models\CoachTrialPayment;
use App\Models\CoachTrialSetting;
use App\Models\CoachTrialSlot;
use App\Models\User;
use App\Notifications\TrialBookingToCoach;
use App\Notifications\TrialBookingToStudent;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * "Book Your Trial Session" — coach config + slots CRUD + the public guest
 * submission flow (free mode), validation, tenant isolation, dedup and the
 * white-label notifications. The Razorpay charge path (external API) is covered
 * by the graceful "not configured" branch here; live gateway calls are not hit.
 */
class TrialSessionBookingTest extends TestCase
{
    use DatabaseTransactions;

    private function coach(): User
    {
        $id = DB::table('users')->insertGetId([
            'role' => 'instructor', 'name' => 'Coach ' . uniqid(), 'email' => 'c' . uniqid() . '@t.local',
            'password' => bcrypt('x'), 'status' => 'active', 'is_banned' => 'no',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return User::find($id);
    }

    private function enable(User $coach, array $overrides = []): CoachTrialSetting
    {
        return CoachTrialSetting::create(array_merge([
            'coach_id' => $coach->id, 'is_enabled' => true, 'price' => 0,
            'require_payment' => false, 'currency' => 'INR', 'currency_icon' => '₹',
        ], $overrides));
    }

    private function submit(int $coachId, array $body): \Illuminate\Http\JsonResponse
    {
        $req = Request::create('/coach/trial-session', 'POST', $body);
        $req->attributes->set('resolved_coach_id', $coachId);
        app()->instance('request', $req);
        return app(TrialSessionController::class)->submit($req);
    }

    private function validBody(int $slotId, array $o = []): array
    {
        return array_merge([
            'name' => 'Ravi Kumar', 'email' => 'ravi' . uniqid() . '@ex.com', 'mobile' => '9876543210',
            'plan_type' => 'online', 'course_type' => 'individual', 'slot_id' => $slotId,
            'gender' => 'male', 'reason' => 'fitness',
        ], $o);
    }

    /* ───────────── coach settings + slots ───────────── */

    public function test_coach_can_save_settings_and_manage_slots(): void
    {
        $coach = $this->coach();
        $this->actingAs($coach);
        $ctrl = app(CoachTrialSessionController::class);

        $ctrl->update(Request::create('/', 'PUT', [
            'is_enabled' => '1', 'price' => '51', 'require_payment' => '1',
            'title' => 'Book Trial', 'currency_icon' => '₹', 'payment_gateway' => 'razorpay',
            'show_frequency' => 'session', 'show_delay_seconds' => '2',
        ]));

        $s = CoachTrialSetting::forCoach($coach->id);
        $this->assertTrue($s->is_enabled);
        $this->assertSame('51.00', (string) $s->price);
        $this->assertTrue($s->chargesMoney());

        $ctrl->storeSlot(Request::create('/', 'POST', ['label' => '05:00 AM - Mansi Rawat']));
        $this->assertDatabaseHas('coach_trial_slots', ['coach_id' => $coach->id, 'label' => '05:00 AM - Mansi Rawat']);
    }

    public function test_slot_management_is_tenant_scoped(): void
    {
        $a = $this->coach();
        $b = $this->coach();
        $slot = CoachTrialSlot::create(['coach_id' => $a->id, 'label' => 'A slot', 'is_active' => true]);

        $this->actingAs($b);
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(CoachTrialSessionController::class)->destroySlot($slot->id); // b cannot delete a's slot
    }

    /* ───────────── public free-mode submission ───────────── */

    public function test_free_submission_creates_enquiry_and_notifies(): void
    {
        Notification::fake();
        $coach = $this->coach();
        $this->enable($coach); // free (price 0)
        $slot = CoachTrialSlot::create(['coach_id' => $coach->id, 'label' => '06:00 AM - Guru', 'is_active' => true]);

        $res = $this->submit($coach->id, $this->validBody($slot->id));
        $this->assertSame(200, $res->getStatusCode());
        $j = $res->getData();
        $this->assertTrue($j->ok);
        $this->assertSame('free', $j->mode);

        $e = CoachTrialEnquiry::forCoach($coach->id)->first();
        $this->assertNotNull($e);
        $this->assertSame('free', $e->payment_status);
        $this->assertSame('06:00 AM - Guru', $e->time_slot); // label snapshot server-side
        $this->assertNotNull($e->ip_address);

        Notification::assertSentTo($coach, TrialBookingToCoach::class);
        Notification::assertSentOnDemand(TrialBookingToStudent::class);
    }

    public function test_disabled_popup_rejects_submission(): void
    {
        $coach = $this->coach();
        $this->enable($coach, ['is_enabled' => false]);
        $slot = CoachTrialSlot::create(['coach_id' => $coach->id, 'label' => 'x', 'is_active' => true]);

        $res = $this->submit($coach->id, $this->validBody($slot->id));
        $this->assertSame(403, $res->getStatusCode());
        $this->assertFalse($res->getData()->ok);
    }

    public function test_validation_rejects_bad_input(): void
    {
        $coach = $this->coach();
        $this->enable($coach);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->submit($coach->id, ['name' => '', 'email' => 'not-an-email']); // missing required + bad email
    }

    public function test_slot_must_belong_to_this_coach(): void
    {
        $coach = $this->coach();
        $other = $this->coach();
        $this->enable($coach);
        $foreignSlot = CoachTrialSlot::create(['coach_id' => $other->id, 'label' => 'foreign', 'is_active' => true]);

        $res = $this->submit($coach->id, $this->validBody($foreignSlot->id));
        $this->assertSame(422, $res->getStatusCode());
        $this->assertObjectHasProperty('errors', $res->getData());
    }

    public function test_duplicate_submission_is_guarded(): void
    {
        Notification::fake();
        $coach = $this->coach();
        $this->enable($coach);
        $slot = CoachTrialSlot::create(['coach_id' => $coach->id, 'label' => 's', 'is_active' => true]);
        $body = $this->validBody($slot->id, ['email' => 'dup@ex.com', 'mobile' => '9998887770']);

        $this->submit($coach->id, $body);
        $this->submit($coach->id, $body); // within 120s window

        $this->assertSame(1, CoachTrialEnquiry::forCoach($coach->id)->count());
    }

    public function test_paid_mode_without_gateway_creds_fails_gracefully(): void
    {
        Notification::fake();
        $coach = $this->coach();
        $this->enable($coach, ['price' => 51, 'require_payment' => true]); // no razorpay creds configured
        $slot = CoachTrialSlot::create(['coach_id' => $coach->id, 'label' => 's', 'is_active' => true]);

        $res = $this->submit($coach->id, $this->validBody($slot->id));
        // Enquiry is still captured as an unpaid lead; the visitor gets a clear error.
        $this->assertSame(422, $res->getStatusCode());
        $this->assertFalse($res->getData()->ok);
        $e = CoachTrialEnquiry::forCoach($coach->id)->first();
        $this->assertSame('unpaid', $e->payment_status);
    }
}
