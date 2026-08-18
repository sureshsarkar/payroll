<?php

namespace Modules\Company\app\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global tenant scope. Filters every query on a `company_id` model by the
 * request's active company.
 *
 * CRITICAL: when no company is bound (CLI, seeders, the backfill migration,
 * queued jobs, and Super-Admin requests that never set a context) it is a
 * NO-OP — it must never filter, or console/queue code and platform-wide admin
 * views would silently return nothing.
 */
class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $company = currentCompany();

        if ($company) {
            // Qualify the column so the scope is safe inside joins.
            $builder->where($model->getTable().'.company_id', $company->id);
        }
    }
}
