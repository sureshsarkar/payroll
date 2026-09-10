<?php

namespace Tests\Feature\Payroll;

use Illuminate\Support\Collection;
use Modules\HrEmployee\app\Models\EmployeeProfile;
use Modules\Payroll\app\Models\PayrollItem;
use Modules\Payroll\app\Models\PayrollRun;
use Modules\Payroll\app\Support\EcrSheet;
use Tests\TestCase;

/**
 * Locks the 12-column EPFO ECR salary sheet against the reference file
 * (ECRPF_SPY.xls): exact headers/order, one row per employee, and the
 * wage / contribution / NCP maths — including NCP pro-ration, which a filable
 * ECR must do even though the simplified payslip does not.
 */
class EcrSheetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The reference sheet is an August (31-day) run; pin the statutory
        // config to the shipped India defaults the sheet is built around.
        config()->set('payroll.statutory.pf', [
            'enabled' => true, 'employee_rate' => 12.0, 'wage_ceiling' => 15000, 'cap_to_ceiling' => true,
        ]);
    }

    private function newRun(): PayrollRun
    {
        return new PayrollRun(['year' => 2026, 'month' => 8]);
    }

    /**
     * @param  array<int, array{name:string,amount:float}>  $earnings
     */
    private function item(int $userId, string $name, array $earnings, float $grossEarned, float $lopAmount = 0, float $lopDays = 0): PayrollItem
    {
        $item = new PayrollItem([
            'user_id'        => $userId,
            'earnings'       => $earnings,
            'total_earnings' => $grossEarned,
            'lop_amount'     => $lopAmount,
            'lop_days'       => $lopDays,
        ]);

        $item->setRelation('employee', (object) ['name' => $name]);

        return $item;
    }

    private function profile(int $userId, string $code, string $uan): EmployeeProfile
    {
        // uan_number carries an "encrypted" cast — set via the model so the
        // accessor round-trips to the plain value.
        return new EmployeeProfile([
            'user_id'       => $userId,
            'employee_code' => $code,
            'uan_number'    => $uan,
        ]);
    }

    private function sheet(Collection $items, Collection $profiles): EcrSheet
    {
        return new EcrSheet($this->newRun(), $items, $profiles);
    }

    public function test_header_row_matches_the_reference_exactly(): void
    {
        // "EMP ID" was dropped per client request; the rest of the row is verbatim.
        $this->assertSame([
            'UAN', 'NAME', 'EARN GROSS',
            'EARN BASIC', 'EARN BASIC', 'EARN BASIC',
            'EMP-SHARE', 'EMPR-SHARE', 'EMPR SHARE', 'NCP ', 'DED',
        ], EcrSheet::HEADERS);

        // "NCP " keeps its trailing space; "EARN BASIC" appears three times.
        $this->assertSame('NCP ', EcrSheet::HEADERS[9]);
        $this->assertSame(3, collect(EcrSheet::HEADERS)->filter(fn ($h) => $h === 'EARN BASIC')->count());
    }

    public function test_full_month_capped_employee_matches_reference_row(): void
    {
        // HETAL AGGARWAL: gross 85,000, basic ≥ ceiling, no LOP.
        $items = collect([
            $this->item(1, 'Hetal Aggarwal', [
                ['name' => 'Basic', 'amount' => 40000],
                ['name' => 'HRA', 'amount' => 20000],
                ['name' => 'Special Allowance', 'amount' => 25000],
            ], grossEarned: 85000),
        ]);
        $profiles = collect([1 => $this->profile(1, 'SPY01', '102349976607')]);

        $row = $this->sheet($items, $profiles)->rows()[0];

        $this->assertSame(['102349976607', 'HETAL AGGARWAL', 85000], array_slice($row, 0, 3));
        $this->assertSame([15000, 15000, 15000], array_slice($row, 3, 3), 'EPF/EPS/EDLI wages capped to 15,000');
        $this->assertSame(1800, $row[6], 'employee EPF = 12% of 15,000');
        $this->assertSame(1250, $row[7], 'employer EPS = 8.33% of 15,000');
        $this->assertSame(550, $row[8], 'employer EPF balance = 1800 − 1250');
        $this->assertSame(0, $row[9], 'NCP');
        $this->assertSame(0, $row[10], 'DED');
    }

    public function test_ncp_days_prorate_the_contributory_wage_and_contributions(): void
    {
        // AKANKSHA: basic ≥ ceiling, 1 NCP day in a 31-day month.
        // Reference row: wages 14516, EE 1742, ER-EPS 1209, ER-EPF 533.
        $items = collect([
            $this->item(2, 'Akanksha', [['name' => 'Basic', 'amount' => 16000]], grossEarned: 21688, lopAmount: 720, lopDays: 1),
        ]);
        $profiles = collect([2 => $this->profile(2, 'SPY07', '102349994868')]);

        $row = $this->sheet($items, $profiles)->rows()[0];

        $this->assertSame(14516, $row[3], '15,000 × 30/31');
        $this->assertSame([14516, 14516], array_slice($row, 4, 2));
        $this->assertSame(1742, $row[6], '12% of 14,516');
        $this->assertSame(1209, $row[7], '8.33% of 14,516');
        $this->assertSame(533, $row[8], '1742 − 1209');
        $this->assertSame(1, $row[9], 'NCP days');
        $this->assertSame(20968, $row[2], 'EARN GROSS = 21,688 − 720 loss of pay');
    }

    public function test_below_ceiling_employee_uses_actual_basic(): void
    {
        // ANJALI: basic below the ceiling, no LOP → wages == basic.
        $items = collect([
            $this->item(3, 'Anjali Kumari', [['name' => 'Basic', 'amount' => 10161]], grossEarned: 13800),
        ]);
        $profiles = collect([3 => $this->profile(3, 'SPY06', '102349979036')]);

        $row = $this->sheet($items, $profiles)->rows()[0];

        $this->assertSame([10161, 10161, 10161], array_slice($row, 3, 3));
        $this->assertSame(1219, $row[6], 'round(10161 × 12%)');
        $this->assertSame(846, $row[7], 'round(10161 × 8.33%)');
        $this->assertSame(373, $row[8]);
    }

    public function test_one_row_per_employee_ordered_by_employee_code(): void
    {
        $items = collect([
            $this->item(30, 'Zoe Last', [['name' => 'Basic', 'amount' => 12000]], grossEarned: 20000),
            $this->item(10, 'Amy First', [['name' => 'Basic', 'amount' => 12000]], grossEarned: 20000),
        ]);
        $profiles = collect([
            30 => $this->profile(30, 'SPY30', '111111111111'),
            10 => $this->profile(10, 'SPY03', '222222222222'),
        ]);

        $rows = $this->sheet($items, $profiles)->rows();

        // Still ordered by employee code (SPY03 before SPY30) even though the
        // EMP ID column itself is no longer emitted — assert on the NAME column.
        $this->assertCount(2, $rows);
        $this->assertSame(['AMY FIRST', 'ZOE LAST'], [$rows[0][1], $rows[1][1]]);
    }

    public function test_csv_has_a_header_line_then_one_line_per_employee(): void
    {
        $items = collect([
            $this->item(1, 'Hetal Aggarwal', [['name' => 'Basic', 'amount' => 40000]], grossEarned: 85000),
            $this->item(2, 'Varun Aggarwal', [['name' => 'Basic', 'amount' => 40000]], grossEarned: 85000),
        ]);
        $profiles = collect([
            1 => $this->profile(1, 'SPY01', '102349976607'),
            2 => $this->profile(2, 'SPY02', '101459732895'),
        ]);

        $csv   = $this->sheet($items, $profiles)->csv();
        $lines = array_values(array_filter(explode("\n", trim($csv))));

        $this->assertCount(3, $lines, 'header + 2 employees');
        $this->assertStringStartsWith('UAN,NAME,"EARN GROSS"', $lines[0]);
        $this->assertStringContainsString('102349976607,"HETAL AGGARWAL",85000', $lines[1]);
    }

    public function test_xls_keeps_uan_as_text_so_it_is_not_mangled(): void
    {
        $items = collect([$this->item(1, 'Hetal', [['name' => 'Basic', 'amount' => 40000]], grossEarned: 85000)]);
        $profiles = collect([1 => $this->profile(1, 'SPY01', '102349976607')]);

        $xls = $this->sheet($items, $profiles)->xls();

        $this->assertStringContainsString("mso-number-format:'\\@'", $xls);
        $this->assertStringContainsString('>102349976607<', $xls);
        $this->assertStringContainsString('<th>NCP </th>', $xls);
    }
}
