<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

/**
 * Regression scanner for open-redirect risk.
 *
 * 2026-05-06 audit triaged every redirect site in app/ + Modules/.
 * Verdict: zero open-redirect vulnerabilities. Every variable-targeted
 * redirect is one of:
 *
 *   - redirect($url) where $url is a server-built constant (Zoom OAuth URL)
 *   - redirect()->away($url) where $url comes from a gateway API response
 *     to a known HTTPS endpoint (Stripe, PayPal, bKash)
 *   - redirect()->intended() reading the Laravel-set `url.intended`
 *     session key (which only ever contains request URLs from auth
 *     middleware, never user input)
 *
 * This test is the forcing function: scans for the dangerous pattern of
 * a user-controlled URL flowing into a redirect, and fails CI if any new
 * one appears. The fix in every case is to either:
 *   (a) restrict the redirect to a known safe set (route('...') or a
 *       hash of allowed external URLs)
 *   (b) validate the URL host against an allowlist before redirecting
 */
class OpenRedirectScanTest extends TestCase
{
    /**
     * Patterns considered dangerous. The check is substring-based, so the
     * patterns are quite literal — a more permissive match here means
     * more false-positives at scan time.
     */
    private const DANGEROUS_PATTERNS = [
        'redirect($request->',           // redirect($request->input('next'))
        'redirect(request()->',          // redirect(request()->input('next'))
        '->away($request->',
        '->away(request()->',
        '->to($request->',
        '->to(request()->',
        // Writing user input to url.intended would re-route ->intended() to it
        "session(['url.intended' => \$request",
        "session()->put('url.intended', \$request",
    ];

    public function test_no_user_input_redirects_under_app(): void
    {
        $this->assertNoDangerousPatterns(base_path('app'));
    }

    public function test_no_user_input_redirects_under_modules(): void
    {
        $this->assertNoDangerousPatterns(base_path('Modules'));
    }

    private function assertNoDangerousPatterns(string $root): void
    {
        if (!is_dir($root)) {
            return;
        }

        $hits = [];
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iter as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') continue;
            $abs = $file->getPathname();
            if (str_contains($abs, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) continue;
            if (str_contains($abs, DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR)) continue;
            // AuditSmoke embeds these patterns as literal pattern strings
            // (its checkOpenRedirect block mirrors this scanner). Skip
            // it to avoid self-matching the pattern definitions.
            if (basename($abs) === 'AuditSmoke.php') continue;

            $lines = file($abs);
            if ($lines === false) continue;

            foreach ($lines as $i => $line) {
                if (preg_match('/^\s*(?:\/\/|\*|#)/', $line)) continue;

                foreach (self::DANGEROUS_PATTERNS as $pat) {
                    if (str_contains($line, $pat)) {
                        $rel = str_replace([base_path() . DIRECTORY_SEPARATOR, '\\'], ['', '/'], $abs);
                        $hits[] = sprintf('%s:%d  %s', $rel, $i + 1, trim($line));
                    }
                }
            }
        }

        $this->assertEmpty(
            $hits,
            "Found user-controlled redirect targets — these are open-redirect vectors.\n" .
            "Each must be either:\n" .
            "  (a) restricted to route('...') or a known allowlist\n" .
            "  (b) validated with an explicit host-allowlist before redirecting\n\n" .
            "Sites:\n" . implode("\n", $hits)
        );
    }
}
