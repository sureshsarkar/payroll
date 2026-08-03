<?php

namespace Modules\Payroll\app\Support;

use Illuminate\Support\Str;
use Modules\Payroll\app\Models\PayrollItem;

/**
 * Turns raw payroll items + attendance summaries into rows shaped for the
 * statutory "Register of Payment of Wages / Salary" (Delhi Form IV).
 *
 * The form has fixed columns (Basic / VDA / HRA / Conv. / Others / OT / Arrear
 * on the earnings side; PF Wages / PF / ESI / TDS / Loan-Adv / Others on the
 * deductions side). Our payroll engine stores free-form earning/deduction
 * lines, so this formatter buckets each line into the correct fixed column by
 * matching on its name/code. Anything unmatched falls into "Others" so no money
 * is ever dropped — every bucket sum reconciles back to the stored totals.
 */
class WageRegisterFormatter
{
    /**
     * @param  \Illuminate\Support\Collection<int, PayrollItem>  $items
     * @param  \Illuminate\Support\Collection<int, array>        $summaries  attendance monthlySummary keyed by user_id
     * @param  \Illuminate\Support\Collection<int, \Modules\HrEmployee\app\Models\EmployeeProfile>  $profiles  keyed by user_id
     * @return array{rows: array<int, array>, totals: array<string, float>}
     */
    public function build($items, $summaries, $profiles): array
    {
        $rows = [];

        foreach ($items as $item) {
            $rows[] = $this->row($item, $summaries->get($item->user_id, []), $profiles->get($item->user_id));
        }

        return ['rows' => $rows, 'totals' => $this->totals($rows)];
    }

    private function row(PayrollItem $item, array $summary, $profile): array
    {
        $earn = $this->bucketEarnings(collect($item->earnings ?? []));
        $ded  = $this->bucketDeductions(collect($item->deductions ?? []), $earn['basic']);

        return [
            'employee'   => $item->employee,
            'profile'    => $profile,
            'attendance' => $this->attendance($summary),
            // "Rate of salary" (full monthly rate) equals earnings here because the
            // engine treats loss-of-pay as a deduction, not a proration of earnings.
            'rate'       => $earn,
            'earnings'   => $earn,
            'gross'      => (float) $item->total_earnings,
            'deductions' => $ded,
            'total_ded'  => (float) $item->total_deductions,
            'net_pay'    => (float) $item->net_pay,
        ];
    }

    /** @return array<string, float> */
    private function attendance(array $s): array
    {
        return [
            'w_days'    => (float) ($s['present'] ?? 0) + (float) ($s['wfh'] ?? 0),
            'wo'        => 0.0, // weekly-offs not yet modelled
            'hd'        => (float) ($s['holiday'] ?? 0),
            'el'        => 0.0,
            'cl'        => (float) ($s['leave'] ?? 0),
            'sl'        => 0.0,
            'sp'        => (float) ($s['half_day'] ?? 0),
            'pay_days'  => (float) ($s['payable_days'] ?? 0),
            'work_days' => (float) ($s['working_days'] ?? 0),
        ];
    }

    /**
     * Bucket earning lines into the Form IV earning columns.
     *
     * @param  \Illuminate\Support\Collection  $lines
     * @return array<string, float>
     */
    private function bucketEarnings($lines): array
    {
        $b = ['basic' => 0.0, 'vda' => 0.0, 'hra' => 0.0, 'conv' => 0.0, 'others' => 0.0, 'ot' => 0.0, 'arrear' => 0.0];

        foreach ($lines as $line) {
            $name   = Str::lower((string) ($line['name'] ?? ''));
            $amount = (float) ($line['amount'] ?? 0);

            $key = match (true) {
                Str::contains($name, 'basic')                              => 'basic',
                Str::contains($name, ['vda', 'dearness'])                  => 'vda',
                Str::contains($name, ['hra', 'house rent'])                => 'hra',
                Str::contains($name, ['conv', 'transport', 'travel'])      => 'conv',
                Str::contains($name, ['overtime', 'o.t', 'ot hour'])       => 'ot',
                Str::contains($name, 'arrear')                             => 'arrear',
                default                                                    => 'others',
            };
            $b[$key] += $amount;
        }

        return array_map(fn ($v) => round($v, 2), $b);
    }

    /**
     * Bucket deduction lines into the Form IV deduction columns. "PF Wages" is
     * the wage on which PF is levied — Basic capped to the statutory ceiling —
     * shown only when PF was actually deducted.
     *
     * @param  \Illuminate\Support\Collection  $lines
     * @return array<string, float>
     */
    private function bucketDeductions($lines, float $basic): array
    {
        $b = ['pf_wages' => 0.0, 'pf' => 0.0, 'esi' => 0.0, 'tds' => 0.0, 'loan_adv' => 0.0, 'others' => 0.0];

        foreach ($lines as $line) {
            $name   = Str::lower((string) ($line['name'] ?? ''));
            $amount = (float) ($line['amount'] ?? 0);

            $key = match (true) {
                Str::contains($name, ['provident', 'pf']) && ! Str::contains($name, 'wage') => 'pf',
                Str::contains($name, ['esic', 'esi', 'state insurance'])                    => 'esi',
                Str::contains($name, ['tds', 'income tax'])                                 => 'tds',
                Str::contains($name, ['loan', 'advance', 'recovery'])                       => 'loan_adv',
                default                                                                     => 'others',
            };
            $b[$key] += $amount;
        }

        if ($b['pf'] > 0) {
            $ceiling = (float) config('payroll.statutory.pf.wage_ceiling', 15000);
            $capped  = (bool) config('payroll.statutory.pf.cap_to_ceiling', true);
            $b['pf_wages'] = $capped ? min($basic, $ceiling) : $basic;
        }

        return array_map(fn ($v) => round($v, 2), $b);
    }

    /**
     * Column-wise totals across all rows for the register footer.
     *
     * @param  array<int, array>  $rows
     * @return array<string, float>
     */
    private function totals(array $rows): array
    {
        $t = [
            'basic' => 0, 'vda' => 0, 'hra' => 0, 'conv' => 0, 'others' => 0, 'ot' => 0, 'arrear' => 0,
            'gross' => 0, 'pf_wages' => 0, 'pf' => 0, 'esi' => 0, 'tds' => 0, 'loan_adv' => 0,
            'ded_others' => 0, 'total_ded' => 0, 'net_pay' => 0, 'pay_days' => 0, 'work_days' => 0,
        ];

        foreach ($rows as $r) {
            foreach (['basic', 'vda', 'hra', 'conv', 'others', 'ot', 'arrear'] as $k) {
                $t[$k] += $r['earnings'][$k];
            }
            foreach (['pf_wages', 'pf', 'esi', 'tds', 'loan_adv'] as $k) {
                $t[$k] += $r['deductions'][$k];
            }
            $t['ded_others'] += $r['deductions']['others'];
            $t['gross']      += $r['gross'];
            $t['total_ded']  += $r['total_ded'];
            $t['net_pay']    += $r['net_pay'];
            $t['pay_days']   += $r['attendance']['pay_days'];
            $t['work_days']  += $r['attendance']['w_days']; // footer "W.Days" = days actually worked
        }

        return array_map(fn ($v) => round($v, 2), $t);
    }
}
