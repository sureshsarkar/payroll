<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * FeeDemand — Phase 4B foundation.
 *
 * Coach-issued bill against a batch. See migrations
 * 2026_05_19_130000 (this table) and 2026_05_19_140000 (payments).
 */
class FeeDemand extends Model
{
    protected $fillable = [
        'coach_id', 'batch_id', 'course_id', 'title', 'amount', 'due_date',
        'late_fine_per_day', 'notes', 'status', 'created_by',
    ];

    protected $casts = [
        'amount'             => 'decimal:2',
        'late_fine_per_day'  => 'decimal:2',
        'due_date'           => 'date',
    ];

    /* ─────────────────── relations ─────────────────── */

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(CourseBatch::class, 'batch_id');
    }

    /**
     * 2026-06-02 — course-wide demands (course_id set, batch_id NULL).
     * Added so the student fees list can eager-load + display the course
     * title for course-scoped demands (StudentFeePaymentController::index).
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(FeePayment::class, 'fee_demand_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /* ─────────────────── derived ─────────────────── */

    /**
     * Sum of successfully-collected payments for this demand.
     * Refunded payments do NOT count.
     */
    public function collectedAmount(): float
    {
        return (float) $this->payments()
            ->where('status', 'paid')
            ->sum('amount');
    }

    /**
     * Outstanding balance for the WHOLE BATCH on this demand.
     *   = amount × student_count − collected
     * Returns 0 if collected exceeds expected (over-paid).
     */
    public function outstandingTotal(): float
    {
        $studentCount = max(1, (int) ($this->batch?->studentCount() ?? 1));
        $expected     = (float) $this->amount * $studentCount;
        return max(0.0, $expected - $this->collectedAmount());
    }

    /**
     * 2026-06-25 — a course-wide demand targets the whole course (course_id set,
     * batch_id NULL) instead of one batch. StudentFeePaymentController's checkout
     * + index reference this, but the method was never defined — calling it threw
     * BadMethodCallException at fee checkout. Restores that flow.
     */
    public function isCourseWide(): bool
    {
        return is_null($this->batch_id);
    }

    /* ─────────────────── scopes ─────────────────── */

    public function scopeForCoach($query, int $coachId)
    {
        return $query->where('coach_id', $coachId);
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }
}
