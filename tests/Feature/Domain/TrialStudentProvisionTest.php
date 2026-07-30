<?php

namespace Tests\Feature\Domain;

use App\Models\CoachStudentLink;
use App\Models\CoachTrialEnquiry;
use App\Models\CoachTrialSetting;
use App\Models\CoachTrialSlot;
use App\Models\User;
use App\Notifications\TrialStudentWelcomeToStudent;
use App\Services\TrialSessionService;
use App\Services\TrialStudentProvisioner;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Task 1 — a completed trial provisions a coach-scoped student account, reusing
 * the coach-panel logic, with duplicate prevention, once-per-coach guard, secure
 * password, hashed storage, and a coach-branded welcome email.
 */
class TrialStudentProvisionTest extends TestCase
{
    use DatabaseTransactions;

    private function coach(): User
    {
        $id = DB::table('users')->insertGetId([
            'role' => 'instructor', 'name' => 'Coach ' . uniqid(), 'email' => 'c' . uniqid() . '@t.local',
            'password' => bcrypt('x'), 'status' => 'active', 'is_banned' => 'no', 'created_at' => now(), 'updated_at' => now(),
        ]);
        return User::find($id);
    }

    private function enquiry(User $coach, string $email, string $pay = 'paid', array $o = []): CoachTrialEnquiry
    {
        return CoachTrialEnquiry::create(array_merge([
            'coach_id' => $coach->id, 'name' => 'Ravi Kumar', 'email' => $email, 'mobile' => '9876543210',
            'price' => 51, 'currency' => 'INR', 'status' => 'pending', 'payment_status' => $pay,
            'time_slot' => '06:00 AM - Guru',
        ], $o));
    }

    /* ───────────── provisioner ───────────── */

    public function test_new_email_creates_a_coach_scoped_student(): void
    {
        $coach = $this->coach();
        $email = 'new' . uniqid() . '@ex.com';
        $enq = $this->enquiry($coach, $email);

        $res = app(TrialStudentProvisioner::class)->provision($enq);

        $this->assertTrue($res['created']);
        $u = $res['user'];
        $this->assertNotNull($u);
        $this->assertSame('student', $u->role);
        $this->assertSame('active', $u->status);
        $this->assertNotNull($u->email_verified_at);
        $this->assertSame((int) $coach->id, (int) $u->added_by);

        // Password hashed + never plaintext; the one-time plain meets complexity.
        $this->assertTrue(Hash::check($res['plain'], $u->password));
        $this->assertNotSame($res['plain'], $u->password);
        $this->assertMatchesRegularExpression('/[A-Z]/', $res['plain']);
        $this->assertMatchesRegularExpression('/[a-z]/', $res['plain']);
        $this->assertMatchesRegularExpression('/[0-9]/', $res['plain']);
        $this->assertMatchesRegularExpression('/[^A-Za-z0-9]/', $res['plain']);
        $this->assertGreaterThanOrEqual(10, strlen($res['plain']));
        $this->assertLessThanOrEqual(12, strlen($res['plain']));

        // Linked to the coach's roster + stamped on the enquiry.
        $this->assertTrue(in_array((int) $u->id, CoachStudentLink::studentIdsForCoach($coach->id), true));
        $this->assertSame((int) $u->id, (int) $enq->fresh()->student_id);
        $this->assertTrue((bool) $enq->fresh()->student_was_new);
    }

    public function test_existing_student_is_linked_without_new_credentials(): void
    {
        $coach = $this->coach();
        $email = 'exist' . uniqid() . '@ex.com';
        $student = User::create(['role' => 'student', 'name' => 'Old', 'email' => $email, 'password' => Hash::make('keepme'), 'status' => 'active', 'is_banned' => 'no']);
        $enq = $this->enquiry($coach, $email);

        $res = app(TrialStudentProvisioner::class)->provision($enq);

        $this->assertFalse($res['created']);
        $this->assertNull($res['plain']);                         // no new password
        $this->assertTrue(Hash::check('keepme', $student->fresh()->password)); // password untouched
        $this->assertSame((int) $student->id, (int) $res['user']->id);
        $this->assertFalse((bool) $enq->fresh()->student_was_new);
        $this->assertTrue(in_array((int) $student->id, CoachStudentLink::studentIdsForCoach($coach->id), true));
    }

    public function test_non_student_email_is_never_converted(): void
    {
        $coach = $this->coach();
        $other = $this->coach(); // an instructor account owning the email
        $enq = $this->enquiry($coach, $other->email);

        $res = app(TrialStudentProvisioner::class)->provision($enq);

        $this->assertFalse($res['created']);
        $this->assertNull($res['user']);
        $this->assertSame('email_non_student', $res['reason']);
        $this->assertSame('instructor', $other->fresh()->role); // unchanged
    }

    /* ───────────── once-per-coach guard ───────────── */

    public function test_trial_already_used_guard(): void
    {
        $coach = $this->coach();
        $email = 'used' . uniqid() . '@ex.com';
        $this->assertFalse(CoachTrialEnquiry::trialAlreadyUsed($coach->id, $email));

        $this->enquiry($coach, $email, 'paid');
        $this->assertTrue(CoachTrialEnquiry::trialAlreadyUsed($coach->id, $email));

        // Same email under a DIFFERENT coach is NOT blocked (per-coach only).
        $coachB = $this->coach();
        $this->assertFalse(CoachTrialEnquiry::trialAlreadyUsed($coachB->id, $email));
    }

    /* ───────────── full free-mode flow via the service ───────────── */

    public function test_free_trial_booking_provisions_student_and_sends_welcome(): void
    {
        Notification::fake();
        $coach = $this->coach();
        $settings = CoachTrialSetting::create([
            'coach_id' => $coach->id, 'is_enabled' => true, 'price' => 0,
            'require_payment' => false, 'currency' => 'INR', 'currency_icon' => '₹',
        ]);
        $slot = CoachTrialSlot::create(['coach_id' => $coach->id, 'label' => '06:00 AM - Guru', 'is_active' => true]);
        $email = 'free' . uniqid() . '@ex.com';
        $data = [
            'name' => 'Neha', 'email' => $email, 'mobile' => '9998887770',
            'plan_type' => 'online', 'course_type' => 'individual',
            'slot_id' => $slot->id, 'time_slot' => $slot->label, 'gender' => 'female', 'reason' => 'fitness',
        ];

        $res = app(TrialSessionService::class)->book($coach->id, $settings, $data, Request::create('/', 'POST', $data));
        $this->assertTrue($res['ok']);

        $student = User::where('email', $email)->first();
        $this->assertNotNull($student);
        $this->assertSame('student', $student->role);
        Notification::assertSentTo($student, TrialStudentWelcomeToStudent::class);
    }
}
