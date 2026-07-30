<?php

namespace App\Console\Commands;

use App\Models\CourseLiveClass;
use App\Notifications\ZoomMeetingMissingForUpcomingClass;
use App\Services\ZoomApiService;
use Illuminate\Console\Command;

/**
 * Pre-class meeting verification — runs every 5 minutes via the
 * scheduler. For each Zoom live class scheduled in the next 30 minutes
 * whose verification is stale, asks Zoom whether the meeting still
 * exists. Stores the result on the row (verification_status,
 * verification_message, last_verified_at) and emails the instructor
 * once if the meeting is missing.
 *
 * Why: Zoom auto-deletes one-time meetings after 30 days of inactivity.
 * Many of mbsguru's live classes were scheduled weeks in advance and
 * the meeting on Zoom's side may quietly disappear before start time.
 * Without this, the failure mode is a stuck "Joining Meeting…" splash
 * for whichever student arrives first.
 *
 * State machine for `course_live_classes.verification_status`:
 *   pending  - new row, not yet probed
 *   ok       - meeting exists on Zoom (re-verify hourly inside the window)
 *   missing  - 404 from Zoom; instructor notified, won't re-notify
 *   error    - transient failure (network, auth) — retry next run
 *   skipped  - instructor's Zoom credential is dead; covered by
 *              zoom:health-check / ZoomReconnectRequired notification.
 *
 * Schedule entry lives in app/Console/Kernel.php — runs every 5 minutes.
 */
class VerifyUpcomingLiveClasses extends Command
{
    protected $signature = 'live-class:verify
        {--window=30 : Minutes ahead of now to verify (default 30)}
        {--re-verify-after=60 : Re-verify a row already marked ok if last check is older than N minutes}
        {--dry-run : Probe but do not write or notify}';

    protected $description = 'Probe Zoom that each upcoming live class still has a reachable meeting; notify instructor on missing.';

    public function __construct(private readonly ZoomApiService $zoom)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $window      = max(1, min(360, (int) $this->option('window')));
        $reVerifyMin = max(1, (int) $this->option('re-verify-after'));
        $dryRun      = (bool) $this->option('dry-run');

        $cutoffStart = now();
        $cutoffEnd   = now()->copy()->addMinutes($window);

        // Rows in the window that are pending OR that haven't been
        // re-verified within the freshness threshold.
        $rows = CourseLiveClass::with([
                'lesson:id,instructor_id,title',
                'lesson.instructor:id,name,email',
                'lesson.instructor.zoom_credential',
            ])
            ->where('type', 'zoom')
            ->whereBetween('start_time', [$cutoffStart, $cutoffEnd])
            ->where(function ($q) use ($reVerifyMin) {
                $q->whereIn('verification_status', ['pending', 'error'])
                  ->orWhere(function ($qq) use ($reVerifyMin) {
                      $qq->where('verification_status', 'ok')
                         ->where(function ($qqq) use ($reVerifyMin) {
                             $qqq->whereNull('last_verified_at')
                                 ->orWhere('last_verified_at', '<', now()->subMinutes($reVerifyMin));
                         });
                  });
            })
            ->get();

        if ($rows->isEmpty()) {
            $this->info(sprintf('No live classes need verification in the next %d minutes.', $window));
            return self::SUCCESS;
        }

        $this->info(sprintf('Verifying %d live classes (window=%dm)…', $rows->count(), $window));

        $tally = ['ok' => 0, 'missing' => 0, 'error' => 0, 'skipped' => 0];

        foreach ($rows as $row) {
            $instructor = $row->lesson?->instructor;
            $cred       = $instructor?->zoom_credential;

            if (!$instructor || !$cred) {
                $this->logResult($row, 'skipped', 'no instructor or zoom credential', $dryRun);
                $tally['skipped']++;
                continue;
            }

            if (!$row->meeting_id) {
                $this->logResult($row, 'skipped', 'row has no meeting_id', $dryRun);
                $tally['skipped']++;
                continue;
            }

            [$status, $message] = $this->zoom->probeMeeting($cred, (string) $row->meeting_id);
            $tally[$status] = ($tally[$status] ?? 0) + 1;

            $previous = (string) ($row->verification_status ?? 'pending');
            $this->logResult($row, $status, $message, $dryRun);

            if (!$dryRun && $status === 'missing' && $previous !== 'missing') {
                try {
                    $instructor->notify(new ZoomMeetingMissingForUpcomingClass($row));
                } catch (\Throwable $e) {
                    $this->error(sprintf(
                        '  ↳ notify failed for live_class id=%d: %s',
                        $row->id,
                        $e->getMessage()
                    ));
                }
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'Summary: %d ok, %d missing, %d error, %d skipped',
            $tally['ok'], $tally['missing'], $tally['error'], $tally['skipped']
        ));

        return $tally['missing'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function logResult(CourseLiveClass $row, string $status, string $message, bool $dryRun): void
    {
        $line = sprintf(
            '  [%s] live_class=%d lesson=%d meeting=%s starts=%s — %s',
            strtoupper($status),
            $row->id,
            $row->lesson_id,
            (string) $row->meeting_id,
            $row->start_time?->format('Y-m-d H:i') ?? '?',
            $message
        );
        $method = match ($status) {
            'ok'      => 'info',
            'skipped' => 'comment',
            'error'   => 'warn',
            default   => 'error',
        };
        $this->{$method}($line);

        if (!$dryRun) {
            $row->forceFill([
                'verification_status'  => $status,
                'verification_message' => $message,
                'last_verified_at'     => now(),
            ])->save();
        }
    }
}
