<?php

namespace App\Services;

use App\Models\CourseBatch;
use App\Models\CourseLiveClass;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Audit 2026-05-18 — single source of truth for batch attendance
 * counters. Shared by the admin batch detail page, the coach batch
 * detail page, the dashboard summary card, and the API endpoint.
 *
 * Method usage:
 *   $svc = app(BatchAttendanceService::class);
 *   $svc->summaryFor($batch, $date = today())
 *     -> [
 *       'batch_id'       => 5,
 *       'batch_title'    => 'Pariksha Batch',
 *       'date'           => '2026-05-18',
 *       'total_students' => 42,
 *       'attended'       => 30,
 *       'not_attended'   => 12,
 *       'live_class_ids' => [123, 124],  // classes scheduled on $date
 *     ]
 */
class BatchAttendanceService
{
    /**
     * Headline counters for one batch on one calendar date.
     *
     * Audit 2026-05-19 — extended to also return:
     *   - joined / not_joined         (anyone who hit the room, verified or not)
     *   - yesterday_*                 (same metrics for $day - 1 day)
     * so the coach dashboard widget can render today vs yesterday
     * side-by-side and split "joined" from "live-class attended".
     */
    public function summaryFor(CourseBatch $batch, Carbon|CarbonImmutable|string|null $date = null): array
    {
        $day = $this->normaliseDate($date);
        $prev = $day->copy()->subDay();

        $total = $batch->studentCount();

        $attended    = $batch->attendedOn($day);
        $joined      = $batch->joinedOn($day);
        $notAttended = max(0, $total - $attended);
        $notJoined   = max(0, $total - $joined);

        $yAttended    = $batch->attendedOn($prev);
        $yJoined      = $batch->joinedOn($prev);
        $yNotAttended = max(0, $total - $yAttended);

        return [
            'batch_id'              => $batch->id,
            'batch_title'           => $batch->title,
            'date'                  => $day->toDateString(),
            'total_students'        => $total,

            // Today — keep the original keys (back-compat for the
            // admin batch detail page + tests).
            'attended'              => $attended,       // verified live-class attendance
            'not_attended'          => $notAttended,
            'joined'                => $joined,         // any join, verified or not
            'not_joined'            => $notJoined,

            // Yesterday — new fields for the widget side-by-side view.
            'yesterday_date'        => $prev->toDateString(),
            'yesterday_attended'    => $yAttended,
            'yesterday_not_attended'=> $yNotAttended,
            'yesterday_joined'      => $yJoined,

            'live_class_ids'        => $this->liveClassIdsOn($batch, $day),
        ];
    }

    /**
     * Build summaries for every batch a coach owns (via owning the course).
     * Used by the coach dashboard "today's classes" card.
     *
     * @return Collection<int, array> indexed by batch_id
     */
    public function summariesForCoach(int $coachId, Carbon|CarbonImmutable|string|null $date = null): Collection
    {
        $day = $this->normaliseDate($date);

        $batches = CourseBatch::query()
            ->whereHas('course', function ($q) use ($coachId) {
                $q->where('instructor_id', $coachId)->orWhere('added_by', $coachId);
            })
            ->where('status', 'active')
            ->get();

        return $batches->map(fn (CourseBatch $b) => $this->summaryFor($b, $day))
            ->keyBy('batch_id');
    }

    /**
     * Audit 2026-05-19 — operator-dashboard view.
     *
     * Replaces the "render 757 cards in a horizontal scroller" pattern
     * with a focused, scannable layout:
     *
     *   - `aggregate`  : at-a-glance counts (total, active today, healthy,
     *                    at_risk, empty) for the chip row at the top
     *   - `active`     : batches that have a class today, sorted with the
     *                    lowest-attendance-percent first (the ones that
     *                    need a coach's attention bubble up)
     *   - `idle`       : batches without a class today, in a lightweight
     *                    shape (just enough for a collapsed list)
     *
     * Status buckets used by the chip row:
     *   - healthy : has class today AND today_pct >= 75 (or no enrolled
     *               students yet — neutral)
     *   - at_risk : has class today AND today_pct < 75 AND total > 0
     *   - empty   : has class today AND total_students == 0 (empty batch
     *               that still has a class on the calendar — usually a
     *               data-entry mistake worth flagging)
     */
    public function dashboardForCoach(int $coachId, Carbon|CarbonImmutable|string|null $date = null): array
    {
        $day  = $this->normaliseDate($date);
        $prev = $day->copy()->subDay();

        // Audit 2026-05-19 phase 2 — perf: the original implementation
        // looped through batches and called summaryFor() per row, which
        // fanned out to ~6 DB queries per batch. With 757 batches that
        // was 4,500+ queries per page load. We now compute all counters
        // in a fixed set of 6 GROUP-BY queries regardless of batch count.
        //
        // The cache layer (controller side, dashboardForCoachCached) gives
        // an additional short-TTL win for the dashboard hot path.

        $batches = CourseBatch::query()
            ->whereHas('course', function ($q) use ($coachId) {
                $q->where('instructor_id', $coachId)->orWhere('added_by', $coachId);
            })
            ->where('status', 'active')
            ->get(['id', 'title']);

        if ($batches->isEmpty()) {
            return $this->emptyDashboard($day, $prev);
        }

        $batchIds = $batches->pluck('id')->all();
        $counts   = $this->batchedCounters($batchIds, $day, $prev);

        $active = collect();
        $idle   = collect();

        foreach ($batches as $batch) {
            $total       = (int) ($counts['students'][$batch->id]            ?? 0);
            $attended    = (int) ($counts['attended_today'][$batch->id]      ?? 0);
            $joined      = (int) ($counts['joined_today'][$batch->id]        ?? 0);
            $yAttended   = (int) ($counts['attended_yesterday'][$batch->id]  ?? 0);
            $yJoined     = (int) ($counts['joined_yesterday'][$batch->id]    ?? 0);
            $liveIds     = (array) ($counts['classes_today'][$batch->id]     ?? []);

            $hasClassToday = !empty($liveIds);

            if (!$hasClassToday) {
                $idle->push([
                    'batch_id'       => $batch->id,
                    'batch_title'    => $batch->title,
                    'total_students' => $total,
                ]);
                continue;
            }

            $pct = $total > 0 ? (int) round(($attended / $total) * 100) : 0;
            $yPct = $total > 0 ? (int) round(($yAttended / $total) * 100) : 0;

            $status = $total === 0 ? 'empty' : ($pct >= 75 ? 'healthy' : 'at_risk');

            $active->push([
                'batch_id'              => $batch->id,
                'batch_title'           => $batch->title,
                'date'                  => $day->toDateString(),
                'total_students'        => $total,
                'attended'              => $attended,
                'not_attended'          => max(0, $total - $attended),
                'joined'                => $joined,
                'not_joined'            => max(0, $total - $joined),
                'yesterday_date'        => $prev->toDateString(),
                'yesterday_attended'    => $yAttended,
                'yesterday_not_attended'=> max(0, $total - $yAttended),
                'yesterday_joined'      => $yJoined,
                'live_class_ids'        => $liveIds,
                'today_pct'             => $pct,
                'yesterday_pct'         => $yPct,
                'trend_delta'           => $pct - $yPct,
                'status'                => $status,
            ]);
        }

        // Active: sort by status priority (at_risk → empty → healthy)
        // then by attendance percent ascending — coaches see what needs
        // attention first.
        $statusOrder = ['at_risk' => 0, 'empty' => 1, 'healthy' => 2];
        $active = $active
            ->sortBy([
                fn ($a, $b) => $statusOrder[$a['status']] <=> $statusOrder[$b['status']],
                fn ($a, $b) => $a['today_pct'] <=> $b['today_pct'],
                fn ($a, $b) => strcasecmp($a['batch_title'], $b['batch_title']),
            ])
            ->values();

        $idle = $idle->sortBy(fn ($r) => strtolower($r['batch_title']))->values();

        return [
            'date'           => $day->toDateString(),
            'yesterday_date' => $prev->toDateString(),
            'aggregate' => [
                'total_batches' => $batches->count(),
                'active_today'  => $active->count(),
                'idle_today'    => $idle->count(),
                'healthy'       => $active->where('status', 'healthy')->count(),
                'at_risk'       => $active->where('status', 'at_risk')->count(),
                'empty'         => $active->where('status', 'empty')->count(),
            ],
            'active' => $active,
            'idle'   => $idle,
        ];
    }

    /**
     * Cached wrapper for dashboardForCoach(). Use this on hot paths
     * (the coach-dashboard widget renders on every page load).
     *
     * Cache key incorporates coach + date; TTL is intentionally short
     * (60 s) so the counters feel fresh without re-running 6 group-by
     * queries on every navigation. Invalidate explicitly after writes
     * to live_class_attendances if you need stronger consistency.
     */
    public function dashboardForCoachCached(int $coachId, Carbon|CarbonImmutable|string|null $date = null, int $ttlSeconds = 60): array
    {
        $day = $this->normaliseDate($date);
        $key = sprintf('coach_dashboard:%d:%s', $coachId, $day->toDateString());

        return \Illuminate\Support\Facades\Cache::remember(
            $key,
            now()->addSeconds($ttlSeconds),
            fn () => $this->dashboardForCoach($coachId, $day)
        );
    }

    /**
     * Six GROUP-BY queries that produce every counter the dashboard
     * needs, regardless of batch count. Returns keyed-by-batch_id maps.
     *
     * @param  array<int>                $batchIds
     * @return array{
     *   students:           array<int, int>,
     *   attended_today:     array<int, int>,
     *   joined_today:       array<int, int>,
     *   attended_yesterday: array<int, int>,
     *   joined_yesterday:   array<int, int>,
     *   classes_today:      array<int, array<int>>,
     * }
     */
    private function batchedCounters(array $batchIds, CarbonImmutable $day, CarbonImmutable $prev): array
    {
        if (empty($batchIds)) {
            return [
                'students' => [], 'attended_today' => [], 'joined_today' => [],
                'attended_yesterday' => [], 'joined_yesterday' => [],
                'classes_today' => [],
            ];
        }

        $todayStart = (string) $day->copy()->startOfDay()->utc();
        $todayEnd   = (string) $day->copy()->endOfDay()->utc();
        $prevStart  = (string) $prev->copy()->startOfDay()->utc();
        $prevEnd    = (string) $prev->copy()->endOfDay()->utc();
        $dayStr     = $day->toDateString();

        // Q1: distinct active students per batch
        $students = DB::table('enrollments')
            ->select('batch_id', DB::raw('COUNT(DISTINCT user_id) as c'))
            ->whereIn('batch_id', $batchIds)
            ->where('has_access', 1)
            ->groupBy('batch_id')
            ->pluck('c', 'batch_id')
            ->all();

        // Q2 + Q3: today's attendance, both verified and any-join
        $todayAtt = $this->batchAttendanceByDay($batchIds, $todayStart, $todayEnd);

        // Q4 + Q5: yesterday's attendance, both verified and any-join
        $prevAtt  = $this->batchAttendanceByDay($batchIds, $prevStart, $prevEnd);

        // Q6: live classes today, grouped by batch_id
        $classesToday = DB::table('course_live_classes')
            ->whereIn('batch_id', $batchIds)
            ->where(function ($q) use ($todayStart, $todayEnd, $dayStr) {
                $q->whereRaw('STR_TO_DATE(start_time, "%Y-%m-%d %H:%i:%s") BETWEEN ? AND ?', [$todayStart, $todayEnd])
                  ->orWhereRaw('DATE(STR_TO_DATE(start_time, "%Y-%m-%dT%H:%i:%s")) = ?', [$dayStr])
                  ->orWhereRaw('LEFT(start_time, 10) = ?', [$dayStr]);
            })
            ->get(['id', 'batch_id'])
            ->groupBy('batch_id')
            ->map(fn ($rows) => $rows->pluck('id')->all())
            ->all();

        return [
            'students'           => $students,
            'attended_today'     => $todayAtt['verified'],
            'joined_today'       => $todayAtt['joined'],
            'attended_yesterday' => $prevAtt['verified'],
            'joined_yesterday'   => $prevAtt['joined'],
            'classes_today'      => $classesToday,
        ];
    }

    /**
     * Two counters in two queries: distinct verified-attendance students
     * and distinct any-join students per batch on the given window.
     *
     * @return array{verified: array<int, int>, joined: array<int, int>}
     */
    private function batchAttendanceByDay(array $batchIds, string $start, string $end): array
    {
        $base = DB::table('live_class_attendances as a')
            ->join('course_live_classes as c', 'c.id', '=', 'a.course_live_class_id')
            ->whereIn('c.batch_id', $batchIds)
            ->where('a.role', 'student')
            ->whereBetween('a.joined_at', [$start, $end])
            ->groupBy('c.batch_id');

        $verified = (clone $base)
            ->where('a.attendance_verified', 1)
            ->select('c.batch_id', DB::raw('COUNT(DISTINCT a.user_id) as c'))
            ->pluck('c', 'batch_id')
            ->all();

        $joined = (clone $base)
            ->select('c.batch_id', DB::raw('COUNT(DISTINCT a.user_id) as c'))
            ->pluck('c', 'batch_id')
            ->all();

        return ['verified' => $verified, 'joined' => $joined];
    }

    private function emptyDashboard(CarbonImmutable $day, CarbonImmutable $prev): array
    {
        return [
            'date'           => $day->toDateString(),
            'yesterday_date' => $prev->toDateString(),
            'aggregate' => [
                'total_batches' => 0,
                'active_today'  => 0,
                'idle_today'    => 0,
                'healthy'       => 0,
                'at_risk'       => 0,
                'empty'         => 0,
            ],
            'active' => collect(),
            'idle'   => collect(),
        ];
    }

    /**
     * Which live_class ids (of this batch) are scheduled on $date?
     * Used by the UI to deep-link to "today's class".
     *
     * @return array<int>
     */
    public function liveClassIdsOn(CourseBatch $batch, Carbon|CarbonImmutable $day): array
    {
        $start = $day->copy()->startOfDay()->toDateTimeString();
        $end   = $day->copy()->endOfDay()->toDateTimeString();

        $dayStr = $day->toDateString();

        return CourseLiveClass::where('batch_id', $batch->id)
            ->where(function ($q) use ($start, $end, $dayStr) {
                // start_time is varchar in this schema — try multiple parse paths.
                $q->whereRaw('STR_TO_DATE(start_time, "%Y-%m-%d %H:%i:%s") BETWEEN ? AND ?', [$start, $end])
                  ->orWhereRaw('DATE(STR_TO_DATE(start_time, "%Y-%m-%dT%H:%i:%s")) = ?', [$dayStr])
                  // Loose fallback: any start_time whose first 10 chars match YYYY-MM-DD.
                  ->orWhereRaw('LEFT(start_time, 10) = ?', [$dayStr]);
            })
            ->pluck('id')
            ->all();
    }

    /**
     * Audit 2026-05-18 phase 2 — per-student attendance roster for a batch on a date.
     *
     * Returns one row per enrolled student with:
     *   - user_id, name, email, image
     *   - attended (bool)
     *   - joined_at (timestamp of FIRST join that day, null if not attended)
     *   - duration_minutes (sum across all sessions today, 0 if not attended)
     *   - is_manual (bool — was attendance manually marked by coach)
     *
     * Sorted by attendance status (attended first), then name.
     *
     * @return Collection<int, array>
     */
    public function studentRoster(CourseBatch $batch, Carbon|CarbonImmutable|string|null $date = null): Collection
    {
        $day = $this->normaliseDate($date);
        $start = $day->copy()->startOfDay()->toDateTimeString();
        $end   = $day->copy()->endOfDay()->toDateTimeString();

        // Pull all active students of this batch (one row per user even with
        // multiple enrolments — distinct on user_id).
        // 2026-06-02 — the ATTENDANCE roster is batch membership, NOT
        // has_access=1. A student attending class while their fee is still
        // pending (has_access=0) must appear here so the coach can mark them
        // present. Same has_access-vs-membership distinction as the fee fix.
        // (Capacity/seat counting via CourseBatch::studentCount() stays
        // paid-only — that's a different, deliberate metric.)
        $studentRows = \DB::table('enrollments as e')
            ->join('users as u', 'u.id', '=', 'e.user_id')
            ->where('e.batch_id', $batch->id)
            ->select('u.id', 'u.name', 'u.email', 'u.image')
            ->distinct()
            ->get();

        // 2026-07-15 — temporary slots for THIS date: pull guests coming IN
        // (target = this batch) and flag own students going AWAY (primary = this
        // batch, attending elsewhere today). Guarded so pre-migration behaviour
        // is byte-identical.
        $guestIds = collect();
        $awayIds  = collect();
        if (\Illuminate\Support\Facades\Schema::hasTable('student_temporary_slots')) {
            try {
                $d = $day->toDateString();
                $guestIds = \DB::table('student_temporary_slots')->where('target_batch_id', $batch->id)
                    ->whereDate('slot_date', $d)->where('status', 'scheduled')->pluck('student_id')->map(fn ($v) => (int) $v)->unique();
                $awayIds = \DB::table('student_temporary_slots')->where('primary_batch_id', $batch->id)
                    ->whereDate('slot_date', $d)->where('status', 'scheduled')->pluck('student_id')->map(fn ($v) => (int) $v)->unique();
            } catch (\Throwable $e) {
            }
        }
        $existingIds = $studentRows->pluck('id')->map(fn ($v) => (int) $v)->all();
        $newGuestIds = $guestIds->reject(fn ($id) => in_array($id, $existingIds, true))->values();
        if ($newGuestIds->isNotEmpty()) {
            $studentRows = $studentRows->concat(
                \DB::table('users')->whereIn('id', $newGuestIds->all())->select('id', 'name', 'email', 'image')->get()
            );
        }

        if ($studentRows->isEmpty()) {
            return collect([]);
        }

        // Audit 2026-05-18 phase 4 — also pluck attendance_verified +
        // class_finalised so the UI can distinguish:
        //   verified   → green badge (counts in summary)
        //   partial    → yellow badge (joined but < threshold, doesn't count)
        //   pending    → grey badge (class hasn't ended yet)
        //   absent     → red badge (never joined)
        $attendanceRows = \DB::table('live_class_attendances as a')
            ->join('course_live_classes as c', 'c.id', '=', 'a.course_live_class_id')
            ->where('c.batch_id', $batch->id)
            ->where('a.role', 'student')
            ->whereBetween('a.joined_at', [$start, $end])
            ->select(
                'a.user_id',
                \DB::raw('MIN(a.joined_at) as first_joined'),
                \DB::raw('COALESCE(SUM(a.duration_seconds), 0) as total_seconds'),
                \DB::raw('MAX(a.is_manual) as has_manual'),
                \DB::raw('MAX(a.attendance_verified) as is_verified'),
                \DB::raw('MAX(IF(c.ended_at IS NULL, 0, 1)) as class_finalised'),
            )
            ->groupBy('a.user_id')
            ->get()
            ->keyBy('user_id');

        return $studentRows->map(function ($s) use ($attendanceRows, $guestIds, $awayIds) {
            $att = $attendanceRows->get($s->id);
            $isGuest = $guestIds->contains((int) $s->id);
            $isAway  = ! $isGuest && $awayIds->contains((int) $s->id);

            $verification = 'absent';
            if ($att) {
                if ((int) $att->is_verified === 1) {
                    $verification = 'verified';
                } elseif ((int) $att->class_finalised === 1) {
                    $verification = 'partial';   // class ended, didn't meet threshold
                } else {
                    $verification = 'pending';   // class hasn't ended yet
                }
            } elseif ($isAway) {
                $verification = 'away';          // attending a temporary slot elsewhere today — not absent
            }

            return [
                'user_id'             => (int) $s->id,
                'name'                => (string) $s->name,
                'email'               => (string) $s->email,
                'image'               => $s->image,
                'attended'            => $verification === 'verified',   // for counters
                'verification_status' => $verification,                  // phase 4
                'joined_at'           => $att?->first_joined,
                'duration_minutes'    => $att ? (int) round(((int) $att->total_seconds) / 60) : 0,
                'is_manual'           => $att ? (bool) $att->has_manual : false,
                'is_guest'            => $isGuest,   // temporary slot INTO this batch today
                'is_away'             => $isAway,    // primary here, attending elsewhere today
            ];
        })
        ->sortBy([
            ['attended', 'desc'],   // attended first
            ['name', 'asc'],        // then alphabetical
        ])
        ->values();
    }

    private function normaliseDate(Carbon|CarbonImmutable|string|null $date): CarbonImmutable
    {
        if ($date === null) return CarbonImmutable::today();
        if (is_string($date)) return CarbonImmutable::parse($date);
        return CarbonImmutable::instance(
            $date instanceof CarbonImmutable ? $date->toMutable() : $date
        );
    }
}
