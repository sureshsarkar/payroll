<?php

namespace Modules\Payroll\app\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Company\app\Concerns\BelongsToCompany;

class PayrollItem extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'payroll_run_id', 'user_id', 'payable_days', 'lop_days',
        'gross', 'total_earnings', 'total_deductions', 'lop_amount',
        'net_pay', 'earnings', 'deductions', 'payslip_path', 'notified_at',
    ];

    protected $casts = [
        'lop_days'         => 'decimal:1',
        'gross'            => 'decimal:2',
        'total_earnings'   => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'lop_amount'       => 'decimal:2',
        'net_pay'          => 'decimal:2',
        'earnings'         => 'array',
        'deductions'       => 'array',
        'notified_at'      => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
