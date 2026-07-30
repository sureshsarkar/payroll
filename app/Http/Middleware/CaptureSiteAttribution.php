<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Capture coach-site attribution into the session so the next Order
 * created in this session inherits source / source_page_id / source_section_id.
 *
 * Triggered by `?ref=coach_site&utm_source=coach_site&utm_medium=<page-slug>&utm_campaign=<section-id>`
 * which the services_grid_v1 section appends to its course CTA URLs.
 *
 * Stored under session key `site_attribution` with a 24-hour shelf life
 * (validated when consumed by the Order creating event).
 */
class CaptureSiteAttribution
{
    public function handle(Request $request, Closure $next)
    {
        $ref = (string) $request->query('ref', '');
        if ($ref === 'coach_site') {
            $request->session()->put('site_attribution', [
                'source'            => 'coach_site',
                'source_page_slug'  => (string) $request->query('utm_medium', ''),
                'source_section_id' => (int) $request->query('utm_campaign', 0) ?: null,
                'captured_at'       => now()->timestamp,
            ]);
        }
        // Capture an explicit "come back here after payment" URL when the
        // student clicks the drawer's Proceed-to-secure-checkout link from
        // the coach site. The URL is validated to be on this app's origin
        // so a malicious actor can't redirect students off-site.
        $returnTo = $request->query('return_to');
        if (is_string($returnTo) && $returnTo !== '') {
            $appHost = parse_url(config('app.url'), PHP_URL_HOST);
            $linkHost = parse_url($returnTo, PHP_URL_HOST);
            if ($appHost && $linkHost && strcasecmp($appHost, $linkHost) === 0) {
                $request->session()->put('coach_site_return_to', $returnTo);
            }
        }
        return $next($request);
    }
}
