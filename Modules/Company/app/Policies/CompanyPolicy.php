<?php

namespace Modules\Company\app\Policies;

use App\Models\User;
use Modules\Company\app\Models\Company;

/**
 * A user may view/update a company only if they hold an active membership row
 * in company_user for it. This is the single gate for "does this HR own this
 * company" — never trust owner_user_id or a URL id alone.
 */
class CompanyPolicy
{
    public function view(User $user, Company $company): bool
    {
        return $company->hasMember($user);
    }

    public function update(User $user, Company $company): bool
    {
        return $company->hasMember($user);
    }

    /** Switching the active company requires membership. */
    public function switch(User $user, Company $company): bool
    {
        return $company->hasMember($user);
    }
}
