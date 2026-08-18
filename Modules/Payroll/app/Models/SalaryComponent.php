<?php

namespace Modules\Payroll\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Company\app\Concerns\BelongsToCompany;

class SalaryComponent extends Model
{
    use BelongsToCompany;

    public const EARNING   = 'earning';
    public const DEDUCTION = 'deduction';

    public const FIXED             = 'fixed';
    public const PERCENT_OF_BASIC  = 'percent_of_basic';
    public const PERCENT_OF_GROSS  = 'percent_of_gross';

    protected $fillable = [
        'salary_structure_id', 'type', 'name', 'code', 'calc_type',
        'value', 'is_statutory', 'sort_order',
    ];

    protected $casts = [
        'value'        => 'decimal:2',
        'is_statutory' => 'boolean',
    ];

    public function structure(): BelongsTo
    {
        return $this->belongsTo(SalaryStructure::class, 'salary_structure_id');
    }

    /** Resolve this component's monthly amount against a basic + gross base. */
    public function resolveAmount(float $basic, float $gross): float
    {
        return match ($this->calc_type) {
            self::PERCENT_OF_BASIC => round($basic * ((float) $this->value) / 100, 2),
            self::PERCENT_OF_GROSS => round($gross * ((float) $this->value) / 100, 2),
            default                => round((float) $this->value, 2),
        };
    }
}
