<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hardening headers for the Zoom Web SDK launcher pages.
 *
 * Zoom Meeting SDK 3.x uses SharedArrayBuffer for its WASM audio/video
 * pipeline. Browsers only expose SharedArrayBuffer when the document is
 * `crossOriginIsolated`, which requires both:
 *   - Cross-Origin-Opener-Policy: same-origin
 *   - Cross-Origin-Embedder-Policy: require-corp (or credentialless)
 *
 * Without these, the SDK falls back to a degraded codec path. We use
 * `credentialless` for COEP because Zoom CDN responses don't carry
 * Cross-Origin-Resource-Policy, and credentialless lets us load them
 * without the explicit CORP opt-in.
 *
 * Defense-in-depth: tight CSP and standard hardening headers.
 */
class ZoomLiveClassHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Embedder-Policy', 'credentialless');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-site');

        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Permissions-Policy delegation.
        //
        // 2026-05-29 Doc-C-ZoomJoin: coaches reported a console error
        // "navigator.mediaDevices.getDisplayMedia is undefined" when
        // they clicked Share Screen during a live class. Root cause:
        // the previous `display-capture=(self)` policy refused to
        // delegate the API to Zoom's blob:/sub-frame contexts that
        // their Component View SDK spawns for the share preview.
        // When delegation fails the property is straight `undefined`
        // on `navigator.mediaDevices` — not even a permission prompt.
        //
        // The fix is to delegate camera/microphone/display-capture to
        // the same Zoom origins we already trust in CSP, plus the
        // implicit `self` for the launcher document itself, plus
        // `autoplay` so Zoom's audio elements can start without
        // requiring an extra user gesture per browser policy.
        $response->headers->set('Permissions-Policy', implode(', ', [
            'camera=(self "https://zoom.us" "https://source.zoom.us")',
            'microphone=(self "https://zoom.us" "https://source.zoom.us")',
            'display-capture=(self "https://zoom.us" "https://source.zoom.us")',
            'autoplay=(self "https://zoom.us" "https://source.zoom.us")',
            'fullscreen=(self "https://zoom.us" "https://source.zoom.us")',
        ]));

        // CSP allowing only Zoom's CDN + this origin. The Zoom SDK loads
        // additional scripts/wasm/workers from source.zoom.us, posts
        // telemetry/RTC connections to *.zoom.us / zoom.us, and pulls
        // sourcemaps from its own CloudFront distribution.
        //
        // Why both `https://zoom.us` and `https://*.zoom.us`: per CSP spec,
        // a wildcard host like *.zoom.us matches subdomains only — `bare`
        // zoom.us is NOT covered. The SDK calls https://zoom.us/api/v1/wc/info
        // directly, which would otherwise be blocked.
        $self    = $request->getSchemeAndHttpHost();
        $zoom    = 'https://zoom.us https://source.zoom.us https://*.zoom.us https://*.cloudfront.net';
        $zoomW   = 'wss://*.zoom.us https://zoom.us https://*.zoom.us https://*.cloudfront.net';

        // The SDK spawns Web Workers via URL.createObjectURL(new Blob(...))
        // and those workers call importScripts() on `blob:` URLs. CSP treats
        // importScripts as script-src (script-src-elem fallback), so blob:
        // must be allowed in script-src too — not just worker-src/child-src.
        $csp = implode('; ', [
            "default-src 'self' $zoom",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' blob: $zoom",
            "style-src 'self' 'unsafe-inline' $zoom",
            "img-src 'self' data: blob: $zoom $self",
            "media-src 'self' blob: $zoom",
            "connect-src 'self' $zoomW $self",
            "worker-src 'self' blob: $zoom",
            "child-src 'self' blob: $zoom",
            "font-src 'self' data: $zoom",
            "object-src 'none'",
            "base-uri 'self'",
            "frame-ancestors 'self'",
        ]);

        $response->headers->set('Content-Security-Policy', $csp);

        return $response;
    }
}
