<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Multi-tenant conversion — Phase 2 (backfill).
 *
 * Creates one Company per existing HR (users.role='instructor') that owns any
 * employees, then stamps company_id onto every HR-domain row reachable from
 * that HR's employees. Uses raw DB queries (no Eloquent) so no model scope or
 * event interferes.
 *
 * Ownership model (matches EmployeeProfile::teamUserIds):
 *   employees(hr) = employee_profiles.reporting_hr_id = hr.id
 *                 ∪ users(role='student', coach_id = hr.id)   [legacy fallback]
 *
 * NOT automatically reversible — down() clears the stamps and removes the
 * companies it created, but a corrupted run should be restored from the DB
 * backup taken before this migration (per the conversion checkpoint).
 */
return new class extends Migration
{
    public function up(): void
    {
        $firstCompanyId = null;

        $hrs = DB::table('users')->where('role', 'instructor')->orderBy('id')->get(['id', 'name']);

        foreach ($hrs as $hr) {
            $employeeIds = $this->employeeIdsFor($hr->id);
            $ownsRuns    = DB::table('payroll_runs')->where('prepared_by', $hr->id)->exists();

            // Skip HRs with nothing to own — they'll register a company themselves later.
            if ($employeeIds->isEmpty() && ! $ownsRuns) {
                continue;
            }

            $companyId = $this->createCompany($hr);
            $firstCompanyId ??= $companyId;

            $this->stampEmployeeData($companyId, $hr->id, $employeeIds);
        }

        // Global leave_types + any unassigned departments belong to the first
        // company in a single-company install; replicate leave_types for extras.
        $this->assignLeaveTypes($firstCompanyId);
        $this->assignOrphanDepartments($firstCompanyId);
    }

    /** @return \Illuminate\Support\Collection<int,int> */
    private function employeeIdsFor(int $hrId): \Illuminate\Support\Collection
    {
        $byProfile = DB::table('employee_profiles')->where('reporting_hr_id', $hrId)->pluck('user_id');
        $byCoach   = DB::table('users')->where('role', 'student')->where('coach_id', $hrId)->pluck('id');

        return $byProfile->merge($byCoach)->unique()->values();
    }

    private function createCompany(object $hr): int
    {
        $name = trim(($hr->name ?: 'HR '.$hr->id)."'s Company");
        $slug = $this->uniqueSlug(Str::slug($name) ?: 'company-'.$hr->id);

        $companyId = DB::table('companies')->insertGetId([
            'name'          => $name,
            'slug'          => $slug,
            'owner_user_id' => $hr->id,
            'timezone'      => 'Asia/Kolkata',
            'status'        => 'active',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        DB::table('company_user')->insert([
            'company_id' => $companyId,
            'user_id'    => $hr->id,
            'role'       => 'owner',
            'status'     => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $companyId;
    }

    private function uniqueSlug(string $base): string
    {
        $slug = $base;
        $i = 1;
        while (DB::table('companies')->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }

    private function stampEmployeeData(int $companyId, int $hrId, \Illuminate\Support\Collection $employeeIds): void
    {
        // employee_profiles — by the HR link OR the employee set
        DB::table('employee_profiles')
            ->where('reporting_hr_id', $hrId)
            ->orWhereIn('user_id', $employeeIds)
            ->whereNull('company_id')
            ->update(['company_id' => $companyId]);

        if ($employeeIds->isNotEmpty()) {
            // Tables keyed directly by the employee user_id
            foreach (['attendances', 'attendance_regularizations', 'leaves', 'leave_balances',
                      'salary_structures', 'salary_revisions', 'payroll_items', 'loans_advances', 'bonuses'] as $tbl) {
                DB::table($tbl)->whereIn('user_id', $employeeIds)->whereNull('company_id')
                    ->update(['company_id' => $companyId]);
            }

            // salary_components — via their parent structure
            $structureIds = DB::table('salary_structures')->whereIn('user_id', $employeeIds)->pluck('id');
            if ($structureIds->isNotEmpty()) {
                DB::table('salary_components')->whereIn('salary_structure_id', $structureIds)->whereNull('company_id')
                    ->update(['company_id' => $companyId]);
            }

            // departments — those this company's employees actually belong to
            $deptIds = DB::table('employee_profiles')->whereIn('user_id', $employeeIds)
                ->whereNotNull('department_id')->pluck('department_id')->unique();
            if ($deptIds->isNotEmpty()) {
                DB::table('departments')->whereIn('id', $deptIds)->whereNull('company_id')
                    ->update(['company_id' => $companyId]);
            }
        }

        // payroll_runs — owned by the HR who prepared them
        DB::table('payroll_runs')->where('prepared_by', $hrId)->whereNull('company_id')
            ->update(['company_id' => $companyId]);
    }

    /** Assign the existing global leave types to the first company; replicate for the rest. */
    private function assignLeaveTypes(?int $firstCompanyId): void
    {
        if ($firstCompanyId === null) {
            return;
        }

        DB::table('leave_types')->whereNull('company_id')->update(['company_id' => $firstCompanyId]);

        $template = DB::table('leave_types')->where('company_id', $firstCompanyId)->get();
        $otherCompanies = DB::table('companies')->where('id', '!=', $firstCompanyId)->pluck('id');

        foreach ($otherCompanies as $cid) {
            foreach ($template as $lt) {
                $row = (array) $lt;
                unset($row['id']);
                $row['company_id'] = $cid;
                $row['created_at'] = now();
                $row['updated_at'] = now();
                DB::table('leave_types')->insert($row);
            }
        }
    }

    /** Departments no employee references still need a home in a single-company install. */
    private function assignOrphanDepartments(?int $firstCompanyId): void
    {
        if ($firstCompanyId === null) {
            return;
        }

        $companyCount = DB::table('companies')->count();
        if ($companyCount === 1) {
            DB::table('departments')->whereNull('company_id')->update(['company_id' => $firstCompanyId]);
        }
        // With multiple companies, leave orphan departments NULL for manual review
        // (the verification step reports them; NOT NULL is only added afterwards).
    }

    public function down(): void
    {
        foreach (['departments', 'employee_profiles', 'attendances', 'attendance_regularizations',
                  'leave_types', 'leaves', 'leave_balances', 'salary_structures', 'salary_components',
                  'salary_revisions', 'payroll_runs', 'payroll_items', 'loans_advances', 'bonuses'] as $tbl) {
            DB::table($tbl)->update(['company_id' => null]);
        }

        DB::table('company_user')->delete();
        DB::table('companies')->delete();
    }
};
