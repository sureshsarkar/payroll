<?php

namespace Tests\Feature\Domain;

use App\Http\Controllers\Frontend\Coach\FeeManagementController;
use App\Models\CourseBatch;
use App\Models\FeeDemand;
use App\Models\FeePayment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Order\app\Models\Enrollment;
use Tests\TestCase;

/**
 * 2026-07-10 — Offline fee payment (coach records a cash/UPI/cheque/bank
 * payment against a demand; just adds a paid record, no gateway).
 * Exercises the extended recordPayment (paid_at + reference_no + upi) and
 * the new studentsForDemand endpoint that powers the modal.
 */
class OfflineFeePaymentTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array{0:User,1:CourseBatch,2:FeeDemand,3:User} */
    private function scaffold(float $amount = 5000): array
    {
        $coach = User::factory()->create(['role' => 'instructor', 'coach_id' => null]);
        $courseId = DB::table('courses')->insertGetId([
            'title' => 'Fee Course ' . uniqid(), 'slug' => 'fc-' . uniqid(),
            'instructor_id' => $coach->id, 'added_by' => $coach->id,
            'is_approved' => 'approved', 'status' => 'active', 'type' => 'live',
            'price' => 0, 'discount' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $batch = CourseBatch::query()->forceCreate([
            'course_id' => $courseId, 'title' => 'B ' . uniqid(),
            'start_date' => now(), 'end_date' => now()->addMonth(),
            'start_time' => '09:00:00', 'end_time' => '10:00:00',
            'capacity' => 30, 'days' => ['monday'], 'status' => 'active',
        ]);
        $demand = FeeDemand::query()->forceCreate([
            'coach_id' => $coach->id, 'batch_id' => $batch->id, 'title' => 'Term 1 Fee',
            'amount' => $amount, 'status' => 'published', 'created_by' => $coach->id,
        ]);
        $student = User::factory()->create(['role' => 'student']);
        Enrollment::create([
            'user_id' => $student->id, 'course_id' => $courseId,
            'batch_id' => $batch->id, 'has_access' => 0,
        ]);
        return [$coach, $batch, $demand, $student];
    }

    public function test_records_offline_payment_with_method_date_and_reference(): void
    {
        [$coach, , $demand, $student] = $this->scaffold();
        $this->actingAs($coach, 'web');

        $req = Request::create('/', 'POST', [
            'student_id'   => $student->id,
            'amount'       => 2000,
            'gateway'      => 'upi',
            'paid_at'      => now()->subDay()->toDateString(),
            'reference_no' => 'UPI-TXN-12345',
            'note'         => 'Received at desk',
        ]);
        app(FeeManagementController::class)->recordPayment($req, $demand->id);

        $p = FeePayment::where('fee_demand_id', $demand->id)->first();
        $this->assertNotNull($p, 'a payment row must be created');
        $this->assertSame('paid', $p->status);
        $this->assertSame('upi', $p->gateway);
        $this->assertEquals(2000, (float) $p->amount);
        $this->assertSame('UPI-TXN-12345', $p->reference_no);
        $this->assertNotNull($p->receipt_no);
        $this->assertSame(now()->subDay()->toDateString(), $p->paid_at->toDateString(), 'back-dated receipt honoured');
        $this->assertSame($coach->id, $p->recorded_by);
    }

    public function test_future_paid_at_is_clamped_to_now(): void
    {
        [$coach, , $demand, $student] = $this->scaffold();
        $this->actingAs($coach, 'web');

        $req = Request::create('/', 'POST', [
            'student_id' => $student->id, 'amount' => 100, 'gateway' => 'cash',
            'paid_at'    => now()->addWeek()->toDateString(),
        ]);
        app(FeeManagementController::class)->recordPayment($req, $demand->id);

        $p = FeePayment::where('fee_demand_id', $demand->id)->first();
        $this->assertTrue($p->paid_at->lessThanOrEqualTo(now()->addMinute()), 'a future date must clamp to now');
    }

    public function test_rejects_student_not_enrolled_in_the_batch(): void
    {
        [$coach, , $demand] = $this->scaffold();
        $outsider = User::factory()->create(['role' => 'student']); // exists, but not enrolled
        $this->actingAs($coach, 'web');

        $req = Request::create('/', 'POST', ['student_id' => $outsider->id, 'amount' => 100, 'gateway' => 'cash']);
        app(FeeManagementController::class)->recordPayment($req, $demand->id);

        $this->assertSame(0, FeePayment::where('fee_demand_id', $demand->id)->count(),
            'a non-enrolled student must not get a recorded payment');
    }

    public function test_students_for_demand_lists_batch_students_with_outstanding(): void
    {
        [$coach, , $demand, $student] = $this->scaffold(5000);
        $this->actingAs($coach, 'web');

        $data = app(FeeManagementController::class)->studentsForDemand($demand->id)->getData(true);
        $this->assertEquals(5000, $data['amount']);
        $this->assertCount(1, $data['students']);
        $this->assertEquals(5000, $data['students'][0]['outstanding']);

        // A partial payment reduces the outstanding.
        FeePayment::create([
            'fee_demand_id' => $demand->id, 'student_id' => $student->id,
            'receipt_no' => FeePayment::generateReceiptNo(), 'amount' => 2000,
            'gateway' => 'cash', 'status' => 'paid', 'paid_at' => now(), 'recorded_by' => $coach->id,
        ]);
        $data2 = app(FeeManagementController::class)->studentsForDemand($demand->id)->getData(true);
        $this->assertEquals(3000, $data2['students'][0]['outstanding']);
    }

    public function test_cannot_record_against_another_coachs_demand(): void
    {
        [, , $demand, $student] = $this->scaffold();
        $otherCoach = User::factory()->create(['role' => 'instructor', 'coach_id' => null]);
        $this->actingAs($otherCoach, 'web');

        $req = Request::create('/', 'POST', ['student_id' => $student->id, 'amount' => 100, 'gateway' => 'cash']);
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(FeeManagementController::class)->recordPayment($req, $demand->id);
    }
}
