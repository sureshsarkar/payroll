<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseLiveClass extends Model {
    use HasFactory;

    /**
     * 2026-06-05 — How many minutes BEFORE the scheduled start a host (coach /
     * assigned teacher) may start the meeting. Students are never let in early:
     * they join only at/after start_time AND once the coach has joined.
     */
    public const EARLY_JOIN_BUFFER_MINUTES = 10;

    protected $fillable = [
        'batch_id',
        'course_id',
        'lesson_id',
        'start_time',
        'expected_duration_minutes',
        'meeting_id',
        'password',
        'join_url',
        'type',
        'verification_status',
        'verification_message',
        'last_verified_at',
        'coach_joined_at',
        'cancelled_at',
    ];

    protected $casts = [
        'start_time'       => 'datetime',
        'last_verified_at' => 'datetime',
        'coach_joined_at'  => 'datetime',
        'cancelled_at'     => 'datetime',
    ];

    function lesson(): BelongsTo {
        return $this->belongsTo(CourseChapterLesson::class, 'lesson_id', 'id');
    }

    function batch(): BelongsTo {
        return $this->belongsTo(CourseBatch::class, 'batch_id', 'id');
    }

    /**
     * 2026-06-16 — single source of truth for "live classes a student can see".
     * Shared by the Live Classes LIST and the sidebar COUNT so they ALWAYS
     * match (the reported bug: list showed 1, sidebar badge showed "2 LIVE").
     * Tenant-aware: on a coach domain ($tenantCoachId > 0) only that coach's
     * classes. Honours has_access enrolment, the per-(course,batch) pairing,
     * and excludes cancelled classes — exactly like the list.
     */
    public static function visibleToStudent(int $userId, int $tenantCoachId = 0)
    {
        $enrollQuery = \Modules\Order\app\Models\Enrollment::where('user_id', $userId)
            ->where('has_access', 1);
        if ($tenantCoachId > 0) {
            $enrollQuery->whereIn('course_id',
                \App\Models\Course::where('instructor_id', $tenantCoachId)->select('id'));
        }
        $enrollments = $enrollQuery->get(['course_id', 'batch_id']);
        $enrollCourseIds = $enrollments->pluck('course_id')->unique()->all();

        return static::query()
            ->whereHas('lesson', fn ($q) => $q->whereIn('course_id', $enrollCourseIds ?: [0]))
            ->where(function ($outer) use ($enrollments) {
                foreach ($enrollments as $e) {
                    $outer->orWhere(function ($w) use ($e) {
                        $w->whereHas('lesson', fn ($l) => $l->where('course_id', $e->course_id))
                          ->where(function ($b) use ($e) {
                              if ($e->batch_id === null) {
                                  return; // legacy enrolment — every class for this course
                              }
                              $b->whereNull('batch_id')->orWhere('batch_id', $e->batch_id);
                          });
                    });
                }
            })
            ->whereNull('cancelled_at');
    }

    /**
     * 2026-06-03 (#9) — has this class DEFINITIVELY finished, so NOBODY
     * (coach or student) should still be able to join it? Two unambiguous
     * signals that never false-positive on a class that is merely running
     * long past its scheduled slot:
     *   - it was finalised (ended_at set — by the attendance sweep or an
     *     explicit "completed" action), OR
     *   - its batch has ended (batch.end_date is before today).
     */
    public function isFinished(): bool {
        if ($this->ended_at !== null) {
            return true;
        }

        if ($this->batch_id) {
            $batchEnd = optional($this->batch)->end_date;
            if ($batchEnd) {
                try {
                    if (\Illuminate\Support\Carbon::parse($batchEnd)->endOfDay()->isPast()) {
                        return true;
                    }
                } catch (\Throwable $e) { /* unparseable end_date → ignore */ }
            }
        }

        return false;
    }

    /**
     * 2026-06-03 (#7) — stricter "over" used for STUDENTS: finished (above) OR
     * the scheduled window + the same 5-minute grace the attendance sweep uses
     * (LiveClassAttendanceVerifier::verifyPendingClasses) has elapsed. Not used
     * for the host, so a coach running a class past its slot is never kicked.
     */
    public function isOver(): bool {
        if ($this->isFinished()) {
            return true;
        }

        if ($this->start_time) {
            try {
                $start = $this->start_time instanceof \Carbon\CarbonInterface
                    ? $this->start_time
                    : \Illuminate\Support\Carbon::parse($this->start_time);
                $overAt = $start->copy()
                    ->addMinutes((int) ($this->expected_duration_minutes ?: 60))
                    ->addMinutes(5);
                return now()->greaterThanOrEqualTo($overAt);
            } catch (\Throwable $e) {
                return false; // unparseable start_time → don't falsely block
            }
        }

        return false;
    }

    public function attendances(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(LiveClassAttendance::class, 'course_live_class_id');
    }

    /**
     * 2026-06-05 — Is this live class safe to END purely to free the host's
     * Zoom concurrent-meeting slot? Zoom allows up to 2 concurrent meetings
     * per host (when the account enables "Allow host to start concurrent
     * meetings"), so before starting a new class we must reclaim ONLY the
     * slots held by stuck/abandoned meetings — never kill a class students
     * are actively in. Returns true when:
     *   - the class is over (finished / past its window + grace), OR
     *   - nobody is currently in it (no OPEN attendance row, left_at null).
     * Returns false for a class inside its window with at least one attendee
     * still present — that is a genuine parallel class and must keep running.
     *
     * Uses the eager-loaded `attendances` relation when present (so callers
     * can decide in-memory); otherwise issues a single existence query.
     */
    public function isAbandonedForSlotReuse(): bool
    {
        if ($this->isOver()) {
            return true;
        }

        if ($this->relationLoaded('attendances')) {
            return $this->attendances->whereNull('left_at')->isEmpty();
        }

        return ! $this->attendances()->whereNull('left_at')->exists();
    }

    /**
     * 2026-06-05 — Has the HOST (coach / assigned teacher) joined this meeting?
     * Primary signal is the sticky `coach_joined_at` stamp (set the first time a
     * host posts a join event). For rows created before that column existed we
     * fall back to "a host-role attendance row exists". Sticky on purpose: once
     * the coach has joined, students stay allowed in even if the coach briefly
     * drops — matching the spec ("once the coach joins, students may join").
     */
    public function isCoachJoined(): bool
    {
        if ($this->coach_joined_at !== null) {
            return true;
        }

        if ($this->relationLoaded('attendances')) {
            return $this->attendances->where('role', 'host')->isNotEmpty();
        }

        return $this->attendances()->where('role', 'host')->exists();
    }

    /**
     * 2026-06-05 — Has the scheduled start time been reached?
     */
    public function hasStarted(): bool
    {
        if (! $this->start_time) {
            return false;
        }
        return now()->greaterThanOrEqualTo($this->startCarbon());
    }

    /**
     * 2026-06-05 — Is "now" inside the host early-join buffer or later? A coach
     * may start the meeting up to EARLY_JOIN_BUFFER_MINUTES before start_time.
     */
    public function hostEarlyJoinOpen(): bool
    {
        if (! $this->start_time) {
            return true; // no schedule (e.g. instant class) → host may start now
        }
        return now()->greaterThanOrEqualTo(
            $this->startCarbon()->copy()->subMinutes(self::EARLY_JOIN_BUFFER_MINUTES)
        );
    }

    /**
     * 2026-06-05 — Per-class lifecycle status (NO global flag; derived from this
     * row + its attendance). One of:
     *   cancelled | completed | live | ready_to_start | waiting_for_start | scheduled
     *
     *   - cancelled         : cancelled_at set.
     *   - completed         : finished (ended_at / batch ended) or past window+grace.
     *   - live              : coach has joined and the class is not over.
     *   - ready_to_start    : start time reached, coach not joined yet (students wait).
     *   - waiting_for_start : before start time (countdown running).
     *   - scheduled         : no start_time set at all.
     */
    public function lifecycleStatus(): string
    {
        if ($this->cancelled_at !== null) {
            return 'cancelled';
        }
        if ($this->isFinished() || $this->isOver()) {
            return 'completed';
        }
        if ($this->isCoachJoined()) {
            return 'live';
        }
        if (! $this->start_time) {
            return 'scheduled';
        }
        return $this->hasStarted() ? 'ready_to_start' : 'waiting_for_start';
    }

    /**
     * Normalised Carbon for start_time (it is stored as a string column but
     * cast to datetime; guard the rare unparseable legacy value).
     */
    private function startCarbon(): \Carbon\CarbonInterface
    {
        return $this->start_time instanceof \Carbon\CarbonInterface
            ? $this->start_time
            : \Illuminate\Support\Carbon::parse($this->start_time);
    }

    public function recordings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(LiveClassRecording::class, 'course_live_class_id');
    }
}
