<?php

namespace Modules\Company\app\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * A Company is the tenant boundary for the HR/Attendance/Leave/Payroll domain.
 * One HR (users.role='instructor') can own several; every HR-domain record
 * belongs to exactly one company. Access is granted through the company_user
 * pivot, never via owner_user_id directly.
 */
class Company extends Model
{
    public const ACTIVE    = 'active';
    public const SUSPENDED = 'suspended';

    protected $fillable = [
        'name', 'slug', 'code', 'owner_user_id', 'industry', 'timezone', 'status',
        'address', 'city', 'state', 'country', 'postal_code',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(CompanyUser::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'company_user')
            ->withPivot(['role', 'status'])
            ->withTimestamps();
    }

    /**
     * Companies the given user may act for (via the membership pivot).
     *
     * @return Collection<int, Company>
     */
    public static function forUser(User $user): Collection
    {
        return static::query()
            ->whereIn('id', CompanyUser::where('user_id', $user->id)->where('status', 'active')->pluck('company_id'))
            ->orderBy('name')
            ->get();
    }

    /** Does the given user have an active membership in this company? */
    public function hasMember(User $user): bool
    {
        return $this->memberships()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();
    }
}
