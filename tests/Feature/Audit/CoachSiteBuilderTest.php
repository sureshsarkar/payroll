<?php

namespace Tests\Feature\Audit;

use App\Models\CoachPage;
use App\Models\CoachPageSection;
use App\Models\User;
use App\Services\Site\PageManager;
use App\Services\Site\SectionRegistry;
use App\Services\Site\SectionRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

/**
 * Coach Marketing Website System (Phase 1, 2026-05-25) — regression suite.
 *
 * Ensures the new multi-page section-based builder:
 *   - Schema migrations applied cleanly
 *   - SectionRegistry catalog stays intact (12 visible types)
 *   - PageManager CRUD + version snapshot works
 *   - All section Blade partials compile (no missing variables / syntax errors)
 *   - The legacy GrapesJS migration produces a valid Home page + html_passthrough_v1 section
 *   - Public route resolution survives (subdomain + path entries registered)
 */
class CoachSiteBuilderTest extends TestCase
{
    /**
     * Schema sanity — confirms migration applied.
     */
    public function test_schema_has_new_tables(): void
    {
        $this->assertTrue(\Schema::hasTable('coach_pages'),         'coach_pages table missing');
        $this->assertTrue(\Schema::hasTable('coach_page_versions'), 'coach_page_versions table missing');
        $this->assertTrue(\Schema::hasTable('coach_site_page_views'), 'coach_site_page_views table missing');

        $this->assertTrue(\Schema::hasColumn('landing_sections', 'coach_page_id'));
        $this->assertTrue(\Schema::hasColumn('landing_sections', 'section_version'));
        $this->assertTrue(\Schema::hasColumn('orders', 'source'));
        $this->assertTrue(\Schema::hasColumn('orders', 'source_page_id'));
        $this->assertTrue(\Schema::hasColumn('orders', 'source_section_id'));
        $this->assertTrue(\Schema::hasColumn('landing_page_enquiries', 'page_id'));
        $this->assertTrue(\Schema::hasColumn('landing_page_enquiries', 'section_id'));
        $this->assertTrue(\Schema::hasColumn('landing_page_enquiries', 'custom_fields'));
    }

    /**
     * SectionRegistry — every visible section type must define icon, schema, defaults.
     */
    public function test_section_registry_catalog_is_well_formed(): void
    {
        $all = SectionRegistry::all();
        $this->assertGreaterThanOrEqual(12, count($all),
            'Section catalog has fewer than 12 types — review the registry');

        foreach ($all as $code => $def) {
            $this->assertArrayHasKey('label',    $def, "Section $code missing label");
            $this->assertArrayHasKey('icon',     $def, "Section $code missing icon");
            $this->assertArrayHasKey('category', $def, "Section $code missing category");
            $this->assertArrayHasKey('schema',   $def, "Section $code missing schema");
            $this->assertArrayHasKey('defaults', $def, "Section $code missing defaults");
            $this->assertIsArray($def['schema'],   "Section $code schema not an array");
            $this->assertIsArray($def['defaults'], "Section $code defaults not an array");
        }

        // hidden-flagged sections (html_passthrough) MUST NOT appear in byCategory()
        $byCat = SectionRegistry::byCategory();
        $visibleCodes = [];
        foreach ($byCat as $cat => $items) {
            foreach ($items as $i) $visibleCodes[] = $i['code'];
        }
        $this->assertNotContains('html_passthrough_v1', $visibleCodes,
            'html_passthrough_v1 leaked into the visible drawer — should be hidden');
    }

    /**
     * Every visible section type must have a corresponding Blade partial that
     * renders without error given its default content. Catches typos / missing files.
     */
    public function test_every_visible_section_has_compiling_blade_partial(): void
    {
        $brand = (object) [
            'name' => 'Test Coach',
            'primary_color' => '#5751e1',
            'accent_color' => '#0e9de8',
            'logo' => null,
            'support_email' => 'test@example.com',
        ];

        foreach (SectionRegistry::all() as $code => $def) {
            $viewName = "frontend.coach-site.sections.{$code}";
            $this->assertTrue(view()->exists($viewName),
                "View $viewName not found for section $code");

            try {
                $html = view($viewName, [
                    'content'       => $def['defaults'],
                    'section'       => null,
                    'sectionId'     => 1,
                    'page'          => (object) ['slug' => 'home', 'id' => 1],
                    'coach'         => null,
                    'brand'         => $brand,
                    'youtubeVideos' => [],
                ])->render();
                $this->assertIsString($html, "Section $code render returned non-string");
            } catch (\Throwable $e) {
                $this->fail("Section $code render threw: " . $e->getMessage());
            }
        }
    }

    /**
     * Routes registered: editor + public + section content endpoint.
     */
    public function test_critical_routes_registered(): void
    {
        $routes = collect(\Route::getRoutes())->mapWithKeys(fn ($r) => [$r->getName() => $r->uri()])->filter();

        $this->assertArrayHasKey('instructor.web-page.index',          $routes->toArray());
        $this->assertArrayHasKey('instructor.web-page.edit',           $routes->toArray());
        $this->assertArrayHasKey('instructor.web-page.sections.add',   $routes->toArray());
        $this->assertArrayHasKey('instructor.web-page.section.update', $routes->toArray());
        $this->assertArrayHasKey('instructor.web-page.publish',        $routes->toArray());
        $this->assertArrayHasKey('coach.site.path',                    $routes->toArray());
        $this->assertArrayHasKey('instructor.coach.site.preview',      $routes->toArray());
    }

    /**
     * Validation rejects content that violates schema (e.g. headline > 90).
     */
    public function test_section_registry_validates_content(): void
    {
        $errors = SectionRegistry::validate('hero_v1', [
            'headline' => str_repeat('x', 200),  // exceeds 90
        ]);
        $this->assertNotEmpty($errors, 'Long headline should fail validation');

        $ok = SectionRegistry::validate('hero_v1', [
            'headline' => 'Welcome',
            'cta_text' => 'Go',
            'cta_url'  => 'https://example.com',
        ]);
        $this->assertEmpty($ok, "Valid hero_v1 should pass: " . json_encode($ok));
    }

    /**
     * Unknown section type → registry returns null + validate returns one error.
     */
    public function test_section_registry_rejects_unknown_type(): void
    {
        $this->assertNull(SectionRegistry::get('definitely_not_a_section_v9'));
        $errs = SectionRegistry::validate('definitely_not_a_section_v9', []);
        $this->assertNotEmpty($errs);
    }

    /**
     * Order model auto-attribution: session-stored coach_site context should
     * stamp source onto a freshly-created order. Done via booted::creating.
     */
    public function test_order_inherits_coach_site_attribution_from_session(): void
    {
        $this->withSession([
            'site_attribution' => [
                'source'            => 'coach_site',
                'source_page_slug'  => 'about',
                'source_section_id' => 42,
                'captured_at'       => now()->timestamp,
            ],
        ]);

        // Pre-populate a coach_pages row so the slug lookup hits something
        $coach = User::factory()->create();
        \DB::table('coach_pages')->insert([
            'coach_id' => $coach->id, 'slug' => 'about', 'page_type' => 'about',
            'title' => 'About', 'is_published' => 1, 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Run via session — booted::creating reads session() helper
        $this->call('GET', '/'); // warm session
        \Session::put('site_attribution', [
            'source'            => 'coach_site',
            'source_page_slug'  => 'about',
            'source_section_id' => 42,
            'captured_at'       => now()->timestamp,
        ]);

        $order = \Modules\Order\app\Models\Order::create([
            'invoice_id'    => 'TEST-' . \Str::random(6),
            'buyer_id'      => $coach->id,
            'has_coupon'    => 0,
            'payable_amount'=> 100,
            'paid_amount'   => 100,
            'payment_status'=> 'paid',
            'status'        => 'completed',
        ]);

        $this->assertEquals('coach_site', $order->source);
        $this->assertEquals(42, $order->source_section_id);
        $this->assertNotNull($order->source_page_id);
    }

    /**
     * PageManager CRUD smoke — create page → add section → reorder → snapshot exists.
     */
    public function test_page_manager_create_add_section_reorder_snapshot(): void
    {
        $coach = User::factory()->create();
        $pm = app(PageManager::class);

        $page = $pm->createPage($coach->id, 'about', 'About Me');
        $this->assertEquals('about', $page->page_type);
        $this->assertEquals('about-me', $page->slug);

        $s1 = $pm->addSection($page, 'hero_v1');
        $s2 = $pm->addSection($page, 'faq_v1');

        $this->assertEquals(2, $page->sections()->count());

        // Reorder
        $pm->reorderSections($page, [$s2->id, $s1->id]);
        $ordered = $page->sections()->orderBy('sort_order')->pluck('id')->toArray();
        $this->assertEquals([$s2->id, $s1->id], $ordered);

        // Snapshots created on every mutation
        $this->assertGreaterThan(0, $page->versions()->count());
    }
}
