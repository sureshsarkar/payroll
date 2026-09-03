<?php

namespace Tests\Feature\Payroll;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Modules\Attendance\app\Models\Attendance;
use Modules\Payroll\app\Models\PayrollItem;
use Modules\Payroll\app\Models\PayrollRun;
use Modules\Payroll\app\Models\SalaryComponent;
use Modules\Payroll\app\Models\SalaryStructure;
use Modules\Payroll\app\Services\PayrollCalculator;
use Modules\Payroll\app\Services\PayrollRunService;
use Tests\TestCase;

/**
 * Regression coverage for a real bug (reported 2026-08-25): a payslip kept
 * showing full salary for an employee with marked Absent days.
 *
 * Root cause (confirmed by reproducing it against live data before fixing):
 * the LOP math in PayrollCalculator was already correct — gross ÷ days-in-
 * month × absent-days is deducted as a line item — but nothing ever
 * recomputed a PayrollItem once its run left DRAFT. If attendance was
 * marked/corrected AFTER a run was submitted or approved, the stale
 * pre-correction net_pay stood forever with no way to fix it. These tests
 * lock both halves: the calculation itself, and the reopen/recalculate paths
 * that keep it correct after the fact.
 */
class LossOfPayCalculationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // These tests isolate LOP behaviour; statutory deductions (PF/ESIC/PT)
        // are covered separately and would otherwise mix into every net_pay assertion.
        config([
            'payroll.statutory.pf.enabled' => false,
            'payroll.statutory.esic.enabled' => false,
            'payroll.statutory.professional_tax.enabled' => false,
        ]);
    }

    private function structure(int $userId, float $basic, float $hra = 0.0): SalaryStructure
    {
        $structure = SalaryStructure::create([
            'user_id'        => $userId,
            'ctc_annual'     => ($basic + $hra) * 12,
            'gross_monthly'  => $basic + $hra,
            'effective_from' => now()->subMonths(6)->toDateString(),
            'is_current'     => true,
        ]);

        SalaryComponent::create([
            'salary_structure_id' => $structure->id,
            'type'                => SalaryComponent::EARNING,
            'name'                => 'Basic',
            'code'                => 'BASIC',
            'calc_type'           => SalaryComponent::FIXED,
            'value'               => $basic,
            'sort_order'          => 1,
        ]);

        if ($hra > 0) {
            SalaryComponent::create([
                'salary_structure_id' => $structure->id,
                'type'                => SalaryComponent::EARNING,
                'name'                => 'HRA',
                'code'                => 'HRA',
                'calc_type'           => SalaryComponent::FIXED,
                'value'               => $hra,
                'sort_order'          => 2,
            ]);
        }

        return $structure;
    }

    private function markAbsent(int $userId, int $year, int $month, array $days): void
    {
        foreach ($days as $day) {
            (new Attendance())->forceFill([
                'user_id'         => $userId,
                'attendance_date' => Carbon::create($year, $month, $day)->toDateString(),
                'status'          => Attendance::AA,
            ])->save();
        }
    }

    /** ₹30,000/month, 2 unpaid absent days must deduct exactly 2 days' pay — the exact example from the bug report. */
    public function test_absent_days_are_deducted_proportionally(): void
    {
        $userId = 970001;
        $this->structure($userId, 30000);
        $this->markAbsent($userId, 2026, 8, [5, 12]);

        $result = app(PayrollCalculator::class)->compute($userId, 2026, 8);

        $daysInMonth = Carbon::create(2026, 8, 1)->daysInMonth; // 31
        $expectedPerDay = 30000 / $daysInMonth;
        $expectedLop = round($expectedPerDay * 2, 2);

        $this->assertSame(2.0, $result['lop_days']);
        $this->assertSame($expectedLop, $result['lop_amount']);
        $this->assertSame(30000.0, $result['total_earnings'], 'full earnings are shown, then LOP is deducted as a line item');
        $this->assertSame(round(30000 - $expectedLop, 2), $result['net_pay']);
        $this->assertNotSame(30000.0, $result['net_pay'], 'must NOT be the full, undeducted salary');
    }

    /** No attendance marked at all (nothing Absent) must not deduct anything. */
    public function test_no_absences_means_no_lop(): void
    {
        $userId = 970002;
        $this->structure($userId, 25000);

        $result = app(PayrollCalculator::class)->compute($userId, 2026, 8);

        $this->assertSame(0.0, $result['lop_days']);
        $this->assertSame(25000.0, $result['net_pay']);
    }

    /** A half-day counts as 0.5 LOP, not a full day. */
    public function test_half_day_deducts_half_a_days_pay(): void
    {
        $userId = 970003;
        $this->structure($userId, 31000);
        (new Attendance())->forceFill([
            'user_id' => $userId, 'attendance_date' => '2026-08-10', 'status' => Attendance::PA,
        ])->save();

        $result = app(PayrollCalculator::class)->compute($userId, 2026, 8);

        $this->assertSame(0.5, $result['lop_days']);
    }

    /**
     * The actual bug: correcting attendance after a run is approved must not
     * leave the payslip silently wrong forever. isStale() must flag it, and
     * PayrollRunService::recalculate() must fix it.
     */
    public function test_recalculate_fixes_a_stale_approved_run(): void
    {
        Storage::fake('public');

        // generatePayslip() needs a real employee to build a filename/PDF from.
        $userId = User::factory()->create(['role' => 'student'])->id;
        $this->structure($userId, 30000);

        $run = PayrollRun::create(['year' => 2026, 'month' => 9, 'status' => PayrollRun::DRAFT]);
        app(PayrollRunService::class)->prepare($run, [$userId]);

        $item = PayrollItem::where('payroll_run_id', $run->id)->where('user_id', $userId)->first();
        $this->assertSame(30000.0, (float) $item->net_pay, 'no absences yet — full salary is correct at this point');

        // Fast-forward the run to approved, as if it had gone through the real workflow.
        $run->update(['status' => PayrollRun::ADMIN_APPROVED]);

        // Now attendance is corrected AFTER approval — this is the exact bug scenario.
        sleep(1); // ensure a distinct updated_at second from the item's
        $this->markAbsent($userId, 2026, 9, [3, 4]);

        $this->assertTrue($item->fresh()->isStale($run), 'must detect the post-approval attendance edit');

        $recalculated = app(PayrollRunService::class)->recalculate($run);
        $this->assertSame(1, $recalculated);

        $item->refresh();
        $this->assertSame(2.0, (float) $item->lop_days);
        $this->assertNotSame(30000.0, (float) $item->net_pay, 'must no longer be the stale full salary');
        $this->assertFalse($item->isStale($run->fresh()), 'no longer stale once recalculated');
    }

    /** A PAID run must never be silently recalculated — that needs a separate off-cycle correction. */
    public function test_recalculate_refuses_a_paid_run(): void
    {
        $run = PayrollRun::create(['year' => 2026, 'month' => 10, 'status' => PayrollRun::PAID]);

        $this->assertFalse($run->isRecalculable());
        $this->assertSame(0, app(PayrollRunService::class)->recalculate($run));
    }
}
