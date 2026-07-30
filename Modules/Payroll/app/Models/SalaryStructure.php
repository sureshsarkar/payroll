<?php

namespace Modules\Payroll\app\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalaryStructure extends Model
{
    protected $fillable = [
        'user_id', 'ctc_annual', 'gross_monthly', 'effective_from',
        'is_current', 'created_by',
    ];

    protected $casts = [
        'ctc_annual'     => 'decimal:2',
        'gross_monthly'  => 'decimal:2',
        'effective_from' => 'date',
        'is_current'     => 'boolean',
    ];

    public function components(): HasMany
    {
        return $this->hasMany(SalaryComponent::class)->orderBy('sort_order');
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(SalaryComponent::class)->where('type', 'earning')->orderBy('sort_order');
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(SalaryComponent::class)->where('type', 'deduction')->orderBy('sort_order');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Monthly "Basic" amount — the base for percentage components. */
    public function basic(): float
    {
        $basic = $this->components->firstWhere('code', 'BASIC')
            ?? $this->components->firstWhere('name', 'Basic');

        return (float) ($basic->value ?? 0);
    }
}
