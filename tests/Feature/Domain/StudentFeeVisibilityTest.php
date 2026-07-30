<?php

namespace Tests\Feature\Domain;

use App\Http\Controllers\Frontend\StudentFeePaymentController;
use App\Models\CourseBatch;
use App\Models\FeeDemand;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Order\app\Models\Enrollment;
use Tests\TestCase;

/**
 * Regression (2026-06-02) — fee demands raised by a coach were invisible on
 * the student's My Fees page.
 *
 * Root cause: the student query (and checkout + coach notify) filtered batch
 * membership by has_access=1. But a fee demand exists precisely to collect
 * payment from batch members who haven't paid yet — those members commonly
 * have has_access=0 (assigned to the batch, access granted after the fee is
 * paid). So the fee was hidden from exactly the students who owed it.
 *
 * Fix: batch membership for FEES = an enrollment row with that batch_id,
 * regardless of has_access (which gates course content, not fee visibility).
 */
class StudentFeeVisibilityTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array{0:User,1:CourseBatch} */
    private function makeCoachAndBatch(): array
    {
        $coach = User::factory()->create(['role' => 'instructor', 'coach_id' => null]);
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
        return [$coach, $batch];
    }

    private function publishedDemand(User $coach, CourseBatch $batch): FeeDemand
    {
        return FeeDemand::create([
            'coach_id' => $coach->id, 'batch_id' => $batch->id,
            'title' => 'Term 1 Fee', 'amount' => 4999.99, 'status' => 'published',
            'created_by' => $coach->id, 'due_date' => now()->addWeek(),
        ]);
    }

    public function test_unpaid_batch_member_sees_the_fee_demand(): void
    {
        [$coach, $batch] = $this->makeCoachAndBatch();
        $student = User::factory()->create(['role' => 'student']);

        // THE BUG SCENARIO: enrolled in the batch but has_access = 0 (owes fee).
        Enrollment::create([
            'user_id' => $student->id, 'course_id' => $batch->course_id,
            'batch_id' => $batch->id, 'has_access' => 0,
        ]);
        $demand = $this->publishedDemand($coach, $batch);

        Auth::guard('web')->loginUsingId($student->id);
        $view = app(StudentFeePaymentController::class)->index();
        $ids = collect($view->getData()['demands']->items())->pluck('id')->all();

        $this->assertContains($demand->id, $ids,
            'A student in the batch (has_access=0) MUST see the published fee demand.');
    }

    public function test_unpaid_batch_member_is_not_blocked_from_checkout(): void
    {
        [$coach, $batch] = $this->makeCoachAndBatch();
        $student = User::factory()->create(['role' => 'student']);
        Enrollment::create([
            'user_id' => $student->id, 'course_id' => $batch->course_id,
            'batch_id' => $batch->id, 'has_access' => 0,
        ]);
        $demand = $this->publishedDemand($coach, $batch);

        Auth::guard('web')->loginUsingId($student->id);
        $resp = app(StudentFeePaymentController::class)
            ->checkout(Request::create('/x', 'POST'), $demand->id);

        // The enrollment gate must PASS (not 403). Without Razorpay creds in
        // the test env it then returns 503 — anything but 403 proves the
        // unpaid batch member is allowed to proceed to pay.
        $this->assertNotSame(403, $resp->getStatusCode(),
            'An unpaid batch member must be allowed to pay the fee (was 403 before the fix).');
    }

    public function test_non_member_still_blocked(): void
    {
        // The fix must NOT over-expose: a student with NO enrollment in the
        // batch still sees nothing / is blocked.
        [$coach, $batch] = $this->makeCoachAndBatch();
        $outsider = User::factory()->create(['role' => 'student']);
        $demand = $this->publishedDemand($coach, $batch);

        Auth::guard('web')->loginUsingId($outsider->id);
        $view = app(StudentFeePaymentController::class)->index();
        $ids = collect($view->getData()['demands']->items())->pluck('id')->all();

        $this->assertNotContains($demand->id, $ids,
            'A non-member must NOT see another batch\'s fee demand.');
    }
}
