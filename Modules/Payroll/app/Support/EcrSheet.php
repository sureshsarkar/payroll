<?php

namespace Modules\Payroll\app\Support;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\Payroll\app\Models\PayrollRun;

/**
 * The monthly EPF "ECR" (Electronic Challan-cum-Return) salary sheet: one row
 * per employee for a payroll run, in the standard 12-column PF-portal layout
 * (see the reference file ECRPF_SPY.xls).
 *
 * Column semantics — matching the EPFO ECR wage sheet:
 *   UAN         employee_profiles.uan_number  (12-digit, kept as text)
 *   NAME        employee name, upper-cased as EPFO member records hold it
 *   EARN GROSS  gross wages actually earned in the month (full gross − loss of pay)
 *   EARN BASIC  EPF wages   ─┐
 *   EARN BASIC  EPS wages    ├─ all three = the PF-eligible wage (Basic, capped
 *   EARN BASIC  EDLI wages  ─┘  to the statutory ceiling) pro-rated for NCP days
 *   EMP-SHARE   employee EPF   = 12% of EPF wages
 *   EMPR-SHARE  employer EPS   = 8.33% of EPS wages  (≤ ₹1,250)
 *   EMPR SHARE  employer EPF   = employee EPF − employer EPS  (the 3.67% balance)
 *   NCP         non-contributing-period days = loss-of-pay days
 *   DED         refund of PF advances — no such feature yet, always 0
 *
 * The wage ceiling and the 12% employee rate are read from
 * config('payroll.statutory.pf') — the same source PayrollCalculator /
 * StatutoryCalculator use — so the wage base and rate always agree with how PF
 * is computed on the payslip. The one thing the simplified payslip does not do
 * and a filable ECR must is pro-rate the contributory wage for NCP days; that
 * pro-ration is applied here.
 */
class EcrSheet
{
    /**
     * The reference sheet's header row, minus the "EMP ID" column (dropped per
     * client request). "EARN BASIC" repeats three times and "NCP " carries a
     * trailing space, both preserved verbatim.
     */
    public const HEADERS = [
        'UAN', 'NAME', 'EARN GROSS',
        'EARN BASIC', 'EARN BASIC', 'EARN BASIC',
        'EMP-SHARE', 'EMPR-SHARE', 'EMPR SHARE', 'NCP ', 'DED',
    ];

    /** EPS is statutorily 8.33% of wages, ceiling ₹15,000 (so max ₹1,250). */
    private const EPS_RATE    = 8.33;
    private const EPS_CEILING = 15000;

    /**
     * @param  Collection<int, \Modules\Payroll\app\Models\PayrollItem>  $items  the run's items (employee eager-loaded)
     * @param  Collection<int, \Modules\HrEmployee\app\Models\EmployeeProfile>  $profiles  keyed by user_id
     */
    public function __construct(
        private readonly PayrollRun $run,
        private readonly Collection $items,
        private readonly Collection $profiles,
    ) {
    }

    /**
     * One 12-value row per employee, ordered by employee code.
     *
     * @return array<int, array<int, string|int|float>>
     */
    public function rows(): array
    {
        $daysInMonth = Carbon::create($this->run->year, $this->run->month, 1)->daysInMonth;

        $cfg     = config('payroll.statutory.pf', []);
        $ceiling = (float) ($cfg['wage_ceiling'] ?? 15000);
        $capToCe = (bool) ($cfg['cap_to_ceiling'] ?? true);
        $empRate = (float) ($cfg['employee_rate'] ?? 12.0);

        return $this->items
            ->sortBy(fn ($item) => [
                $this->profiles->get($item->user_id)?->employee_code ?: '~',
                (string) ($item->employee?->name ?? ''),
            ])
            ->map(function ($item) use ($daysInMonth, $ceiling, $capToCe, $empRate) {
                $profile = $this->profiles->get($item->user_id);

                $fullBasic = $this->basicOf($item);
                $ncp       = round((float) $item->lop_days, 1);
                $ratio     = $daysInMonth > 0 ? max(0.0, $daysInMonth - $ncp) / $daysInMonth : 1.0;

                $epfWage  = (int) round(($capToCe ? min($fullBasic, $ceiling) : $fullBasic) * $ratio);
                $epsWage  = (int) round(min($fullBasic, self::EPS_CEILING) * $ratio);
                $edliWage = $epsWage;

                $empShare = (int) round($epfWage * $empRate / 100);         // employee EPF (12%)
                $emprEps  = (int) round($epsWage * self::EPS_RATE / 100);   // employer EPS (8.33%)
                $emprEpf  = $empShare - $emprEps;                           // employer EPF balance

                $earnGross = (int) round(max(0.0, (float) $item->total_earnings - (float) $item->lop_amount));

                return [
                    (string) ($profile?->uan_number ?? ''),
                    Str::upper(trim((string) ($item->employee?->name ?: ('Employee #'.$item->user_id)))),
                    $earnGross,
                    $epfWage, $epsWage, $edliWage,
                    $empShare, $emprEps, $emprEpf,
                    $this->plainNumber($ncp),
                    0, // DED — refund of PF advances; not modelled yet
                ];
            })
            ->values()
            ->all();
    }

    public function filename(string $ext): string
    {
        $company = Establishment::forRun($this->run)['name'] ?: config('app.name');
        $slug    = trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', strtolower($company.' '.$this->run->periodLabel())), '-');

        return "ecr-{$slug}.{$ext}";
    }

    /** Header row + data rows as CSV text. */
    public function csv(): string
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, self::HEADERS);
        foreach ($this->rows() as $row) {
            fputcsv($out, $row);
        }
        rewind($out);

        return (string) stream_get_contents($out);
    }

    /**
     * Excel-readable .xls as a styled HTML table (house convention — no
     * PhpSpreadsheet). UAN / NAME are pinned to text format so a 12-digit UAN
     * never renders as 1.02E+11.
     */
    public function xls(): string
    {
        $esc  = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $textFmt = "mso-number-format:'\\@'";

        $head = '';
        foreach (self::HEADERS as $h) {
            $head .= '<th>'.$esc($h).'</th>';
        }

        $body = '';
        foreach ($this->rows() as $row) {
            $body .= '<tr>';
            foreach ($row as $i => $cell) {
                $style = $i <= 1 ? " style=\"{$textFmt}\"" : '';
                $body .= "<td{$style}>".$esc($cell).'</td>';
            }
            $body .= '</tr>';
        }

        return '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">'
            .'<head><meta charset="utf-8"><style>table{border-collapse:collapse}td,th{border:1px solid #cfd6e0;padding:2px 6px}th{background:#eef1f5;font-weight:bold;text-align:left}</style></head>'
            ."<body><table><thead><tr>{$head}</tr></thead><tbody>{$body}</tbody></table></body></html>";
    }

    /**
     * The full monthly "Basic" from the item's stored earnings. Mirrors
     * SalaryStructure::basic() / WageRegisterFormatter — the PF wage base is the
     * Basic component only (DA/other heads are excluded, exactly as
     * StatutoryCalculator::pf() treats it).
     */
    private function basicOf($item): float
    {
        return (float) collect($item->earnings ?? [])
            ->filter(fn ($line) => Str::contains(Str::lower((string) ($line['name'] ?? '')), 'basic'))
            ->sum('amount');
    }

    /** Whole number when integral, else one decimal — for the NCP column. */
    private function plainNumber(float $v): int|float
    {
        return $v == floor($v) ? (int) $v : round($v, 1);
    }
}
