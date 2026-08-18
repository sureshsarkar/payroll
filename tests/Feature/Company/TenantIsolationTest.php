<?php

namespace Tests\Feature\Company;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Modules\Company\app\Models\Company;
use Modules\HrEmployee\app\Models\Department;
use Modules\HrEmployee\app\Models\EmployeeProfile;
use Tests\TestCase;

/**
 * The release gate for multi-tenancy: data from one company must never be
 * reachable while another company is the active context. Exercises the
 * CompanyScope + BelongsToCompany auto-stamp + company-aware teamUserIds at the
 * model layer — the security boundary the whole feature rests on.
 *
 * Wrapped in DatabaseTransactions so it rolls back and never pollutes the DB.
 */
class TenantIsolationTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        app()->forgetInstance('currentCompany');
        parent::tearDown();
    }

    private function makeCompany(string $name): Company
    {
        return Company::create([
            'name'          => $name,
            'slug'          => Str::slug($name).'-'.Str::random(6),
            'owner_user_id' => 990001,
            'timezone'      => 'Asia/Kolkata',
            'status'        => 'active',
        ]);
    }

    private function bind(?Company $company): void
    {
        if ($company) {
            app()->instance('currentCompany', $company);
        } else {
            app()->forgetInstance('currentCompany');
        }
    }

    /**
     * Force-create an employee profile in a specific company. company_id is
     * intentionally NOT mass-assignable (so requests can't inject a foreign
     * tenant), so fixtures must set it explicitly via forceFill.
     */
    private function profile(int $companyId, int $userId, ?int $hr = null): EmployeeProfile
    {
        $p = new EmployeeProfile();
        $p->forceFill(['company_id' => $companyId, 'user_id' => $userId, 'reporting_hr_id' => $hr]);
        $p->save();

        return $p;
    }

    /** A read while company B is active must never see company A's rows. */
    public function test_reads_are_isolated_by_company(): void
    {
        $a = $this->makeCompany('Alpha');
        $b = $this->makeCompany('Beta');

        $this->profile($a->id, 990101);
        $this->profile($b->id, 990102);

        $this->bind($a);
        $this->assertTrue(EmployeeProfile::where('user_id', 990101)->exists(), 'Alpha sees its own row');
        $this->assertFalse(EmployeeProfile::where('user_id', 990102)->exists(), 'Alpha must NOT see Beta row');

        $this->bind($b);
        $this->assertFalse(EmployeeProfile::where('user_id', 990101)->exists(), 'Beta must NOT see Alpha row');
        $this->assertTrue(EmployeeProfile::where('user_id', 990102)->exists(), 'Beta sees its own row');
    }

    /** find() by a foreign id must 404-equivalent (return null) under the scope. */
    public function test_direct_id_lookup_cannot_cross_companies(): void
    {
        $a = $this->makeCompany('Alpha');
        $b = $this->makeCompany('Beta');

        $betaRow = $this->profile($b->id, 990103);

        $this->bind($a);
        $this->assertNull(EmployeeProfile::find($betaRow->id), 'Alpha must not fetch Beta row by id');
    }

    /** New records are auto-stamped with the active company. */
    public function test_creates_are_stamped_with_active_company(): void
    {
        $a = $this->makeCompany('Alpha');
        $this->bind($a);

        $dept = Department::create(['name' => 'Ops', 'code' => 'OPS-'.Str::random(4)]);

        $this->assertSame($a->id, $dept->company_id);
    }

    /** A colliding reporting_hr_id in another company must not leak into a team. */
    public function test_team_user_ids_cannot_cross_companies(): void
    {
        $a = $this->makeCompany('Alpha');
        $b = $this->makeCompany('Beta');

        // Same reporting_hr_id (5555) used in BOTH companies.
        $this->profile($a->id, 990201, 5555);
        $this->profile($b->id, 990202, 5555);

        $hr = new User();
        $hr->id = 5555;

        $this->bind($a);
        $this->assertEquals([990201], EmployeeProfile::teamUserIds($hr)->all(), 'Alpha HR sees only Alpha employee');

        $this->bind($b);
        $this->assertEquals([990202], EmployeeProfile::teamUserIds($hr)->all(), 'Beta HR sees only Beta employee');
    }

    /** Super Admin path (no bound company) sees across companies. */
    public function test_unbound_context_is_unscoped(): void
    {
        $a = $this->makeCompany('Alpha');
        $b = $this->makeCompany('Beta');
        $this->profile($a->id, 990301);
        $this->profile($b->id, 990302);

        $this->bind(null);

        $this->assertTrue(EmployeeProfile::where('user_id', 990301)->exists());
        $this->assertTrue(EmployeeProfile::where('user_id', 990302)->exists());
    }
}
