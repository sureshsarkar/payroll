<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A pricing-plan booking payment record. The `amount` is ALWAYS written
 * server-side from the coach's stored plan price (re-resolved from the page
 * section content) — never from client input — so the charge cannot be
 * tampered with from the frontend. Coach-scoped. Mirrors CoachTrialPayment.
 */
class CoachPricingPayment extends Model
{
    protected $fillable = [
        'coach_id', 'enquiry_id', 'gateway', 'gateway_order_id', 'transaction_id',
        'amount', 'currency', 'status', 'gateway_owner_type', 'gateway_config_id',
        'payment_details', 'paid_at',
    ];

    protected $casts = [
        'amount'  => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public const STATUS_PENDING   = 'pending';
    public const STATUS_PAID      = 'paid';
    public const STATUS_FAILED    = 'failed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUNDED  = 'refunded';

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(CoachPricingEnquiry::class, 'enquiry_id');
    }

    /** THE tenant gate — every query must go through this. */
    public function scopeForCoach(Builder $q, int $coachId): Builder
    {
        return $q->where('coach_id', $coachId);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }
}
