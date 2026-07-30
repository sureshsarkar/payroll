<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\MembershipService;
use Illuminate\Console\Command;

/**
 * One-shot backfill: grant the free trial membership to every existing
 * instructor who doesn't already have an active membership. Use this once
 * after deploying the trial feature to onboard your existing coach base.
 *
 * Idempotent — coaches who already have any active membership are skipped.
 */
class GrantCoachTrials extends Command
{
    protected $signature = 'coach:grant-trials
                            {--dry : Print what would happen without writing}';

    protected $description = 'Backfill the free-trial membership to existing coaches who have no active membership.';

    public function handle(MembershipService $service): int
    {
        $coaches = User::where('role', 'instructor')->get();
        $granted = 0; $skipped = 0;

        foreach ($coaches as $coach) {
            if ($service->currentFor($coach)) {
                $skipped++;
                continue;
            }

            if ($this->option('dry')) {
                $this->line("would grant: #{$coach->id} {$coach->name} ({$coach->email})");
            } else {
                $m = $service->grantTrial($coach);
                if ($m) {
                    $this->line("granted: #{$coach->id} → trial expires {$m->expires_at}");
                    $granted++;
                } else {
                    $this->warn("skipped (trial plan not available): #{$coach->id}");
                    $skipped++;
                }
            }
        }

        $this->info("\nDone. Granted: {$granted}, skipped: {$skipped}");
        return self::SUCCESS;
    }
}
