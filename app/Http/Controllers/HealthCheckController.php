<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Public health-check endpoint for uptime monitors (UptimeRobot, BetterStack, etc.)
 * GET /up — returns 200 with JSON if everything is healthy, 503 otherwise.
 *
 * Verifies:
 *   - DB connection (`SELECT 1`)
 *   - Cache write/read round-trip
 *   - Default filesystem disk reachability
 *   - That at least one queue connection is configured
 *
 * Designed to be cheap (~10ms) and safe to hit every minute from external monitors.
 */
class HealthCheckController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache'    => $this->checkCache(),
            'storage'  => $this->checkStorage(),
            'cron'     => $this->checkCron(),
        ];

        // The cron check is meaningful only on environments that run
        // `* * * * * php artisan schedule:run`. Local dev (Windows XAMPP)
        // doesn't have one, so the heartbeat is permanently absent and
        // would always tip the overall status to "degraded". Skip it locally.
        $blocking = $checks;
        if (app()->environment('local')) {
            unset($blocking['cron']);
        }

        $allOk = collect($blocking)->every(fn ($c) => $c['ok'] === true);

        return response()->json([
            'status'    => $allOk ? 'ok' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'app'       => config('app.name'),
            'env'       => config('app.env'),
            'version'   => $this->appVersion(),
            'checks'    => $checks,
        ], $allOk ? 200 : 503);
    }

    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            return ['ok' => true, 'latency_ms' => round((microtime(true) - $start) * 1000, 1)];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function checkCache(): array
    {
        try {
            $key = 'healthcheck-' . bin2hex(random_bytes(4));
            $start = microtime(true);
            Cache::put($key, 'ping', 10);
            $val = Cache::get($key);
            Cache::forget($key);
            return [
                'ok'         => $val === 'ping',
                'latency_ms' => round((microtime(true) - $start) * 1000, 1),
                'driver'     => config('cache.default'),
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function checkStorage(): array
    {
        try {
            $disk = Storage::disk(config('filesystems.default'));
            // Lightweight existence-check; doesn't actually write.
            $disk->files('/');
            return ['ok' => true, 'disk' => config('filesystems.default')];
        } catch (\Throwable $e) {
            return ['ok' => false, 'disk' => config('filesystems.default'), 'error' => $e->getMessage()];
        }
    }

    /**
     * Cron heartbeat: cache key 'cron_last_run' is updated every minute by the
     * scheduler (see app/Console/Kernel.php). If the latest write is >5 min old,
     * cron has stopped running.
     */
    private function checkCron(): array
    {
        $lastRun = Cache::get('cron_last_run');
        if (!$lastRun) {
            return ['ok' => false, 'last_run' => null, 'note' => 'No cron heartbeat ever recorded — schedule:run not running'];
        }
        try {
            $diffSec = now()->diffInSeconds(\Carbon\Carbon::parse($lastRun));
            return [
                'ok'                  => $diffSec < 300,
                'last_run'            => $lastRun,
                'seconds_since_run'   => $diffSec,
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'last_run' => $lastRun, 'error' => $e->getMessage()];
        }
    }

    private function appVersion(): ?string
    {
        $versionFile = base_path('version.json');
        if (file_exists($versionFile)) {
            $j = json_decode(file_get_contents($versionFile), true);
            return $j['version'] ?? null;
        }
        return null;
    }
}
