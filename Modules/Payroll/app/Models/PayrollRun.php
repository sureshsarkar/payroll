<?php

namespace Modules\Payroll\app\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Company\app\Concerns\BelongsToCompany;

class PayrollRun extends Model
{
    use BelongsToCompany;

    public const DRAFT          = 'draft';
    public const HR_SUBMITTED   = 'hr_submitted';
    public const ADMIN_APPROVED = 'admin_approved';
    public const PAID           = 'paid';

    protected $fillable = [
        'year', 'month', 'status', 'employee_count', 'total_net',
        'prepared_by', 'approved_by', 'submitted_at', 'approved_at',
    ];

    protected $casts = [
        'total_net'    => 'decimal:2',
        'submitted_at' => 'datetime',
        'approved_at'  => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function periodLabel(): string
    {
        return Carbon::create($this->year, $this->month, 1)->format('F Y');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::DRAFT], true);
    }

    /** HR may send a submitted-but-not-yet-approved run back to draft to fix attendance. */
    public function isReopenable(): bool
    {
        return $this->status === self::HR_SUBMITTED;
    }

    /**
     * Super Admin may recompute an approved run in place — e.g. an absence
     * was corrected after approval. Excludes PAID: once wages are actually
     * disbursed, a correction belongs in a separate off-cycle/arrears run,
     * not a silent rewrite of paid history.
     */
    public function isRecalculable(): bool
    {
        return $this->status === self::ADMIN_APPROVED;
    }

    /** Any item whose stored numbers predate a later attendance/leave edit. */
    public function hasStaleItems(): bool
    {
        return $this->items->contains(fn (PayrollItem $item) => $item->isStale($this));
    }
}
