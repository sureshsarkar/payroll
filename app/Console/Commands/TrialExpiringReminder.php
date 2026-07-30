<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserMembership;
use App\Notifications\TrialExpiringSoonToCoach;
use Illuminate\Console\Command;

/**
 * Daily scan for coach trials that are about to end. Pings the coach at
 * 3 days, 1 day, and 0 days (today) before expiry. Idempotent per (membership, day)
 * so the same trial can't get pinged twice on the same threshold day.
 */
class TrialExpiringReminder extends Command
{
    protected $signature = 'notify:trial-expiring';
    protected $description = 'Remind coaches whose free trial is about to end (3, 1, 0 days out).';

    public function handle(): int
    {
        $thresholds = [3, 1, 0];
        $today = now()->startOfDay();
        $sent = 0;

        foreach ($thresholds as $days) {
            $target = $today->copy()->addDays($days);

            $rows = UserMembership::where('payment_method', 'trial')
                ->where('status', 'active')
                ->where('payment_status', 'paid')
                ->whereBetween('expires_at', [$target, $target->copy()->endOfDay()])
                ->with('user')
                ->get();

            foreach ($rows as $m) {
                if (!$m->user) continue;

                $flag = "trial_expiry_notice_{$m->id}_d{$days}";
                if (\Cache::has($flag)) continue;
                \Cache::put($flag, 1, now()->addDays(2));

                try {
                    $m->user->notify(new TrialExpiringSoonToCoach($m, $days));
                    $sent++;
                } catch (\Throwable $e) {
                    \Log::warning("Trial-expiring notify failed for membership {$m->id}: " . $e->getMessage());
                }
            }
        }

        $this->info("Trial-expiring notices sent: {$sent}");
        return self::SUCCESS;
    }
}
