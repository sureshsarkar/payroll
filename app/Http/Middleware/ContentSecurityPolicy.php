<?php

namespace App\Http\Middleware;

use App\Models\CoachSiteSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * P1-4 (2026-05-29) — Content Security Policy.
 *
 * Why:
 *   coach-site/layouts/master.blade.php renders the coach's per-tenant
 *   `custom_css`, `custom_head_scripts`, and `custom_body_scripts` raw
 *   via {!! !!}. The previous mitigation (CoachSiteController validator
 *   blocking expression()/behavior:/javascript:/vbscript: + a 50KB cap)
 *   handles the dumb-case payloads. CSP closes the structural gap: even
 *   if a payload sneaks through (or one coach's settings get tampered
 *   with via the next IDOR we haven't found yet), the browser refuses
 *   to load scripts from unknown origins and refuses to run inline
 *   scripts without a matching nonce.
 *
 * Defaults to Report-Only mode (set CSP_ENFORCE=true in .env to switch
 * to enforcing). Report-Only fires `Content-Security-Policy-Report-Only`
 * — browsers honor the directives by emitting violation reports but do
 * not actually block resources. This lets us deploy CSP without taking
 * down legitimate integrations, audit the violation stream, fix gaps,
 * then flip to enforcing.
 *
 * Nonce:
 *   A 128-bit base64 nonce is generated per request and stamped on the
 *   request attributes. Views can read it via the csp_nonce() helper
 *   to render `<script nonce="...">` tags that survive a tightened
 *   policy (script-src 'self' 'nonce-...' ...).
 *
 * Coach-aware allowlists:
 *   If the coach has GTM / GA4 / Meta Pixel / Hotjar configured in
 *   CoachSiteSettings, the corresponding script-src / connect-src /
 *   frame-src origins are added to the policy. Coaches without those
 *   configured get a tighter policy — no third-party origins.
 *
 * No-op when:
 *   - Response isn't HTML (skip JSON / file downloads / images).
 *   - Header already set by an upstream layer (don't fight Nginx CSP).
 */
class ContentSecurityPolicy
{
    public function handle(Request $request, Closure $next): Response
    {
        // 128-bit nonce. URL-safe base64 so it survives any quoting.
        $nonce = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
        $request->attributes->set('csp_nonce', $nonce);

        /** @var Response $response */
        $response = $next($request);

        // Only stamp CSP on HTML responses — JSON APIs / file downloads /
        // image responses don't need it and CSP on `application/json`
        // confuses some intermediaries.
        $ct = (string) $response->headers->get('Content-Type', '');
        if ($ct !== '' && !str_contains($ct, 'text/html')) {
            return $response;
        }

        // Respect an upstream-set CSP (e.g. Nginx in production may
        // already be doing this).
        $headerName = $this->headerName();
        if ($response->headers->has($headerName)) {
            return $response;
        }

        $coachId = $request->attributes->get('resolved_coach_id');
        $settings = $coachId ? $this->loadSettings((int) $coachId) : null;

        $policy = $this->buildPolicy($nonce, $settings);
        $response->headers->set($headerName, $policy);

        return $response;
    }

    private function headerName(): string
    {
        return $this->enforcing()
            ? 'Content-Security-Policy'
            : 'Content-Security-Policy-Report-Only';
    }

    private function enforcing(): bool
    {
        return filter_var(env('CSP_ENFORCE', false), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Load CoachSiteSettings for the resolved coach. Failure here MUST
     * NOT block the request — we still want to ship a base policy.
     */
    private function loadSettings(int $coachId): ?CoachSiteSettings
    {
        try {
            return CoachSiteSettings::where('coach_id', $coachId)->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Build the CSP directive string. The base policy is intentionally
     * permissive on style-src and img-src because the current Blade
     * templates have widespread `style="..."` attributes and pull
     * images from many origins (S3, gravatar, CDN partners). Tightening
     * those is a separate effort — out of scope for this middleware.
     */
    private function buildPolicy(string $nonce, ?CoachSiteSettings $s): string
    {
        // Always-allowed origins for script-src (CDNs we ship from).
        // 'strict-dynamic' tells modern browsers to ignore 'self' and
        // host-source allowlists for scripts and rely solely on the
        // nonce + scripts dynamically inserted by nonce'd scripts. We
        // hold it back for the enforcing pass — for Report-Only we
        // explicitly allowlist hosts so violation reports stay readable.
        $scriptSrc = [
            "'self'",
            "'nonce-$nonce'",
            // Legacy bundlers + a number of pre-2026 plugins emit
            // inline scripts without nonces. We can't break them in
            // one go, so allow inline as a stopgap. Modern browsers
            // ignore 'unsafe-inline' when a nonce is also present, so
            // tightening here later is purely about removing the
            // fallback for older browsers.
            "'unsafe-inline'",
            // Some legacy plugins (TinyMCE, certain analytics shims)
            // call eval()/Function(). Keep until we audit and remove.
            "'unsafe-eval'",
            // Razorpay Checkout loads its widget script from its own host
            // (trial-session popup + course checkout). connect/frame already
            // allow it; script-src is needed to load checkout.js itself.
            'https://checkout.razorpay.com',
        ];

        $styleSrc = [
            "'self'",
            "'unsafe-inline'",
            'https://fonts.googleapis.com',
            'https://cdn.jsdelivr.net',
        ];

        $imgSrc = [
            "'self'",
            'data:',
            'blob:',
            'https:',
        ];

        $fontSrc = [
            "'self'",
            'data:',
            'https://fonts.gstatic.com',
            'https://cdn.jsdelivr.net',
        ];

        $connectSrc = [
            "'self'",
            // Stripe / Razorpay / bKash / PayPal / MercadoPago all
            // open XHR/WebSocket back to their own domains during
            // checkout. Allow on all sites — the actual gateway
            // selection per coach is enforced elsewhere.
            'https://api.stripe.com',
            'https://m.stripe.network',
            'https://lumberjack.razorpay.com',
            'https://api.razorpay.com',
            'wss:',
        ];

        $frameSrc = [
            "'self'",
            // Video embeds.
            'https://www.youtube.com',
            'https://www.youtube-nocookie.com',
            'https://player.vimeo.com',
            'https://*.vimeocdn.com',
            // Payment gateway 3DS / OTP iframes.
            'https://js.stripe.com',
            'https://hooks.stripe.com',
            'https://api.razorpay.com',
            'https://checkout.razorpay.com',
        ];

        $mediaSrc = [
            "'self'",
            'blob:',
            'https:',
        ];

        // Coach-aware allowlists. Only added if the coach has the
        // integration configured.
        if ($s) {
            if (!empty($s->analytics_gtm_id) || !empty($s->analytics_ga4_id)) {
                $scriptSrc[] = 'https://www.googletagmanager.com';
                $scriptSrc[] = 'https://*.googletagmanager.com';
                $scriptSrc[] = 'https://www.google-analytics.com';
                $connectSrc[] = 'https://www.google-analytics.com';
                $connectSrc[] = 'https://*.google-analytics.com';
                $connectSrc[] = 'https://www.googletagmanager.com';
                $imgSrc[]    = 'https://www.google-analytics.com';
                $imgSrc[]    = 'https://www.googletagmanager.com';
                $frameSrc[]  = 'https://www.googletagmanager.com';
            }
            if (!empty($s->analytics_meta_pixel_id)) {
                $scriptSrc[]  = 'https://connect.facebook.net';
                $connectSrc[] = 'https://www.facebook.com';
                $connectSrc[] = 'https://connect.facebook.net';
                $imgSrc[]     = 'https://www.facebook.com';
                $imgSrc[]     = 'https://*.facebook.com';
            }
            if (!empty($s->analytics_hotjar_id ?? null)) {
                $scriptSrc[]  = 'https://static.hotjar.com';
                $scriptSrc[]  = 'https://script.hotjar.com';
                $connectSrc[] = 'https://*.hotjar.com';
                $connectSrc[] = 'wss://*.hotjar.com';
                $frameSrc[]   = 'https://vars.hotjar.com';
            }
        }

        $directives = [
            "default-src 'self'",
            'script-src ' . implode(' ', array_unique($scriptSrc)),
            'style-src '  . implode(' ', array_unique($styleSrc)),
            'img-src '    . implode(' ', array_unique($imgSrc)),
            'font-src '   . implode(' ', array_unique($fontSrc)),
            'connect-src '. implode(' ', array_unique($connectSrc)),
            'frame-src '  . implode(' ', array_unique($frameSrc)),
            'media-src '  . implode(' ', array_unique($mediaSrc)),
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            // Clickjacking defense — only same-origin pages may iframe
            // this app. Operators wanting to embed in a partner shell
            // should override via Nginx with an explicit allowlist.
            "frame-ancestors 'self'",
        ];

        $reportUri = env('CSP_REPORT_URI');
        if (!empty($reportUri)) {
            $directives[] = 'report-uri ' . $reportUri;
        }

        return implode('; ', $directives);
    }
}
