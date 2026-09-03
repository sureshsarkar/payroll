<?php

namespace Tests\Feature\HrEmployee;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Modules\Company\app\Http\Middleware\EnsureCompanyContext;
use Modules\Company\app\Models\Company;
use Modules\Company\app\Models\CompanyUser;
use Modules\HrEmployee\app\Models\Department;
use Modules\HrEmployee\app\Models\EmployeeProfile;
use Tests\TestCase;

/**
 * The HR employee list: search across code / name / email / designation /
 * department, "newest added first" as the default order (without breaking a
 * hand-picked column sort), and 50-per-page pagination.
 */
class EmployeeListTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;
    private User $hr;
    private Department $dept;

    protected function setUp(): void
    {
        parent::setUp();

        // .env's APP_URL carries a /laravel/erpsystem/ sub-path; the test HTTP
        // kernel has no web-server rewrite, so routes only match from the root.
        config(['app.url' => 'http://localhost']);
        URL::forceRootUrl('http://localhost');

        // Render the list through a JSON stub, not the instructor-dashboard
        // master layout (which needs the settings/brand cache warmed). Every
        // other hremployee:: view still resolves from the module.
        View::replaceNamespace('hremployee', [
            __DIR__.'/stubs',
            base_path('Modules/HrEmployee/resources/views'),
        ]);

        $this->company = Company::create([
            'name'          => 'Acme '.Str::random(6),
            'slug'          => 'acme-'.Str::random(8),
            'owner_user_id' => 0,
            'timezone'      => 'Asia/Kolkata',
            'status'        => 'active',
        ]);

        $this->hr = User::factory()->create(['role' => 'instructor', 'coach_id' => null]);
        $this->company->update(['owner_user_id' => $this->hr->id]);
        CompanyUser::create([
            'company_id' => $this->company->id,
            'user_id'    => $this->hr->id,
            'role'       => 'owner',
            'status'     => 'active',
        ]);

        // Bind so BelongsToCompany stamps company_id on the fixtures below.
        app()->instance('currentCompany', $this->company);

        $this->dept = Department::create(['name' => 'Engineering', 'code' => 'ENG-'.Str::random(4), 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('currentCompany');
        parent::tearDown();
    }

    /**
     * Add one employee to this HR's team. $profileCreatedAt drives the
     * "newest first" ordering; $attrs overrides profile columns.
     */
    private function addEmployee(string $name, array $attrs = [], ?string $profileCreatedAt = null): User
    {
        $user = User::factory()->create([
            'name'   => $name,
            'role'   => 'student',
            'status' => 1,
        ] + ($attrs['user'] ?? []));

        $profile = EmployeeProfile::create(array_merge([
            'user_id'         => $user->id,
            'reporting_hr_id' => $this->hr->id,
            'department_id'   => $this->dept->id,
            'status'          => EmployeeProfile::ACTIVE,
        ], collect($attrs)->except('user')->all()));

        if ($profileCreatedAt) {
            $profile->forceFill(['created_at' => $profileCreatedAt])->saveQuietly();
        }

        return $user;
    }

    private function listing(array $query = [])
    {
        $url = 'hr/employees'.($query ? '?'.http_build_query($query) : '');

        return $this->actingAs($this->hr, 'web')
            ->withSession([EnsureCompanyContext::SESSION_KEY => $this->company->id])
            ->get($url);
    }

    public function test_default_order_is_newest_added_first(): void
    {
        $this->addEmployee('Oldest Olivia', ['employee_code' => 'E-001'], now()->subDays(10)->toDateTimeString());
        $this->addEmployee('Middle Mike', ['employee_code' => 'E-002'], now()->subDays(5)->toDateTimeString());
        $newest = $this->addEmployee('Newest Nadia', ['employee_code' => 'E-003'], now()->toDateTimeString());

        $response = $this->listing();
        $response->assertOk();

        $ids = $response->viewData('employees')->pluck('id')->all();

        $this->assertSame($newest->id, $ids[0], 'The most recently added employee is first');
        $this->assertSame('Newest Nadia', $response->viewData('employees')->first()->name);
    }

    public function test_manual_column_sort_overrides_the_default(): void
    {
        $this->addEmployee('Zed Zephyr', [], now()->toDateTimeString());          // newest
        $this->addEmployee('Aaron Abbott', [], now()->subDays(3)->toDateTimeString());

        $names = $this->listing(['sort' => 'name', 'dir' => 'asc'])
            ->viewData('employees')->pluck('name')->all();

        $this->assertSame(['Aaron Abbott', 'Zed Zephyr'], $names, 'Explicit name-asc sort wins over newest-first');
    }

    public function test_search_matches_code_name_email_designation_and_department(): void
    {
        $target = $this->addEmployee('Priya Sharma', [
            'employee_code' => 'ENG-4471',
            'designation'   => 'Staff Engineer',
            'user'          => ['email' => 'priya.unique@acme-test.example'],
        ]);
        $this->addEmployee('Bob Jones', ['employee_code' => 'FIN-0001', 'designation' => 'Accountant']);

        foreach (['ENG-4471', 'priya sha', 'priya.unique@acme', 'staff engineer', 'Engineer'] as $term) {
            $ids = $this->listing(['q' => $term])->viewData('employees')->pluck('id')->all();
            $this->assertContains($target->id, $ids, "'{$term}' should match Priya");
        }

        // A department search still narrows when only some rows are in it.
        $finance = Department::create(['name' => 'Finance', 'code' => 'FIN-'.Str::random(4), 'is_active' => true]);
        $this->addEmployee('Carol Finance', ['department_id' => $finance->id]);

        $deptHits = $this->listing(['q' => 'Finance'])->viewData('employees')->pluck('name')->all();
        $this->assertContains('Carol Finance', $deptHits);
        $this->assertNotContains('Priya Sharma', $deptHits);
    }

    public function test_search_is_case_insensitive_and_partial(): void
    {
        $target = $this->addEmployee('Alexander Hamilton', ['designation' => 'Treasury Lead']);

        $ids = $this->listing(['q' => 'HAMIL'])->viewData('employees')->pluck('id')->all();

        $this->assertSame([$target->id], $ids);
    }

    public function test_clearing_the_search_restores_the_full_list(): void
    {
        $this->addEmployee('Dana Scully');
        $this->addEmployee('Fox Mulder');

        $this->assertCount(1, $this->listing(['q' => 'scully'])->viewData('employees'));
        $this->assertCount(2, $this->listing()->viewData('employees'), 'No query -> every employee back');
    }

    public function test_list_paginates_at_fifty_per_page(): void
    {
        for ($i = 1; $i <= 55; $i++) {
            $this->addEmployee(sprintf('Emp %02d', $i), ['employee_code' => 'P-'.$i]);
        }

        $page1 = $this->listing()->viewData('employees');
        $this->assertSame(55, $page1->total());
        $this->assertSame(50, $page1->perPage());
        $this->assertCount(50, $page1);
        $this->assertTrue($page1->hasPages());

        $page2 = $this->listing(['page' => 2])->viewData('employees');
        $this->assertCount(5, $page2);
        $this->assertSame(2, $page2->currentPage());
    }

    public function test_pagination_keeps_the_search_and_sort_on_page_links(): void
    {
        $ops = Department::create(['name' => 'Operations', 'code' => 'OPS-'.Str::random(4), 'is_active' => true]);
        for ($i = 1; $i <= 55; $i++) {
            $this->addEmployee("Person {$i}", ['employee_code' => 'ENG-'.$i, 'designation' => 'Engineer']);
        }
        $this->addEmployee('Barista Bob', ['designation' => 'Barista', 'department_id' => $ops->id]);

        $paginator = $this->listing(['q' => 'Engineer', 'sort' => 'code', 'dir' => 'asc'])->viewData('employees');

        $this->assertSame(55, $paginator->total(), 'Only the 55 with the Engineer designation match');
        $this->assertStringContainsString('q=Engineer', $paginator->nextPageUrl());
        $this->assertStringContainsString('sort=code', $paginator->nextPageUrl());
    }

    public function test_bogus_sort_column_falls_back_to_default_without_erroring(): void
    {
        $this->addEmployee('Real Person', [], now()->toDateTimeString());

        $response = $this->listing(['sort' => 'password) DROP TABLE users;--', 'dir' => 'asc'])
            ->assertOk()
            ->assertViewHas('sort', 'created');

        // A bogus column resets to the natural default direction too.
        $this->assertSame('desc', $response->viewData('dir'));
    }
}
