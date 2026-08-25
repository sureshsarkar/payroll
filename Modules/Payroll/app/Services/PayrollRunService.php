<?php

namespace Modules\Payroll\app\Services;

use App\Models\User;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Payroll\app\Models\PayrollItem;
use Modules\Payroll\app\Models\PayrollRun;

/**
 * Orchestrates the monthly payroll workflow:
 *   HR: createOrGetRun -> prepare (compute items) -> submit
 *   Super Admin: approve -> payslip PDFs generated
 */
class PayrollRunService
{
    public function __construct(private readonly PayrollCalculator $calculator)
    {
    }

    public function createOrGetRun(int $year, int $month, ?int $preparedBy = null): PayrollRun
    {
        return PayrollRun::firstOrCreate(
            ['year' => $year, 'month' => $month],
            ['status' => PayrollRun::DRAFT, 'prepared_by' => $preparedBy],
        );
    }

    /**
     * Compute + upsert a payroll item per employee that has a salary structure.
     * Employees without a structure are skipped and reported back.
     *
     * @param  array<int>  $userIds
     * @return array{prepared:int, skipped:array<int>}
     */
    public function prepare(PayrollRun $run, array $userIds): array
    {
        if (! $run->isEditable()) {
            return ['prepared' => 0, 'skipped' => []];
        }

        $prepared = 0;
        $skipped = [];

        DB::transaction(function () use ($run, $userIds, &$prepared, &$skipped) {
            foreach (array_unique($userIds) as $userId) {
                $breakup = $this->calculator->compute((int) $userId, $run->year, $run->month);
                if ($breakup === null) {
                    $skipped[] = (int) $userId;
                    continue;
                }

                PayrollItem::updateOrCreate(
                    ['payroll_run_id' => $run->id, 'user_id' => $userId],
                    [
                        'payable_days'     => (int) round($breakup['payable_days']),
                        'lop_days'         => $breakup['lop_days'],
                        'gross'            => $breakup['gross'],
                        'total_earnings'   => $breakup['total_earnings'],
                        'total_deductions' => $breakup['total_deductions'],
                        'lop_amount'       => $breakup['lop_amount'],
                        'net_pay'          => $breakup['net_pay'],
                        'earnings'         => $breakup['earnings'],
                        'deductions'       => $breakup['deductions'],
                    ],
                );
                $prepared++;
            }

            $run->update([
                'employee_count' => $run->items()->count(),
                'total_net'      => (float) $run->items()->sum('net_pay'),
            ]);
        });

        return ['prepared' => $prepared, 'skipped' => $skipped];
    }

    /**
     * Send a submitted-but-not-yet-approved run back to draft so HR can fix
     * attendance and re-prepare it. The items are left as-is (re-preparing
     * overwrites them); nothing is deleted.
     */
    public function reopen(PayrollRun $run): bool
    {
        if (! $run->isReopenable()) {
            return false;
        }

        $run->update(['status' => PayrollRun::DRAFT, 'submitted_at' => null]);

        return true;
    }

    /**
     * Recompute every item in an already-approved run from current attendance/
     * leave data and regenerate its payslip PDFs — for the case an absence or
     * leave was corrected after approval. Super-Admin-only, and deliberately
     * excluded once a run is PAID (see PayrollRun::isRecalculable()).
     *
     * @return int number of items recalculated
     */
    public function recalculate(PayrollRun $run): int
    {
        if (! $run->isRecalculable()) {
            return 0;
        }

        $recalculated = 0;

        DB::transaction(function () use ($run, &$recalculated) {
            foreach ($run->items as $item) {
                $breakup = $this->calculator->compute($item->user_id, $run->year, $run->month);
                if ($breakup === null) {
                    continue;
                }

                $item->update([
                    'payable_days'     => (int) round($breakup['payable_days']),
                    'lop_days'         => $breakup['lop_days'],
                    'gross'            => $breakup['gross'],
                    'total_earnings'   => $breakup['total_earnings'],
                    'total_deductions' => $breakup['total_deductions'],
                    'lop_amount'       => $breakup['lop_amount'],
                    'net_pay'          => $breakup['net_pay'],
                    'earnings'         => $breakup['earnings'],
                    'deductions'       => $breakup['deductions'],
                ]);
                $recalculated++;
            }

            $run->update(['total_net' => (float) $run->items()->sum('net_pay')]);
        });

        foreach ($run->items as $item) {
            $this->generatePayslip($item->refresh());
        }

        return $recalculated;
    }

    public function submit(PayrollRun $run, ?int $hrId = null): bool
    {
        if ($run->status !== PayrollRun::DRAFT || $run->items()->count() === 0) {
            return false;
        }

        $run->update([
            'status'       => PayrollRun::HR_SUBMITTED,
            'prepared_by'  => $run->prepared_by ?: $hrId,
            'submitted_at' => now(),
        ]);

        return true;
    }

    /**
     * Super Admin approval — locks the run and generates payslip PDFs.
     */
    public function approve(PayrollRun $run, ?int $adminId = null): bool
    {
        if ($run->status !== PayrollRun::HR_SUBMITTED) {
            return false;
        }

        $run->update([
            'status'      => PayrollRun::ADMIN_APPROVED,
            'approved_by' => $adminId,
            'approved_at' => now(),
        ]);

        foreach ($run->items()->with('employee')->get() as $item) {
            $this->generatePayslip($item);
        }

        return true;
    }

    /**
     * Render + store a payslip PDF for one item; returns the storage path.
     */
    public function generatePayslip(PayrollItem $item): string
    {
        $run = $item->run;
        $employee = $item->employee ?: User::find($item->user_id);

        $html = view('payroll::payslip-pdf', [
            'item'     => $item,
            'run'      => $run,
            'employee' => $employee,
            'currency' => config('payroll.payslip.currency_symbol', '₹'),
        ])->render();

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->render();

        $disk = config('payroll.payslip.storage_disk', 'public');
        $dir  = config('payroll.payslip.storage_dir', 'payslips');
        $path = sprintf('%s/%d-%02d/payslip-%d.pdf', $dir, $run->year, $run->month, $employee->id);

        Storage::disk($disk)->put($path, $dompdf->output());
        $item->update(['payslip_path' => $path]);

        return $path;
    }

    public function periodLabel(PayrollRun $run): string
    {
        return Carbon::create($run->year, $run->month, 1)->format('F Y');
    }
}
