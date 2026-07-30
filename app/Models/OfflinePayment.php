<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Offline Payment — Phase 1.
 *
 * One row per manually-recorded (offline) payment. Always scoped to a coach
 * (tenant). The record itself never processes money — it is written by
 * OfflinePaymentService, which applies the domain effect (enroll / mark fee
 * paid) separately and idempotently.
 */
class OfflinePayment extends Model
{
    use HasFactory;

    public const SOURCE_ORDER = 'order';
    public const SOURCE_FEE   = 'fee';
    public const SOURCE_TRIAL = 'trial';

    /** Payment methods offered by default; a coach may enable a subset. */
    public const METHODS = ['cash', 'bank_transfer', 'upi', 'cheque', 'other'];

    /** Human labels + an icon for each method (used by every offline form). */
    public const METHOD_META = [
        'cash'          => ['Cash', 'fa-money-bill-wave'],
        'bank_transfer' => ['Bank transfer', 'fa-building-columns'],
        'upi'           => ['UPI', 'fa-mobile-screen'],
        'cheque'        => ['Cheque', 'fa-money-check'],
        'other'         => ['Other', 'fa-ellipsis'],
    ];

    /**
     * The offline methods a coach accepts. NULL/empty config = all methods
     * (backward compatible). Always returns a non-empty, valid subset so a form
     * never renders zero methods.
     */
    public static function enabledMethodsForCoach(int $coachId): array
    {
        $row = \App\Models\CoachBrandSetting::where('coach_id', $coachId)->first();
        $set = $row?->offline_payment_methods;
        if (empty($set) || ! is_array($set)) {
            return self::METHODS;
        }
        $valid = array_values(array_intersect($set, self::METHODS));
        return $valid ?: self::METHODS;
    }

    public const STATUS_PAID    = 'paid';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_PENDING = 'pending';

    public const APPROVAL_AUTO     = 'auto_approved';
    public const APPROVAL_PENDING  = 'pending';
    public const APPROVAL_APPROVED = 'approved';
    public const APPROVAL_REJECTED = 'rejected';

    protected $fillable = [
        'coach_id', 'student_id', 'source_type', 'source_id', 'course_id', 'batch_id',
        'amount', 'base_amount', 'tax_amount', 'tax_rate', 'tax_label', 'currency', 'method', 'reference_no', 'paid_at',
        'proof_path', 'proof_name', 'notes',
        'status', 'approval_status',
        'recorded_by', 'approved_by', 'approved_at', 'cancelled_by', 'cancelled_at', 'cancel_reason',
        'receipt_no', 'order_id', 'fee_payment_id',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'base_amount'  => 'decimal:2',
        'tax_amount'   => 'decimal:2',
        'tax_rate'     => 'decimal:2',
        'paid_at'      => 'datetime',
        'approved_at'  => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /* ───────── relations ───────── */

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id')->withDefault();
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id')->withDefault();
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by')->withDefault();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by')->withDefault();
    }

    /* ───────── scopes (TENANT ISOLATION) ───────── */

    /**
     * The one and only tenant gate. Every read path MUST start from here so a
     * coach can never see another coach's offline payments, students or proofs.
     */
    public function scopeForCoach(Builder $q, int $coachId): Builder
    {
        return $q->where('coach_id', $coachId);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('approval_status', '!=', self::APPROVAL_REJECTED)
                 ->whereNull('cancelled_at');
    }

    /* ───────── helpers ───────── */

    /** Is the payment effective (applied to the fee/order) right now? */
    public function isEffective(): bool
    {
        return is_null($this->cancelled_at)
            && in_array($this->approval_status, [self::APPROVAL_AUTO, self::APPROVAL_APPROVED], true);
    }

    public function isAwaitingApproval(): bool
    {
        return $this->approval_status === self::APPROVAL_PENDING;
    }

    public function methodLabel(): string
    {
        return [
            'cash'          => 'Cash',
            'bank_transfer' => 'Bank transfer',
            'upi'           => 'UPI',
            'cheque'        => 'Cheque',
            'other'         => 'Other',
        ][$this->method] ?? ucfirst((string) $this->method);
    }

    /**
     * Per-coach, human-friendly receipt number. Prefixed so two coaches never
     * collide and a coach's own sequence reads cleanly (OFF-<coach>-<random>).
     */
    public static function generateReceiptNo(int $coachId): string
    {
        do {
            $no = 'OFF-' . $coachId . '-' . strtoupper(\Illuminate\Support\Str::random(6));
        } while (static::where('receipt_no', $no)->exists());

        return $no;
    }
}
