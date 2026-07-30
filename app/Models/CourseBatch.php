<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Order\app\Models\Enrollment;

class CourseBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'title',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'days',
        'capacity',
        'attendance_min_percent',   // Audit 2026-05-18 phase 5 — per-batch override
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        // 'start_time' => 'time:H:i A',
        // 'end_time' => 'time:H:i A',
        'days' => 'array',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id', 'id');
    }

    /**
     * Audit 2026-05-18 — enrollments scoped to this batch (after the
     * enrollments.batch_id backfill migration ran).
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'batch_id', 'id');
    }

    /**
     * Live classes scheduled against this batch. course_live_classes.batch_id
     * pre-existed in the schema — this is the convenience relation.
     */
    public function liveClasses(): HasMany
    {
        return $this->hasMany(CourseLiveClass::class, 'batch_id', 'id');
    }

    /**
     * Announcements scoped to this batch (post-2026-05-18 migration).
     */
    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class, 'batch_id', 'id');
    }

    /**
     * Count of students with active access in this batch.
     * Counts distinct user_ids on the enrollments table where has_access=1.
     */
    public function studentCount(): int
    {
        return (int) $this->enrollments()
            ->where('has_access', 1)
            ->distinct('user_id')
            ->count('user_id');
    }

    /**
     * Count of distinct students who attended ANY live class of this batch
     * on the given date.
     *
     * Audit 2026-05-18 phase 4 — now counts only VERIFIED attendance.
     * "Joined for 30 seconds and bounced" no longer counts. Manual marks
     * (is_manual=1) are auto-verified so they continue to count.
     */
    public function attendedOn(\DateTimeInterface|string $date): int
    {
        $start = (string) (is_string($date) ? \Carbon\Carbon::parse($date) : \Carbon\Carbon::instance($date))
            ->startOfDay()->utc();
        $end = (string) (is_string($date) ? \Carbon\Carbon::parse($date) : \Carbon\Carbon::instance($date))
            ->endOfDay()->utc();

        return (int) \DB::table('live_class_attendances as a')
            ->join('course_live_classes as c', 'c.id', '=', 'a.course_live_class_id')
            ->where('c.batch_id', $this->id)
            ->where('a.role', 'student')
            ->where('a.attendance_verified', 1)   // phase 4
            ->whereBetween('a.joined_at', [$start, $end])
            ->distinct('a.user_id')
            ->count('a.user_id');
    }

    /**
     * Not-attended = total active students in batch − attended on date.
     * Returns >= 0 always.
     */
    public function notAttendedOn(\DateTimeInterface|string $date): int
    {
        return max(0, $this->studentCount() - $this->attendedOn($date));
    }

    /**
     * Audit 2026-05-19 — distinct students who JOINED any live class on
     * the given date, regardless of verification status. Use this when
     * the UI needs the "showed up at all" number — the class may still
     * be running (so verification hasn't fired) or the student may have
     * dropped before threshold (Partial).
     *
     * Difference vs attendedOn():
     *   - attendedOn() only counts attendance_verified=1
     *   - joinedOn() counts any row in live_class_attendances
     */
    public function joinedOn(\DateTimeInterface|string $date): int
    {
        $start = (string) (is_string($date) ? \Carbon\Carbon::parse($date) : \Carbon\Carbon::instance($date))
            ->startOfDay()->utc();
        $end = (string) (is_string($date) ? \Carbon\Carbon::parse($date) : \Carbon\Carbon::instance($date))
            ->endOfDay()->utc();

        return (int) \DB::table('live_class_attendances as a')
            ->join('course_live_classes as c', 'c.id', '=', 'a.course_live_class_id')
            ->where('c.batch_id', $this->id)
            ->where('a.role', 'student')
            ->whereBetween('a.joined_at', [$start, $end])
            ->distinct('a.user_id')
            ->count('a.user_id');
    }

    /**
     * Audit 2026-05-19 — convenience inverse of joinedOn(): students who
     * are enrolled in this batch but never joined any class on the date.
     */
    public function notJoinedOn(\DateTimeInterface|string $date): int
    {
        return max(0, $this->studentCount() - $this->joinedOn($date));
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // ─────────────────────────────────────────────────────────────────
    //  Capacity helpers — audit 2026-05-22.
    //
    //  Capacity was previously a vestigial field: the column exists,
    //  the create UI captures it, but no code anywhere enforced it.
    //  A 5-seat batch could hold 500 enrollments.
    //
    //  This release introduces the helper methods that *future* code
    //  should consult. Today they're WARN-mode only — we log when a
    //  caller would breach capacity but we DON'T block the operation,
    //  because retro-blocking on production data would break already-
    //  enrolled students. Once the business confirms enforcement, swap
    //  the warn() in PaymentFulfilmentService::markPaid() to a throw.
    // ─────────────────────────────────────────────────────────────────

    /**
     * Returns true if this batch has a finite capacity. Capacity 0/NULL
     * is conventionally treated as "unlimited" in this codebase.
     */
    public function hasCapacityLimit(): bool
    {
        return $this->capacity !== null && (int) $this->capacity > 0;
    }

    /**
     * 2026-06-11 — The SOLE batch id for a course, or null.
     *
     * Used to auto-assign a batch-less paid enrollment when a course has
     * EXACTLY ONE batch (PaymentFulfilmentService::markPaid + the backfill
     * command call this). Returns null when the course has:
     *   - ZERO batches  → recorded courses ('course' | 'recorded') — the
     *     enrollment stays batch-less (correct; recorded needs no batch), and
     *   - MORE THAN ONE batch → ambiguous, leave NULL for the coach to assign.
     *
     * This method was referenced but never defined, so any RECORDED-course
     * paid order threw a "Call to undefined method" inside markPaid(), rolling
     * back the order (status stuck pending, no access) and bouncing the buyer
     * to /payment-failed. Batched orders skipped this branch and worked.
     *
     * @param bool $activeOnly only count active, not-yet-ended batches.
     */
    public static function soleBatchIdForCourse(int $courseId, bool $activeOnly = true): ?int
    {
        $query = static::query()->where('course_id', $courseId);

        if ($activeOnly) {
            $query->where('status', 'active')->where('end_date', '>=', date('Y-m-d'));
        }

        $ids = $query->pluck('id');

        return $ids->count() === 1 ? (int) $ids->first() : null;
    }

    /**
     * Seats currently used by students with `has_access=1`.
     */
    public function seatsUsed(): int
    {
        return $this->studentCount();
    }

    /**
     * Seats still available. Returns PHP_INT_MAX when capacity is unlimited.
     */
    public function seatsRemaining(): int
    {
        if (! $this->hasCapacityLimit()) {
            return PHP_INT_MAX;
        }
        return max(0, (int) $this->capacity - $this->seatsUsed());
    }

    /**
     * True when adding ONE more student would put the batch over capacity.
     * Use this at enrollment time:
     *
     *     if ($batch && $batch->wouldExceedCapacity()) {
     *         $batch->logCapacityBreach('coach_manual_order', $studentId, $orderId);
     *     }
     */
    public function wouldExceedCapacity(int $additional = 1): bool
    {
        return $this->hasCapacityLimit() && $this->seatsUsed() + $additional > (int) $this->capacity;
    }

    /**
     * Structured log entry for a capacity breach. Operators can grep:
     *   storage/logs/laravel-YYYY-MM-DD.log | grep 'batch-capacity-breach'
     * to find every breach + decide whether to enforce going forward.
     *
     * @param string $source  e.g. 'razorpay_webhook', 'coach_manual_order'
     */
    public function logCapacityBreach(string $source, int $studentId, ?int $orderId = null): void
    {
        \Log::warning('batch-capacity-breach', [
            'batch_id'   => $this->id,
            'course_id'  => $this->course_id,
            'capacity'   => (int) $this->capacity,
            'seats_used' => $this->seatsUsed(),
            'student_id' => $studentId,
            'order_id'   => $orderId,
            'source'     => $source,
        ]);
    }
}
