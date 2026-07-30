<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A named tax rate owned by a coach (Phase 1, 2026-06-13), e.g. "GST 18%".
 * A coach may define several and mark one default; a course can point at a
 * specific rate (courses.tax_rate_id) else the coach default applies.
 */
class TaxRate extends Model
{
    protected $fillable = [
        'coach_id', 'name', 'rate', 'components', 'is_default', 'status',
    ];

    protected $casts = [
        'rate'       => 'decimal:3',
        'components' => 'array', // [{name, rate}, ...] or null for a single rate
        'is_default' => 'boolean',
    ];

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function scopeActive($q)
    {
        return $q->where('status', 'active');
    }
}
