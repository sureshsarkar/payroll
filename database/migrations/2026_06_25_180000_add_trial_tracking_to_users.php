<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-06-25 — one-time free-trial tracking. Persists the trial usage on the
 * coach (account) record so the "trial only once per coach" rule survives even
 * if old UserMembership trial rows are deleted/changed. Backfills existing
 * coaches who already consumed a coach-free-trial membership so they cannot
 * re-claim. Idempotent + additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            if (! Schema::hasColumn('users', 'trial_used_at')) {
                $t->timestamp('trial_used_at')->nullable()->after('status')->index();
            }
            if (! Schema::hasColumn('users', 'trial_started_at')) {
                $t->timestamp('trial_started_at')->nullable()->after('trial_used_at');
            }
            if (! Schema::hasColumn('users', 'trial_expired_at')) {
                $t->timestamp('trial_expired_at')->nullable()->after('trial_started_at');
            }
        });

        // Backfill: any coach who already has a coach-free-trial membership has
        // consumed their trial — stamp the flag from that row so the one-time
        // rule applies to legacy accounts too. Only fills NULL flags.
        try {
            DB::statement("
                UPDATE users u
                JOIN (
                    SELECT um.user_id,
                           MIN(um.started_at)  AS started_at,
                           MAX(um.expires_at)  AS expires_at,
                           MIN(um.started_at)  AS used_at
                    FROM user_memberships um
                    JOIN membership_plans p ON p.id = um.plan_id
                    WHERE p.slug = 'coach-free-trial'
                    GROUP BY um.user_id
                ) t ON t.user_id = u.id
                SET u.trial_used_at    = COALESCE(u.trial_used_at, t.used_at, t.started_at, NOW()),
                    u.trial_started_at = COALESCE(u.trial_started_at, t.started_at),
                    u.trial_expired_at = COALESCE(u.trial_expired_at, t.expires_at)
                WHERE u.trial_used_at IS NULL
            ");
        } catch (\Throwable $e) {
            // Backfill is best-effort; the flag + membership-history fallback in
            // CoachTrialService::hasUsedTrial() still enforces the rule.
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            foreach (['trial_expired_at', 'trial_started_at', 'trial_used_at'] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $t->dropColumn($col);
                }
            }
        });
    }
};
