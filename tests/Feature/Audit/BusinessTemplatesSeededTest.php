<?php

namespace Tests\Feature\Audit;

use Illuminate\Support\Facades\DB;
use Modules\PageTemplateBuilder\app\Models\PageTemplateBuilder;
use Modules\PageTemplateBuilder\app\Models\PageTemplateCategory;
use Tests\TestCase;

/**
 * Regression tripwire — Phase 1 of the multi-template business website system
 * (2026-05-12). Guarantees the seven business-vertical templates remain
 * present and selectable so the /instructor/web-page modal can offer them.
 *
 * A future migration that drops these rows or flips them to status=0 would
 * silently strip the catalog without breaking any other test — this fixture
 * catches that.
 */
class BusinessTemplatesSeededTest extends TestCase
{
    /**
     * The seven categories the system promises to support. If any of these is
     * missing or inactive, the template browse UI will quietly skip it.
     */
    private const REQUIRED_CATEGORIES = [
        'Academy', 'Institute', 'School', 'Gym', 'Training Center', 'Coaching', 'Yoga',
    ];

    /**
     * Each category must have at least this template name available so the
     * modal has something to render under that tab. The names match what
     * SeedBusinessTemplates inserts.
     */
    private const REQUIRED_TEMPLATES = [
        'Academy'         => 'Academy Pro',
        'Institute'       => 'Institute Pro',
        'School'          => 'BrightSchool',
        'Gym'             => 'IronForge Gym',
        'Training Center' => 'TrainHub',
        'Coaching'        => 'Coach Pro',
        'Yoga'            => 'Prana Yoga',
    ];

    public function test_seven_business_categories_exist_and_are_active(): void
    {
        foreach (self::REQUIRED_CATEGORIES as $name) {
            $cat = PageTemplateCategory::where('name', $name)->first();
            $this->assertNotNull(
                $cat,
                "Category '{$name}' missing — run `php artisan business-templates:seed`"
            );
            $this->assertSame(
                1, (int) $cat->status,
                "Category '{$name}' is inactive (status={$cat->status}) — coaches won't see it in the picker"
            );
        }
    }

    public function test_each_business_category_has_its_starter_template_active(): void
    {
        foreach (self::REQUIRED_TEMPLATES as $catName => $tplName) {
            $cat = PageTemplateCategory::where('name', $catName)->first();
            $this->assertNotNull($cat, "Category '{$catName}' missing (precondition)");

            $tpl = PageTemplateBuilder::where('template_name', $tplName)->first();
            $this->assertNotNull(
                $tpl,
                "Template '{$tplName}' missing — Phase 1 seeder didn't run or row was deleted"
            );
            $this->assertSame(
                (int) $cat->id, (int) $tpl->category,
                "Template '{$tplName}' is filed under wrong category (expected '{$catName}')"
            );
            $this->assertSame(
                1, (int) $tpl->status,
                "Template '{$tplName}' is inactive — won't appear in the picker"
            );
        }
    }

    public function test_each_template_blade_file_exists_on_disk(): void
    {
        foreach (array_values(self::REQUIRED_TEMPLATES) as $tplName) {
            $tpl = PageTemplateBuilder::where('template_name', $tplName)->first();
            $this->assertNotNull($tpl, "Template '{$tplName}' missing (precondition)");

            $path = public_path($tpl->file);
            $this->assertFileExists(
                $path,
                "Template file for '{$tplName}' is referenced in DB but missing on disk at {$path}"
            );
            $this->assertFileExists(
                public_path($tpl->image),
                "Thumbnail for '{$tplName}' is referenced in DB but missing on disk"
            );
        }
    }

    public function test_legacy_learning_management_category_was_renamed(): void
    {
        // The legacy "Learning Management" bucket was too vague for the catalog;
        // the seeder renames it to "Coaching" so we don't end up with both names
        // pointing at the same templates. If we ever see it back, someone has
        // re-introduced the old taxonomy.
        $this->assertSame(
            0,
            PageTemplateCategory::where('name', 'Learning Management')->count(),
            "Legacy 'Learning Management' category is back — rename it to 'Coaching' or update this test"
        );
    }
}
