<?php

namespace Modules\Payroll\app\Support;

use App\Models\User;
use Illuminate\Support\Collection;
use Modules\HrEmployee\app\Models\EmployeeProfile;
use Modules\Payroll\app\Models\PayrollItem;
use Mpdf\Mpdf;

/**
 * Assembles and renders the statutory Form XI (Rule 26(2)) pay slip, returning
 * the raw PDF bytes — one employee, or a whole run one slip per page.
 *
 * Centralised here so every caller — HR/admin per-employee export, the bulk
 * run export and the employee's own self-service download — produces a
 * byte-identical slip.
 *
 * Rendered with mPDF rather than the Dompdf used elsewhere: the slip is
 * bilingual and Dompdf does no Indic shaping, so Devanagari matras land on the
 * wrong side of their consonant ("किराया" prints as "करिाया").
 */
class FormXiPayslip
{
    public function __construct(private readonly SlipAttendance $attendance)
    {
    }

    /** One employee's slip. */
    public function render(PayrollItem $item): string
    {
        return $this->pdf($this->slipHtml($item));
    }

    /**
     * Every slip in a run, one per page, in the given order.
     *
     * @param  Collection<int, PayrollItem>  $items
     */
    public function renderMany(Collection $items): string
    {
        $pages = $items->map(fn (PayrollItem $item) => $this->slipHtml($item));

        return $this->pdf($pages->implode('<pagebreak />'));
    }

    /** Suggested download filename for the given item's slip. */
    public function filename(PayrollItem $item): string
    {
        $name = $item->employee->name ?? 'Employee '.$item->user_id;

        return 'Payslip - '.$name.' - '.$item->run->periodLabel().'.pdf';
    }

    private function slipHtml(PayrollItem $item): string
    {
        $run      = $item->run;
        $employee = $item->employee ?: User::find($item->user_id);
        $profile  = EmployeeProfile::with('department')->where('user_id', $item->user_id)->first();

        $register = (new WageRegisterFormatter())->build(
            collect([$item]),
            collect([$item->user_id => []]),
            collect([$item->user_id => $profile]),
        );

        return view('payroll::payslip-formxi-pdf', [
            'run'           => $run,
            'employee'      => $employee,
            'profile'       => $profile,
            'item'          => $item,
            'rate'          => $register['rows'][0]['rate'],
            'earnings'      => $register['rows'][0]['earnings'],
            'days'          => $this->attendance->forMonth($item->user_id, $run->year, $run->month),
            'deductions'    => collect($item->deductions ?? []),
            'words'         => AmountToWords::rupees((float) $item->net_pay),
            'establishment' => Establishment::forRun($run),
            'photo'         => $this->photoPath($profile),
            'bilingual'     => (bool) config('payroll.payslip.bilingual', true),
        ])->render();
    }

    private function photoPath(?EmployeeProfile $profile): ?string
    {
        if (! $profile || blank($profile->photo_path)) {
            return null;
        }

        $path = public_path($profile->photo_path);

        return is_file($path) ? $path : null;
    }

    private function pdf(string $html): string
    {
        $tmp = storage_path('framework/cache/mpdf');
        if (! is_dir($tmp)) {
            mkdir($tmp, 0775, true);
        }

        $mpdf = new Mpdf([
            'tempDir'          => $tmp,
            'format'           => 'A4',
            'orientation'      => 'P',
            'margin_left'      => 8,
            'margin_right'     => 8,
            'margin_top'       => 8,
            'margin_bottom'    => 8,
            'default_font'     => 'freeserif',
            'default_font_size' => 9,
            // Devanagari needs OpenType shaping; without these the matras and
            // conjuncts render in logical rather than visual order.
            'useOTL'           => 0xFF,
            'autoScriptToLang' => true,
            'autoLangToFont'   => true,
        ]);

        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }
}
