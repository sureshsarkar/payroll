<?php

use App\Models\User;
use App\Services\SubdomainAssigner;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Per-coach white-label — Phase 6 backfill.
 *
 * Every existing coach (role='instructor') without a coach_domains
 * row gets one auto-assigned subdomain so they have a working
 * white-label URL out of the box. New coaches created after this
 * migration get theirs via the User model's created event (P6).
 *
 * Skip rules (intentional):
 *   - Coach already has any domain row -> skip (don't overwrite
 *     custom domains they added in P5).
 *   - config('app.coach_domain') is unset or 'localhost' -> skip
 *     (auto-generating 'acme.localhost' is noise on dev installs).
 *
 * Idempotent — re-running on a coach who already got their row
 * is a no-op via SubdomainAssigner's existence check.
 *
 * Non-destructive — no down() rollback. Rolling back would leave
 * P5 / P6 working but coaches without a default URL, which is the
 * pre-P6 state. If you genuinely need to roll back, restore from
 * DB backup; this migration is one-way by design.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Skip when there's no platform host to use — typical on
        // localhost dev installs. The User-model 'created' event
        // is also gated by the same check; coaches who sign up
        // later on a properly-configured server will pick up
        // their subdomain naturally.
        $assigner = app(SubdomainAssigner::class);
        if (! $assigner->platformHost()) {
            Log::info('subdomain-backfill-skipped', [
                'reason' => 'no platform coach_domain configured',
            ]);
            return;
        }

        $assigned = 0;
        $skipped  = 0;

        User::where('role', 'instructor')
            ->chunkById(100, function ($coaches) use ($assigner, &$assigned, &$skipped) {
                foreach ($coaches as $coach) {
                    try {
                        $row = $assigner->ensureForCoach($coach);
                        if ($row && $row->wasRecentlyCreated) {
                            $assigned++;
                        } else {
                            $skipped++;
                        }
                    } catch (\Throwable $e) {
                        Log::warning('subdomain-backfill-row-failed', [
                            'coach_id' => $coach->id,
                            'error'    => $e->getMessage(),
                        ]);
                        $skipped++;
                    }
                }
            });

        Log::info('subdomain-backfill-done', [
            'assigned' => $assigned,
            'skipped'  => $skipped,
        ]);
    }

    public function down(): void
    {
        // No-op — see file docblock.
    }
};
