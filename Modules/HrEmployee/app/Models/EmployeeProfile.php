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
        'status',
    ];

    protected $casts = [
        'date_of_joining' => 'date',
        'date_of_exit'    => 'date',
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
