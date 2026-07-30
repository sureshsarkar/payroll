<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Coach ↔ Student many-to-many link.
 *
 * One row = this student is on this coach's roster. Multiple rows
 * with the same student_id = the student belongs to multiple coaches
 * (which is the whole point of this table).
 *
 * status='active' is the live grant. status='removed' is a soft
 * remove (Q1 spec: student can self-remove themselves from a coach's
 * roster) — the row stays for audit, but read paths filter it out.
 *
 * source records how the link came to be:
 *   added     coach explicitly added the student via the UI
 *   purchase  student bought a course owned by this coach
 *   admin     platform admin established the link
 *   invite    (future) student accepted a coach's invitation link
 *
 * Two static helpers cover ~all read paths:
 *
 *   CoachStudentLink::studentIdsForCoach($coachId)
 *       returns the student ids on this coach's active roster.
 *       Cached 60s. Used by my-students, dashboard counts, fee
 *       picker, analytics.
 *
 *   CoachStudentLink::coachIdsForStudent($studentId)
 *       returns the coach ids the student is actively linked to.
 *       Cached 60s. Used by self-remove gate + anywhere we need to
 *       know "which coaches can see this student".
 */
class CoachStudentLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'coach_id',
        'student_id',
        'source',
        'status',
        'joined_at',
        'removed_at',
    ];

    protected $casts = [
        'joined_at'  => 'datetime',
        'removed_at' => 'datetime',
    ];

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', 'active');
    }

    public function scopeForCoach(Builder $q, int $coachId): Builder
    {
        return $q->where('coach_id', $coachId);
    }

    public function scopeForStudent(Builder $q, int $studentId): Builder
    {
        return $q->where('student_id', $studentId);
    }

    /**
     * Active student ids on this coach's roster. Cached 60s per coach.
     * Invalidated via forgetCacheForCoach() when a link is added /
     * removed.
     */
    public static function studentIdsForCoach(int $coachId): array
    {
        return Cache::remember(
            self::coachCacheKey($coachId),
            60,
            fn () => static::active()
                ->where('coach_id', $coachId)
                ->pluck('student_id')
                ->map(fn ($v) => (int) $v)
                ->all()
        );
    }

    /**
     * Active coach ids linked to this student. Cached 60s per student.
     * Used by the student self-remove flow + any read that needs the
     * inverse direction.
     */
    public static function coachIdsForStudent(int $studentId): array
    {
        return Cache::remember(
            self::studentCacheKey($studentId),
            60,
            fn () => static::active()
                ->where('student_id', $studentId)
                ->pluck('coach_id')
                ->map(fn ($v) => (int) $v)
                ->all()
        );
    }

    /**
     * Idempotent link writer.
     *
     * Used by:
     *   - storeStudents controller (source='added')
     *   - order-completion hook (source='purchase')
     *   - admin reassignment (source='admin')
     *
     * Behaviour:
     *   - If no row exists: insert with status='active'.
     *   - If a 'removed' row exists: flip back to 'active', refresh
     *     joined_at, retain the original created_at (it's the row's
     *     birth, not the latest link).
     *   - If an 'active' row exists: no-op (return existing).
     *
     * Both caches are invalidated.
     */
    public static function link(int $coachId, int $studentId, string $source = 'added'): self
    {
        $row = static::where('coach_id', $coachId)
            ->where('student_id', $studentId)
            ->first();

        $now = now();

        if ($row === null) {
            $row = static::create([
                'coach_id'   => $coachId,
                'student_id' => $studentId,
                'source'     => $source,
                'status'     => 'active',
                'joined_at'  => $now,
            ]);
        } elseif ($row->status === 'removed') {
            $row->fill([
                'status'     => 'active',
                'joined_at'  => $now,
                'removed_at' => null,
            ])->save();
        }
        // else: row exists + active → no-op (don't overwrite source).

        self::forgetCacheForCoach($coachId);
        self::forgetCacheForStudent($studentId);

        return $row;
    }

    /**
     * Soft-remove (status='removed'). Student can self-remove (Q1);
     * coach can also remove a student from their roster.
     */
    public static function unlink(int $coachId, int $studentId): bool
    {
        $row = static::where('coach_id', $coachId)
            ->where('student_id', $studentId)
            ->where('status', 'active')
            ->first();

        if ($row === null) {
            return false;
        }

        $row->update(['status' => 'removed', 'removed_at' => now()]);
        self::forgetCacheForCoach($coachId);
        self::forgetCacheForStudent($studentId);
        return true;
    }

    public static function forgetCacheForCoach(int $coachId): void
    {
        Cache::forget(self::coachCacheKey($coachId));
    }

    public static function forgetCacheForStudent(int $studentId): void
    {
        Cache::forget(self::studentCacheKey($studentId));
    }

    protected static function coachCacheKey(int $coachId): string
    {
        return "csl:students_for_coach:$coachId";
    }

    protected static function studentCacheKey(int $studentId): string
    {
        return "csl:coaches_for_student:$studentId";
    }
}
