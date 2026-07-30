<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A trial-session payment record. The `amount` is ALWAYS written server-side from
 * the coach's configured trial price — never from client input — so the charge
 * cannot be tampered with from the frontend. Coach-scoped.
 */
class CoachTrialPayment extends Model
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

    public const STATUS_PENDING  = 'pending';
    public const STATUS_PAID     = 'paid';
    public const STATUS_FAILED   = 'failed';
    public const STATUS_REFUNDED = 'refunded';

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(CoachTrialEnquiry::class, 'enquiry_id');
    }

    public function scopeForCoach(Builder $q, int $coachId): Builder
    {
        return $q->where('coach_id', $coachId);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }
}
