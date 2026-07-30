<?php

namespace Modules\Testimonial\app\Providers;

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
    protected string $moduleName      = 'Testimonial';
    protected string $moduleNamespace = 'Modules\Testimonial\app\Http\Controllers';
}
