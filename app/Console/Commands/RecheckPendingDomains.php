<?php

namespace App\Console\Commands;

use App\Models\CoachDomain;
use App\Services\CustomDomainSettings;
use Illuminate\Console\Command;

/**
 * Auto-recheck pending / failed custom domains.
 *
 * A coach often clicks "Verify" before their DNS has propagated. Instead
 * of making them keep clicking, this command re-runs the points-to-us
 * check on every PENDING/FAILED custom domain and flips it to ACTIVE
 * (or VERIFIED in strict mode) the moment DNS lands. Capped by
 * verify_attempts so a domain that never points at us stops being
 * polled forever.
 *
 * Scheduled every 10 minutes (Console\Kernel).
 */
class RecheckPendingDomains extends Command
{
    protected $signature = 'domains:recheck-pending
        {--max-attempts=30 : stop polling a domain after this many misses}
        {--id= : recheck only this coach_domains row (admin-triggered)}
        {--force : ignore the attempt cap (used with --id)}';

    protected $description = 'Re-verify pending/failed coach custom domains and activate those now pointing at us';

    public function handle(CustomDomainSettings $settings): int
    {
        $maxAttempts = (int) $this->option('max-attempts');

        $query = CoachDomain::query()->where('kind', 'custom');

        if ($id = $this->option('id')) {
            // Admin-triggered single recheck — any non-active status, cap optional.
            $query->whereKey((int) $id)
                  ->whereIn('status', [
                      CoachDomain::STATUS_PENDING, CoachDomain::STATUS_FAILED,
                      CoachDomain::STATUS_VERIFIED,
                  ]);
            if (! $this->option('force')) {
                $query->where('verify_attempts', '<', $maxAttempts);
            }
        } else {
            $query->whereIn('status', [CoachDomain::STATUS_PENDING, CoachDomain::STATUS_FAILED])
                  ->where('verify_attempts', '<', $maxAttempts);
        }

        $rows = $query->get();

        $activated = 0;
        $stillWaiting = 0;

        foreach ($rows as $row) {
            if ($this->resolvesToPlatform($row->hostname, $settings)) {
                $row->applyDnsVerified($settings->requiresApproval(), [
                    'method' => 'auto_recheck', 'actor_type' => 'system',
                ]);
                $activated++;
                continue;
            }

            $row->markStatus(CoachDomain::STATUS_FAILED, [
                'fill'  => ['verify_attempts' => (int) $row->verify_attempts + 1,
                            'last_error' => 'Auto-recheck: domain still does not point to us.'],
                'event' => 'verify_attempt',
                'actor_type' => 'system',
                'meta'  => ['auto' => true],
            ]);
            $stillWaiting++;
        }

        $this->info("Rechecked {$rows->count()} domain(s): {$activated} activated, {$stillWaiting} still waiting.");
        return self::SUCCESS;
    }

    /** A record at a platform IP, or CNAME to the platform host (or a sub-host of it). */
    protected function resolvesToPlatform(string $host, CustomDomainSettings $settings): bool
    {
        $ips = $settings->serverIps();
        foreach ($this->lookupA($host) as $rec) {
            $ip = is_array($rec) ? (string) ($rec['ip'] ?? '') : '';
            if ($ip !== '' && in_array($ip, $ips, true)) {
                return true;
            }
        }

        $platformHost = strtolower(trim((string) config('app.coach_domain', '')));
        if ($platformHost !== '' && $platformHost !== 'localhost') {
            foreach ($this->lookupCname($host) as $rec) {
                $target = strtolower(rtrim(is_array($rec) ? (string) ($rec['target'] ?? '') : '', '.'));
                if ($target !== '' && ($target === $platformHost || str_ends_with($target, '.' . $platformHost))) {
                    return true;
                }
            }
        }
        return false;
    }

    /** Isolated so tests can mock it. */
    protected function lookupA(string $host): array
    {
        try {
            $r = @dns_get_record($host, DNS_A);
            return is_array($r) ? $r : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Isolated so tests can mock it. */
    protected function lookupCname(string $host): array
    {
        try {
            $r = @dns_get_record($host, DNS_CNAME);
            return is_array($r) ? $r : [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
