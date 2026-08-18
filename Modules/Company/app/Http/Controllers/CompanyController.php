<?php

namespace Modules\Company\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Modules\Company\app\Http\Middleware\EnsureCompanyContext;
use Modules\Company\app\Models\Company;
use Modules\Company\app\Models\CompanyUser;

/**
 * HR-facing company management: list / register / switch the active company.
 * These routes deliberately do NOT carry the company-context middleware, so an
 * HR with zero companies can still reach the register form.
 */
class CompanyController extends Controller
{
    /** List the HR's companies with the active one highlighted. */
    public function index(Request $request): View
    {
        $companies = Company::forUser($request->user());

        return view('company::index', [
            'companies' => $companies,
            'activeId'  => (int) session(EnsureCompanyContext::SESSION_KEY),
        ]);
    }

    public function create(): View
    {
        return view('company::create');
    }

    /** Register a new company owned by the acting HR, then make it active. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:191'],
            'industry' => ['nullable', 'string', 'max:191'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'address'  => ['nullable', 'string', 'max:191'],
            'city'     => ['nullable', 'string', 'max:191'],
            'state'    => ['nullable', 'string', 'max:191'],
            'country'  => ['nullable', 'string', 'max:191'],
        ]);

        $company = DB::transaction(function () use ($data, $request) {
            $company = Company::create([
                'name'          => $data['name'],
                'slug'          => $this->uniqueSlug($data['name']),
                'owner_user_id' => $request->user()->id,
                'industry'      => $data['industry'] ?? null,
                'timezone'      => $data['timezone'] ?: 'Asia/Kolkata',
                'status'        => Company::ACTIVE,
                'address'       => $data['address'] ?? null,
                'city'          => $data['city'] ?? null,
                'state'         => $data['state'] ?? null,
                'country'       => $data['country'] ?? null,
            ]);

            CompanyUser::create([
                'company_id' => $company->id,
                'user_id'    => $request->user()->id,
                'role'       => CompanyUser::OWNER,
                'status'     => 'active',
            ]);

            $this->seedDefaults($company->id);

            return $company;
        });

        session([EnsureCompanyContext::SESSION_KEY => $company->id]);

        return redirect()->route('hr.overview')
            ->with('success', __(':name is ready.', ['name' => $company->name]));
    }

    /** Switch the active company (membership-checked). */
    public function switch(Request $request, Company $company): RedirectResponse
    {
        abort_unless($company->hasMember($request->user()), 403);

        session([EnsureCompanyContext::SESSION_KEY => $company->id]);

        return redirect()->back()
            ->with('success', __('Switched to :name.', ['name' => $company->name]));
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'company';
        $slug = $base;
        $i = 1;
        while (Company::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }

    /**
     * Seed sane defaults for a brand-new company so the HR isn't staring at an
     * empty workspace: a "General" department + the standard leave types.
     * company_id is set explicitly (the new company isn't the active context yet).
     */
    private function seedDefaults(int $companyId): void
    {
        DB::table('departments')->insert([
            'company_id' => $companyId,
            'name'       => 'General',
            'code'       => 'GEN',
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $leaveTypes = [
            ['name' => 'Casual Leave', 'code' => 'CL',  'is_paid' => true,  'annual_quota' => 12, 'carry_forward' => false],
            ['name' => 'Sick Leave',   'code' => 'SL',  'is_paid' => true,  'annual_quota' => 10, 'carry_forward' => false],
            ['name' => 'Earned Leave',  'code' => 'EL', 'is_paid' => true,  'annual_quota' => 15, 'carry_forward' => true],
            ['name' => 'Loss of Pay',   'code' => 'LOP', 'is_paid' => false, 'annual_quota' => 0,  'carry_forward' => false],
        ];
        foreach ($leaveTypes as $lt) {
            DB::table('leave_types')->insert($lt + [
                'company_id' => $companyId,
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
