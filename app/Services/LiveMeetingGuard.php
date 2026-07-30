<?php

namespace App\Services;

use App\Exceptions\ActiveMeetingExistsException;
use App\Models\CoachActiveMeeting;
use App\Models\Course;
use App\Models\CourseLiveClass;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-06-22 — enforces "one coach = one active live meeting at a time".
 *
 * Scope key is the OWNING coach (course.instructor_id) → tenant-safe: a row is
 * keyed on coach_id, so Coach A claiming a slot can never block Coach B.
 *
 * Race safety (two tabs / two devices / double-click / parallel API calls):
 *   - claim() runs in a DB transaction, takes a row lock (lockForUpdate), and
 *     relies on the UNIQUE(coach_id) constraint as the final arbiter. If two
 *     requests race, exactly one INSERT wins; the loser catches the unique
 *     violation and is rejected with ActiveMeetingExistsException.
 *
 * Stuck-session safety: every claim purges expired rows first (expires_at past,
 * or the referenced class ended/cancelled/over), and a scheduled command purges
 * globally — so an abandoned meeting never permanently blocks the coach.
 */
class LiveMeetingGuard
{
    /** Fallback expiry for a claimed slot (stuck-session ceiling). */
    public const DEFAULT_TTL_MINUTES = 360; // 6 hours

    /**
     * Claim the coach's single active-meeting slot for $liveClassId.
     * Re-claiming the SAME class refreshes the expiry (host reconnect / reload).
     * Claiming a DIFFERENT class while one is active throws.
     *
     * @throws ActiveMeetingExistsException
     */
    public function claim(int $coachId, int $liveClassId, ?int $ttlMinutes = null): void
    {
        // No resolvable owner, or table not migrated yet → don't block (defensive).
        if ($coachId <= 0 || !Schema::hasTable('coach_active_meetings')) {
            // F29 (audit 2026-06-26) — surface that one-active-meeting enforcement
            // is silently OFF (table missing) so operators notice, instead of the
            // guarantee being void without a trace.
            if ($coachId > 0 && !Schema::hasTable('coach_active_meetings')) {
                \Log::warning('LiveMeetingGuard: coach_active_meetings table missing — one-active-meeting enforcement is DISABLED');
            }
            return;
        }
        $ttl = $ttlMinutes ?: self::DEFAULT_TTL_MINUTES;

        DB::transaction(function () use ($coachId, $liveClassId, $ttl) {
            $this->purgeStale($coachId);

            // Robustness fallback: block if the coach already has another class
            // that is genuinely RUNNING (started + not over/ended/cancelled),
            // even if it never claimed a slot row (legacy / pre-fix meetings).
            // The slot table stays the race arbiter; this just closes the gap.
            if ($this->coachHasRunningClass($coachId, $liveClassId)) {
                throw new ActiveMeetingExistsException();
            }

            $existing = CoachActiveMeeting::where('coach_id', $coachId)->lockForUpdate()->first();
            if ($existing) {
                if ((int) $existing->course_live_class_id === $liveClassId) {
                    $existing->update(['expires_at' => now()->addMinutes($ttl)]); // same class → refresh
                    return;
                }
                throw new ActiveMeetingExistsException();
            }

            try {
                CoachActiveMeeting::create([
                    'coach_id'             => $coachId,
                    'course_live_class_id' => $liveClassId,
                    'started_at'           => now(),
                    'expires_at'           => now()->addMinutes($ttl),
                ]);
            } catch (QueryException $e) {
                // UNIQUE(coach_id) race — a parallel request inserted first.
                $other = CoachActiveMeeting::where('coach_id', $coachId)->first();
                if ($other && (int) $other->course_live_class_id === $liveClassId) {
                    return; // the winner was for the same class — harmless
                }
                throw new ActiveMeetingExistsException();
            }
        });
    }

    /**
     * Claim the coach's single active-meeting slot for a 1:1 INSTANT meeting.
     * Shares the SAME UNIQUE(coach_id) mutex as batch classes, so a coach can't
     * run a 1:1 while a batch class is live (or vice-versa). Re-claiming the same
     * instant meeting refreshes the expiry (host reconnect / reload).
     *
     * @throws ActiveMeetingExistsException
     */
    public function claimInstant(int $coachId, int $instantMeetingId, ?int $ttlMinutes = null): void
    {
        if ($coachId <= 0 || !Schema::hasTable('coach_active_meetings')) {
            if ($coachId > 0 && !Schema::hasTable('coach_active_meetings')) {
                \Log::warning('LiveMeetingGuard: coach_active_meetings table missing — one-active-meeting enforcement is DISABLED');
            }
            return;
        }
        $ttl = $ttlMinutes ?: self::DEFAULT_TTL_MINUTES;

        DB::transaction(function () use ($coachId, $instantMeetingId, $ttl) {
            $this->purgeStale($coachId);

            // Block if the coach has a running BATCH class. Instant meetings are
            // NOT CourseLiveClass rows, so this never self-triggers.
            if ($this->coachHasRunningClass($coachId)) {
                throw new ActiveMeetingExistsException();
            }

            $existing = CoachActiveMeeting::where('coach_id', $coachId)->lockForUpdate()->first();
            if ($existing) {
                if ((int) $existing->instant_meeting_id === $instantMeetingId) {
                    $existing->update(['expires_at' => now()->addMinutes($ttl)]); // same meeting → refresh
                    return;
                }
                throw new ActiveMeetingExistsException(); // another batch/1:1 already holds the slot
            }

            try {
                CoachActiveMeeting::create([
                    'coach_id'           => $coachId,
                    'instant_meeting_id' => $instantMeetingId,
                    'started_at'         => now(),
                    'expires_at'         => now()->addMinutes($ttl),
                ]);
            } catch (QueryException $e) {
                $other = CoachActiveMeeting::where('coach_id', $coachId)->first();
                if ($other && (int) $other->instant_meeting_id === $instantMeetingId) {
                    return; // parallel request won for the SAME meeting — harmless
                }
                throw new ActiveMeetingExistsException();
            }
        });
    }

    /** Release the coach's slot held by a 1:1 instant meeting (on end/cancel). */
    public function releaseInstant(int $coachId, ?int $instantMeetingId = null): void
    {
        if ($coachId <= 0 || !Schema::hasTable('coach_active_meetings')) {
            return;
        }
        $q = CoachActiveMeeting::where('coach_id', $coachId);
        if ($instantMeetingId !== null) {
            $q->where('instant_meeting_id', $instantMeetingId);
        }
        $q->delete();
    }

    /** Release the coach's slot (optionally only if it points at $liveClassId). */
    public function release(int $coachId, ?int $liveClassId = null): void
    {
        if ($coachId <= 0 || !Schema::hasTable('coach_active_meetings')) {
            return;
        }
        $q = CoachActiveMeeting::where('coach_id', $coachId);
        if ($liveClassId !== null) {
            $q->where('course_live_class_id', $liveClassId);
        }
        $q->delete();
    }

    /** Release whatever coach holds the slot for this live class (on end/cancel). */
    public function releaseByLiveClass(int $liveClassId): void
    {
        if ($liveClassId <= 0 || !Schema::hasTable('coach_active_meetings')) {
            return;
        }
        CoachActiveMeeting::where('course_live_class_id', $liveClassId)->delete();
    }

    /**
     * Remove stale slots: TTL-expired, or whose live class is gone / ended /
     * cancelled / past its window. Returns count removed.
     */
    public function purgeStale(?int $coachId = null): int
    {
        if (!Schema::hasTable('coach_active_meetings')) {
            return 0;
        }
        $q = CoachActiveMeeting::query();
        if ($coachId) {
            $q->where('coach_id', $coachId);
        }

        // Whether the shared mutex table carries the 1:1 instant-meeting column.
        $hasInstant = Schema::hasColumn('coach_active_meetings', 'instant_meeting_id');

        $deleted = 0;
        foreach ($q->get() as $row) {
            $stale = $row->expires_at && $row->expires_at->isPast();

            if (!$stale && $row->course_live_class_id) {
                $lc = CourseLiveClass::find($row->course_live_class_id);
                if (!$lc || $lc->ended_at !== null || $lc->cancelled_at !== null || $lc->isOver()) {
                    $stale = true;
                }
            }

            // A 1:1 instant-meeting slot is stale once the meeting is gone or no
            // longer active (ended/cancelled). Additive — only touches instant rows.
            if (!$stale && $hasInstant && $row->instant_meeting_id) {
                $im = \App\Models\InstantMeeting::find($row->instant_meeting_id);
                if (!$im || $im->status !== \App\Models\InstantMeeting::STATUS_ACTIVE) {
                    $stale = true;
                }
            }

            if ($stale) {
                $row->delete();
                $deleted++;
            }
        }
        return $deleted;
    }

    /**
     * Does the coach already have ANOTHER live class that is genuinely running
     * (host start time reached AND not over/ended/cancelled)? Mirrors the
     * coach-list ownership scope (lesson.instructor_id). Future/scheduled
     * classes are NOT counted, so scheduling is never blocked (only running).
     */
    public function coachHasRunningClass(int $coachId, int $exceptLiveClassId = 0): bool
    {
        if ($coachId <= 0) {
            return false;
        }
        $rows = CourseLiveClass::query()
            ->whereNull('ended_at')
            ->whereNull('cancelled_at')
            ->whereNotNull('start_time')
            ->when($exceptLiveClassId > 0, fn ($q) => $q->where('id', '!=', $exceptLiveClassId))
            ->whereHas('lesson', fn ($q) => $q->where('instructor_id', $coachId))
            ->get();

        foreach ($rows as $lc) {
            if ($lc->hasStarted() && !$lc->isOver()) {
                return true; // started + within window = actively running
            }
        }
        return false;
    }

    /** The owning coach for a live class (course.instructor_id). */
    public function coachOwnerId(CourseLiveClass $liveClass): int
    {
        $courseId = $liveClass->course_id ?: optional($liveClass->lesson)->course_id;
        return (int) (Course::where('id', $courseId)->value('instructor_id') ?? 0);
    }

    /* ===================================================================
     * 2026-06-23 — TIME-OVERLAP enforcement (one live class per time slot).
     *
     * The slot table above guarantees a coach can only ever have ONE meeting
     * *running right now*. This block adds the complementary rule: a coach may
     * not CREATE or START a live class whose time window overlaps another of
     * their own (still-active) classes — covering the case where an instant
     * meeting would clash with an upcoming scheduled one, or two scheduled
     * classes would run at the same time. Non-overlapping future classes are
     * never blocked. Scoped by the OWNING coach (lesson.instructor_id) →
     * tenant-safe: Coach A's classes never clash with Coach B's.
     * =================================================================== */

    /** Business message shown when a time-overlapping class is detected. */
    public const OVERLAP_MESSAGE = 'This time slot conflicts with another of your live classes. Please choose a time after the current session has ended.';

    /**
     * Return the coach's first live class whose time window overlaps
     * [$newStart, $newStart + $durationMin], or null if the slot is free.
     * Ended / cancelled classes never occupy a slot. $exceptLiveClassId lets
     * an edit skip the row being edited.
     */
    public function coachHasOverlappingClass(int $coachId, \Carbon\Carbon $newStart, int $durationMin, int $exceptLiveClassId = 0): ?CourseLiveClass
    {
        if ($coachId <= 0) {
            return null;
        }
        $newEnd = $newStart->copy()->addMinutes(max(1, $durationMin));

        $rows = CourseLiveClass::query()
            ->whereNull('ended_at')
            ->whereNull('cancelled_at')
            ->whereNotNull('start_time')
            ->when($exceptLiveClassId > 0, fn ($q) => $q->where('id', '!=', $exceptLiveClassId))
            ->whereHas('lesson', fn ($q) => $q->where('instructor_id', $coachId))
            ->with('lesson:id,duration')
            ->get();

        foreach ($rows as $lc) {
            try {
                $exStart = \Carbon\Carbon::parse($lc->start_time);
            } catch (\Throwable $e) {
                continue; // unparseable time → can't overlap
            }
            $exDuration = (int) ($lc->expected_duration_minutes
                ?: optional($lc->lesson)->duration
                ?: 60);
            $exEnd = $exStart->copy()->addMinutes(max(1, $exDuration));

            // Half-open intervals overlap iff each starts before the other ends.
            if ($newStart->lt($exEnd) && $exStart->lt($newEnd)) {
                return $lc;
            }
        }
        return null;
    }

    /**
     * Throw ActiveMeetingExistsException (with the overlap message) if the
     * proposed window clashes with another of the coach's active classes.
     *
     * @throws ActiveMeetingExistsException
     */
    public function assertNoOverlap(int $coachId, \Carbon\Carbon $newStart, int $durationMin, int $exceptLiveClassId = 0): void
    {
        if ($this->coachHasOverlappingClass($coachId, $newStart, $durationMin, $exceptLiveClassId)) {
            throw new ActiveMeetingExistsException(self::OVERLAP_MESSAGE);
        }
    }

    /**
     * Run $fn while holding a per-coach advisory lock so two concurrent
     * create/start requests for the SAME coach can't both pass the overlap
     * check and insert clashing rows (req: "two simultaneous requests cannot
     * create two live classes"). Different coaches use different keys → no
     * cross-tenant contention. Non-MySQL / no coach → runs without locking.
     */
    public function withCoachLock(int $coachId, callable $fn)
    {
        if ($coachId <= 0 || DB::getDriverName() !== 'mysql') {
            return $fn();
        }
        $key = 'mbs_liveclass_coach_' . $coachId;
        // 30s > the 20s Zoom HTTP timeout, so a waiting same-coach request
        // serialises behind the holder instead of timing out mid-flight.
        DB::statement('SELECT GET_LOCK(?, 30)', [$key]);
        try {
            return $fn();
        } finally {
            DB::statement('SELECT RELEASE_LOCK(?)', [$key]);
        }
    }
}
