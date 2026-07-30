<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A 1:1 instant meeting: one coach ↔ one student, private Zoom room. Separate
 * from batch/group live classes. Strictly tenant-scoped by coach_id.
 */
class InstantMeeting extends Model
{
    protected $fillable = [
        'coach_id', 'student_id', 'topic', 'purpose',
        'meeting_id', 'password', 'join_url',
        'status', 'expected_duration_minutes',
        'coach_joined_at', 'student_joined_at', 'started_at', 'ended_at', 'expires_at',
        'created_ip',
    ];

    protected $casts = [
        'coach_joined_at'   => 'datetime',
        'student_joined_at' => 'datetime',
        'started_at'        => 'datetime',
        'ended_at'          => 'datetime',
        'expires_at'        => 'datetime',
    ];

    public const STATUS_ACTIVE    = 'active';
    public const STATUS_ENDED     = 'ended';
    public const STATUS_CANCELLED = 'cancelled';

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function scopeForCoach(Builder $q, int $coachId): Builder
    {
        return $q->where('coach_id', $coachId);
    }

    /**
     * Active 1:1 meetings this student is invited to. Strict student_id match =
     * a meeting is NEVER visible to any other student. On a coach custom domain
     * (tenantCoachId > 0) it's further scoped to that coach so Coach A's meeting
     * can't surface on Coach B's site. Newest first.
     */
    public static function visibleToStudent(int $userId, int $tenantCoachId = 0)
    {
        return static::query()
            ->where('student_id', $userId)
            ->where('status', self::STATUS_ACTIVE)
            ->when($tenantCoachId > 0, fn ($q) => $q->where('coach_id', $tenantCoachId))
            ->orderByDesc('started_at');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isCoachJoined(): bool
    {
        return $this->coach_joined_at !== null;
    }

    /** Is $userId a participant (the coach owner or the invited student)? */
    public function isParticipant(int $userId): bool
    {
        return $userId === (int) $this->coach_id || $userId === (int) $this->student_id;
    }
}
