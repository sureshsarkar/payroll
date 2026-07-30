<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferralCommission extends Model
{
    use HasFactory;

    protected $fillable = [
        'referrer_user_id',
        'referred_user_id',
        'order_id',
        'amount',
        'currency',
        'percent',
        'status',
        'paid_at',
        'eligible_at',
        'approved_at',
        'credited_at',
        'reversed_at',
        'reversal_reason',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'percent'     => 'decimal:2',
        'paid_at'     => 'datetime',
        'eligible_at' => 'datetime',
        'approved_at' => 'datetime',
        'credited_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    // 2026-06-03 (Referral A+) — corporate lifecycle. A commission row only
    // exists AFTER a confirmed paid order, so it is born 'eligible'. STATUS_PENDING
    // / STATUS_PAID are kept as aliases so any legacy reference still resolves.
    public const STATUS_ELIGIBLE = 'eligible';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_CREDITED = 'credited';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_REVERSED = 'reversed';

    /** @deprecated use STATUS_ELIGIBLE — a commission is created only post-payment */
    public const STATUS_PENDING  = 'eligible';
    /** @deprecated use STATUS_CREDITED */
    public const STATUS_PAID     = 'credited';

    public function referrer()
    {
        return $this->belongsTo(User::class, 'referrer_user_id');
    }

    public function referred()
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    public function order()
    {
        return $this->belongsTo(\Modules\Order\app\Models\Order::class, 'order_id');
    }
}
