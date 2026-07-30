<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ApiVersionAlias — audit 2026-05-22.
 *
 * Lets mobile clients request `/api/v1/<endpoint>` while the platform's
 * routes are still defined at the bare `/api/<endpoint>` path. The
 * middleware rewrites the URI BEFORE Laravel's route matcher runs, so
 * a request for `/api/v1/student/dashboard` is handled by exactly the
 * same controller method as `/api/student/dashboard`.
 *
 * Why this matters:
 *   The audit flagged "no API versioning" — meaning the next breaking
 *   change to a payload structure (e.g. renaming a JSON key) hard-breaks
 *   every installed Android/iOS app. Adding `/v1/` gives mobile a stable
 *   contract to pin against. When v2 ships, we can clone individual
 *   routes for the v2 prefix without disturbing v1 clients.
 *
 * Why path rewrite instead of route duplication:
 *   The api.php has 200+ routes. Wrapping the whole file in
 *   Route::prefix('v1') would either (a) duplicate every route — fragile
 *   maintenance — or (b) require restructuring api.php into a loadable
 *   sub-file with no side effects. This middleware achieves the same
 *   effect with zero route duplication and zero changes to api.php.
 *
 * Trade-off:
 *   route() helper still returns `/api/...` URLs (not `/api/v1/...`).
 *   That's intentional: the canonical URL stays bare; v1 is an alias
 *   for clients that need a version handle.
 */
class ApiVersionAlias
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->getPathInfo();

        // Match BOTH `/api/v1` (exact) and `/api/v1/...` (with subpath).
        if ($path === '/api/v1' || str_starts_with($path, '/api/v1/')) {
            $stripped = $path === '/api/v1' ? '/api' : '/api' . substr($path, 7);

            // Rewrite both PATH_INFO (Laravel router uses this) AND
            // REQUEST_URI (preserves query string + fragments). The
            // SERVER copy is the source of truth for re-fetches.
            $request->server->set('PATH_INFO', $stripped);
            $qs = $request->server->get('QUERY_STRING');
            $newUri = $stripped . ($qs ? '?' . $qs : '');
            $request->server->set('REQUEST_URI', $newUri);

            // Symfony Request caches the parsed pathinfo internally — clear
            // it so subsequent calls pick up the rewrite.
            $request->initialize(
                $request->query->all(),
                $request->request->all(),
                $request->attributes->all(),
                $request->cookies->all(),
                $request->files->all(),
                $request->server->all(),
                $request->getContent()
            );
        }

        return $next($request);
    }
}
