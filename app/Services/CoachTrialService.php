<?php

namespace App\Services;

use App\Models\MembershipPlan;
use App\Models\User;
use App\Models\UserMembership;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\GlobalSetting\app\Models\Setting;

/**
 * Configurable coach trial system (2026-06-25).
 *
 * Replaces the old "look up the coach-free-trial plan and read its duration_days,
 * else hardcode 15" approach with an admin-configurable trial:
 *   - coach_trial_enabled      (bool)  master on/off
 *   - coach_trial_days         (int)   length of the free trial
 *   - coach_trial_grace_days   (int)   soft window AFTER expiry before the gate
 *   - coach_trial_after        (enum)  what happens once trial+grace ends:
 *                                        soft = dashboard stays, premium actions gated
 *                                        hard = gate everywhere
 *                                        none = never gate (warn only)
 *
 * All knobs live in the `settings` table so the Super-Admin can change them
 * without a deploy. Defaults: 14 days + 3 day grace + soft gate.
 *
 * This service is the single source of truth: grant, status, and the
 * premium-access decision all derive from the same config + the user's latest
 * UserMembership row (so it is real state, not just a date field).
 */
class CoachTrialService
{
    public const K_ENABLED = 'coach_trial_enabled';
    public const K_DAYS    = 'coach_trial_days';
    public const K_GRACE   = 'coach_trial_grace_days';
    public const K_AFTER   = 'coach_trial_after';

    public const DEF_DAYS  = 14;
    public const DEF_GRACE = 3;
    public const DEF_AFTER = 'soft';
    public const AFTERS    = ['soft', 'hard', 'none'];

    private const CACHE_KEY = 'coach_trial_config';

    /** Admin-configurable trial settings (cached 5 min, busted on save()). */
    public function config(): array
    {
        return Cache::remember(self::CACHE_KEY, 300, function () {
            $s = Setting::whereIn('key', [self::K_ENABLED, self::K_DAYS, self::K_GRACE, self::K_AFTER])
                ->pluck('value', 'key');

            $after = $s[self::K_AFTER] ?? self::DEF_AFTER;

            return [
                'enabled' => ! isset($s[self::K_ENABLED]) ? true : ((string) $s[self::K_ENABLED] === '1'),
                'days'    => max(0, (int) ($s[self::K_DAYS]  ?? self::DEF_DAYS)),
                'grace'   => max(0, (int) ($s[self::K_GRACE] ?? self::DEF_GRACE)),
                'after'   => in_array($after, self::AFTERS, true) ? $after : self::DEF_AFTER,
            ];
        });
    }

    public function enabled(): bool
    {
        return $this->config()['enabled'];
    }

    /** Persist new config (Super-Admin). Validates + busts the cache. */
    public function save(array $cfg): void
    {
        $map = [
            self::K_ENABLED => ! empty($cfg['enabled']) ? '1' : '0',
            self::K_DAYS    => (string) max(0, (int) ($cfg['days']  ?? self::DEF_DAYS)),
            self::K_GRACE   => (string) max(0, (int) ($cfg['grace'] ?? self::DEF_GRACE)),
            self::K_AFTER   => in_array($cfg['after'] ?? '', self::AFTERS, true) ? $cfg['after'] : self::DEF_AFTER,
        ];
        foreach ($map as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        Cache::forget(self::CACHE_KEY);
    }

    /** Is this user a head coach (the membership-bearing identity)? */
    public function isHeadCoach(User $user): bool
    {
        return ($user->role ?? null) === 'instructor' && empty($user->coach_id);
    }

    /**
     * The trial plan is just a container for the UserMembership.plan_id. Created
     * on demand so trials never silently break if the seed row is missing/renamed
     * (the old design returned null → no trial → empty paywall).
     */
    protected function trialPlan(int $days): MembershipPlan
    {
        $plan = MembershipPlan::where('slug', 'coach-free-trial')->first();
        if ($plan) {
            return $plan;
        }
        return MembershipPlan::create([
            'name'          => 'Coach Free Trial',
            'slug'          => 'coach-free-trial',
            'tier'          => MembershipPlan::TIER_CUSTOM ?? 'custom',
            'role'          => 'instructor',
            'price'         => 0,
            'duration_days' => $days,
            'status'        => 'active',
        ]);
    }

    /** Is this plan THE free-trial plan? (one-time rule keys off this, never an id) */
    public function isTrialPlan(?MembershipPlan $plan): bool
    {
        return $plan && $plan->slug === 'coach-free-trial';
    }

    /**
     * Has this coach already consumed their one-time free trial? Authoritative:
     * the persisted users.trial_used_at flag OR (legacy fallback) the existence
     * of any coach-free-trial membership for the coach. Resolved to the head
     * coach so staff share the account's trial status. Tenant-safe: only this
     * coach's own rows are consulted.
     */
    public function hasUsedTrial(User $coach): bool
    {
        $head = $this->isHeadCoach($coach) ? $coach : (User::find($coach->coach_id) ?: $coach);

        if (! empty($head->trial_used_at)) {
            return true;
        }

        return UserMembership::where('user_memberships.user_id', $head->id)
            ->whereHas('plan', fn ($q) => $q->where('slug', 'coach-free-trial'))
            ->exists();
    }

    /**
     * Hard backend guard for any code path that would (re)claim the trial.
     * Logs the blocked attempt and throws TrialAlreadyUsedException when the
     * coach has already used it. Call before creating a trial membership.
     */
    public function assertCanClaimTrial(User $coach): void
    {
        if ($this->hasUsedTrial($coach)) {
            $head = $this->isHeadCoach($coach) ? $coach : (User::find($coach->coach_id) ?: $coach);
            \App\Services\ActivityLogger::log(
                \App\Models\ActivityLog::TRIAL_BLOCKED, 'coach-trial', $head,
                null, null, 'Duplicate free-trial attempt blocked'
            );
            throw new \App\Exceptions\TrialAlreadyUsedException();
        }
    }

    /** Persist the one-time trial usage on the (head) coach record. */
    protected function markTrialUsed(User $coach, ?UserMembership $m): void
    {
        $coach->forceFill([
            'trial_used_at'    => $coach->trial_used_at ?: now(),
            'trial_started_at' => $coach->trial_started_at ?: ($m?->started_at ?? now()),
            'trial_expired_at' => $m?->expires_at,
        ])->save();
    }

    /**
     * Grant the configured free trial. ONE-TIME + idempotent: blocked if the
     * coach has ever used a trial (flag or history) or already has a membership.
     * Returns the membership, or null if trials are disabled / not a head coach /
     * already used. Auto-grant path → silent null (no exception) on re-use.
     */
    public function grantTrial(User $coach): ?UserMembership
    {
        $cfg = $this->config();
        if (! $cfg['enabled'] || ! $this->isHeadCoach($coach)) {
            return null;
        }

        // One-time rule — never re-grant once used (flag or legacy history).
        if ($this->hasUsedTrial($coach)) {
            return UserMembership::where('user_id', $coach->id)->active()->first();
        }

        $existing = UserMembership::where('user_id', $coach->id)->orderByDesc('id')->first();
        if ($existing) {
            return $existing->status === 'active' ? $existing : null;
        }

        $plan = $this->trialPlan($cfg['days']);

        $membership = DB::transaction(function () use ($coach, $plan, $cfg) {
            $now = now();
            return UserMembership::create([
                'user_id'            => $coach->id,
                'plan_id'            => $plan->id,
                'started_at'         => $now,
                'expires_at'         => $cfg['days'] > 0 ? $now->copy()->addDays($cfg['days']) : null,
                'price_paid'         => 0,
                'wallet_credit_used' => 0,
                'payment_method'     => 'trial',
                'payment_status'     => 'paid',
                'status'             => 'active',
                'transaction_id'     => 'TRIAL-' . $coach->id,
            ]);
        });

        // Persist the one-time flag + audit.
        $this->markTrialUsed($coach, $membership);
        try {
            \App\Services\ActivityLogger::log(
                \App\Models\ActivityLog::TRIAL_ACTIVATED, 'coach-trial', $coach,
                null, ['plan_id' => $plan->id, 'expires_at' => (string) $membership->expires_at],
                'Free trial activated (' . $cfg['days'] . ' days)'
            );
        } catch (\Throwable $e) {
        }

        return $membership;
    }

    /**
     * Real trial/membership state for a coach (not just a date).
     * state ∈ paid | trial | grace | expired | none.
     */
    public function status(User $coach): array
    {
        $cfg = $this->config();
        $coachId = $this->isHeadCoach($coach) ? (int) $coach->id : (int) ($coach->coach_id ?: $coach->id);

        $m = UserMembership::where('user_id', $coachId)->orderByDesc('id')->first();

        $base = [
            'state' => 'none', 'is_trial' => false, 'days_left' => 0, 'in_grace' => false,
            'grace_left' => 0, 'expires_at' => null, 'after' => $cfg['after'], 'config' => $cfg,
        ];

        if (! $m) {
            return $base;
        }

        // Trial is detected by payment_method OR the plan itself being the trial
        // plan — so a Coach-Free-Trial membership granted by any path (auto, admin
        // assign, legacy) is consistently treated as a trial everywhere.
        $isTrial  = $m->payment_method === 'trial' || optional($m->plan)->slug === 'coach-free-trial';
        $expires  = $m->expires_at;
        $active   = $m->status === 'active' && $m->payment_status === 'paid'
            && (is_null($expires) || $expires >= now());

        if ($active) {
            return array_merge($base, [
                'state'      => $isTrial ? 'trial' : 'paid',
                'is_trial'   => $isTrial,
                'days_left'  => $expires ? max(0, (int) ceil(now()->floatDiffInDays($expires, false))) : 0,
                'expires_at' => $expires,
            ]);
        }

        // Not active any more — within grace?
        if ($expires) {
            $graceEnd = $expires->copy()->addDays($cfg['grace']);
            if ($cfg['grace'] > 0 && now()->lessThanOrEqualTo($graceEnd)) {
                return array_merge($base, [
                    'state'      => 'grace', 'is_trial' => $isTrial, 'in_grace' => true,
                    'grace_left' => max(0, (int) ceil(now()->floatDiffInDays($graceEnd, false))),
                    'expires_at' => $expires,
                ]);
            }
        }

        return array_merge($base, ['state' => 'expired', 'is_trial' => $isTrial, 'expires_at' => $expires]);
    }

    /**
     * May this coach use premium (gated) features right now?
     * paid/trial/grace → yes. After grace → only if after=none.
     */
    public function allowsPremium(User $coach): bool
    {
        $st = $this->status($coach);
        if (in_array($st['state'], ['paid', 'trial', 'grace'], true)) {
            return true;
        }
        return $st['config']['after'] === 'none';
    }
}
