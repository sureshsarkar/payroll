<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserMembership extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'started_at'         => 'datetime',
        'expires_at'         => 'datetime',
        'price_paid'         => 'decimal:2',
        'wallet_credit_used' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class, 'plan_id');
    }

    /**
     * Active = paid AND status='active' AND not expired.
     */
    public function isActive(): bool
    {
        if ($this->status !== 'active') return false;
        if ($this->payment_status !== 'paid') return false;
        if ($this->expires_at && $this->expires_at->isPast()) return false;
        return true;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where('payment_status', 'paid')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            });
    }
}
