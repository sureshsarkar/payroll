<?php

namespace Tests\Feature\CoachSite;

use App\Services\Site\SectionRegistry;
use Tests\TestCase;

/**
 * About section (about_v1) image/content layout swap (2026-07-16).
 * Default stays image-left (backward compatible); image-right is an opt-in,
 * per-section (tenant) choice applied via a CSS-only modifier. No data change.
 */
class AboutLayoutSwapTest extends TestCase
{
    private function render(?string $pos): string
    {
        $content = ['image' => '/x.jpg', 'name' => 'Coach', 'bio' => 'Hello'];
        if ($pos !== null) {
            $content['layout_position'] = $pos;
        }
        return view('frontend.coach-site.sections.about_v1', ['content' => $content, 'appearanceStyle' => ''])->render();
    }

    private function sectionClass(string $html): string
    {
        preg_match('/<section class="([^"]*)"/', $html, $m);
        return trim($m[1] ?? '');
    }

    public function test_registry_exposes_layout_position_defaulting_to_image_left(): void
    {
        $schema = SectionRegistry::get('about_v1')['schema'] ?? [];
        $this->assertArrayHasKey('layout_position', $schema);
        $this->assertSame(['image_left', 'image_right'], $schema['layout_position']['options']);
        $this->assertSame('image_left', SectionRegistry::defaults('about_v1')['layout_position']);
    }

    public function test_default_and_image_left_keep_the_original_layout(): void
    {
        // No key at all (legacy content) → original layout, no modifier.
        $this->assertStringNotContainsString('cs-about--img-right', $this->sectionClass($this->render(null)));
        // Explicit image_left → same.
        $this->assertStringNotContainsString('cs-about--img-right', $this->sectionClass($this->render('image_left')));
    }

    public function test_image_right_adds_the_flip_modifier(): void
    {
        $this->assertStringContainsString('cs-about--img-right', $this->sectionClass($this->render('image_right')));
        // The flip rule keeps children LTR so text is unaffected (responsive-safe).
        $this->assertStringContainsString('direction:ltr', $this->render('image_right'));
    }
}
