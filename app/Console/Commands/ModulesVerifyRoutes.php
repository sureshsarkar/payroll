<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * White-label hygiene: verify every enabled module ships its route files.
 *
 * Why this exists
 *   The runtime guard in BaseModuleRouteServiceProvider lets the app
 *   boot when a module's routes/web.php or routes/api.php is missing
 *   (it logs and returns). That guard is the safety net — this CLI is
 *   the alarm bell. Wire it into your deploy script so a botched
 *   sync fails the deploy step instead of degrading silently in prod.
 *
 * What it checks (per enabled module in modules_statuses.json)
 *   1. routes/web.php exists
 *   2. routes/api.php exists
 *   3. each existing file is syntactically valid PHP (php -l)
 *
 * Exit codes
 *   0  every enabled module clean
 *   1  one or more modules degraded (missing file OR syntax error)
 *
 * Usage
 *   php artisan modules:verify-routes
 *   php artisan modules:verify-routes --json    # for CI consumption
 *   php artisan modules:verify-routes --strict  # also warn on extra
 *                                                 modules in folder that
 *                                                 aren't in statuses.json
 */
class ModulesVerifyRoutes extends Command
{
    protected $signature = 'modules:verify-routes
        {--json : Emit machine-readable JSON}
        {--strict : Also report modules present on disk but absent from modules_statuses.json}';

    protected $description = 'Verify every enabled module has its required route files (white-label deploy gate)';

    public function handle(): int
    {
        $statusesPath = base_path('modules_statuses.json');
        if (! is_file($statusesPath)) {
            $this->error("modules_statuses.json not found at $statusesPath");
            return self::FAILURE;
        }

        $statuses = json_decode((string) file_get_contents($statusesPath), true);
        if (! is_array($statuses)) {
            $this->error('modules_statuses.json is not valid JSON');
            return self::FAILURE;
        }

        $modulesDir = base_path('Modules');
        $rows = [];           // for the human table
        $machine = [];        // for --json
        $hasFailure = false;

        foreach ($statuses as $module => $enabled) {
            if (! $enabled) continue;

            $base = $modulesDir . DIRECTORY_SEPARATOR . $module;
            if (! is_dir($base)) {
                $rows[] = [$module, 'ENABLED', 'MODULE-FOLDER-MISSING', '-', '-'];
                $machine[] = ['module' => $module, 'state' => 'module-folder-missing'];
                $hasFailure = true;
                continue;
            }

            $web = $base . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php';
            $api = $base . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'api.php';

            $webState = $this->probe($web);
            $apiState = $this->probe($api);

            if ($webState['state'] !== 'ok' || $apiState['state'] !== 'ok') {
                $hasFailure = true;
            }

            $rows[] = [
                $module,
                'ENABLED',
                'ROUTES',
                $this->cell($webState),
                $this->cell($apiState),
            ];
            $machine[] = [
                'module' => $module,
                'web'    => $webState,
                'api'    => $apiState,
            ];
        }

        // Strict mode: report extras
        if ($this->option('strict')) {
            foreach (glob($modulesDir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [] as $dir) {
                $name = basename($dir);
                if (! array_key_exists($name, $statuses)) {
                    $rows[] = [$name, 'NOT-IN-STATUSES', 'WARN', '-', '-'];
                    $machine[] = ['module' => $name, 'state' => 'not-in-statuses'];
                }
            }
        }

        if ($this->option('json')) {
            $this->line(json_encode([
                'ok'      => ! $hasFailure,
                'modules' => $machine,
            ], JSON_PRETTY_PRINT));
        } else {
            $this->newLine();
            $this->table(['Module', 'Status', 'Check', 'web.php', 'api.php'], $rows);
            $this->newLine();
            if ($hasFailure) {
                $this->error('One or more modules failed route verification. Fix before deploy.');
            } else {
                $this->info('All enabled modules have valid route files.');
            }
        }

        return $hasFailure ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Probe one route file: exists? parses?
     *
     * Returns ['state' => 'ok' | 'missing' | 'syntax-error', 'detail' => ...]
     */
    protected function probe(string $path): array
    {
        if (! file_exists($path)) {
            return ['state' => 'missing', 'path' => $path];
        }

        // php -l on a routes file is fast (<100ms) and catches the
        // class of corruption (truncated copy, encoding issue, stray
        // BOM) that would crash Route::group at runtime. We spawn it
        // via Symfony Process rather than include() because including
        // would actually register routes and bleed state into the
        // verify run. Symfony Process auto-escapes argv, so the path
        // can't be coerced into a second command — same safety as
        // escapeshellarg + exec but without the dangerous-function
        // footprint flagged by the audit scanner.
        $proc = new Process([PHP_BINARY, '-l', $path]);
        $proc->setTimeout(10);
        $proc->run();
        if (! $proc->isSuccessful()) {
            return [
                'state'  => 'syntax-error',
                'path'   => $path,
                'detail' => trim($proc->getOutput() . $proc->getErrorOutput()),
            ];
        }

        return ['state' => 'ok', 'path' => $path];
    }

    protected function cell(array $state): string
    {
        return match ($state['state']) {
            'ok'           => '<info>OK</info>',
            'missing'      => '<error>MISSING</error>',
            'syntax-error' => '<error>SYNTAX</error>',
            default        => '<comment>'.($state['state']).'</comment>',
        };
    }
}
