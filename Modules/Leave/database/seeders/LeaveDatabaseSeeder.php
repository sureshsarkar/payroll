<?php

namespace Modules\Leave\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Company\app\Models\Company;
use Modules\Company\app\Scopes\CompanyScope;
use Modules\Leave\app\Models\LeaveType;

class LeaveDatabaseSeeder extends Seeder
{
    /**
     * Seed the standard leave types per company (idempotent — safe to re-run).
     * Multi-tenant: leave types belong to a company, so seed a set for each
     * existing company. New companies get their own set at registration time
     * (CompanyController::seedDefaults).
     */
    public function run(): void
    {
        $types = [
            ['name' => 'Casual Leave',  'code' => 'CL',  'is_paid' => true,  'annual_quota' => 12, 'carry_forward' => false],
            ['name' => 'Sick Leave',    'code' => 'SL',  'is_paid' => true,  'annual_quota' => 10, 'carry_forward' => false],
            ['name' => 'Earned Leave',  'code' => 'EL',  'is_paid' => true,  'annual_quota' => 15, 'carry_forward' => true],
            ['name' => 'Loss of Pay',   'code' => 'LOP', 'is_paid' => false, 'annual_quota' => 0,  'carry_forward' => false],
        ];

        foreach (Company::pluck('id') as $companyId) {
            foreach ($types as $t) {
                LeaveType::withoutGlobalScope(CompanyScope::class)->updateOrCreate(
                    ['company_id' => $companyId, 'code' => $t['code']],
                    $t + ['company_id' => $companyId, 'is_active' => true],
                );
            }
        }
    }
}
