<?php

namespace Modules\Payroll\app\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Company\app\Concerns\BelongsToCompany;

class SalaryStructure extends Model
{
    use BelongsToCompany;

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

    /**
     * The HRA component's stored percentage-of-basic. This is what the user
     * actually typed when the structure was saved — the salary form must show
     * this back, not a hard-coded 40, otherwise a manually entered figure looks
     * like it never saved.
     */
    public function hraPercent(): ?float
    {
        $hra = $this->components->firstWhere('code', 'HRA')
            ?? $this->components->firstWhere('name', 'HRA');

        return $hra ? (float) $hra->value : null;
    }

    /** The Special Allowance component's stored monthly amount. */
    public function special(): ?float
    {
        $spl = $this->components->firstWhere('code', 'SPL')
            ?? $this->components->firstWhere('name', 'Special Allowance');

        return $spl ? (float) $spl->value : null;
    }
}
