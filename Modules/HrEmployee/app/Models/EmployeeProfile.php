<?php

namespace Modules\HrEmployee\app\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Company\app\Concerns\BelongsToCompany;

class EmployeeProfile extends Model
{
    use BelongsToCompany;
    use SoftDeletes;

    public const ACTIVE     = 'active';
    public const ONBOARDING = 'onboarding';
    public const EXITED     = 'exited';
    public const SUSPENDED  = 'suspended';

    protected $fillable = [
        'user_id', 'employee_code', 'department_id', 'reporting_hr_id',
        'designation', 'employment_type', 'date_of_joining', 'date_of_exit',
        'status', 'photo_path', 'father_or_spouse_name', 'date_of_birth',
        'phone', 'personal_email', 'current_address', 'permanent_address',
        'bank_name', 'bank_account_number', 'bank_ifsc_code',
        'pf_number', 'uan_number', 'esi_number', 'pan_number', 'aadhaar_number',
        'emergency_contact_name', 'emergency_contact_phone',
        'nominee_name', 'nominee_relation',
    ];

    protected $casts = [
        'date_of_joining' => 'date',
        'date_of_exit'    => 'date',
        'date_of_birth'   => 'date',
        'bank_account_number' => 'encrypted',
        'pf_number'           => 'encrypted',
        'uan_number'          => 'encrypted',
        'esi_number'          => 'encrypted',
        'pan_number'          => 'encrypted',
        'aadhaar_number'      => 'encrypted',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function reportingHr(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporting_hr_id');
    }

    /**
     * User-ids of the employees an HR manages, scoped to the active company.
     *
     * The `reporting_hr_id` lookup is automatically constrained to the active
     * company by the CompanyScope global scope, so a colliding reporting_hr_id
     * in another tenant can never leak in. The legacy coach_id fallback is only
     * used when NO company context is bound (pre-company / CLI paths) — inside a
     * tenant request we trust profile membership alone, which cannot cross
     * companies.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    public static function teamUserIds(User $hr): \Illuminate\Support\Collection
    {
        $byProfile = static::where('reporting_hr_id', $hr->id)->pluck('user_id');

        if (currentCompany()) {
            return $byProfile->unique()->values();
        }

        $byCoach = User::where('role', 'student')->where('coach_id', $hr->id)->pluck('id');

        return $byProfile->merge($byCoach)->unique()->values();
    }
}
