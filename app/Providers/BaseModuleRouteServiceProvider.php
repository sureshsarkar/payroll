<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/**
 * Shared base for every Modules\<Name>\app\Providers\RouteServiceProvider.
 *
 * Two responsibilities:
 *
 *   1. Single source of truth for the web + api route-loading pattern
 *      that every module repeats verbatim. Child providers now only
 *      declare $moduleName + $moduleNamespace; mapping is inherited.
 *
 *   2. Defensive guard: a missing routes/web.php or routes/api.php on
 *      ONE module would otherwise abort the entire app boot
 *      (file_get_contents inside Route::group throws ErrorException).
 *      We log a warning and skip — module still registers, it just
 *      contributes no routes for that channel.
 *
 *      The Log::warning() is deliberate: a silent guard would hide
 *      genuine deploy failures. The line lands in storage/logs/
 *      laravel.log so ops can grep for "module-route-missing" and
 *      know exactly which module on which deploy is degraded.
 *
 * Children declare:
 *
 *     protected string $moduleName      = 'Badges';
 *     protected string $moduleNamespace = 'Modules\Badges\app\Http\Controllers';
 *
 * Nothing else — the inherited map()/mapWebRoutes()/mapApiRoutes()
 * do the rest.
 *
 * Companion CLI: php artisan modules:verify-routes
 * Fails fast at deploy time so the silent runtime guard never has
 * to actually fire in production.
 */
abstract class BaseModuleRouteServiceProvider extends RouteServiceProvider
{
    /** Module folder name under Modules/ — e.g. 'Badges'. */
    protected string $moduleName = '';

    /** PSR-4 controller namespace — e.g. 'Modules\Badges\app\Http\Controllers'. */
    protected string $moduleNamespace = '';

    public function boot(): void
    {
        parent::boot();
    }

    public function map(): void
    {
        $this->mapApiRoutes();
        $this->mapWebRoutes();
    }

    /**
     * Web routes — session, CSRF, etc.
     * Guarded: missing file logs + returns instead of crashing.
     */
    protected function mapWebRoutes(): void
    {
        $path = module_path($this->moduleName, '/routes/web.php');
        if (! $this->routeFilePresent($path, 'web')) {
            return;
        }

        Route::middleware('web')
            ->namespace($this->moduleNamespace)
            ->group($path);
    }

    /**
     * API routes — stateless.
     * Guarded: same shape as web.
     */
    protected function mapApiRoutes(): void
    {
        $path = module_path($this->moduleName, '/routes/api.php');
        if (! $this->routeFilePresent($path, 'api')) {
            return;
        }

        Route::prefix('api')
            ->middleware('api')
            ->namespace($this->moduleNamespace)
            ->group($path);
    }

    /**
     * Existence probe + warning log on miss.
     *
     * Returns true if the file is present and readable. On miss,
     * writes a single warning line with enough breadcrumbs that ops
     * can correlate which module + channel + path + deploy host:
     *
     *   module-route-missing module=Badges channel=api path=...
     *
     * Single helper so the two callers above stay one-liners and
     * the log shape stays consistent.
     */
    protected function routeFilePresent(string $path, string $channel): bool
    {
        if (file_exists($path)) {
            return true;
        }

        Log::warning('module-route-missing', [
            'module'   => $this->moduleName,
            'channel'  => $channel,
            'path'     => $path,
        ]);

        return false;
    }
}
