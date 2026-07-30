<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

/**
 * Verifies the cron + queue + Artisan-via-HTTP audit guarantees.
 *
 * Real bug found and fixed:
 *   - Modules/GlobalSetting/.../GlobalSettingController::database_clear_success
 *     was an admin HTTP endpoint that called Artisan::call('migrate:fresh')
 *     — drops every table, then db:seed re-fills from defaults. Pre-audit
 *     gates: auth:admin + admin password re-entry + setting.update permission.
 *     Missing: a hard "this is LIVE production, refuse" check.
 *
 *     A misclick (or compromised admin session that successfully shoulder-
 *     surfed the password during a phishing attack) would destroy ALL
 *     production data — orders, courses, users, payment records, the lot.
 *     The pretty Spatie Backup integration would not save you because
 *     migrate:fresh runs while the row deletion is still happening. By
 *     the time the next backup:run fires, the only thing on tape is the
 *     already-empty post-fresh database.
 *
 *     Fix: hard abort(403) when APP_MODE === 'LIVE'. This feature is for
 *     DEMO/TEST installs only.
 *
 * Other findings during the audit:
 *   - All other Artisan::call from HTTP take internal-only arguments
 *     (Module::find()->getName(), config-driven values) — no user-input
 *     command injection.
 *   - Queue uses database driver, jobs run via schedule->command(
 *     'queue:work --tries=3 --stop-when-empty --max-time=50'). Failed
 *     jobs pruned at 30 days. No Horizon web UI exposed.
 *   - Telescope already gated to admin-only in non-local envs (separate
 *     test in DebugInfoDisclosureTest).
 */
class CronQueueTest extends TestCase
{
    public function test_database_clear_blocks_live_mode(): void
    {
        $src = file_get_contents(base_path(
            'Modules/GlobalSetting/app/Http/Controllers/GlobalSettingController.php'
        ));

        $this->assertMatchesRegularExpression(
            '/function\s+database_clear_success[^}]+strtoupper\(config\([\'"]app\.app_mode[\'"]\)\)\s*===\s*[\'"]LIVE[\'"]/s',
            $src,
            'database_clear_success must hard-abort when APP_MODE === LIVE — migrate:fresh on a prod DB is unrecoverable'
        );

        $this->assertMatchesRegularExpression(
            '/function\s+database_clear_success[^}]+abort\(403/s',
            $src,
            'LIVE-mode block must abort(403), not just return back() with a notification'
        );
    }

    public function test_database_clear_still_requires_password_and_permission(): void
    {
        // Belt-and-suspenders: even with LIVE mode lockout, the pre-existing
        // password + permission gates must remain.
        $src = file_get_contents(base_path(
            'Modules/GlobalSetting/app/Http/Controllers/GlobalSettingController.php'
        ));
        $this->assertStringContainsString(
            "Hash::check(\$request->password, auth('admin')->user()->password)",
            $src,
            'database_clear_success must still re-confirm the calling admin\'s password'
        );
        $this->assertStringContainsString(
            "checkAdminHasPermissionAndThrowException('setting.update')",
            $src
        );
    }

    public function test_no_artisan_call_takes_request_input(): void
    {
        // Scan app/ + Modules/ for Artisan::call calls whose argument or
        // params interpolate $request input. None should exist.
        $hits = [];
        foreach ([base_path('app'), base_path('Modules')] as $root) {
            if (!is_dir($root)) continue;
            $iter = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iter as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') continue;
                $abs = $file->getPathname();
                if (str_contains($abs, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) continue;

                $body = (string) file_get_contents($abs);
                // Match Artisan::call('...$request' or with $request->X in args.
                if (preg_match_all('/Artisan::call\s*\([^;]*\$request->/', $body, $m)) {
                    $rel = str_replace([base_path() . DIRECTORY_SEPARATOR, '\\'], ['', '/'], $abs);
                    $hits[] = "$rel  (" . count($m[0]) . " hit(s))";
                }
            }
        }
        $this->assertEmpty($hits,
            "Artisan::call() must never take user input — that's command injection.\n  " .
            implode("\n  ", $hits)
        );
    }

    public function test_queue_failed_jobs_are_pruned_in_schedule(): void
    {
        $src = file_get_contents(app_path('Console/Kernel.php'));
        $this->assertStringContainsString('queue:prune-failed', $src,
            'schedule must include queue:prune-failed — failed_jobs payloads can contain sensitive request bodies (passwords, bearer tokens) and accumulate without rotation otherwise');
    }
}
