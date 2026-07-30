<?php

namespace Modules\Leave\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    protected $fillable = [
        'name', 'code', 'is_paid', 'annual_quota', 'carry_forward', 'is_active',
    ];

    protected $casts = [
        'is_paid'       => 'boolean',
        'carry_forward' => 'boolean',
        'is_active'     => 'boolean',
        'annual_quota'  => 'decimal:1',
    ];

    public function leaves(): HasMany
    {
        return $this->hasMany(Leave::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
