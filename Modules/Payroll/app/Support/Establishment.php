<?php

namespace Modules\Payroll\app\Support;

use Modules\Company\app\Models\Company;
use Modules\Payroll\app\Models\PayrollRun;

/**
 * The employer identity printed on statutory payroll documents.
 *
 * Resolved from the company the payroll run belongs to, so a slip exported in
 * one tenant can never carry another tenant's letterhead. Falls back to the
 * install-wide PAYROLL_ESTABLISHMENT_* config for pre-multi-tenant data whose
 * runs have no company_id yet.
 *
 * @phpstan-type Letterhead array{name:string, address:string, logo:?string, pf_no:string, esi_no:string, contact:string}
 */
class Establishment
{
    /** @return Letterhead */
    public static function forRun(PayrollRun $run): array
    {
        $company = $run->company_id
            ? Company::withoutGlobalScopes()->find($run->company_id)
            : null;

        $config = config('payroll.establishment');

        if (! $company) {
            return [
                'name'    => $config['name'] ?: config('app.name'),
                'address' => $config['address'] ?: '',
                'logo'    => null,
                'pf_no'   => $config['pf_no'] ?: '',
                'esi_no'  => $config['esi_no'] ?: '',
                'contact' => '',
            ];
        }

        return [
            'name'    => $company->name,
            'address' => $company->addressLine() ?: ($config['address'] ?: ''),
            'logo'    => self::logoPath($company),
            'pf_no'   => $company->pf_number ?: ($config['pf_no'] ?: ''),
            'esi_no'  => $company->esi_number ?: ($config['esi_no'] ?: ''),
            'contact' => $company->contactLine(),
        ];
    }

    /** Absolute path to the logo file, or null when unset/missing on disk. */
    private static function logoPath(Company $company): ?string
    {
        if (blank($company->logo_path)) {
            return null;
        }

        $path = public_path($company->logo_path);

        return is_file($path) ? $path : null;
    }
}
