<?php

namespace Modules\HrEmployee\app\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $fillable = [
        'name', 'code', 'head_user_id', 'parent_id', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function head(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_user_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(EmployeeProfile::class, 'department_id');
    }
}
