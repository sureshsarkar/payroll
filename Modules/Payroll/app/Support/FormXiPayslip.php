<?php

namespace Modules\Payroll\app\Support;

use App\Models\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use Modules\Attendance\app\Services\AttendanceService;
use Modules\HrEmployee\app\Models\EmployeeProfile;
use Modules\Payroll\app\Models\PayrollItem;

/**
 * Assembles and renders the statutory Form XI (Rule 26(2)) pay slip for a
 * single payroll item, returning the raw PDF bytes.
 *
 * Centralised here so every caller — HR/admin per-employee export and the
 * employee's own self-service download — produces a byte-identical slip.
 */
class FormXiPayslip
{
    public function __construct(private readonly AttendanceService $attendance)
    {
    }

    public function render(PayrollItem $item): string
    {
        $run      = $item->run;
        $employee = $item->employee ?: User::find($item->user_id);
        $profile  = EmployeeProfile::with('department')->where('user_id', $item->user_id)->first();
        $summary  = $this->attendance->monthlySummary($item->user_id, $run->year, $run->month);

        $register = (new WageRegisterFormatter())->build(
            collect([$item]),
            collect([$item->user_id => $summary]),
            collect([$item->user_id => $profile]),
        );

        $photo = ($profile && filled($profile->photo_path) && is_file(public_path($profile->photo_path)))
            ? public_path($profile->photo_path) : null;

        $html = view('payroll::payslip-formxi-pdf', [
            'run'           => $run,
            'employee'      => $employee,
            'profile'       => $profile,
            'item'          => $item,
            'row'           => $register['rows'][0],
            'summary'       => $summary,
            'deductions'    => collect($item->deductions ?? []),
            'words'         => AmountToWords::rupees((float) $item->net_pay),
            'establishment' => config('payroll.establishment'),
            'photo'         => $photo,
        ])->render();

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $pdf = new Dompdf($options);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        return $pdf->output();
    }

    /** Suggested download filename for the given item's slip. */
    public function filename(PayrollItem $item): string
    {
        $name = $item->employee->name ?? 'Employee '.$item->user_id;

        return 'Payslip - '.$name.' - '.$item->run->periodLabel().'.pdf';
    }
}
