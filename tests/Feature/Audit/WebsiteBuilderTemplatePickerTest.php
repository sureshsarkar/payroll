<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

/**
 * Regression tripwire — Phase 2 of the multi-template business website system
 * (2026-05-12). Static source audit of the /instructor/web-page template
 * picker modal — catches three specific regressions that have happened before
 * or are easy to re-introduce:
 *
 *   1. The malformed `<h3>…</button>` markup in the nav-tabs (the original
 *      template-picker had this bug — the opening tag was h3 but it closed
 *      with </button>, so browsers silently dropped the heading).
 *   2. The hardcoded `.mbsguru.com` domain suffix in the subdomain input —
 *      breaks staging/local environments where coach_domain is different.
 *   3. The duplicate `function selectTemplate(el, id)` definition (it was
 *      declared twice in two <script> blocks — second one shadowed the first).
 *
 * Also asserts the new picker primitives (search box, chips container,
 * preview-before-select sub-modal, "Use This Template" button) are wired in.
 */
class WebsiteBuilderTemplatePickerTest extends TestCase
{
    private function viewSource(): string
    {
        return (string) file_get_contents(
            resource_path('views/frontend/instructor-dashboard/landing-page/index.blade.php')
        );
    }

    public function test_malformed_h3_button_nav_tab_is_gone(): void
    {
        $src = $this->viewSource();
        // The legacy markup opened with <h3 class="btn"...> then closed with
        // </button>. Browsers were lenient about it but the heading was invalid
        // HTML and accessibility scanners flagged it.
        $this->assertDoesNotMatchRegularExpression(
            '/<h3[^>]*data-bs-toggle="tab"[^>]*>.*?<\/button>/s',
            $src,
            'Malformed <h3 …>…</button> nav-tab is back — opening tag must match the closing tag'
        );
    }

    public function test_subdomain_suffix_uses_config_not_hardcoded_domain(): void
    {
        $src = $this->viewSource();
        // The picker used to hardcode `.mbsguru.com` next to the subdomain input.
        // That broke when developers ran the app on .localhost or a staging
        // domain — coaches would create subdomains under the wrong suffix.
        $this->assertDoesNotMatchRegularExpression(
            '/rounded-2"\s*>\s*\.mbsguru\.com\s*<\/span>/',
            $src,
            'Subdomain suffix is hardcoded to .mbsguru.com — must use config(\'app.coach_domain\')'
        );
        $this->assertStringContainsString(
            "config('app.coach_domain'",
            $src,
            'Subdomain suffix must read from config(\'app.coach_domain\') so non-prod environments work'
        );
    }

    public function test_selecttemplate_is_defined_exactly_once(): void
    {
        $src = $this->viewSource();
        // Previously declared twice — the second declaration silently shadowed
        // the first. Easy to re-introduce when copy-pasting JS blocks.
        $count = preg_match_all('/function\s+selectTemplate\s*\(/', $src);
        $this->assertSame(
            1, $count,
            "Expected exactly one selectTemplate() definition, found {$count} — duplicate definitions shadow each other"
        );
    }

    public function test_new_picker_primitives_are_present(): void
    {
        $src = $this->viewSource();

        // Search input
        $this->assertStringContainsString(
            'id="tplSearch"', $src,
            'Template search input (#tplSearch) is missing — Phase 2 picker UX broken'
        );
        // Filter chips container
        $this->assertStringContainsString(
            'id="templateChips"', $src,
            'Template filter chips container (#templateChips) is missing'
        );
        // All-templates chip exists
        $this->assertMatchesRegularExpression(
            '/data-cat="all"[^>]*>\s*All Templates/',
            $src,
            'The "All Templates" chip is missing — category-specific filtering will trap users in one bucket'
        );
        // Preview-before-select sub-modal
        $this->assertStringContainsString(
            'id="templatePreviewModal"', $src,
            'Preview sub-modal (#templatePreviewModal) is missing — preview-before-select is the headline Phase 2 feature'
        );
        // Use-this-template CTA
        $this->assertStringContainsString(
            'id="useTemplateBtn"', $src,
            'The "Use This Template" button in the preview sub-modal is missing — preview cannot route to selection'
        );
    }

    public function test_picker_filters_out_empty_categories(): void
    {
        // Categories with zero active templates should be hidden from the chip
        // bar — otherwise coaches click a chip and stare at an empty grid
        // wondering whether the page broke.
        $src = $this->viewSource();
        $this->assertStringContainsString(
            '$categories->filter',
            $src,
            'The chip bar must filter out categories with no active templates — otherwise dead chips appear'
        );
        $this->assertMatchesRegularExpression(
            '/templates->where\(\s*[\'"]status[\'"]\s*,\s*1\s*\)->count\(\)\s*>\s*0/',
            $src,
            'The "has templates" filter must check status=1 — inactive templates would otherwise count'
        );
    }
}
