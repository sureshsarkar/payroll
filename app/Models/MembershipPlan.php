<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MembershipPlan extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'features'      => 'array',
        'price'         => 'decimal:2',
        'annual_price'  => 'decimal:2',
        'duration_days' => 'integer',
        'sort_order'    => 'integer',
        // 2026-06-24 — pricing-plan fields
        'setup_fee'                => 'decimal:2',
        'setup_fee_custom'         => 'boolean',
        'platform_commission_rate' => 'decimal:2',
        'commission_min_rate'      => 'decimal:2',
        'commission_max_rate'      => 'decimal:2',
        'student_capacity'         => 'integer',
        'payout_required'          => 'boolean',
        'direct_settlement'        => 'boolean',
    ];

    public const TIER_STARTER    = 'starter';
    public const TIER_MEDIUM     = 'medium';
    public const TIER_ENTERPRISE = 'enterprise';
    public const TIER_CUSTOM     = 'custom';

    public function memberships(): HasMany
    {
        return $this->hasMany(UserMembership::class, 'plan_id');
    }

    public function isEnterprise(): bool
    {
        return $this->tier === self::TIER_ENTERPRISE;
    }

    /** 2026-06-25 — annual billing is offered only when annual_price is set (>0). */
    public function hasAnnual(): bool
    {
        return ! $this->isEnterprise() && (float) ($this->annual_price ?? 0) > 0;
    }

    /** % saved by paying annually vs 12× the monthly price (0 when n/a). */
    public function annualSavingsPct(): int
    {
        if (! $this->hasAnnual() || (float) $this->price <= 0) {
            return 0;
        }
        $yearlyAtMonthly = (float) $this->price * 12;
        if ($yearlyAtMonthly <= 0) {
            return 0;
        }
        return max(0, (int) round((1 - ((float) $this->annual_price / $yearlyAtMonthly)) * 100));
    }

    /** Unlimited when student_capacity is NULL/0. */
    public function isUnlimitedStudents(): bool
    {
        return $this->student_capacity === null || (int) $this->student_capacity <= 0;
    }

    /**
     * The platform commission % this plan charges, clamped to its own band when
     * an enterprise min/max is configured. Returns null when the plan doesn't
     * set a rate (caller then falls back to coach override / global).
     */
    public function effectiveCommissionRate(): ?float
    {
        if ($this->platform_commission_rate === null) {
            return null;
        }
        $rate = (float) $this->platform_commission_rate;
        if ($this->commission_min_rate !== null) {
            $rate = max($rate, (float) $this->commission_min_rate);
        }
        if ($this->commission_max_rate !== null) {
            $rate = min($rate, (float) $this->commission_max_rate);
        }
        return max(0.0, min(100.0, $rate));
    }

    /**
     * Scope: plans available for a given user role. Includes the catch-all 'all' bucket.
     */
    public function scopeForRole($query, string $role)
    {
        return $query->whereIn('role', [$role, 'all']);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Cached list of active plans for a given role. Plans rarely change so a
     * 1-hour cache is safe; saved()/deleted() events bust the cache below.
     */
    public static function activeForRoleCached(string $role): \Illuminate\Support\Collection
    {
        return \Cache::remember('membership_plans_active_' . $role, 3600, function () use ($role) {
            return self::active()->forRole($role)->orderBy('sort_order')->orderBy('price')->get();
        });
    }

    protected static function booted()
    {
        $bust = function () {
            \Cache::forget('membership_has_active_plans');
            // F9 (audit 2026-06-26) — role-scoped auto-grace keys used by RequiresMembership.
            foreach (['student', 'instructor', 'all'] as $role) {
                \Cache::forget('membership_has_active_plans_' . $role);
            }
            \Cache::forget('membership_plans_active_student');
            \Cache::forget('membership_plans_active_instructor');
        };
        static::saved($bust);
        static::deleted($bust);
    }
}
