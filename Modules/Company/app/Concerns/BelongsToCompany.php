<?php

namespace Modules\Company\app\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Company\app\Models\Company;
use Modules\Company\app\Scopes\CompanyScope;

/**
 * Makes a model tenant-aware:
 *   - every query is filtered to the active company (via CompanyScope), and
 *   - new records are auto-stamped with the active company's id.
 *
 * Both behaviours no-op when no company is bound, so console/seeder/backfill/
 * Super-Admin code paths keep working unscoped. To read across tenants in a
 * scoped context (rare — mostly Super Admin), use
 * `Model::withoutGlobalScope(CompanyScope::class)`.
 */
trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope());

        static::creating(function ($model) {
            if (empty($model->company_id) && ($company = currentCompany())) {
                $model->company_id = $company->id;
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
