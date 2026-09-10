<?php

namespace Tests\Feature\Attendance;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Modules\Attendance\app\Models\Attendance;
use Modules\Attendance\app\Models\Holiday;
use Modules\Attendance\app\Services\AttendanceService;
use Modules\Company\app\Http\Middleware\EnsureCompanyContext;
use Modules\Company\app\Models\Company;
use Modules\Company\app\Models\CompanyUser;
use Modules\HrEmployee\app\Models\EmployeeProfile;
use Tests\TestCase;

/**
 * The Attendance Register PDF must give an accurate monthly summary:
 *   - a First Half / Second Half leave counts and shows as Leave (½ day each), and
 *   - every company holiday in the month shows as HD, marked or not.
 */
class AttendanceRegisterPdfTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;
    private User $hr;
    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'http://localhost']);
        URL::forceRootUrl('http://localhost');

        // The instructor/student dashboard master layouts need the settings /
        // brand cache warmed, which these tests don't boot. Swap them for a
        // bare stub that just yields the page section.
        View::getFinder()->prependLocation(__DIR__.'/stubs');

        $this->company = Company::create([
            'name' => 'Regist '.Str::random(5), 'slug' => 'regist-'.Str::random(8),
            'owner_user_id' => 0, 'timezone' => 'Asia/Kolkata', 'status' => 'active',
        ]);
        $this->hr = User::factory()->create(['role' => 'instructor', 'coach_id' => null]);
        $this->company->update(['owner_user_id' => $this->hr->id]);
        CompanyUser::create(['company_id' => $this->company->id, 'user_id' => $this->hr->id, 'role' => 'owner', 'status' => 'active']);

        app()->instance('currentCompany', $this->company);

        $this->employee = User::factory()->create(['name' => 'Riya Sen', 'role' => 'student']);
        EmployeeProfile::create([
            'user_id' => $this->employee->id, 'reporting_hr_id' => $this->hr->id,
            'employee_code' => 'REG01', 'status' => EmployeeProfile::ACTIVE,
        ]);
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('currentCompany');
        parent::tearDown();
    }

    private function mark(string $date, string $status, array $attrs = []): void
    {
        app(AttendanceService::class)->mark($this->employee->id, $date, $status, $attrs + ['source' => 'manual']);
    }

    public function test_first_and_second_half_leave_each_count_as_half_a_leave_day(): void
    {
        // First-half leave from an approved request (no paid head — still leave).
        $this->mark('2026-08-04', Attendance::AP, ['source' => 'leave']);
        // Second-half paid leave carrying its leave head.
        $this->mark('2026-08-11', Attendance::PA, ['day_type' => Attendance::DAY_CL]);

        $summary = app(AttendanceService::class)->monthlySummary($this->employee->id, 2026, 8);

        $this->assertSame(1.0, $summary['leave_days'], 'two half-day leaves add up to one leave day');
        $this->assertTrue(Attendance::where('user_id', $this->employee->id)->whereDate('attendance_date', '2026-08-04')->first()->isHalfDayLeave());
        $this->assertTrue(Attendance::where('user_id', $this->employee->id)->whereDate('attendance_date', '2026-08-11')->first()->isHalfDayLeave());
    }

    public function test_full_day_leave_counts_as_one_leave_day(): void
    {
        $this->mark('2026-08-06', Attendance::AA, ['day_type' => Attendance::DAY_EL]);

        $summary = app(AttendanceService::class)->monthlySummary($this->employee->id, 2026, 8);

        $this->assertSame(1.0, $summary['leave_days']);
    }

    public function test_holidays_for_the_month_are_keyed_by_day_of_month(): void
    {
        Holiday::create(['holiday_date' => '2026-08-15', 'name' => 'Independence Day']);
        Holiday::create(['holiday_date' => '2026-09-02', 'name' => 'Out of range']);

        $map = Holiday::datesForMonth(2026, 8);

        $this->assertSame(['15' => 'Independence Day'], $map->mapWithKeys(fn ($v, $k) => [(string) $k => $v])->all());
    }

    public function test_register_pdf_shows_holidays_as_hd_and_half_day_leave_as_leave(): void
    {
        Holiday::create(['holiday_date' => '2026-08-15', 'name' => 'Independence Day']);
        $this->mark('2026-08-04', Attendance::AP, ['source' => 'leave']);   // first-half leave
        $this->mark('2026-08-20', Attendance::PP);                          // ordinary present day
        $this->mark('2026-08-25', Attendance::PA, ['day_type' => Attendance::DAY_HD]); // half day (unpaid)
        $this->mark('2026-08-15', Attendance::PP, ['check_in' => '09:15', 'check_out' => '18:20']); // worked the holiday

        $res = $this->actingAs($this->hr, 'web')
            ->withSession([EnsureCompanyContext::SESSION_KEY => $this->company->id])
            ->get('hr/attendance/sheet/employee/export?'.http_build_query([
                'format' => 'pdf', 'employee_id' => $this->employee->id, 'year' => 2026, 'month' => 8,
            ]));

        $res->assertOk();
        $res->assertHeader('content-type', 'application/pdf');

        // Assert against the same Blade the controller renders, with the same data.
        $service = app(AttendanceService::class);
        $map = $service->monthMap($this->employee->id, 2026, 8);
        $holidays = Holiday::datesForMonth(2026, 8);
        $first = Carbon::create(2026, 8, 1);
        $days = collect(range(1, $first->daysInMonth))->map(fn (int $d) => [
            'date' => Carbon::create(2026, 8, $d),
            'record' => $map->get($d),
            'holiday' => $holidays->get($d),
        ]);

        $html = view('attendance::employee-register-pdf', [
            'sheets' => [[
                'employee' => $this->employee,
                'profile' => EmployeeProfile::where('user_id', $this->employee->id)->first(),
                'summary' => $service->monthlySummary($this->employee->id, 2026, 8),
                'first' => $first,
                'days' => $days,
                'weeklyOffs' => 0,
                'holidayCount' => $holidays->count(),
            ]],
            'company' => ['name' => $this->company->name, 'address' => ''],
        ])->render();

        $this->assertStringContainsString('Independence Day', $html, 'holiday name is on the sheet');
        $this->assertStringContainsString('Holiday (HD): <b>1</b>', $html);
        $this->assertStringContainsString('>L</div>', $html, 'the first-half leave day shows as L');
        $this->assertMatchesRegularExpression('/>HD<\/div>/', $html, 'the holiday day shows as HD');
        $this->assertMatchesRegularExpression('/>H<\/div>/', $html, 'a half-day is denoted H, not HD');
        $this->assertSame('H', Attendance::DAY_HD, 'the half-day day_type tag is "H"');
        $this->assertStringNotContainsString('09:15', $html, 'the holiday day shows no in/out time even when worked');
    }

    public function test_multi_employee_register_pdf_stacks_selected_employees_under_one_header(): void
    {
        $emp2 = User::factory()->create(['name' => 'Arjun Rao', 'role' => 'student']);
        EmployeeProfile::create(['user_id' => $emp2->id, 'reporting_hr_id' => $this->hr->id, 'employee_code' => 'REG02', 'status' => EmployeeProfile::ACTIVE]);
        $emp3 = User::factory()->create(['name' => 'Meena Iyer', 'role' => 'student']);
        EmployeeProfile::create(['user_id' => $emp3->id, 'reporting_hr_id' => $this->hr->id, 'employee_code' => 'REG03', 'status' => EmployeeProfile::ACTIVE]);
        $outsider = User::factory()->create(['name' => 'Not Mine', 'role' => 'student']); // different HR

        $res = $this->actingAs($this->hr, 'web')
            ->withSession([EnsureCompanyContext::SESSION_KEY => $this->company->id])
            ->get('hr/attendance/sheet/employees/register?'.http_build_query([
                'year' => 2026, 'month' => 8,
                'employee_ids' => [$this->employee->id, $emp3->id, $outsider->id],
            ]));

        $res->assertOk();
        $res->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('2 employees', $res->headers->get('content-disposition'));

        // Re-render the same view to inspect structure: one page break per extra sheet.
        $service = app(AttendanceService::class);
        $holidays = Holiday::datesForMonth(2026, 8);
        $sheet = function (User $u) use ($service, $holidays) {
            $first = Carbon::create(2026, 8, 1);
            $map = $service->monthMap($u->id, 2026, 8);
            return [
                'employee' => $u,
                'profile' => EmployeeProfile::where('user_id', $u->id)->first(),
                'summary' => $service->monthlySummary($u->id, 2026, 8),
                'first' => $first,
                'days' => collect(range(1, 31))->map(fn ($d) => ['date' => Carbon::create(2026, 8, $d), 'record' => $map->get($d), 'holiday' => $holidays->get($d)]),
                'weeklyOffs' => 0,
                'holidayCount' => 0,
            ];
        };
        $html = view('attendance::employee-register-pdf', [
            'sheets' => [$sheet($this->employee), $sheet($emp3)],
            'company' => ['name' => $this->company->name, 'address' => ''],
        ])->render();

        $this->assertSame(2, substr_count($html, 'class="emp-block"'), 'one flowing block per employee');
        $this->assertStringContainsString('page-break-inside: avoid', $html, 'a block is never split across pages');
        $this->assertSame(1, substr_count($html, 'class="company"'), 'company header appears once (it is fixed, repeats per page)');
        $this->assertStringContainsString('Riya Sen', $html);
        $this->assertStringContainsString('Meena Iyer', $html);
        $this->assertStringNotContainsString('Not Mine', $html);
    }

    public function test_spreadsheet_export_also_reflects_holidays_and_half_day_leave(): void
    {
        Holiday::create(['holiday_date' => '2026-08-15', 'name' => 'Independence Day']);
        $this->mark('2026-08-04', Attendance::AP, ['source' => 'leave']);
        // Worked on the holiday — times must still be blanked in the sheet.
        $this->mark('2026-08-15', Attendance::PP, ['check_in' => '09:00', 'check_out' => '18:00']);

        $res = $this->actingAs($this->hr, 'web')
            ->withSession([EnsureCompanyContext::SESSION_KEY => $this->company->id])
            ->get('hr/attendance/sheet/employee/export?'.http_build_query([
                'format' => 'csv', 'employee_id' => $this->employee->id, 'year' => 2026, 'month' => 8,
            ]));

        $res->assertOk();
        $body = $res->streamedContent();

        $this->assertStringContainsString('"15 Aug 2026",Sat,HD,"Holiday — Independence Day",,,', $body, 'holiday row: HD code, blank in/out');
        $this->assertStringNotContainsString('09:00', $body, 'the holiday day\'s check-in time is suppressed');
        $this->assertStringContainsString('"04 Aug 2026",Tue,L,', $body);
    }

    public function test_hr_monthly_calendar_shows_holiday_as_hd_with_no_time(): void
    {
        Holiday::create(['holiday_date' => '2026-08-15', 'name' => 'Independence Day']);
        $this->mark('2026-08-15', Attendance::PP, ['check_in' => '09:15', 'check_out' => '18:20']);

        $res = $this->actingAs($this->hr, 'web')
            ->withSession([EnsureCompanyContext::SESSION_KEY => $this->company->id])
            ->get('hr/attendance/sheet?'.http_build_query([
                'employee_id' => $this->employee->id, 'year' => 2026, 'month' => 8,
            ]));

        $res->assertOk();
        $c = $res->getContent();
        $this->assertMatchesRegularExpression('/Holiday — Independence Day".*?>HD<\/span>/s', $c, 'holiday cell shows an HD badge');
        // The 15th is a holiday — its worked times must not appear on the calendar.
        $this->assertStringNotContainsString('09:15 – 18:20', $c);
    }

    public function test_workspace_has_a_month_and_year_dropdown_for_jumping_to_any_month(): void
    {
        $res = $this->actingAs($this->hr, 'web')
            ->withSession([EnsureCompanyContext::SESSION_KEY => $this->company->id])
            ->get('hr/attendance/sheet?'.http_build_query([
                'employee_id' => $this->employee->id, 'year' => 2026, 'month' => 3,
            ]));

        $res->assertOk();
        $c = $res->getContent();
        $this->assertStringContainsString('<select name="month"', $c);
        $this->assertStringContainsString('<select name="year"', $c);
        $this->assertMatchesRegularExpression('/<option value="3" selected>\s*March\s*<\/option>/', $c, 'the viewed month is pre-selected');
        $this->assertMatchesRegularExpression('/<option value="2026" selected>\s*2026\s*<\/option>/', $c);

        // The dropdown target actually loads that month.
        $jump = $this->actingAs($this->hr, 'web')
            ->withSession([EnsureCompanyContext::SESSION_KEY => $this->company->id])
            ->get('hr/attendance/sheet?'.http_build_query([
                'employee_id' => $this->employee->id, 'year' => 2025, 'month' => 11,
            ]));
        $jump->assertOk();
        $this->assertStringContainsString('November 2025 calendar', $jump->getContent());
    }

    public function test_employee_monthly_calendar_shows_holiday_as_hd(): void
    {
        Holiday::create(['holiday_date' => '2026-08-15', 'name' => 'Independence Day']);

        $res = $this->actingAs($this->employee, 'web')
            ->withSession([EnsureCompanyContext::SESSION_KEY => $this->company->id])
            ->get('employee/attendance?'.http_build_query(['year' => 2026, 'month' => 8]));

        $res->assertOk();
        $this->assertMatchesRegularExpression('/Holiday — Independence Day".*?>HD<\/span>/s', $res->getContent());
    }

    public function test_holiday_admin_screen_is_hr_only(): void
    {
        $this->actingAs($this->employee, 'web')
            ->withSession([EnsureCompanyContext::SESSION_KEY => $this->company->id])
            ->get('hr/attendance/holidays')
            ->assertRedirect();

        $this->actingAs($this->hr, 'web')
            ->withSession([EnsureCompanyContext::SESSION_KEY => $this->company->id])
            ->post('hr/attendance/holidays', ['holiday_date' => '2026-12-25', 'name' => 'Christmas'])
            ->assertRedirect();

        $this->assertDatabaseHas('holidays', [
            'company_id' => $this->company->id, 'holiday_date' => '2026-12-25', 'name' => 'Christmas',
        ]);
    }
}
