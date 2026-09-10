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
     * The HRA component's stored monthly amount. HRA is now a plain amount; a
     * legacy structure that stored it as a percentage-of-basic is resolved to
     * its rupee value so the salary form still shows a sane figure.
     */
    public function hraAmount(): ?float
    {
        return $this->earningAmount('HRA', 'HRA');
    }

    /** The Convenience component's stored monthly amount. */
    public function convenience(): ?float
    {
        return $this->earningAmount('CONV', 'Convenience');
    }

    /**
     * The Other Balance component's stored monthly amount. Falls back to a
     * legacy "Special Allowance" (code SPL) component, which it replaced.
     */
    public function otherBalance(): ?float
    {
        return $this->earningAmount('OTHR', 'Other Balance')
            ?? $this->earningAmount('SPL', 'Special Allowance');
    }

    /**
     * The HRA component's stored percentage-of-basic, if it was saved that way.
     * Retained for legacy views/reports; the salary form uses hraAmount().
     */
    public function hraPercent(): ?float
    {
        $hra = $this->components->firstWhere('code', 'HRA')
            ?? $this->components->firstWhere('name', 'HRA');

        return $hra && $hra->calc_type === 'percent_of_basic' ? (float) $hra->value : null;
    }

    /** Resolved ₹ amount of an earning component, matched by code then name. */
    private function earningAmount(string $code, string $name): ?float
    {
        $c = $this->components->firstWhere('code', $code)
            ?? $this->components->firstWhere('name', $name);

        if (! $c) {
            return null;
        }

        return $c->resolveAmount($this->basic(), (float) $this->gross_monthly);
    }
}
