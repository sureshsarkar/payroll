<?php

namespace Tests\Feature\Payroll;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Modules\Company\app\Models\Company;
use Modules\Payroll\app\Models\PayrollRun;
use Modules\Payroll\app\Support\Establishment;
use Tests\TestCase;

/**
 * The Form IV / Form XI letterhead must always come from the run's OWN
 * company — never a different tenant's, and never the wrong one when two
 * companies share the same HR account. This is the exact requirement behind
 * the 2026-08-25 Form IV redesign: "Company A's employee sees Company A's
 * letterhead; never another company's information."
 */
class EstablishmentIsolationTest extends TestCase
{
    use DatabaseTransactions;

    private function makeCompany(string $name, array $attrs = []): Company
    {
        return Company::create(array_merge([
            'name'          => $name,
            'slug'          => Str::slug($name).'-'.Str::random(6),
            'owner_user_id' => 990001,
            'timezone'      => 'Asia/Kolkata',
            'status'        => 'active',
        ], $attrs));
    }

    public function test_letterhead_matches_the_runs_own_company_not_another(): void
    {
        $a = $this->makeCompany('Alpha Textiles', ['address' => 'Alpha Street', 'pf_number' => 'PF-A']);
        $b = $this->makeCompany('Beta Garments', ['address' => 'Beta Road', 'pf_number' => 'PF-B']);

        $runA = PayrollRun::create(['year' => 2026, 'month' => 8, 'status' => PayrollRun::DRAFT]);
        $runA->forceFill(['company_id' => $a->id])->save();

        $runB = PayrollRun::create(['year' => 2026, 'month' => 8, 'status' => PayrollRun::DRAFT]);
        $runB->forceFill(['company_id' => $b->id])->save();

        $letterheadA = Establishment::forRun($runA);
        $letterheadB = Establishment::forRun($runB);

        $this->assertSame('Alpha Textiles', $letterheadA['name']);
        $this->assertSame('PF-A', $letterheadA['pf_no']);

        $this->assertSame('Beta Garments', $letterheadB['name']);
        $this->assertSame('PF-B', $letterheadB['pf_no']);

        $this->assertNotSame($letterheadA['name'], $letterheadB['name']);
    }

    /** A run with no company_id (pre-multi-tenant data) falls back to the install-wide config, not a stray company. */
    public function test_run_without_company_falls_back_to_config_not_any_company(): void
    {
        $this->makeCompany('Should Not Appear');
        $run = PayrollRun::create(['year' => 2026, 'month' => 8, 'status' => PayrollRun::DRAFT]);

        $letterhead = Establishment::forRun($run);

        $this->assertNotSame('Should Not Appear', $letterhead['name']);
    }
}
