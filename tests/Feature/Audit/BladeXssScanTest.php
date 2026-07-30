<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

/**
 * Regression scanner for stored-XSS via unsanitized `{!! $var !!}` in Blade.
 *
 * 2026-05-06 audit triaged every `{!! ... !!}` site under resources/views
 * + Modules/*\/resources/views.
 *
 *   125 total sites across themes, sections, dashboards, emails.
 *   ~100 sites correctly wrap with `clean()` (mews/purifier — HTMLPurifier-
 *        backed sanitization); these are SAFE.
 *   The audit fixed 8 admin-content sites that had been rendering
 *   without `clean()`:
 *     - frontend/home/main/sections/about-area, service-area, why-choose
 *     - frontend/pages/about-us (x3), privacy-policy, terms-conditions
 *     - Modules/BasicPayment/.../bank.blade.php (nl2br+clean+e wrap)
 *     - resources/views/emails/notification.blade.php ($bodyHtml)
 *
 *   The remaining unwrapped sites are intentional and listed in
 *   ALLOWLIST below — each one carries a per-line audit signoff.
 */
class BladeXssScanTest extends TestCase
{
    /**
     * Substrings inside the `{!! ... !!}` body that mark the site as
     * known-safe — sanitizer call, server-built URL, etc.
     */
    private const SAFE_WRAPPERS = [
        'clean(',          // mews/purifier — HTMLPurifier
        'strip_tags(',     // raw text only
        'asset(',          // server-built asset URL
        'route(',          // server-built route URL
        'url(',            // server-built absolute URL
        'config(',         // config value (server-controlled)
        'json_encode(',    // safely encoded for JS context
        'sprintf(',        // composed from format string
        '__(',             // localization key
        'csrf_field(',     // server-emitted hidden input
        'method_field(',   // ditto
        'csrfToken(',
        // Coach Marketing Website (2026-05-25) — these are auto-safe:
        'Str::markdown(',  // Laravel's Str::markdown ships GitHub-flavored renderer with HTML-safe defaults
        'markdown(',       // global helper alias of Str::markdown
        'nl2br(e(',        // nl2br over an e()-escaped string — escaped first, br injected last
    ];

    /**
     * Per-site allowlist for legitimately-unwrapped sites. Each entry MUST
     * have a brief justification in the comment. Adding to this list
     * requires per-site review.
     */
    private const ALLOWLIST = [
        // server-generated SVG from BaconQrCode (TwoFactorAuthService).
        // admin line shifted from 67 → 129 in the 2026-05-12 M10 inline-
        // styles extraction (rewrite moved markup down by ~60 lines).
        'resources/views/admin/two-factor/setup.blade.php:129',
        'resources/views/frontend/two-factor/setup.blade.php:61',
        // literal HTML entities in ternary, no variable content
        'resources/views/admin/partials/stat-trend.blade.php:9',
        // server-side file_get_contents of a server-controlled template
        'resources/views/frontend/instructor-dashboard/landing-page/create.blade.php:74',
        // intentional design — instructor builds their landing page HTML/CSS.
        // Mitigation: the route serving these pages should carry CSP. TODO.
        'resources/views/frontend/instructor-dashboard/landing-page/publish.blade.php:9',
        'resources/views/frontend/instructor-dashboard/landing-page/publish.blade.php:96',
        // admin custom-code injection feature (Google Analytics, etc.)
        'resources/views/frontend/layouts/header-scripts.blade.php:70',
        // Lines shifted +7 on 2026-05-21 when P4 brand-strip added
        // an @if($brand->faviconUrl()) wrap. Same safe customCode()
        // sites — just at new positions.
        'resources/views/frontend/layouts/master.blade.php:29',
        'resources/views/frontend/layouts/master.blade.php:138',
        // Payment-gateway $paymentUrl rendered in a JS string. The variable
        // is built server-side via route() (see each controller); no user
        // input flows in. Switching to {{ json_encode($paymentUrl) }} would
        // be safer defense-in-depth but doesn't change the practical risk.
        'Modules/BasicPayment/resources/views/gateway-actions/flutterwave.blade.php:46',
        'Modules/BasicPayment/resources/views/gateway-actions/paystack.blade.php:45',
        'Modules/MercadoPagoPG/resources/views/payment-button.blade.php:29',
        'Modules/MercadoPagoPG/resources/views/payment-button.blade.php:31',
        // Audit 2026-05-19 phase 3 — operator-strip + instructor-pulse
        // render trend deltas via a local closure. The closure builds the
        // HTML from numeric values only (Cache-warmed pct deltas computed
        // server-side from SUM() queries), with all attribute values
        // hard-coded. No user input flows in. Using {{ }} would escape the
        // <i> / <span> tags we intentionally emit.
        'resources/views/admin/partials/operator-strip.blade.php:66',
        'resources/views/admin/partials/operator-strip.blade.php:75',
        'resources/views/admin/partials/operator-strip.blade.php:84',
        // Shifted again on 2026-05-22 — section reorder moved
        // "My Content" to bottom and KPI icon chips were added to
        // the LIFETIME PERFORMANCE tiles. Same safe server-built
        // delta strings, new line numbers.
        // Lines shifted +17 on 2026-05-26 when the dashboard added the
        // hasActiveNewMembership check (so coaches with a Lifetime / Trial
        // UserMembership no longer see the upsell modal).
        'resources/views/frontend/instructor-dashboard/index.blade.php:591',
        'resources/views/frontend/instructor-dashboard/index.blade.php:596',

        // Coach Marketing Website (2026-05-25) — server-composed body from
        // SectionRenderer. Line shifted 95 → 169 in evening's customization
        // pass (analytics/favicon/sticky-CTA/WA/custom-CSS sections added
        // above the body output).
        // Line shifts as master layout grows. 2026-06-01 (audit [12]) added
        // the $brandHomeUrl @php block above the top-nav brand link, pushing
        // bodyHtml 278→294 and custom_body_scripts 459→475. The {!! !!} sites
        // themselves are unchanged (server-built $bodyHtml + the coach's own
        // custom_css/head/body scripts).
        'resources/views/frontend/coach-site/layouts/master.blade.php:294',
        'resources/views/frontend/coach-site/layouts/master.blade.php:138',
        'resources/views/frontend/coach-site/layouts/master.blade.php:143',
        'resources/views/frontend/coach-site/layouts/master.blade.php:475',
        // html_passthrough_v1 — legacy GrapesJS migration escape hatch.
        'resources/views/frontend/coach-site/sections/html_passthrough_v1.blade.php:5',
        'resources/views/frontend/coach-site/sections/html_passthrough_v1.blade.php:8',
    ];

    public function test_no_unwrapped_blade_unsafe_print(): void
    {
        $hits = [];
        foreach ([base_path('resources/views'), base_path('Modules')] as $root) {
            if (!is_dir($root)) continue;
            $iter = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iter as $file) {
                if (!$file->isFile()) continue;
                $abs = $file->getPathname();
                if (str_contains($abs, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) continue;
                if (!str_ends_with($abs, '.blade.php')) continue;

                $body = (string) file_get_contents($abs);
                if (!preg_match_all('/\{!!\s*(.*?)\s*!!\}/s', $body, $m, PREG_OFFSET_CAPTURE)) continue;

                foreach ($m[1] as [$expr, $offset]) {
                    $line = substr_count($body, "\n", 0, $offset) + 1;
                    $rel = str_replace([base_path() . DIRECTORY_SEPARATOR, '\\'], ['', '/'], $abs);
                    $key = "$rel:$line";

                    if (in_array($key, self::ALLOWLIST, true)) continue;
                    if ($this->hasSafeWrapper($expr)) continue;

                    $hits[] = sprintf('%s  %s', $key, mb_substr($expr, 0, 100));
                }
            }
        }

        $this->assertEmpty(
            $hits,
            "Unsanitized {!! \$var !!} blade sites detected. Each one is a stored-XSS vector\n" .
            "if the variable comes from user input. Choose one of:\n" .
            "  (a) Wrap with clean() (mews/purifier) — most cases\n" .
            "  (b) Use {{ \$var }} for plain-text rendering (Blade auto-escapes)\n" .
            "  (c) If the site is genuinely safe (server-built URL, generated SVG, etc.),\n" .
            "      add it to BladeXssScanTest::ALLOWLIST with a per-line justification.\n\n" .
            "Sites:\n  " . implode("\n  ", $hits)
        );
    }

    private function hasSafeWrapper(string $expr): bool
    {
        foreach (self::SAFE_WRAPPERS as $needle) {
            if (str_contains($expr, $needle)) return true;
        }
        // Pure literal ternary like '&uarr;' : '&darr;' (no variable interpolation).
        if (preg_match("/^['\"][^'\"\$]*['\"]\s*[\?]\s*['\"][^'\"\$]*['\"]\s*:\s*['\"][^'\"\$]*['\"]/", $expr)) {
            return true;
        }
        return false;
    }
}
