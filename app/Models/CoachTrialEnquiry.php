<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A visitor's "Book Your Trial Session" enquiry (the lead). Strictly coach-scoped.
 * Captures the full form plus IP/UA for audit. A pending payment is linked via
 * CoachTrialPayment when the coach's trial requires a charge.
 */
class CoachTrialEnquiry extends Model
{
    protected $fillable = [
        'coach_id', 'website_id', 'student_id', 'student_was_new',
        'name', 'email', 'mobile', 'gender', 'height', 'weight',
        'plan_type', 'course_type', 'slot_id', 'time_slot',
        'reason', 'problem_description',
        'price', 'currency',
        'status', 'payment_status',
        'ip_address', 'user_agent', 'source_page', 'meta',
    ];

    protected $casts = [
        'meta'            => 'array',
        'price'           => 'decimal:2',
        'student_was_new' => 'boolean',
    ];

    public const STATUS_PENDING = 'pending';

    public const PAY_UNPAID = 'unpaid';
    public const PAY_PAID   = 'paid';
    public const PAY_FAILED = 'failed';
    public const PAY_FREE   = 'free';

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    /** The student account provisioned/linked after a successful trial. */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(CoachTrialPayment::class, 'enquiry_id')->latestOfMany();
    }

    /** Has this visitor already completed a trial (paid or free) for this coach? */
    public static function trialAlreadyUsed(int $coachId, string $email): bool
    {
        return static::forCoach($coachId)
            ->where('email', $email)
            ->whereIn('payment_status', [self::PAY_PAID, self::PAY_FREE])
            ->exists();
    }

    public function scopeForCoach(Builder $q, int $coachId): Builder
    {
        return $q->where('coach_id', $coachId);
    }
}
