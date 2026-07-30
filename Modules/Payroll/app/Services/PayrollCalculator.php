<?php

namespace Modules\Payroll\app\Services;

use Modules\Attendance\app\Services\AttendanceService;
use Modules\Payroll\app\Models\Bonus;
use Modules\Payroll\app\Models\LoanAdvance;
use Modules\Payroll\app\Models\SalaryComponent;
use Modules\Payroll\app\Models\SalaryStructure;

/**
 * The payroll engine. For one employee + month it assembles a full payslip
 * breakup from: the employee's salary structure, attendance (LOP), statutory
 * deductions, one-time bonuses, and loan/advance recovery.
 *
 * Returns null when the employee has no current salary structure.
 */
class PayrollCalculator
{
    public function __construct(
        private readonly AttendanceService $attendance,
        private readonly StatutoryCalculator $statutory,
    ) {
    }

    /**
     * @return array{
     *   basic:float, gross:float, working_days:int, lop_days:float, payable_days:float,
     *   earnings:array, deductions:array, lop_amount:float,
     *   total_earnings:float, total_deductions:float, net_pay:float
     * }|null
     */
    public function compute(int $userId, int $year, int $month): ?array
    {
        $structure = SalaryStructure::with('components')
            ->where('user_id', $userId)
            ->where('is_current', true)
            ->latest('effective_from')
            ->first();

        if (! $structure) {
            return null;
        }

        $basic = $structure->basic();

        // --- Earnings (from structure) ------------------------------------
        $earnings = [];
        $gross = 0.0;
        foreach ($structure->components->where('type', SalaryComponent::EARNING) as $c) {
            $amt = $c->resolveAmount($basic, (float) $structure->gross_monthly);
            $earnings[] = ['name' => $c->name, 'amount' => $amt];
            $gross += $amt;
        }
        $gross = round($gross, 2);

        // --- Attendance → Loss of Pay -------------------------------------
        $sum = $this->attendance->monthlySummary($userId, $year, $month);
        $workingDays = max(1, (int) $sum['working_days']);
        $lopDays     = (float) $sum['lop_days'];
        $perDay      = $gross / $workingDays;
        $lopAmount   = round($perDay * $lopDays, 2);

        // --- One-time bonuses (added to earnings) -------------------------
        $bonuses = Bonus::where('user_id', $userId)
            ->where('year', $year)->where('month', $month)
            ->where('is_applied', false)->get();
        foreach ($bonuses as $b) {
            $earnings[] = ['name' => $b->title, 'amount' => (float) $b->amount];
            $gross += (float) $b->amount; // bonus is part of this month's gross earnings
        }
        $totalEarnings = round(array_sum(array_column($earnings, 'amount')), 2);

        // --- Deductions ---------------------------------------------------
        $deductions = [];

        // structure-defined (non-statutory) deductions
        foreach ($structure->components->where('type', SalaryComponent::DEDUCTION) as $c) {
            $deductions[] = [
                'name'      => $c->name,
                'amount'    => $c->resolveAmount($basic, $gross),
                'statutory' => (bool) $c->is_statutory,
            ];
        }

        // statutory (PF / ESIC / PT / TDS) computed on basic + gross
        foreach ($this->statutory->deductions($basic, $gross) as $line) {
            $deductions[] = $line;
        }

        // Loss of Pay
        if ($lopAmount > 0) {
            $deductions[] = ['name' => 'Loss of Pay ('.$lopDays.'d)', 'amount' => $lopAmount, 'statutory' => false];
        }

        // Loan / advance recovery
        foreach (LoanAdvance::where('user_id', $userId)->where('status', LoanAdvance::ACTIVE)->get() as $loan) {
            if (($due = $loan->dueThisMonth()) > 0) {
                $deductions[] = ['name' => ucfirst($loan->type).' recovery', 'amount' => $due, 'statutory' => false];
            }
        }

        $totalDeductions = round(array_sum(array_column($deductions, 'amount')), 2);
        $netPay = round($totalEarnings - $totalDeductions, 2);

        return [
            'basic'            => round($basic, 2),
            'gross'            => $totalEarnings,
            'working_days'     => $workingDays,
            'lop_days'         => $lopDays,
            'payable_days'     => (float) $sum['payable_days'],
            'earnings'         => $earnings,
            'deductions'       => $deductions,
            'lop_amount'       => $lopAmount,
            'total_earnings'   => $totalEarnings,
            'total_deductions' => $totalDeductions,
            'net_pay'          => $netPay,
        ];
    }
}
