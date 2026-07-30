<?php

namespace Modules\Marquee\app\Providers;

use App\Providers\BaseModuleRouteServiceProvider;

/**
 * Module route service provider.
 *
 * All wiring (map, guard, log-on-miss) lives in the shared base.
 * If you need a custom middleware stack or prefix for this module,
 * override mapWebRoutes() / mapApiRoutes() here.
 */
class RouteServiceProvider extends BaseModuleRouteServiceProvider
{
    protected string $moduleName      = 'Marquee';
    protected string $moduleNamespace = 'Modules\Marquee\app\Http\Controllers';
}
