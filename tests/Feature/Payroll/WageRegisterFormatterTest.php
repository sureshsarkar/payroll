<?php

namespace Tests\Feature\Payroll;

use Modules\Payroll\app\Models\PayrollItem;
use Modules\Payroll\app\Support\WageRegisterFormatter;
use Tests\TestCase;

/**
 * Locks the Delhi Form IV wage-register bucketing. The payroll engine stores
 * free-form earning/deduction lines; the register has fixed statutory columns.
 * These tests guarantee every rupee lands in the right column and reconciles
 * back to the stored totals — modelled on the real sample register
 * (MANOJ KUMAR SINGH, ₹55,500 gross, June 2026).
 */
class WageRegisterFormatterTest extends TestCase
{
    private function item(array $earnings, array $deductions, array $totals): PayrollItem
    {
        $item = new PayrollItem(array_merge([
            'user_id'   => 19,
            'earnings'  => $earnings,
            'deductions'=> $deductions,
        ], $totals));
        $item->setRelation('employee', null);

        return $item;
    }

    public function test_earnings_bucket_into_form_iv_columns(): void
    {
        $item = $this->item(
            earnings: [
                ['name' => 'Basic', 'amount' => 23711],
                ['name' => 'HRA', 'amount' => 11856],
                ['name' => 'Conveyance Allowance', 'amount' => 5550],
                ['name' => 'Special Others', 'amount' => 14383],
            ],
            deductions: [],
            totals: ['total_earnings' => 55500, 'total_deductions' => 0, 'net_pay' => 55500],
        );

        $out = (new WageRegisterFormatter())->build(collect([$item]), collect([19 => []]), collect());
        $e   = $out['rows'][0]['earnings'];

        $this->assertSame(23711.0, $e['basic']);
        $this->assertSame(11856.0, $e['hra']);
        $this->assertSame(5550.0, $e['conv']);
        $this->assertSame(14383.0, $e['others']);
        $this->assertSame(0.0, $e['vda']);
        // Every rupee is accounted for — buckets reconcile to the stored gross.
        $this->assertSame(55500.0, array_sum($e));
    }

    public function test_deductions_bucket_and_pf_wages_is_basic_capped_to_ceiling(): void
    {
        config()->set('payroll.statutory.pf.wage_ceiling', 15000);
        config()->set('payroll.statutory.pf.cap_to_ceiling', true);

        $item = $this->item(
            earnings: [['name' => 'Basic', 'amount' => 23711]],
            deductions: [
                ['name' => 'Provident Fund (PF)', 'amount' => 1800],
                ['name' => 'ESIC', 'amount' => 0],
                ['name' => 'Professional Tax', 'amount' => 200],
                ['name' => 'Advance recovery', 'amount' => 500],
            ],
            totals: ['total_earnings' => 23711, 'total_deductions' => 2500, 'net_pay' => 21211],
        );

        $d = (new WageRegisterFormatter())->build(collect([$item]), collect([19 => []]), collect())['rows'][0]['deductions'];

        $this->assertSame(1800.0, $d['pf']);
        $this->assertSame(500.0, $d['loan_adv']);
        $this->assertSame(200.0, $d['others']);        // PT has no dedicated column
        $this->assertSame(15000.0, $d['pf_wages']);    // Basic 23,711 capped to ceiling
    }

    /** LWF and Loss-of-Pay each get their own column instead of falling into "Others". */
    public function test_lwf_and_loss_of_pay_get_dedicated_columns(): void
    {
        $item = $this->item(
            earnings: [['name' => 'Basic', 'amount' => 30000]],
            deductions: [
                ['name' => 'LWF', 'amount' => 20],
                ['name' => 'Loss of Pay (2d)', 'amount' => 1935.48],
                ['name' => 'Professional Tax', 'amount' => 200],
            ],
            totals: ['total_earnings' => 30000, 'total_deductions' => 2155.48, 'net_pay' => 27844.52],
        );

        $d = (new WageRegisterFormatter())->build(collect([$item]), collect([19 => []]), collect())['rows'][0]['deductions'];

        $this->assertSame(20.0, $d['lwf']);
        $this->assertSame(1935.48, $d['lop']);
        $this->assertSame(200.0, $d['others'], 'PT still has no dedicated column');
    }

    public function test_attendance_maps_worked_and_pay_days(): void
    {
        $item = $this->item([], [], ['total_earnings' => 0, 'total_deductions' => 0, 'net_pay' => 0]);

        $summary = ['present' => 26, 'wfh' => 0, 'holiday' => 0, 'leave' => 0, 'half_day' => 0,
                    'working_days' => 30, 'payable_days' => 30];

        $out = (new WageRegisterFormatter())->build(collect([$item]), collect([19 => $summary]), collect());

        $this->assertSame(26.0, $out['rows'][0]['attendance']['w_days']);
        $this->assertSame(30.0, $out['rows'][0]['attendance']['pay_days']);
        $this->assertSame(26.0, $out['totals']['work_days']); // footer "W.Days" = days worked
        $this->assertSame(30.0, $out['totals']['pay_days']);
    }
}
