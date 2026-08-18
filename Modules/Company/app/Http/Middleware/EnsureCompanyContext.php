<?php

namespace Modules\Company\app\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Company\app\Models\Company;
use Modules\HrEmployee\app\Models\EmployeeProfile;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the "active company" for the current request and binds it into the
 * container as `currentCompany`, which the tenant global scope reads.
 *
 *   HR (instructor) : session('active_company_id'); falls back to their only
 *                     company. Zero companies → forced to register one; several
 *                     with none chosen → company picker.
 *   Employee (student): implicit — their employee_profiles.company_id.
 *
 * Super Admin (admin guard) never hits this middleware; they browse unscoped.
 */
class EnsureCompanyContext
{
    public const SESSION_KEY = 'active_company_id';

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // Not a web user (or admin guard) — nothing to scope here.
        if (! $user) {
            return $next($request);
        }

        if (($user->role ?? null) === 'instructor') {
            return $this->forHr($request, $next, $user);
        }

        if (($user->role ?? null) === 'student') {
            $company = $this->employeeCompany($user);
            if ($company) {
                app()->instance('currentCompany', $company);
            }

            return $next($request);
        }

        return $next($request);
    }

    private function forHr(Request $request, Closure $next, $user): Response
    {
        $companies = Company::forUser($user);

        if ($companies->isEmpty()) {
            return redirect()->route('hr.companies.create')
                ->with('info', __('Register your first company to get started.'));
        }

        $activeId = (int) session(self::SESSION_KEY);
        $active   = $companies->firstWhere('id', $activeId);

        if (! $active && $companies->count() === 1) {
            $active = $companies->first();
            session([self::SESSION_KEY => $active->id]);
        }

        if (! $active) {
            return redirect()->route('hr.companies.index')
                ->with('info', __('Select a company to continue.'));
        }

        app()->instance('currentCompany', $active);

        return $next($request);
    }

    private function employeeCompany($user): ?Company
    {
        $companyId = EmployeeProfile::where('user_id', $user->id)->value('company_id');

        return $companyId ? Company::find($companyId) : null;
    }
}
