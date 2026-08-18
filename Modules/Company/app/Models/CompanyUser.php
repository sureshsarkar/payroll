<?php

namespace Modules\Company\app\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Membership pivot: which users may act for which company, and in what role.
 * Phase 1 only ever creates 'owner' rows; 'hr_staff' is reserved for a later
 * multi-staff phase.
 */
class CompanyUser extends Model
{
    protected $table = 'company_user';

    public const OWNER    = 'owner';
    public const HR_STAFF = 'hr_staff';

    protected $fillable = ['company_id', 'user_id', 'role', 'status'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
