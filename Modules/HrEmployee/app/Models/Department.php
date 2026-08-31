<?php

namespace Modules\HrEmployee\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Company\app\Concerns\BelongsToCompany;

class Department extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'name', 'code', 'parent_id', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(EmployeeProfile::class, 'department_id');
    }
}
