<?php

namespace App\Console\Commands;

use App\Models\Announcement;
use App\Services\AnnouncementNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Audit 2026-05-18 phase 3 — fires due scheduled announcements.
 *
 * Runs every minute via Kernel::schedule(). For each Announcement where:
 *   status = 'active'                          AND
 *   delivered_at IS NULL                        AND
 *   scheduled_at <= NOW()
 *
 * calls AnnouncementNotifier::fanOut() and marks delivered_at = now().
 *
 * Idempotent — if the fanout throws, delivered_at stays NULL and the
 * next tick retries. To avoid infinite retry of a poison message, we
 * skip rows where the most recent error is < 5 minutes old; for now
 * we just log and move on (a simple cap is fine for a school-LMS use case).
 *
 * Usage:
 *   php artisan announcements:dispatch-scheduled            (normal)
 *   php artisan announcements:dispatch-scheduled --dry-run  (preview)
 */
class DispatchScheduledAnnouncements extends Command
{
    protected $signature = 'announcements:dispatch-scheduled
                            {--dry-run : Show what would be sent without dispatching}';

    protected $description = 'Fan out announcements whose scheduled_at is past and delivered_at is null.';

    public function handle(AnnouncementNotifier $notifier): int
    {
        $dry = (bool) $this->option('dry-run');

        $due = Announcement::query()
            ->where('status', 'active')
            ->whereNull('delivered_at')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->limit(50)
            ->get();

        if ($due->isEmpty()) {
            if ($this->getOutput()->isVerbose()) {
                $this->info('No scheduled announcements due.');
            }
            return self::SUCCESS;
        }

        $this->info(sprintf('%d announcement(s) due', $due->count()));

        $delivered = 0;
        $failed = 0;

        foreach ($due as $a) {
            $line = sprintf(
                '  #%d "%s" scheduled %s',
                $a->id,
                \Illuminate\Support\Str::limit($a->title, 40),
                $a->scheduled_at?->toIso8601String()
            );

            if ($dry) {
                $this->line($line.'  [DRY-RUN]');
                continue;
            }

            try {
                $count = $notifier->fanOut($a);
                $a->delivered_at = now();
                $a->save();
                $this->line($line."  → sent to {$count} students");
                $delivered++;
            } catch (\Throwable $e) {
                Log::warning('Scheduled announcement dispatch failed', [
                    'announcement_id' => $a->id,
                    'error'           => $e->getMessage(),
                ]);
                $this->error($line.'  FAILED: '.$e->getMessage());
                $failed++;
            }
        }

        $this->info("Done. delivered=$delivered failed=$failed");
        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
