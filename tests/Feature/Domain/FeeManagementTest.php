<?php

namespace Tests\Feature\Domain;

use App\Models\CourseBatch;
use App\Models\FeeDemand;
use App\Models\FeePayment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Fee Management — Phase 4B foundation.
 *
 * Pins schema invariants + the core model contracts so the gateway
 * integration phase can build on a stable base.
 */
class FeeManagementTest extends TestCase
{
    use DatabaseTransactions;

    /* ───────────────── schema ───────────────── */

    public function test_fee_demands_table_has_required_columns(): void
    {
        $cols = collect(DB::select('SHOW COLUMNS FROM fee_demands'))->pluck('Field')->all();
        foreach (['id', 'coach_id', 'batch_id', 'title', 'amount', 'due_date',
                  'late_fine_per_day', 'notes', 'status', 'created_by',
                  'created_at', 'updated_at'] as $col) {
            $this->assertContains($col, $cols, "fee_demands.$col must exist (migration 2026_05_19_130000).");
        }
    }

    public function test_fee_payments_table_has_required_columns(): void
    {
        $cols = collect(DB::select('SHOW COLUMNS FROM fee_payments'))->pluck('Field')->all();
        foreach (['id', 'fee_demand_id', 'student_id', 'receipt_no', 'amount',
                  'gateway', 'gateway_txn_id', 'status', 'paid_at',
                  'refunded_at', 'note', 'recorded_by',
                  'created_at', 'updated_at'] as $col) {
            $this->assertContains($col, $cols, "fee_payments.$col must exist (migration 2026_05_19_140000).");
        }
    }

    public function test_fee_payments_receipt_no_is_unique(): void
    {
        $idx = collect(DB::select(
            "SHOW INDEX FROM fee_payments WHERE Non_unique=0 AND Column_name='receipt_no'"
        ));
        $this->assertNotEmpty($idx, 'fee_payments.receipt_no must have a UNIQUE index.');
    }

    /* ───────────────── model behaviour ───────────────── */

    public function test_receipt_no_generator_produces_unique_values(): void
    {
        // Hammer it: 50 receipts must all be unique.
        $seen = [];
        for ($i = 0; $i < 50; $i++) {
            $rcp = FeePayment::generateReceiptNo();
            $this->assertStringStartsWith('RCP-', $rcp);
            $this->assertNotContains($rcp, $seen, 'Receipt generator must not repeat in a single batch.');
            $seen[] = $rcp;
        }
    }

    public function test_demand_collected_amount_sums_only_paid_status(): void
    {
        $coach = User::factory()->create(['role' => 'instructor']);
        $student = User::factory()->create(['role' => 'student']);
        $batch = $this->makeBatchForCoach($coach);

        $demand = FeeDemand::create([
            'coach_id'   => $coach->id,
            'batch_id'   => $batch->id,
            'title'      => 'Test fee',
            'amount'     => 1000,
            'status'     => 'published',
            'created_by' => $coach->id,
        ]);

        // 3 payments — 2 paid, 1 refunded, 1 failed
        FeePayment::create([
            'fee_demand_id' => $demand->id, 'student_id' => $student->id,
            'receipt_no' => FeePayment::generateReceiptNo(),
            'amount' => 500, 'gateway' => 'manual', 'status' => 'paid',
            'paid_at' => now(),
        ]);
        FeePayment::create([
            'fee_demand_id' => $demand->id, 'student_id' => $student->id,
            'receipt_no' => FeePayment::generateReceiptNo(),
            'amount' => 200, 'gateway' => 'manual', 'status' => 'paid',
            'paid_at' => now(),
        ]);
        FeePayment::create([
            'fee_demand_id' => $demand->id, 'student_id' => $student->id,
            'receipt_no' => FeePayment::generateReceiptNo(),
            'amount' => 100, 'gateway' => 'manual', 'status' => 'refunded',
            'paid_at' => now()->subDay(), 'refunded_at' => now(),
        ]);
        FeePayment::create([
            'fee_demand_id' => $demand->id, 'student_id' => $student->id,
            'receipt_no' => FeePayment::generateReceiptNo(),
            'amount' => 300, 'gateway' => 'razorpay', 'status' => 'failed',
        ]);

        $this->assertSame(700.0, (float) $demand->fresh()->collectedAmount(),
            'collectedAmount should sum only status=paid rows (500 + 200 = 700)');
    }

    public function test_for_coach_scope_isolates_demands(): void
    {
        $coachA = User::factory()->create(['role' => 'instructor']);
        $coachB = User::factory()->create(['role' => 'instructor']);
        $batchA = $this->makeBatchForCoach($coachA);
        $batchB = $this->makeBatchForCoach($coachB);

        FeeDemand::create([
            'coach_id'   => $coachA->id, 'batch_id' => $batchA->id,
            'title' => 'A fee', 'amount' => 100, 'status' => 'published',
            'created_by' => $coachA->id,
        ]);
        FeeDemand::create([
            'coach_id'   => $coachB->id, 'batch_id' => $batchB->id,
            'title' => 'B fee', 'amount' => 100, 'status' => 'published',
            'created_by' => $coachB->id,
        ]);

        $this->assertSame(1, FeeDemand::forCoach($coachA->id)->count(),
            'Coach A must only see their own demand');
        $this->assertSame(1, FeeDemand::forCoach($coachB->id)->count(),
            'Coach B must only see their own demand');
        $this->assertSame(0, FeeDemand::forCoach(99999999)->count(),
            'Unknown coach must see zero demands');
    }

    /* ───────────────── controller contracts ───────────────── */

    public function test_controller_methods_exist(): void
    {
        $ref = new \ReflectionClass(
            \App\Http\Controllers\Frontend\Coach\FeeManagementController::class
        );
        foreach (['index', 'storeDemand', 'transactions', 'recordPayment', 'refundPayment'] as $m) {
            $this->assertTrue($ref->hasMethod($m),
                "FeeManagementController must expose $m()");
        }
    }

    public function test_routes_are_registered(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes());
        foreach ([
            'instructor.fees.index',
            'instructor.fees.demands.store',
            'instructor.fees.transactions',
            'instructor.fees.payments.store',
            'instructor.fees.payments.refund',
        ] as $name) {
            $this->assertNotNull(
                $routes->first(fn ($r) => $r->getName() === $name),
                "Route $name must be registered."
            );
        }
    }

    /* ───────────────── helpers ───────────────── */

    private function makeBatchForCoach(User $coach): CourseBatch
    {
        $courseId = DB::table('courses')->insertGetId([
            'title'         => 'Fee test ' . uniqid(),
            'slug'          => 'fee-' . uniqid(),
            'instructor_id' => $coach->id,
            'added_by'      => $coach->id,
            'is_approved'   => 'approved',
            'status'        => 'active',
            'type'          => 'live',
            'price'         => 0, 'discount' => 0,
            'created_at'    => now(), 'updated_at' => now(),
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
}
