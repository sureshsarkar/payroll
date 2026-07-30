<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A lead submitted from a coach's "Pricing & Plans" booking modal. Tenant-safe:
 * every query is scoped by coach_id; a coach only ever sees their own enquiries.
 */
class CoachPricingEnquiry extends Model
{
    use HasFactory;

    protected $table = 'coach_pricing_enquiries';

    protected $fillable = [
        'coach_id', 'category', 'course_type', 'time_period', 'price',
        'name', 'email', 'mobile', 'age', 'gender', 'reason', 'time_slot',
        'details', 'status',
        // 2026-06-26 — reusable Booking Enquiry modal context (Class Schedule /
        // Trainer / any future CTA). schedule_id & trainer_id are free-form refs.
        'source_page', 'source_button', 'trainer_id', 'schedule_id',
        'ip_address', 'user_agent',
        // 2026-07-13 — Pricing & Plans payment. plan_amount/currency are the
        // SERVER-resolved authoritative figures; payment_status/paid_amount are
        // the enquiry-level snapshot (detail lives on coach_pricing_payments).
        'section_id', 'website_id', 'plan_amount', 'currency',
        'payment_status', 'paid_amount',
        // 2026-07-15 — Trainer "Book Personal Class Session" (Phase 2). Discriminator
        // + real FKs; readable snapshots reuse category (trainer name) + time_period
        // (package label) + plan_amount (server-resolved price).
        'enquiry_type', 'trainer_ref_id', 'trainer_package_id',
    ];

    /** Discriminator value for a trainer personal-session booking. */
    public const TYPE_TRAINER_SESSION = 'trainer_personal_session';

    protected $casts = [
        'details'     => 'array',
        'plan_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    public const STATUS_NEW = 'new';

    // Payment lifecycle on the enquiry (detail rows live on the payment table).
    public const PAY_UNPAID    = 'unpaid';
    public const PAY_PENDING   = 'pending';
    public const PAY_PAID      = 'paid';
    public const PAY_FAILED    = 'failed';
    public const PAY_CANCELLED = 'cancelled';

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CoachPricingPayment::class, 'enquiry_id');
    }

    /**
     * The payment row that best represents this enquiry's money state — the
     * paid one if any, otherwise the most recent attempt. Used by the coach
     * enquiry list to show Gateway / Transaction ID / Payment date.
     */
    public function effectivePayment(): ?CoachPricingPayment
    {
        $paid = $this->payments->firstWhere('status', CoachPricingPayment::STATUS_PAID);
        return $paid ?: $this->payments->sortByDesc('id')->first();
    }

    public function scopeForCoach(Builder $q, int $coachId): Builder
    {
        return $q->where('coach_id', $coachId);
    }
}
