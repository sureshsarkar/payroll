<?php

namespace Modules\Payroll\app\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Company\app\Concerns\BelongsToCompany;

class PayrollRun extends Model
{
    use BelongsToCompany;
    use SoftDeletes;

    public const DRAFT          = 'draft';
    public const HR_SUBMITTED   = 'hr_submitted';
    public const ADMIN_APPROVED = 'admin_approved';
    public const PAID           = 'paid';

    /**
     * Human labels for each stored status. HR now finalises payroll directly
     * (no Super Admin approval step), so the stored `admin_approved` value is
     * surfaced as "Finalized" everywhere it faces a user.
     */
    public const STATUS_LABELS = [
        self::DRAFT          => 'Draft',
        self::HR_SUBMITTED   => 'Submitted',
        self::ADMIN_APPROVED => 'Finalized',
        self::PAID           => 'Paid',
    ];

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

    /** Display label for the run's current status (see STATUS_LABELS). */
    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? str_replace('_', ' ', $this->status);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::DRAFT], true);
    }

    /**
     * HR may pull a run back to draft to fix attendance — both a still-open
     * submitted run and an already-finalised one (but never a PAID run).
     */
    public function isReopenable(): bool
    {
        return in_array($this->status, [self::HR_SUBMITTED, self::ADMIN_APPROVED], true);
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

    /**
     * HR may delete a run from the list unless wages have actually been paid —
     * a PAID run is audit history and must not disappear.
     */
    public function isDeletable(): bool
    {
        return $this->status !== self::PAID;
    }

    /** Any item whose stored numbers predate a later attendance/leave edit. */
    public function hasStaleItems(): bool
    {
        return $this->items->contains(fn (PayrollItem $item) => $item->isStale($this));
    }
}
