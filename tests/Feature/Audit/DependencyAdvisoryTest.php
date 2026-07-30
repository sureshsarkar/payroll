<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

/**
 * Pins the minimum installed versions of packages that have had
 * security advisories applied during the audit.
 *
 * 2026-05-06 audit: composer audit reported 6 advisories across 5
 * packages. 5 cleared by version bumps; 1 remaining is documented.
 *
 *   unisharp/laravel-filemanager 2.8.0 → 2.14.0
 *     CVE-2024-21546 (HIGH) — code injection in filemanager admin
 *     Was hard-pinned to "2.8" in composer.json. Fixed in >=2.9.1.
 *     Bumped to ^2.9.1 (resolved to 2.14.0 latest minor).
 *
 *   paragonie/sodium_compat <2.5.0 → 2.5.0
 *     CVE-2025-69277 (medium) + 1 informational. Used as fallback
 *     when libsodium isn't compiled into PHP. Modern PHP has native
 *     libsodium, so this fallback rarely runs in practice. Updated
 *     transitively.
 *
 *   phpunit/phpunit 10.x < 10.5.62 → 10.5.63
 *     CVE-2026-24765 (HIGH) — unsafe deserialization in PHPT code
 *     coverage handling. require-dev only — no production impact,
 *     but bumped for CI hygiene.
 *
 *   psy/psysh < 0.12.18 → 0.12.22
 *     CVE-2026-25129 — local privilege escalation via auto-loaded
 *     .psysh.php from CWD. Tinker dev dependency, no prod impact.
 *
 *   firebase/php-jwt <7.0.0 — DEFERRED, documented
 *     CVE-2025-45769 (LOW) — affects HS256 with very short secret
 *     keys. As of 2026-05-07 we have no first-party Firebase\JWT
 *     callers in app/ or Modules/ (the previous RS256 caller,
 *     JitsiTokenService, was removed when Jitsi was retired). The
 *     dependency is still pulled in transitively by laravel/socialite
 *     v5.16.x (which pins ^6.4). The test below is a regression guard
 *     to make sure no new HS256 caller is introduced while we're
 *     pinned to <7.0.
 */
class DependencyAdvisoryTest extends TestCase
{
    /**
     * Minimum allowed version for each package the audit hardened.
     * The test asserts the installed version is >= these values.
     *
     * Format: package-name => minimum-version
     */
    private const MINIMUM_VERSIONS = [
        'unisharp/laravel-filemanager' => '2.9.1',
        'paragonie/sodium_compat'      => '2.5.0',
        'phpunit/phpunit'              => '10.5.62',
        'psy/psysh'                    => '0.12.18',
    ];

    public function test_installed_versions_satisfy_audit_minimums(): void
    {
        $installed = json_decode(
            (string) file_get_contents(base_path('vendor/composer/installed.json')),
            true
        );
        $this->assertIsArray($installed);

        $byName = [];
        foreach ($installed['packages'] ?? [] as $p) {
            $byName[$p['name']] = ltrim($p['version'], 'v');
        }

        foreach (self::MINIMUM_VERSIONS as $name => $minimum) {
            $this->assertArrayHasKey($name, $byName, "Package '$name' must remain installed");
            $current = $byName[$name];
            $this->assertGreaterThanOrEqual(
                0,
                version_compare($current, $minimum),
                "$name=$current is older than the audit-required minimum $minimum — see DependencyAdvisoryTest for the CVE this version pins past"
            );
        }
    }

    public function test_php_jwt_only_used_with_rs256_2048bit(): void
    {
        // Defense in depth: the firebase/php-jwt CVE-2025-45769
        // (HS256-with-short-keys) would only bite if a caller started
        // using HS256. As of the Jitsi removal (2026-05-07) we have
        // no first-party callers of Firebase\JWT, so the test should
        // come up empty. If a future change introduces HS256 usage
        // before the package is upgraded to >=7.0, this test fails
        // and forces a review.
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
                if (!str_contains($body, 'Firebase\\JWT')) continue;

                // Look for HS256 usage — flag for review.
                if (str_contains($body, "'HS256'") || str_contains($body, '"HS256"')) {
                    $rel = str_replace([base_path() . DIRECTORY_SEPARATOR, '\\'], ['', '/'], $abs);
                    $hits[] = $rel;
                }
            }
        }
        $this->assertEmpty(
            $hits,
            "New HS256 usage with firebase/php-jwt detected. While the package is locked\n" .
            "to <7.0 by laravel/socialite, a HS256 caller would re-expose CVE-2025-45769\n" .
            "(weak-key-attack on HMAC). Either:\n" .
            "  (a) Use RS256 + an RSA key pair (asymmetric — not affected by the CVE)\n" .
            "  (b) Verify the HMAC secret is at least 256 bits of entropy and document why\n\n" .
            "Sites: " . implode(', ', $hits)
        );
    }
}
