<?php

namespace App\Services;

use App\Models\CourseLiveClass;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Audit 2026-05-18 phase 4 — verified-attendance verifier.
 *
 * Semantics:
 *   - joining the class = creates a live_class_attendances row with
 *     attendance_verified=0 (just "joined", not yet counted).
 *   - leaving / class ending = duration_seconds is filled in.
 *   - This verifier runs AFTER the class ends. For each row of that
 *     class, it sums duration across all sessions for the same user
 *     (rejoin case) and sets attendance_verified=1 only if the total
 *     meets the threshold.
 *
 * Threshold:
 *   - Default: 50% of expected_duration_minutes.
 *   - Configurable via the global setting 'attendance_min_percent'.
 *   - Caller can override per call.
 *
 * Idempotent:
 *   - verifyClass() is safe to re-run; already-verified rows are not
 *     touched.
 *   - ended_at IS NULL means "not yet processed". Once set, the class
 *     is considered finalised.
 */
class LiveClassAttendanceVerifier
{
    private const DEFAULT_MIN_PERCENT = 50;

    /**
     * Verify attendance for one class. Returns counts:
     *   ['verified' => N, 'partial' => N, 'class_id' => id]
     */
    public function verifyClass(CourseLiveClass $class, ?int $minPercent = null): array
    {
        $expectedSeconds = max(60, ((int) ($class->expected_duration_minutes ?? 60)) * 60);

        // Audit 2026-05-18 phase 5 — resolution order for the percent:
        //   1. Explicit $minPercent argument (caller override)
        //   2. course_batches.attendance_min_percent (per-batch override)
        //   3. Global setting attendance_min_percent
        //   4. Hardcoded default
        if ($minPercent === null && $class->batch_id) {
            $perBatch = \DB::table('course_batches')
                ->where('id', $class->batch_id)
                ->value('attendance_min_percent');
            if ($perBatch !== null) {
                $minPercent = (int) $perBatch;
            }
        }
        $minPercent      = max(1, min(100, $minPercent ?? $this->globalMinPercent()));
        $thresholdSeconds = (int) floor($expectedSeconds * $minPercent / 100);

        // Total session-time per (class, user). live_class_attendances may
        // have multiple rows per user (rejoin scenario) — sum them.
        $sumsByUser = DB::table('live_class_attendances')
            ->where('course_live_class_id', $class->id)
            ->where('role', 'student')
            ->where('is_manual', 0)              // manual rows are auto-verified separately
            ->groupBy('user_id')
            ->selectRaw('user_id, COALESCE(SUM(duration_seconds), 0) AS total_seconds')
            ->get()
            ->keyBy('user_id');

        $verified = 0;
        $partial  = 0;

        DB::transaction(function () use ($class, $sumsByUser, $thresholdSeconds, &$verified, &$partial) {
            foreach ($sumsByUser as $userId => $row) {
                $meets = ((int) $row->total_seconds) >= $thresholdSeconds;

                $affected = DB::table('live_class_attendances')
                    ->where('course_live_class_id', $class->id)
                    ->where('user_id', $userId)
                    ->where('role', 'student')
                    ->where('attendance_verified', 0)
                    ->update([
                        'attendance_verified' => $meets ? 1 : 0,
                        'verified_at'         => $meets ? now() : null,
                        'updated_at'          => now(),
                    ]);

                if ($meets && $affected > 0) {
                    $verified++;
                } elseif (!$meets) {
                    $partial++;
                }
            }

            // Mark class as finalised
            if ($class->ended_at === null) {
                $class->ended_at = now();
                $class->save();
                // 2026-06-22 — sweep finalised the class → free the coach's
                // active-meeting slot (one coach = one active meeting).
                app(\App\Services\LiveMeetingGuard::class)->releaseByLiveClass((int) $class->id);
            }
        });

        return [
            'class_id'         => $class->id,
            'verified'         => $verified,
            'partial'          => $partial,
            'threshold_min'    => (int) round($thresholdSeconds / 60),
            'expected_minutes' => (int) ($class->expected_duration_minutes ?? 60),
        ];
    }

    /**
     * Sweep all classes whose scheduled-end + grace window has passed
     * and which haven't been finalised yet.
     *
     * @return array  Per-class results.
     */
    public function verifyPendingClasses(int $graceMinutes = 5): array
    {
        $cutoff = Carbon::now();

        // Pick classes where:
        //   ended_at IS NULL                                AND
        //   start_time (parsed) + expected + grace <= NOW()
        $candidates = CourseLiveClass::query()
            ->whereNull('ended_at')
            ->whereNotNull('start_time')
            ->get();

        $results = [];
        foreach ($candidates as $class) {
            $startTs = $this->parseStartTime($class->start_time);
            if (!$startTs) continue;

            $endTs = $startTs->copy()
                ->addMinutes((int) ($class->expected_duration_minutes ?? 60))
                ->addMinutes(max(0, $graceMinutes));

            if ($endTs->lessThanOrEqualTo($cutoff)) {
                try {
                    $results[] = $this->verifyClass($class);
                } catch (\Throwable $e) {
                    Log::warning('verifyClass failed', [
                        'class_id' => $class->id,
                        'error'    => $e->getMessage(),
                    ]);
                }
            }
        }
        return $results;
    }

    private function globalMinPercent(): int
    {
        try {
            $val = cache()->get('setting')?->attendance_min_percent ?? null;
            if ($val !== null && is_numeric($val)) return (int) $val;
        } catch (\Throwable $e) { /* fall through */ }
        return self::DEFAULT_MIN_PERCENT;
    }

    /**
     * course_live_classes.start_time is a VARCHAR — could be various formats.
     * Try common shapes; return null on failure.
     */
    private function parseStartTime($value): ?Carbon
    {
        if (empty($value)) return null;
        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
