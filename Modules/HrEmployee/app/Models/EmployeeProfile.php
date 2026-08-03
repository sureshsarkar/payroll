<?php

namespace Modules\HrEmployee\app\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeProfile extends Model
{
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
     * User-ids of the employees an HR manages: those reporting to them via
     * profile, plus their legacy coach_id-linked students (transition fallback).
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    public static function teamUserIds(User $hr): \Illuminate\Support\Collection
    {
        $byProfile = static::where('reporting_hr_id', $hr->id)->pluck('user_id');
        $byCoach   = User::where('role', 'student')->where('coach_id', $hr->id)->pluck('id');

        return $byProfile->merge($byCoach)->unique()->values();
    }
}
