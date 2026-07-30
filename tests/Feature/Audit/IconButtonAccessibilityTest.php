<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

/**
 * UI/UX audit P0-4 — every icon-only action button must carry both:
 *   - title=        for sighted-mouse-user hover tooltip
 *   - aria-label=   for screen-reader announcement
 *
 * This test scans the SOURCE BLADE files (not rendered HTML — that
 * would require auth fixtures + DB seeding) and asserts the pattern.
 *
 * It is intentionally narrow: it only checks the index/listing pages
 * that the audit explicitly identified. As surfaces get cleaned up,
 * add their files to the SURFACES array below.
 *
 * The check is conservative: it looks for the dangerous pattern
 *
 *     class="btn ... btn-sm ...">
 *         <i class="fa fa-X" aria-hidden></i></button>
 *
 * (i.e. an icon-only button without title/aria-label nearby) and
 * fails if any such pattern is found in a surface file.
 */
class IconButtonAccessibilityTest extends TestCase
{
    /** Surfaces explicitly checked by this test. Extend as cleanup proceeds. */
    private const SURFACES = [
        'Modules/Coupon/resources/views/index.blade.php',
        'Modules/Course/resources/views/course-language/index.blade.php',
        'Modules/Course/resources/views/course-level/index.blade.php',
        'Modules/Course/resources/views/course-review/index.blade.php',
        'Modules/Course/resources/views/course-sub-category/index.blade.php',
        'Modules/Course/resources/views/course-category/index.blade.php',
        'resources/views/admin/admin-list/admin.blade.php',
        'resources/views/admin/theme-studio/categories.blade.php',
    ];

    /**
     * Pattern that matches an icon-only btn-sm action button.
     * Captures the entire opening tag including attributes.
     */
    private const ICON_BTN_PATTERN = '/<(?:a|button)[^>]*class="[^"]*\bbtn-sm\b[^"]*"[^>]*>\s*<i[^>]*class="(?:fa|fas|fa-solid|fa-regular)[^"]*"[^>]*><\/i>\s*<\/(?:a|button)>/s';

    public function test_every_surface_listed_actually_exists(): void
    {
        foreach (self::SURFACES as $rel) {
            $abs = base_path($rel);
            $this->assertFileExists($abs, "Surface file missing: $rel");
        }
    }

    public function test_every_icon_only_btn_has_title_and_aria_label(): void
    {
        $violations = [];

        foreach (self::SURFACES as $rel) {
            $abs = base_path($rel);
            $contents = file_get_contents($abs);

            if (preg_match_all(self::ICON_BTN_PATTERN, $contents, $matches)) {
                foreach ($matches[0] as $tag) {
                    $hasTitle = str_contains($tag, 'title=');
                    $hasAriaLabel = str_contains($tag, 'aria-label=');
                    if (! $hasTitle || ! $hasAriaLabel) {
                        $missing = [];
                        if (! $hasTitle) $missing[] = 'title=';
                        if (! $hasAriaLabel) $missing[] = 'aria-label=';
                        $violations[] = "$rel — missing " . implode(' + ', $missing)
                            . "\n  in: " . substr($tag, 0, 120) . '...';
                    }
                }
            }
        }

        $this->assertEmpty(
            $violations,
            "Icon-only buttons missing accessibility labels:\n" . implode("\n", $violations)
        );
    }
}
