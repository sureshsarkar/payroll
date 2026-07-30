<?php

namespace App\Console\Commands;

use App\Models\CourseBatch;
use App\Models\User;
use App\Notifications\LowAttendanceAlertToCoach;
use App\Services\BatchAttendanceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Audit 2026-05-18 phase 3 — coach low-attendance alert.
 *
 * Runs after the expected class start window (default daily 18:00).
 * For every active batch that had at least one live class scheduled
 * today AND has total students > 0 AND attendance % is below
 * threshold (default 50%), fires a LowAttendanceAlertToCoach to the
 * owning coach.
 *
 * Idempotent within a day (cache lock prevents repeat alerts).
 *
 * Usage:
 *   php artisan attendance:low-alert
 *   php artisan attendance:low-alert --threshold=40 --dry-run
 */
class CheckLowAttendance extends Command
{
    protected $signature = 'attendance:low-alert
                            {--threshold=50 : Attendance % below which to alert (default 50)}
                            {--dry-run : Preview without sending}';

    protected $description = 'Alert coaches when a batch attendance fell below threshold today.';

    public function handle(BatchAttendanceService $svc): int
    {
        $threshold = max(1, min(99, (int) $this->option('threshold')));
        $dry = (bool) $this->option('dry-run');

        $batches = CourseBatch::where('status', 'active')
            ->with('course:id,title,instructor_id,added_by')
            ->get();

        if ($batches->isEmpty()) {
            $this->info('No active batches.');
            return self::SUCCESS;
        }

        $sent = 0;
        $skipped = 0;

        foreach ($batches as $batch) {
            $summary = $svc->summaryFor($batch, now());

            // Only consider batches that actually had a class today.
            if (empty($summary['live_class_ids'])) { $skipped++; continue; }
            if ($summary['total_students'] === 0)  { $skipped++; continue; }

            $pct = (int) round(($summary['attended'] / $summary['total_students']) * 100);
            if ($pct >= $threshold) { $skipped++; continue; }

            $cacheKey = 'low-attend-alert:batch:'.$batch->id.':'.now()->toDateString();
            if (cache()->has($cacheKey)) { $skipped++; continue; }   // already alerted today

            $coachId = $batch->course?->instructor_id ?? $batch->course?->added_by;
            if (!$coachId) { $skipped++; continue; }
            $coach = User::find($coachId);
            if (!$coach) { $skipped++; continue; }

            $line = sprintf(
                '  batch #%d "%s" — %d/%d (%d%%) coach=%s',
                $batch->id,
                \Illuminate\Support\Str::limit($batch->title, 40),
                $summary['attended'],
                $summary['total_students'],
                $pct,
                $coach->email,
            );

            if ($dry) {
                $this->line($line.'  [DRY-RUN]');
                continue;
            }

            try {
                Notification::send($coach, new LowAttendanceAlertToCoach(
                    $batch, $summary['total_students'], $summary['attended']
                ));
                cache()->put($cacheKey, 1, now()->endOfDay());
                $this->line($line.'  → notified');
                $sent++;
            } catch (\Throwable $e) {
                $this->error($line.'  FAILED: '.$e->getMessage());
            }
        }

        $this->info("Done. sent=$sent skipped=$skipped");
        return self::SUCCESS;
    }
}
