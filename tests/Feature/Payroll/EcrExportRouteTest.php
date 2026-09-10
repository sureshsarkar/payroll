<?php

namespace Tests\Feature\Payroll;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Modules\Company\app\Http\Middleware\EnsureCompanyContext;
use Modules\Company\app\Models\Company;
use Modules\Company\app\Models\CompanyUser;
use Modules\HrEmployee\app\Models\EmployeeProfile;
use Modules\Payroll\app\Models\PayrollItem;
use Modules\Payroll\app\Models\PayrollRun;
use Tests\TestCase;

/**
 * The ECR export endpoint: HR-only, both formats, one attachment covering every
 * employee in the run, headers exactly the reference file's.
 */
class EcrExportRouteTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;
    private User $hr;
    private PayrollRun $run;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'http://localhost',
            'payroll.statutory.pf' => ['enabled' => true, 'employee_rate' => 12.0, 'wage_ceiling' => 15000, 'cap_to_ceiling' => true],
        ]);
        URL::forceRootUrl('http://localhost');

        $this->company = Company::create([
            'name' => 'Spry '.Str::random(5), 'slug' => 'spry-'.Str::random(8),
            'owner_user_id' => 0, 'timezone' => 'Asia/Kolkata', 'status' => 'active',
        ]);
        $this->hr = User::factory()->create(['role' => 'instructor', 'coach_id' => null]);
        $this->company->update(['owner_user_id' => $this->hr->id]);
        CompanyUser::create(['company_id' => $this->company->id, 'user_id' => $this->hr->id, 'role' => 'owner', 'status' => 'active']);

        app()->instance('currentCompany', $this->company);

        $this->run = PayrollRun::create(['year' => 2026, 'month' => 8, 'status' => PayrollRun::ADMIN_APPROVED]);

        $this->addEmployee('SPY01', '102349976607', 'Hetal Aggarwal', basic: 40000, gross: 85000);
        $this->addEmployee('SPY07', '102349994868', 'Akanksha', basic: 16000, gross: 21688, lopAmount: 720, lopDays: 1);
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('currentCompany');
        parent::tearDown();
    }

    private function addEmployee(string $code, string $uan, string $name, float $basic, float $gross, float $lopAmount = 0, float $lopDays = 0): void
    {
        $user = User::factory()->create(['name' => $name, 'role' => 'student']);

        EmployeeProfile::create([
            'user_id' => $user->id, 'reporting_hr_id' => $this->hr->id,
            'employee_code' => $code, 'uan_number' => $uan, 'status' => EmployeeProfile::ACTIVE,
        ]);

        PayrollItem::create([
            'payroll_run_id' => $this->run->id, 'user_id' => $user->id,
            'earnings'       => [['name' => 'Basic', 'amount' => $basic], ['name' => 'HRA', 'amount' => $gross - $basic]],
            'deductions'     => [],
            'gross'          => $gross, 'total_earnings' => $gross,
            'total_deductions' => 0, 'lop_amount' => $lopAmount, 'lop_days' => $lopDays,
            'payable_days'   => 31 - (int) $lopDays, 'net_pay' => $gross - $lopAmount,
        ]);
    }

    private function ecr(string $query = '')
    {
        return $this->actingAs($this->hr, 'web')
            ->withSession([EnsureCompanyContext::SESSION_KEY => $this->company->id])
            ->get('hr/payroll/'.$this->run->id.'/ecr'.$query);
    }

    public function test_csv_export_lists_every_employee_with_the_reference_headers(): void
    {
        $res = $this->ecr('?format=csv');

        $res->assertOk();
        $res->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('.csv', $res->headers->get('content-disposition'));

        $body  = $res->getContent();
        $lines = array_values(array_filter(explode("\n", trim($body))));

        $this->assertCount(3, $lines, 'header + 2 employees');
        $this->assertStringContainsString('UAN,NAME,"EARN GROSS"', $lines[0]);
        $this->assertStringContainsString('"NCP ",DED', $lines[0]);
        // full month, capped — matches reference row SPY01 (EMP ID column removed)
        $this->assertStringContainsString('102349976607,"HETAL AGGARWAL",85000,15000,15000,15000,1800,1250,550,0,0', $body);
        // 1 NCP day in a 31-day month — wage & contributions pro-rated, matches reference row SPY07
        $this->assertStringContainsString('102349994868,AKANKSHA,20968,14516,14516,14516,1742,1209,533,1,0', $body);
    }

    public function test_excel_export_is_served_as_an_xls_table(): void
    {
        $res = $this->ecr('?format=xlsx');

        $res->assertOk();
        $res->assertHeader('content-type', 'application/vnd.ms-excel; charset=UTF-8');
        $this->assertStringContainsString('.xls"', $res->headers->get('content-disposition'));
        $this->assertStringContainsString('<th>EARN BASIC</th><th>EARN BASIC</th><th>EARN BASIC</th>', $res->getContent());
    }

    public function test_default_format_is_excel(): void
    {
        $this->ecr()->assertOk()->assertHeader('content-type', 'application/vnd.ms-excel; charset=UTF-8');
    }

    public function test_unknown_format_is_rejected(): void
    {
        $this->ecr('?format=pdf')->assertStatus(400);
    }

    public function test_a_student_cannot_reach_the_hr_ecr_route(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student, 'web')
            ->get('hr/payroll/'.$this->run->id.'/ecr?format=csv')
            ->assertRedirect();
    }
}
