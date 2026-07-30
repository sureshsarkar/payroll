<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * Coach → CoachStaff ("teacher") → CourseBatch permission grant.
 *
 * One row = one teacher is allowed to manage one batch. While
 * status='active' the gate methods in Coach/LiveClassController and
 * StudentLiveClassController treat the teacher as authorized for
 * that batch. status='inactive' is a soft-remove — the row stays
 * for audit but blocks access immediately.
 *
 * Helper: assignedBatchIdsFor($userId) returns the active batch IDs
 * for any teacher, cached 60s per user. Coaches (role='instructor')
 * are unbounded and the helper returns null for them — callers must
 * treat null as "no batch filter, full access".
 */
class TeacherBatchAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'coach_id',
        'teacher_id',
        'course_id',
        'batch_id',
        'permission_type',
        'status',
        'assigned_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(CoachStaff::class, 'teacher_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(CourseBatch::class, 'batch_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', 'active');
    }

    public function scopeForTeacher(Builder $q, int $teacherId): Builder
    {
        return $q->where('teacher_id', $teacherId);
    }

    public function scopeForCoach(Builder $q, int $coachId): Builder
    {
        return $q->where('coach_id', $coachId);
    }

    /**
     * Return active batch IDs the given user is allowed to manage.
     *
     * Returns:
     *   - null   → caller is a coach (role='instructor') — unbounded
     *   - []     → caller is a teacher with NO active assignments — block all
     *   - int[]  → caller is a teacher with specific batch grants
     *
     * Cached 60s per user to avoid hammering the DB on every
     * live-class request. The cache key is invalidated by
     * forgetCacheFor($teacherId) when the coach grants/revokes.
     */
    public static function assignedBatchIdsFor(int $userId): ?array
    {
        $user = CoachStaff::find($userId);
        if (! $user) {
            return [];
        }

        // Coach themselves — no batch filter applies. They see
        // everything their existing course-ownership scope already
        // allows. 2026-06-01: a real coach has coach_id NULL; pin on it so a
        // staff row with a stray role='instructor' stays batch-scoped (null
        // here means UNBOUNDED/all-batches).
        if ($user->role === 'instructor' && empty($user->coach_id)) {
            return null;
        }

        return Cache::remember(
            self::cacheKey($userId),
            60,
            fn () => static::active()
                ->where('teacher_id', $userId)
                ->pluck('batch_id')
                ->map(fn ($v) => (int) $v)
                ->all()
        );
    }

    /**
     * Drop the cached batch list for one teacher. Called after the
     * coach assigns / revokes / status-flips an assignment.
     */
    public static function forgetCacheFor(int $teacherId): void
    {
        Cache::forget(self::cacheKey($teacherId));
    }

    protected static function cacheKey(int $userId): string
    {
        return "tba:assigned_batches:user:{$userId}";
    }
}
