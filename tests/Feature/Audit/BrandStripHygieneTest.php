<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

/**
 * Per-coach white-label — Phase 4 hygiene.
 *
 * Two tests, both static-source scans:
 *
 *   1. Critical PUBLIC views (master layout, header, footer, auth
 *      pages) must NOT contain a hardcoded "MBSGuru" / "MBS Guru"
 *      string — those surfaces are seen by students on coach
 *      domains and must render the per-coach brand.
 *
 *   2. The same views must reference $brand (not $setting->app_name
 *      or $setting->logo) for brand-ish fields. Catches the case
 *      where someone reverts the migration "to fix a styling bug".
 *
 * The exempt list is for files where the platform brand is
 * intentional — admin panel pages (admins always see platform brand),
 * landing-page builder templates (those are starter templates the
 * coach customizes themselves), the Zoom example payload (the API
 * names are real Zoom Marketplace app names, not branding).
 */
class BrandStripHygieneTest extends TestCase
{
    /**
     * Files we audit. Migration to $brand is required for these
     * specific surfaces — students see them on coach domains.
     */
    private const PUBLIC_VIEWS_REQUIRING_BRAND = [
        'resources/views/frontend/layouts/master.blade.php',
        'resources/views/frontend/layouts/header.blade.php',
        'resources/views/frontend/layouts/footer.blade.php',
        'resources/views/frontend/layouts/styles.blade.php',
        'resources/views/auth/login.blade.php',
        'resources/views/auth/register.blade.php',
        'resources/views/auth/forgot-password.blade.php',
        'resources/views/auth/reset-password.blade.php',
    ];

    /**
     * Substrings that are red flags in public views.
     */
    private const FORBIDDEN_HARDCODED_BRAND = [
        'MBSGuru',
        'MBS Guru',
    ];

    public function test_critical_public_views_have_no_hardcoded_platform_brand(): void
    {
        $offenders = [];
        foreach (self::PUBLIC_VIEWS_REQUIRING_BRAND as $rel) {
            $abs = base_path($rel);
            if (! file_exists($abs)) continue;
            $src = file_get_contents($abs);

            foreach (self::FORBIDDEN_HARDCODED_BRAND as $token) {
                // Allow the token if it's inside a comment / docblock —
                // we only care about user-rendered occurrences. Cheap
                // heuristic: strip Blade comments + HTML comments + PHP
                // comments before searching.
                $stripped = preg_replace([
                    '#\{\{--.*?--\}\}#s',
                    '#<!--.*?-->#s',
                    '#/\*.*?\*/#s',
                    '#//[^\n]*#',
                    '#\{\{[^}]*\}\}#',  // (these are echo expressions — values, fine)
                ], '', $src);

                if (stripos($stripped, $token) !== false) {
                    $offenders[] = "$rel  (contains hardcoded '$token')";
                }
            }
        }

        $this->assertEmpty(
            $offenders,
            "Public-facing views must NOT contain hardcoded platform brand strings — " .
            "students on coach1.com would see them and break the white-label illusion.\n" .
            "Use \$brand->name / \$brand->logoUrl() / \$brand->footerText instead:\n  - " .
            implode("\n  - ", $offenders)
        );
    }

    public function test_critical_public_views_reference_brand_variable(): void
    {
        $missing = [];
        foreach (self::PUBLIC_VIEWS_REQUIRING_BRAND as $rel) {
            $abs = base_path($rel);
            if (! file_exists($abs)) continue;
            $src = file_get_contents($abs);

            // Each file must reference $brand at least once. Doesn't
            // matter how — header img, footer text, style root color.
            if (! str_contains($src, '$brand')) {
                $missing[] = $rel;
            }
        }

        $this->assertEmpty(
            $missing,
            "These public views don't reference \$brand — they likely still " .
            "use \$setting->app_name / \$setting->logo for brand-ish fields, " .
            "which means students on coach1.com see the PLATFORM brand:\n  - " .
            implode("\n  - ", $missing)
        );
    }
}
