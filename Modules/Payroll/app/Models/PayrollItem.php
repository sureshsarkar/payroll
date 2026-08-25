<?php

namespace Modules\Payroll\app\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Attendance\app\Models\Attendance;
use Modules\Company\app\Concerns\BelongsToCompany;
use Modules\Leave\app\Models\Leave;

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

    /**
     * True when this employee's attendance or leave records for the run's
     * month were touched AFTER this item's numbers were last computed — e.g.
     * an absence marked/corrected after the run was prepared, submitted, or
     * even approved. The stored net_pay is then stale until someone
     * re-prepares (draft) or recalculates (submitted/approved) the run.
     */
    public function isStale(?PayrollRun $run = null): bool
    {
        $run   = $run ?? $this->run;
        $start = Carbon::create($run->year, $run->month, 1)->startOfMonth();
        $end   = (clone $start)->endOfMonth();

        $attendanceChanged = Attendance::withoutGlobalScopes()
            ->where('user_id', $this->user_id)
            ->whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()])
            ->where('updated_at', '>', $this->updated_at)
            ->exists();

        if ($attendanceChanged) {
            return true;
        }

        return Leave::withoutGlobalScopes()
            ->where('user_id', $this->user_id)
            ->where('status', Leave::APPROVED)
            ->where('start_date', '<=', $end->toDateString())
            ->where('end_date', '>=', $start->toDateString())
            ->where('updated_at', '>', $this->updated_at)
            ->exists();
    }
}
