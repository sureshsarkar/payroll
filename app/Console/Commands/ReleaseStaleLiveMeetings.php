<?php

namespace App\Console\Commands;

use App\Services\LiveMeetingGuard;
use Illuminate\Console\Command;

/**
 * 2026-06-22 — frees active-meeting slots that are stuck (TTL-expired, or whose
 * live class ended/cancelled/over). Belt-and-suspenders alongside the
 * purge-on-claim, so an abandoned meeting never permanently blocks the coach
 * even if they never retry.
 */
class ReleaseStaleLiveMeetings extends Command
{
    protected $signature = 'liveclass:release-stale';
    protected $description = 'Release stuck/expired one-coach-one-meeting slots';

    public function handle(LiveMeetingGuard $guard): int
    {
        $freed = $guard->purgeStale();
        $this->info("Released {$freed} stale live-meeting slot(s).");
        return self::SUCCESS;
    }
}
