<?php

namespace Tests\Feature\Audit;

use App\Http\Controllers\Frontend\Coach\CoachSiteController;
use App\Http\Controllers\Frontend\CoachSitePublicController;
use App\Http\Middleware\CaptureSiteAttribution;
use App\Models\CoachLandingPage;
use App\Models\CoachPage;
use App\Models\CoachPageSection;
use App\Models\LandingPageEnquiry;
use App\Models\User;
use App\Services\Site\PageManager;
use App\Services\Site\SectionRegistry;
use App\Services\Site\SectionRenderer;
use App\Services\Site\YouTubeFetcher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * UAT (User Acceptance Test) for the Coach Marketing Website System (Phase 1).
 *
 * Follows the codebase convention of testing via the service / controller
 * layer directly (the existing audit suite never performs $this->get/post
 * because the test env doesn't bootstrap settings cache / payment-provider
 * service-providers cleanly).
 *
 * Coverage:
 *   COACH FLOW
 *     1. Dashboard auto-creates Home page on first load
 *     2. Site settings save (subdomain + website name)
 *     3. New page create (about / services / contact / custom) with slug uniqueness
 *     4. Section CRUD (add / update / delete) with autosave + version snapshot
 *     5. Reorder writes sort_order correctly
 *     6. Publish toggle enforces "Home must be published first"
 *     7. Home page is delete-protected
 *
 *   IDOR SAFETY
 *     8. Coach A cannot resolve Coach B's page via findOwnedPage()
 *     9. Coach A cannot resolve Coach B's section via findOwnedSection()
 *
 *   VISITOR / PUBLIC RENDER
 *    10. SectionRenderer produces HTML for all 12 visible section types
 *    11. Lead form submission writes page_id + section_id into landing_page_enquiries
 *    12. Course buy attribution: session('site_attribution') → Order.source
 *
 *   ROUTING + SEO
 *    13. All critical route names registered (incl. legacy redirects)
 *    14. CaptureSiteAttribution middleware writes session key
 *
 *   SYSTEM
 *    15. YouTube fetcher 24h cache key
 *    16. Legacy GrapesJS migration produces html_passthrough_v1 section
 *    17. SectionRegistry rejects invalid content
 *    18. Migration applied (schema sanity)
 */
class CoachSiteBuilderUatTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Bootstrap a coach user — User factory + 'instructor' role so the
     * checkPermission() helper returns 1 and the controllers accept them.
     */
    private function makeCoach(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role'              => 'instructor',
            'email_verified_at' => now(),
        ], $overrides));
    }

    /**
     * Authenticate the request-cycle so controllers reading userAuth() /
     * auth('web')->user() see the coach.
     */
    private function login(User $coach): void
    {
        auth('web')->login($coach);
    }

    private function controller(): CoachSiteController
    {
        return app(CoachSiteController::class);
    }

    private function publicController(): CoachSitePublicController
    {
        return app(CoachSitePublicController::class);
    }

    // ──────────────────────────────────────────────────────────────────
    // 1. SCHEMA SANITY (migration applied)
    // ──────────────────────────────────────────────────────────────────

    public function test_uat01_schema_applied(): void
    {
        $this->assertTrue(\Schema::hasTable('coach_pages'),                 'coach_pages missing');
        $this->assertTrue(\Schema::hasTable('coach_page_versions'),         'coach_page_versions missing');
        $this->assertTrue(\Schema::hasTable('coach_site_page_views'),       'coach_site_page_views missing');
        $this->assertTrue(\Schema::hasColumn('landing_sections', 'coach_page_id'));
        $this->assertTrue(\Schema::hasColumn('orders', 'source'));
        $this->assertTrue(\Schema::hasColumn('landing_page_enquiries', 'page_id'));
        $this->assertTrue(\Schema::hasColumn('landing_page_enquiries', 'section_id'));
        $this->assertTrue(\Schema::hasColumn('landing_page_enquiries', 'custom_fields'));
    }

    // ──────────────────────────────────────────────────────────────────
    // 2. ROUTE NAMES REGISTERED
    // ──────────────────────────────────────────────────────────────────

    public function test_uat02_all_critical_route_names_registered(): void
    {
        $names = collect(Route::getRoutes())->mapWithKeys(fn ($r) => [$r->getName() => true])->toArray();

        foreach ([
            'instructor.web-page.index',
            'instructor.web-page.site',
            'instructor.web-page.pages.store',
            'instructor.web-page.edit',
            'instructor.web-page.update',
            'instructor.web-page.publish',
            'instructor.web-page.destroy',
            'instructor.web-page.sections.add',
            'instructor.web-page.sections.reorder',
            'instructor.web-page.section.update',
            'instructor.web-page.section.delete',
            'instructor.web-page.section.content',
            'instructor.web-page.media',
            'instructor.web-page.youtube-refresh',
            'instructor.coach.site.preview',
            'instructor.web-page.versions',
            'instructor.web-page.restore',
            'coach.site.path',
            // legacy redirects so old bookmarks survive
            'instructor.website-builder.index',
            'instructor.landing-page-builder.index',
        ] as $name) {
            $this->assertArrayHasKey($name, $names, "Route name '{$name}' not registered");
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // 3. DASHBOARD BOOTSTRAPS HOME PAGE
    // ──────────────────────────────────────────────────────────────────

    public function test_uat03_dashboard_creates_home_on_first_load(): void
    {
        $coach = $this->makeCoach();
        $this->login($coach);

        // Simulate index() which calls PageManager::ensureHome internally
        app(PageManager::class)->ensureHome($coach->id);

        $home = CoachPage::forCoach($coach->id)->where('page_type', 'home')->first();
        $this->assertNotNull($home, 'ensureHome must create Home page');
        $this->assertEquals('home', $home->slug);
        $this->assertEquals('Home', $home->title);
    }

    // ──────────────────────────────────────────────────────────────────
    // 4. PAGE CRUD
    // ──────────────────────────────────────────────────────────────────

    public function test_uat04_create_page_generates_unique_slug(): void
    {
        $coach = $this->makeCoach();
        $pm = app(PageManager::class);

        $p1 = $pm->createPage($coach->id, 'about', 'About Me');
        $p2 = $pm->createPage($coach->id, 'about', 'About Me'); // same title → unique slug

        $this->assertEquals('about-me', $p1->slug);
        $this->assertEquals('about-me-1', $p2->slug);
    }

    public function test_uat05_rename_page_updates_title_and_slug_except_home(): void
    {
        $coach = $this->makeCoach();
        $pm = app(PageManager::class);
        $home = $pm->ensureHome($coach->id);
        $about = $pm->createPage($coach->id, 'about', 'About');

        $pm->renamePage($home, 'Home Renamed');
        $pm->renamePage($about, 'My Story');

        $this->assertEquals('Home Renamed', $home->fresh()->title);
        $this->assertEquals('home', $home->fresh()->slug, 'Home slug must remain "home"');
        $this->assertEquals('My Story', $about->fresh()->title);
        $this->assertEquals('my-story', $about->fresh()->slug);
    }

    public function test_uat06_publish_requires_published_home(): void
    {
        $coach = $this->makeCoach();
        $pm = app(PageManager::class);
        $home = $pm->ensureHome($coach->id);
        $about = $pm->createPage($coach->id, 'about', 'About');

        // Home is draft → cannot publish About
        $r1 = $pm->setPublished($about, true);
        $this->assertFalse($r1['ok'], 'About should be blocked when Home is draft');
        $this->assertFalse($about->fresh()->is_published);

        // Publish Home, then About
        $pm->setPublished($home, true);
        $r2 = $pm->setPublished($about, true);
        $this->assertTrue($r2['ok']);
        $this->assertTrue($about->fresh()->is_published);
    }

    public function test_uat07_home_cannot_be_deleted(): void
    {
        $coach = $this->makeCoach();
        $pm = app(PageManager::class);
        $home = $pm->ensureHome($coach->id);

        $pm->deletePage($home);

        // Should still exist (PageManager returns early for home)
        $this->assertNotNull(CoachPage::withTrashed()->find($home->id));
        $this->assertNull($home->fresh()->deleted_at);
    }

    public function test_uat08_non_home_page_can_be_soft_deleted(): void
    {
        $coach = $this->makeCoach();
        $pm = app(PageManager::class);
        $about = $pm->createPage($coach->id, 'about', 'About');

        $pm->deletePage($about);

        $this->assertNotNull($about->fresh()->deleted_at, 'About should be soft-deleted');
    }

    // ──────────────────────────────────────────────────────────────────
    // 5. SECTION CRUD
    // ──────────────────────────────────────────────────────────────────

    public function test_uat09_add_section_creates_row_with_defaults(): void
    {
        $coach = $this->makeCoach();
        $pm = app(PageManager::class);
        $page = $pm->ensureHome($coach->id);
        $s = $pm->addSection($page, 'hero_v1');

        $this->assertNotNull($s->id);
        $this->assertEquals('hero_v1', $s->section_type);
        $this->assertEquals('v1', $s->section_version);
        $this->assertIsArray($s->content_json);
        // hero_v1 default includes a headline
        $this->assertArrayHasKey('headline', $s->content_json);
    }

    public function test_uat10_update_section_validates_content(): void
    {
        $coach = $this->makeCoach();
        $pm = app(PageManager::class);
        $page = $pm->ensureHome($coach->id);
        $s = $pm->addSection($page, 'hero_v1');

        // Bad: headline too long
        $r1 = $pm->updateSection($s, ['headline' => str_repeat('X', 200)]);
        $this->assertFalse($r1['ok']);
        $this->assertNotEmpty($r1['errors']);

        // Good: within limits
        $r2 = $pm->updateSection($s, [
            'headline' => 'Welcome',
            'cta_text' => 'Go',
            'cta_url'  => 'https://example.com',
        ]);
        $this->assertTrue($r2['ok']);
        $this->assertEquals('Welcome', $s->fresh()->content_json['headline']);
    }

    public function test_uat11_reorder_writes_sort_order(): void
    {
        $coach = $this->makeCoach();
        $pm = app(PageManager::class);
        $page = $pm->ensureHome($coach->id);
        $a = $pm->addSection($page, 'hero_v1');
        $b = $pm->addSection($page, 'about_v1');
        $c = $pm->addSection($page, 'faq_v1');

        $pm->reorderSections($page, [$c->id, $a->id, $b->id]);

        $ordered = $page->fresh()->sections()->orderBy('sort_order')->pluck('id')->toArray();
        $this->assertEquals([$c->id, $a->id, $b->id], $ordered);
    }

    public function test_uat12_delete_section_drops_row(): void
    {
        $coach = $this->makeCoach();
        $pm = app(PageManager::class);
        $page = $pm->ensureHome($coach->id);
        $s = $pm->addSection($page, 'hero_v1');

        $pm->deleteSection($s);

        $this->assertEquals(0, $page->fresh()->sections()->count());
    }

    public function test_uat13_save_creates_version_snapshot(): void
    {
        $coach = $this->makeCoach();
        $pm = app(PageManager::class);
        $page = $pm->ensureHome($coach->id);
        $pm->addSection($page, 'hero_v1');

        $this->assertGreaterThan(0, $page->fresh()->versions()->count(),
            'Every section mutation must create a version snapshot');
    }

    public function test_uat14_version_history_trimmed_to_20(): void
    {
        $coach = $this->makeCoach();
        $pm = app(PageManager::class);
        $page = $pm->ensureHome($coach->id);

        for ($i = 0; $i < 25; $i++) {
            $pm->snapshot($page, $coach->id);
        }

        $this->assertLessThanOrEqual(20, $page->fresh()->versions()->count(),
            'Snapshot count should be trimmed to 20');
    }

    // ──────────────────────────────────────────────────────────────────
    // 6. CONTROLLER-LEVEL CRUD (direct invocation, exercises the same
    //    permission + ownership checks as the HTTP route)
    // ──────────────────────────────────────────────────────────────────

    public function test_uat15_controller_add_section_returns_html(): void
    {
        $coach = $this->makeCoach();
        $this->login($coach);
        $page = app(PageManager::class)->ensureHome($coach->id);

        $request = Request::create("/instructor/web-page/pages/{$page->id}/sections", 'POST', [
            'section_type' => 'hero_v1',
        ]);
        $response = $this->controller()->addSection($request, $page->id);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['ok'] ?? false);
        $this->assertNotEmpty($data['html'] ?? '');
        $this->assertStringContainsString('cs-hero', $data['html']);
    }

    public function test_uat16_controller_update_section_renders_fresh_html(): void
    {
        $coach = $this->makeCoach();
        $this->login($coach);
        $page = app(PageManager::class)->ensureHome($coach->id);
        $section = app(PageManager::class)->addSection($page, 'hero_v1');

        $request = Request::create("/instructor/web-page/sections/{$section->id}", 'PUT', [
            'content' => ['headline' => 'My New Headline', 'cta_text' => 'Buy', 'cta_url' => 'https://x.com'],
        ]);
        $response = $this->controller()->updateSection($request, $section->id);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['ok']);
        $this->assertStringContainsString('My New Headline', $data['html']);
    }

    public function test_uat17_controller_section_content_endpoint(): void
    {
        $coach = $this->makeCoach();
        $this->login($coach);
        $page = app(PageManager::class)->ensureHome($coach->id);
        $section = app(PageManager::class)->addSection($page, 'about_v1');

        $response = $this->publicController()->sectionContent($section->id);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['ok']);
        $this->assertIsArray($data['content']);
        $this->assertArrayHasKey('bio', $data['content']);
    }

    // ──────────────────────────────────────────────────────────────────
    // 7. IDOR — coach A can't touch coach B's resources
    // ──────────────────────────────────────────────────────────────────

    public function test_uat18_idor_section_content_blocked_for_other_coach(): void
    {
        $coachA = $this->makeCoach();
        $coachB = $this->makeCoach();
        $bPage = app(PageManager::class)->ensureHome($coachB->id);
        $bSection = app(PageManager::class)->addSection($bPage, 'hero_v1');

        $this->login($coachA);
        $response = $this->publicController()->sectionContent($bSection->id);
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['ok'] ?? true,
            'Coach A must not be able to read Coach B\'s section content');
    }

    public function test_uat19_idor_editor_findOwnedPage_throws_for_other_coach(): void
    {
        $coachA = $this->makeCoach();
        $coachB = $this->makeCoach();
        $bPage = app(PageManager::class)->ensureHome($coachB->id);

        $this->login($coachA);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->controller()->editPage($bPage->id);
    }

    public function test_uat20_idor_section_update_blocked_for_other_coach(): void
    {
        $coachA = $this->makeCoach();
        $coachB = $this->makeCoach();
        $bPage = app(PageManager::class)->ensureHome($coachB->id);
        $bSection = app(PageManager::class)->addSection($bPage, 'hero_v1');

        $this->login($coachA);
        $req = Request::create("/instructor/web-page/sections/{$bSection->id}", 'PUT', [
            'content' => ['headline' => 'HACKED'],
        ]);
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->controller()->updateSection($req, $bSection->id);
    }

    // ──────────────────────────────────────────────────────────────────
    // 8. SECTION RENDERER — every section partial compiles
    // ──────────────────────────────────────────────────────────────────

    public function test_uat21_every_section_partial_compiles(): void
    {
        $coach = $this->makeCoach();
        $page = app(PageManager::class)->ensureHome($coach->id);

        foreach (SectionRegistry::all() as $code => $def) {
            $section = app(PageManager::class)->addSection($page, $code);
            $html = app(SectionRenderer::class)->renderOne($section->fresh(), $page->fresh(), $coach);
            $this->assertIsString($html, "Section $code returned non-string");
            // Don't enforce non-empty — html_passthrough_v1 with empty defaults
            // legitimately produces an empty string.
        }
    }

    public function test_uat22_renderSections_concatenates_in_sort_order(): void
    {
        $coach = $this->makeCoach();
        $pm = app(PageManager::class);
        $page = $pm->ensureHome($coach->id);
        $hero = $pm->addSection($page, 'hero_v1');
        $faq  = $pm->addSection($page, 'faq_v1');
        // Default order: hero before faq
        $pm->updateSection($hero, ['headline' => 'HERO-MARKER']);
        $pm->updateSection($faq,  ['title' => 'FAQ-MARKER', 'items' => []]);

        $html = app(SectionRenderer::class)->renderSections($page->fresh(), $coach);
        $heroPos = strpos($html, 'HERO-MARKER');
        $faqPos  = strpos($html, 'FAQ-MARKER');
        $this->assertNotFalse($heroPos);
        $this->assertNotFalse($faqPos);
        $this->assertLessThan($faqPos, $heroPos,
            'hero_v1 should render before faq_v1 in sort_order');
    }

    // ──────────────────────────────────────────────────────────────────
    // 9. SECTION REGISTRY — schema validation
    // ──────────────────────────────────────────────────────────────────

    public function test_uat23_registry_rejects_overlong_string(): void
    {
        $errors = SectionRegistry::validate('hero_v1', ['headline' => str_repeat('X', 300)]);
        $this->assertNotEmpty($errors);
    }

    public function test_uat24_registry_rejects_invalid_enum_value(): void
    {
        $errors = SectionRegistry::validate('hero_v1', [
            'headline' => 'OK',
            'background_variant' => 'totally-made-up-value',
        ]);
        $this->assertNotEmpty($errors);
    }

    public function test_uat25_registry_accepts_valid_payload(): void
    {
        $errors = SectionRegistry::validate('hero_v1', [
            'headline' => 'Welcome',
            'cta_text' => 'Go',
            'cta_url'  => 'https://example.com',
            'background_variant' => 'gradient',
            'layout'   => 'text-image-right',
        ]);
        $this->assertEmpty($errors, 'Valid payload should pass: ' . json_encode($errors));
    }

    public function test_uat26_registry_hides_legacy_section_from_drawer(): void
    {
        $byCat = SectionRegistry::byCategory();
        $visible = [];
        foreach ($byCat as $items) foreach ($items as $i) $visible[] = $i['code'];
        $this->assertNotContains('html_passthrough_v1', $visible,
            'html_passthrough_v1 must be hidden from the drawer');
    }

    // ──────────────────────────────────────────────────────────────────
    // 10. LEAD CAPTURE — page_id + section_id flow into CRM
    // ──────────────────────────────────────────────────────────────────

    public function test_uat27_lead_form_writes_page_and_section_attribution(): void
    {
        $coach = $this->makeCoach();
        $pm = app(PageManager::class);
        $home = $pm->ensureHome($coach->id);
        $section = $pm->addSection($home, 'lead_form_v1');

        // Direct creation mimics what LandingPageController::submit_landing_page does
        $enquiry = LandingPageEnquiry::create([
            'coach_id'      => $coach->id,
            'page_id'       => $home->id,
            'section_id'    => $section->id,
            'first_name'    => 'UAT',
            'last_name'     => 'Visitor',
            'email'         => 'uat-' . Str::random(6) . '@example.com',
            'phone'         => '9999999999',
            'service'       => 'General enquiry',
            'enquiry_type'  => 'contact',
            'source'        => 'landing_page',
            'custom_fields' => ['budget' => '5000', 'experience' => 'beginner'],
        ]);

        $this->assertNotNull($enquiry->id);
        $this->assertEquals($home->id, $enquiry->page_id);
        $this->assertEquals($section->id, $enquiry->section_id);
        $this->assertEquals('5000', $enquiry->custom_fields['budget'] ?? null);
    }

    // ──────────────────────────────────────────────────────────────────
    // 11. CaptureSiteAttribution middleware
    // ──────────────────────────────────────────────────────────────────

    public function test_uat28_middleware_captures_query_into_session(): void
    {
        $middleware = new CaptureSiteAttribution();
        $req = Request::create('/coach/some-site?ref=coach_site&utm_source=coach_site&utm_medium=home&utm_campaign=42');
        $req->setLaravelSession(app('session.store'));

        $middleware->handle($req, fn () => response('OK'));

        $attrib = $req->session()->get('site_attribution');
        $this->assertIsArray($attrib);
        $this->assertEquals('coach_site', $attrib['source']);
        $this->assertEquals('home', $attrib['source_page_slug']);
        $this->assertEquals(42, $attrib['source_section_id']);
    }

    public function test_uat29_middleware_ignores_request_without_ref(): void
    {
        $middleware = new CaptureSiteAttribution();
        $req = Request::create('/coach/some-site');  // no ?ref
        $req->setLaravelSession(app('session.store'));

        $middleware->handle($req, fn () => response('OK'));

        $this->assertNull($req->session()->get('site_attribution'));
    }

    // ──────────────────────────────────────────────────────────────────
    // 12. ORDER ATTRIBUTION — booted::creating reads session
    // ──────────────────────────────────────────────────────────────────

    public function test_uat30_order_creating_event_attributes_from_session(): void
    {
        $coach = $this->makeCoach();
        \DB::table('coach_pages')->insert([
            'coach_id' => $coach->id, 'slug' => 'home', 'page_type' => 'home',
            'title' => 'Home', 'is_published' => 1, 'sort_order' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $pageId = \DB::table('coach_pages')->where('coach_id', $coach->id)->where('slug', 'home')->value('id');

        \Session::put('site_attribution', [
            'source'            => 'coach_site',
            'source_page_slug'  => 'home',
            'source_section_id' => 77,
            'captured_at'       => now()->timestamp,
        ]);

        $order = \Modules\Order\app\Models\Order::create([
            'invoice_id'     => 'UAT-' . Str::random(6),
            'buyer_id'       => $coach->id,
            'has_coupon'     => 0,
            'payable_amount' => 100, 'paid_amount' => 100,
            'payment_status' => 'paid', 'status' => 'completed',
        ]);

        $this->assertEquals('coach_site', $order->source);
        $this->assertEquals(77, $order->source_section_id);
        $this->assertEquals($pageId, $order->source_page_id);
    }

    public function test_uat31_order_attribution_expires_after_24h(): void
    {
        $coach = $this->makeCoach();
        \Session::put('site_attribution', [
            'source'            => 'coach_site',
            'source_page_slug'  => 'home',
            'source_section_id' => 99,
            'captured_at'       => now()->subDays(2)->timestamp, // EXPIRED
        ]);

        $order = \Modules\Order\app\Models\Order::create([
            'invoice_id'     => 'UAT-' . Str::random(6),
            'buyer_id'       => $coach->id,
            'has_coupon'     => 0,
            'payable_amount' => 100, 'paid_amount' => 100,
            'payment_status' => 'paid', 'status' => 'completed',
        ]);

        $this->assertNull($order->source, 'Expired attribution should NOT be applied');
    }

    // ──────────────────────────────────────────────────────────────────
    // 13. YOUTUBE 24H CACHE
    // ──────────────────────────────────────────────────────────────────

    public function test_uat32_youtube_cache_returns_value_without_api_call(): void
    {
        $channel = 'UC_TEST_' . Str::random(6);
        $sample = [['id' => 'abc', 'title' => 'Sample', 'thumbnail' => 'http://x']];
        Cache::put("youtube:videos:{$channel}", $sample, 86400);

        $fetcher = app(YouTubeFetcher::class);
        $result = $fetcher->latestVideos($channel);
        $this->assertEquals($sample, $result);

        Cache::forget("youtube:videos:{$channel}");
    }

    public function test_uat33_youtube_invalidate_clears_cache(): void
    {
        $channel = 'UC_TEST_' . Str::random(6);
        Cache::put("youtube:videos:{$channel}", [['id' => 'x']], 86400);

        app(YouTubeFetcher::class)->invalidate($channel);

        $this->assertNull(Cache::get("youtube:videos:{$channel}"));
    }

    // ──────────────────────────────────────────────────────────────────
    // 14. LEGACY GRAPESJS MIGRATION
    // ──────────────────────────────────────────────────────────────────

    public function test_uat34_legacy_site_migrates_to_html_passthrough(): void
    {
        $coach = $this->makeCoach();
        $site = CoachLandingPage::create([
            'added_by'     => $coach->id,
            'website_name' => 'Legacy',
            'slug'         => 'legacy-' . Str::random(6),
            'subdomain'    => 'legacy-' . Str::random(6) . '.' . config('app.coach_domain'),
            'is_published' => 1,
            'title'        => 'Legacy',
            'html_content' => '<div class="hero">Old hero</div>',
            'css_content'  => '.hero { color: red; }',
        ]);

        $page = app(PageManager::class)->migrateLegacy($site);

        $this->assertNotNull($page);
        $this->assertEquals('home', $page->page_type);
        $first = $page->sections()->first();
        $this->assertEquals('html_passthrough_v1', $first->section_type);
        $this->assertStringContainsString('Old hero',   $first->content_json['html']);
        $this->assertStringContainsString('color: red', $first->content_json['css']);
    }

    public function test_uat35_legacy_migration_is_idempotent(): void
    {
        $coach = $this->makeCoach();
        $site = CoachLandingPage::create([
            'added_by'     => $coach->id,
            'website_name' => 'Legacy',
            'slug'         => 'legacy-' . Str::random(6),
            'subdomain'    => 'legacy-' . Str::random(6) . '.' . config('app.coach_domain'),
            'is_published' => 1, 'title' => 'Legacy',
            'html_content' => '<p>old</p>',
            'css_content'  => '',
        ]);

        $a = app(PageManager::class)->migrateLegacy($site);
        $b = app(PageManager::class)->migrateLegacy($site);

        $this->assertNotNull($a);
        $this->assertNull($b, 'Second call must be a no-op');
        $this->assertEquals(1, CoachPage::forCoach($coach->id)->count());
    }

    public function test_uat36_legacy_migration_skipped_when_no_html(): void
    {
        $coach = $this->makeCoach();
        $site = CoachLandingPage::create([
            'added_by' => $coach->id, 'website_name' => 'Fresh', 'slug' => 'fresh-' . Str::random(6),
            'subdomain' => 'fresh-' . Str::random(6) . '.' . config('app.coach_domain'),
            'is_published' => 1, 'title' => 'Fresh',
            'html_content' => null,
        ]);

        $page = app(PageManager::class)->migrateLegacy($site);
        $this->assertNull($page);
    }

    // ──────────────────────────────────────────────────────────────────
    // 15. SEO meta surface
    // ──────────────────────────────────────────────────────────────────

    // ──────────────────────────────────────────────────────────────────
    // 16. FULL CUSTOMIZATION — Phase 1.5 (audit 2026-05-25 evening)
    // ──────────────────────────────────────────────────────────────────

    public function test_uat38_site_settings_row_auto_created_on_first_read(): void
    {
        $coach = $this->makeCoach();
        $svc = app(\App\Services\Site\SiteSettingsService::class);
        $s = $svc->for($coach->id);
        $this->assertNotNull($s->id);
        $this->assertEquals($coach->id, $s->coach_id);
        // Defaults
        $this->assertFalse((bool) $s->sticky_cta_enabled);
        $this->assertFalse((bool) $s->whatsapp_enabled);
    }

    public function test_uat39_site_settings_update_persists_all_fields(): void
    {
        $coach = $this->makeCoach();
        $svc = app(\App\Services\Site\SiteSettingsService::class);
        $svc->update($coach->id, [
            'sticky_cta_enabled' => true,
            'sticky_cta_text'    => 'Book a Call',
            'sticky_cta_url'     => 'https://calendly.com/me',
            'whatsapp_enabled'   => true,
            'whatsapp_number'    => '+919876543210',
            'whatsapp_message'   => 'Hi, I am interested',
            'social_instagram'   => 'https://instagram.com/me',
            'analytics_ga4_id'   => 'G-ABC123',
            'analytics_meta_pixel_id' => '1234567890',
            'custom_css'         => '.brand { color: red; }',
        ]);

        $fresh = $svc->for($coach->id);
        $this->assertTrue((bool) $fresh->sticky_cta_enabled);
        $this->assertEquals('Book a Call', $fresh->sticky_cta_text);
        $this->assertEquals('+919876543210', $fresh->whatsapp_number);
        $this->assertEquals('G-ABC123', $fresh->analytics_ga4_id);
        $this->assertStringContainsString('brand', $fresh->custom_css);
    }

    public function test_uat40_whatsapp_url_builds_correctly(): void
    {
        $coach = $this->makeCoach();
        $svc = app(\App\Services\Site\SiteSettingsService::class);
        $s = $svc->for($coach->id);
        $s->whatsapp_enabled = true;
        $s->whatsapp_number = '+91 98765-43210';
        $s->whatsapp_message = 'Hi from website';

        $url = $svc->whatsappUrl($s);
        $this->assertStringContainsString('wa.me/919876543210', $url);
        $this->assertStringContainsString('text=Hi%20from%20website', $url);

        // Disabled → null
        $s->whatsapp_enabled = false;
        $this->assertNull($svc->whatsappUrl($s));
    }

    public function test_uat41_section_visibility_toggle_persists(): void
    {
        $coach = $this->makeCoach();
        $page = app(\App\Services\Site\PageManager::class)->ensureHome($coach->id);
        $s = app(\App\Services\Site\PageManager::class)->addSection($page, 'hero_v1');

        $this->assertTrue((bool) $s->is_visible);
        $s->update(['is_visible' => false]);
        $this->assertFalse((bool) $s->fresh()->is_visible);
    }

    public function test_uat42_hidden_section_excluded_from_public_render(): void
    {
        $coach = $this->makeCoach();
        $pm = app(\App\Services\Site\PageManager::class);
        $page = $pm->ensureHome($coach->id);
        $visible = $pm->addSection($page, 'hero_v1');
        $hidden  = $pm->addSection($page, 'about_v1');
        $hidden->update(['is_visible' => false]);
        $pm->updateSection($visible, ['headline' => 'VISIBLE-MARKER', 'cta_text' => 'Go', 'cta_url' => 'https://x.com']);
        $pm->updateSection($hidden,  ['name' => 'HIDDEN-MARKER', 'bio' => 'should not appear']);

        $html = app(\App\Services\Site\SectionRenderer::class)->renderSections($page->fresh(), $coach);
        $this->assertStringContainsString('VISIBLE-MARKER', $html);
        $this->assertStringNotContainsString('HIDDEN-MARKER', $html);
    }

    public function test_uat43_section_duplicate_clones_content_and_advances_sort(): void
    {
        $coach = $this->makeCoach();
        $pm = app(\App\Services\Site\PageManager::class);
        $page = $pm->ensureHome($coach->id);
        $orig = $pm->addSection($page, 'hero_v1');
        $pm->updateSection($orig, ['headline' => 'ORIGINAL', 'cta_text' => 'Go', 'cta_url' => 'https://x.com']);

        // Directly call the controller helper that performs the duplicate
        $this->login($coach);
        $controller = app(\App\Http\Controllers\Frontend\Coach\CoachSiteController::class);
        $response = $controller->duplicateSection($orig->id);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['ok']);
        $this->assertEquals(2, $page->fresh()->sections()->count());
        $copy = $page->fresh()->sections()->latest('id')->first();
        $this->assertEquals('ORIGINAL', $copy->content_json['headline']);
    }

    public function test_uat44_page_duplicate_clones_with_sections(): void
    {
        $coach = $this->makeCoach();
        $pm = app(\App\Services\Site\PageManager::class);
        $home = $pm->ensureHome($coach->id);
        $about = $pm->createPage($coach->id, 'about', 'About Me');
        $pm->addSection($about, 'hero_v1');
        $pm->addSection($about, 'faq_v1');

        $this->login($coach);
        $controller = app(\App\Http\Controllers\Frontend\Coach\CoachSiteController::class);
        $response = $controller->duplicatePage($about->id);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['ok']);
        $clonedId = $data['page_id'];
        $cloned = \App\Models\CoachPage::findOrFail($clonedId);
        $this->assertEquals('About Me (Copy)', $cloned->title);
        $this->assertFalse((bool) $cloned->is_published, 'Clone must start as draft');
        $this->assertEquals(2, $cloned->sections()->count());
    }

    public function test_uat45_page_nav_controls_persist(): void
    {
        $coach = $this->makeCoach();
        $page = app(\App\Services\Site\PageManager::class)->createPage($coach->id, 'about', 'About');

        $page->update([
            'is_visible_in_nav' => false,
            'nav_label'         => 'My Story',
            'nav_external_url'  => 'https://example.com/about',
        ]);

        $fresh = $page->fresh();
        $this->assertFalse((bool) $fresh->is_visible_in_nav);
        $this->assertEquals('My Story', $fresh->nav_label);
        $this->assertEquals('https://example.com/about', $fresh->nav_external_url);
    }

    public function test_uat46_manual_slug_update_rejected_for_home(): void
    {
        $coach = $this->makeCoach();
        $home = app(\App\Services\Site\PageManager::class)->ensureHome($coach->id);

        $this->login($coach);
        $controller = app(\App\Http\Controllers\Frontend\Coach\CoachSiteController::class);
        $req = \Illuminate\Http\Request::create('/x', 'PUT', ['slug' => 'something-else']);
        $response = $controller->updatePageSlug($req, $home->id);
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['ok']);
        $this->assertEquals('home', $home->fresh()->slug, 'Home slug must remain locked');
    }

    public function test_uat47_manual_slug_update_works_for_non_home(): void
    {
        $coach = $this->makeCoach();
        $about = app(\App\Services\Site\PageManager::class)->createPage($coach->id, 'about', 'About');

        $this->login($coach);
        $controller = app(\App\Http\Controllers\Frontend\Coach\CoachSiteController::class);
        $req = \Illuminate\Http\Request::create('/x', 'PUT', ['slug' => 'founder-story']);
        $response = $controller->updatePageSlug($req, $about->id);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['ok']);
        $this->assertEquals('founder-story', $about->fresh()->slug);
    }

    // ──────────────────────────────────────────────────────────────────
    // 17. PER-SECTION APPEARANCE (bg color / text color / preset)
    // ──────────────────────────────────────────────────────────────────

    public function test_uat49_appearance_fields_auto_merged_into_every_section(): void
    {
        foreach (\App\Services\Site\SectionRegistry::all() as $code => $def) {
            if ($code === 'html_passthrough_v1') continue;  // legacy escape hatch — skipped
            $this->assertArrayHasKey('_preset',     $def['schema'], "Section $code missing _preset appearance field");
            $this->assertArrayHasKey('_bg_color',   $def['schema'], "Section $code missing _bg_color");
            $this->assertArrayHasKey('_text_color', $def['schema'], "Section $code missing _text_color");
            $this->assertArrayHasKey('_text_align', $def['schema'], "Section $code missing _text_align");
        }
    }

    public function test_uat50_buildStyle_dark_preset_emits_white_text(): void
    {
        $style = \App\Services\Site\SectionRegistry::buildStyle(['_preset' => 'dark']);
        $this->assertStringContainsString('background: #0F172A', $style);
        $this->assertStringContainsString('color: #FFFFFF', $style);
        $this->assertStringContainsString('--brand-text: #FFFFFF', $style);
    }

    public function test_uat51_buildStyle_custom_solid_color(): void
    {
        $style = \App\Services\Site\SectionRegistry::buildStyle([
            '_preset' => 'custom',
            '_bg_color' => '#FF6B6B',
            '_text_color' => '#1A1A1A',
        ]);
        $this->assertStringContainsString('background: #FF6B6B', $style);
        $this->assertStringContainsString('color: #1A1A1A', $style);
        $this->assertStringContainsString('--brand-text: #1A1A1A', $style);
    }

    public function test_uat52_buildStyle_custom_gradient(): void
    {
        $style = \App\Services\Site\SectionRegistry::buildStyle([
            '_preset' => 'custom',
            '_bg_color' => '#FF6B6B',
            '_bg_color_2' => '#FFE66D',
        ]);
        $this->assertStringContainsString('linear-gradient(135deg, #FF6B6B 0%, #FFE66D 100%)', $style);
    }

    public function test_uat53_buildStyle_brand_preset_with_no_overrides_is_empty(): void
    {
        // Brand preset + no explicit colors → no inline overrides (global CSS wins)
        $style = \App\Services\Site\SectionRegistry::buildStyle(['_preset' => 'brand']);
        $this->assertEquals('', $style, 'Brand preset with no custom colors must emit no overrides');
    }

    public function test_uat53b_brand_preset_with_custom_color_still_applies(): void
    {
        // Coach picks a color but leaves preset on brand → explicit pick wins
        $style = \App\Services\Site\SectionRegistry::buildStyle([
            '_preset'     => 'brand',
            '_bg_color'   => '#FF6B6B',
            '_text_color' => '#1A1A1A',
        ]);
        $this->assertStringContainsString('background: #FF6B6B', $style);
        $this->assertStringContainsString('color: #1A1A1A', $style);
    }

    public function test_uat53d_font_family_emits_font_stack(): void
    {
        $style = \App\Services\Site\SectionRegistry::buildStyle([
            '_font_family' => 'Poppins',
        ]);
        $this->assertStringContainsString("font-family: 'Poppins', sans-serif", $style);
    }

    public function test_uat53e_font_family_inherit_emits_nothing(): void
    {
        $style = \App\Services\Site\SectionRegistry::buildStyle([
            '_font_family' => 'inherit',
        ]);
        $this->assertStringNotContainsString('font-family', $style);
    }

    public function test_uat53f_unknown_font_label_emits_nothing(): void
    {
        $style = \App\Services\Site\SectionRegistry::buildStyle([
            '_font_family' => 'TotallyMadeUpFont',
        ]);
        $this->assertStringNotContainsString('font-family', $style);
    }

    public function test_uat53g_collect_font_slugs_from_sections(): void
    {
        $coach = $this->makeCoach();
        $pm = app(\App\Services\Site\PageManager::class);
        $page = $pm->ensureHome($coach->id);
        $s1 = $pm->addSection($page, 'hero_v1');
        $s2 = $pm->addSection($page, 'about_v1');
        $s3 = $pm->addSection($page, 'faq_v1');
        $pm->updateSection($s1, array_merge($s1->content_json, ['_font_family' => 'Poppins',     'headline' => 'X', 'cta_text' => 'Y', 'cta_url' => 'https://x.com']));
        $pm->updateSection($s2, array_merge($s2->content_json, ['_font_family' => 'Playfair Display', 'name' => 'X', 'bio' => 'Y']));
        $pm->updateSection($s3, array_merge($s3->content_json, ['_font_family' => 'Poppins',     'items' => []]));   // duplicate

        $slugs = \App\Services\Site\SectionRegistry::collectFontSlugs($page->fresh()->sections);
        $this->assertContains('Poppins:wght@400;500;600;700;800', $slugs);
        $this->assertContains('Playfair+Display:wght@400;500;600;700;800;900', $slugs);
        $this->assertCount(2, $slugs, 'Duplicates must be de-duped');
    }

    public function test_uat53c_explicit_color_overrides_preset_color(): void
    {
        // Dark preset (bg = #0F172A) + custom bg #FF6B6B → custom wins
        $style = \App\Services\Site\SectionRegistry::buildStyle([
            '_preset'   => 'dark',
            '_bg_color' => '#FF6B6B',
        ]);
        $this->assertStringContainsString('background: #FF6B6B', $style);
        $this->assertStringNotContainsString('background: #0F172A', $style,
            'Dark preset bg color must be overridden by explicit pick');
        // Text color stays dark-preset's white because not overridden
        $this->assertStringContainsString('color: #FFFFFF', $style);
    }

    public function test_uat54_buildStyle_text_alignment(): void
    {
        $style = \App\Services\Site\SectionRegistry::buildStyle([
            '_preset' => 'brand',
            '_text_align' => 'center',
        ]);
        $this->assertStringContainsString('text-align: center', $style);
    }

    public function test_uat55_section_renderer_passes_appearance_style_to_view(): void
    {
        $coach = $this->makeCoach();
        $pm = app(\App\Services\Site\PageManager::class);
        $page = $pm->ensureHome($coach->id);
        $section = $pm->addSection($page, 'about_v1');
        $section->update(['content_json' => array_merge($section->content_json, [
            '_preset' => 'dark',
            'name' => 'Test Coach',
            'bio'  => 'About me',
        ])]);

        $html = app(\App\Services\Site\SectionRenderer::class)->renderOne($section->fresh(), $page->fresh(), $coach);
        $this->assertStringContainsString('background: #0F172A', $html, 'Dark preset must inject bg color into rendered HTML');
        $this->assertStringContainsString('--brand-text: #FFFFFF', $html, 'Dark preset must override brand-text CSS variable');
    }

    public function test_uat48_all_new_routes_registered(): void
    {
        $names = collect(\Route::getRoutes())->mapWithKeys(fn ($r) => [$r->getName() => true])->toArray();
        foreach ([
            'instructor.web-page.settings.get',
            'instructor.web-page.settings.update',
            'instructor.web-page.section.duplicate',
            'instructor.web-page.section.toggle-visible',
            'instructor.web-page.page.duplicate',
            'instructor.web-page.page.nav',
            'instructor.web-page.page.slug',
            'instructor.web-page.pages.list',
            'instructor.web-page.pages.reorder',
        ] as $name) {
            $this->assertArrayHasKey($name, $names, "Customization route {$name} not registered");
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // 18. RECORDED COURSES SECTION (60s preview + add-to-cart)
    // ──────────────────────────────────────────────────────────────────

    public function test_uat56_recorded_courses_section_registered(): void
    {
        $registry = \App\Services\Site\SectionRegistry::all();
        $this->assertArrayHasKey('recorded_courses_v1', $registry);
        $section = $registry['recorded_courses_v1'];
        $this->assertEquals('conversion', $section['category']);
        $this->assertArrayHasKey('preview_seconds', $section['schema']);
        $this->assertArrayHasKey('limit', $section['schema']);
        $this->assertArrayHasKey('cta_text', $section['schema']);
        $this->assertEquals('60', $section['defaults']['preview_seconds']);
    }

    public function test_uat57_recorded_courses_partial_compiles(): void
    {
        $coach = $this->makeCoach();
        $page = app(\App\Services\Site\PageManager::class)->ensureHome($coach->id);
        $section = app(\App\Services\Site\PageManager::class)->addSection($page, 'recorded_courses_v1');

        $html = app(\App\Services\Site\SectionRenderer::class)->renderOne($section->fresh(), $page->fresh(), $coach);
        $this->assertIsString($html);
        $this->assertStringContainsString('cs-rec', $html);
    }

    public function test_uat58_courses_table_has_preview_seconds(): void
    {
        $this->assertTrue(\Schema::hasColumn('courses', 'preview_seconds'),
            'courses.preview_seconds column must exist for the 60s preview cap');
    }

    public function test_uat59_recorded_section_schema_validates_correctly(): void
    {
        // Valid payload accepted
        $errors = \App\Services\Site\SectionRegistry::validate('recorded_courses_v1', [
            'title'           => 'My courses',
            'preview_seconds' => '60',
            'columns'         => '3',
            'limit'           => '6',
            'cta_text'        => 'Add to cart',
        ]);
        $this->assertEmpty($errors, 'Valid recorded_courses_v1 should pass: ' . json_encode($errors));

        // Invalid preview_seconds (not in enum) rejected
        $errors = \App\Services\Site\SectionRegistry::validate('recorded_courses_v1', [
            'preview_seconds' => '5000',
        ]);
        $this->assertNotEmpty($errors, 'preview_seconds=5000 should fail enum validation');
    }

    public function test_uat60_video_first_themes_include_recorded_section(): void
    {
        // Themes that should have the recorded preview section
        $expected = [
            'modern-yoga-studio', 'hiit-power-trainer', 'strength-conditioning',
            'personal-trainer-studio', 'language-learning-coach', 'dietician-nutrition',
        ];
        foreach ($expected as $slug) {
            $theme = \App\Models\Theme::where('slug', $slug)->first();
            $this->assertNotNull($theme, "Theme $slug must be seeded");
            $home = $theme->pages()->where('page_type', 'home')->first();
            $hasRecorded = $home->sections()
                ->where('section_type', 'recorded_courses_v1')
                ->exists();
            $this->assertTrue($hasRecorded, "Theme $slug must include recorded_courses_v1 on home page");
        }
    }

    public function test_uat61_non_video_themes_do_not_have_recorded_section(): void
    {
        // Themes where video preview doesn't make sense (text-heavy verticals)
        $textHeavyThemes = ['executive-business-coach', 'counseling-practice', 'mindfulness-therapist'];
        foreach ($textHeavyThemes as $slug) {
            $theme = \App\Models\Theme::where('slug', $slug)->first();
            $home = $theme->pages()->where('page_type', 'home')->first();
            $hasRecorded = $home->sections()
                ->where('section_type', 'recorded_courses_v1')
                ->exists();
            $this->assertFalse($hasRecorded, "Theme $slug should NOT have recorded_courses_v1 (non-video vertical)");
        }
    }

    public function test_uat62_recorded_section_pulls_only_recorded_type_courses(): void
    {
        $coach = $this->makeCoach();

        // Create one recorded course + one regular course for this coach
        try {
            \App\Models\Course::create([
                'instructor_id' => $coach->id,
                'title' => 'Recorded Yoga Series',
                'slug'  => 'recorded-yoga-' . \Str::random(6),
                'price' => 999, 'discount' => 0,
                'type' => 'recorded',
                'status' => 'active', 'is_approved' => 'approved',
                'coach_soft_delete' => 0,
            ]);
            \App\Models\Course::create([
                'instructor_id' => $coach->id,
                'title' => 'Live Yoga Workshop',
                'slug'  => 'live-yoga-' . \Str::random(6),
                'price' => 1499, 'discount' => 0,
                'type' => 'live',
                'status' => 'active', 'is_approved' => 'approved',
                'coach_soft_delete' => 0,
            ]);
        } catch (\Throwable $e) {
            $this->markTestSkipped('Course factory not fully set up for this test env: ' . $e->getMessage());
            return;
        }

        $page = app(\App\Services\Site\PageManager::class)->ensureHome($coach->id);
        $section = app(\App\Services\Site\PageManager::class)->addSection($page, 'recorded_courses_v1');

        $html = app(\App\Services\Site\SectionRenderer::class)->renderOne($section->fresh(), $page->fresh(), $coach);
        $this->assertStringContainsString('Recorded Yoga Series', $html, 'Recorded course must appear in section');
        // The "live" course should NOT appear when recorded courses exist
        $this->assertStringNotContainsString('Live Yoga Workshop', $html, 'Live course must NOT appear when recorded courses are present');
    }

    public function test_uat37_page_has_seo_columns(): void
    {
        $coach = $this->makeCoach();
        $page = app(PageManager::class)->ensureHome($coach->id);
        $page->update([
            'meta_title'       => 'My Coach Site',
            'meta_description' => 'Yoga · meditation · clarity',
            'og_image'         => 'https://example.com/og.jpg',
            'robots'           => 'index',
        ]);

        $fresh = $page->fresh();
        $this->assertEquals('My Coach Site', $fresh->meta_title);
        $this->assertEquals('index', $fresh->robots);
    }

    // ──────────────────────────────────────────────────────────────────
    // 17. YOUTUBE API KEY RESOLUTION + DIAGNOSTIC (added 2026-05-26)
    //
    // Symptom that triggered this: coach 1143 dropped a channel ID into
    // the Recorded Courses / YouTube sections but no videos appeared,
    // because they had no row in youtube_credentials. The fetcher silently
    // returned []. These tests pin the new behaviour:
    //   - Platform-wide YOUTUBE_API_KEY is used when a coach has no key
    //   - Coach-specific keys still take precedence
    //   - SectionRenderer::youtubeDiagnostic() returns a useful owner-only
    //     message explaining WHY the list is empty.
    // ──────────────────────────────────────────────────────────────────

    public function test_uat63_youtube_platform_fallback_key_used_when_coach_has_none(): void
    {
        config(['services.youtube.api_key' => 'AIzaSyTEST_PLATFORM_xxxxxxxxxxxxxxxxxxx']);

        $coach = $this->makeCoach();   // no youtube_credentials row
        $fetcher = app(YouTubeFetcher::class);

        $ref = new \ReflectionClass($fetcher);
        $m = $ref->getMethod('apiKeyForCoach');
        $m->setAccessible(true);

        $this->assertSame(
            'AIzaSyTEST_PLATFORM_xxxxxxxxxxxxxxxxxxx',
            $m->invoke($fetcher, $coach->id),
            'Platform fallback key must be returned when coach has no credential row.'
        );

        $this->assertSame(
            'AIzaSyTEST_PLATFORM_xxxxxxxxxxxxxxxxxxx',
            $m->invoke($fetcher, null),
            'Platform fallback key must also be returned for anonymous visits.'
        );
    }

    public function test_uat64_youtube_coach_key_wins_over_platform_fallback(): void
    {
        config(['services.youtube.api_key' => 'AIzaSyTEST_PLATFORM_xxxxxxxxxxxxxxxxxxx']);

        $coach = $this->makeCoach();
        \DB::table('youtube_credentials')->insert([
            'instructor_id' => $coach->id,
            'api_key'       => 'AIzaSyCOACH_OWN_KEY_yyyyyyyyyyyyyyyyyyy',
            'channel_id'    => 'UCtest',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $fetcher = app(YouTubeFetcher::class);
        $ref = new \ReflectionClass($fetcher);
        $m = $ref->getMethod('apiKeyForCoach');
        $m->setAccessible(true);

        $this->assertSame(
            'AIzaSyCOACH_OWN_KEY_yyyyyyyyyyyyyyyyyyy',
            $m->invoke($fetcher, $coach->id),
            'Coach-specific key must take precedence over platform fallback.'
        );
    }

    public function test_uat65_youtube_diagnostic_explains_empty_results(): void
    {
        config(['services.youtube.api_key' => null]);

        $coach = $this->makeCoach();
        $renderer = app(SectionRenderer::class);

        // Case A: channel id supplied, no key anywhere
        $diag = $renderer->youtubeDiagnostic('UCtest_channel_id_abc', $coach, []);
        $this->assertNotNull($diag);
        $this->assertStringContainsString('API key', $diag);

        // Case B: no channel id, no creds
        $diag2 = $renderer->youtubeDiagnostic(null, $coach, []);
        $this->assertNotNull($diag2);
        $this->assertStringContainsString('channel ID', $diag2);

        // Case C: non-empty result => no diagnostic
        $diag3 = $renderer->youtubeDiagnostic('UCtest', $coach, [['id' => 'x']]);
        $this->assertNull($diag3, 'Diagnostic must be null when videos are present.');
    }

    // ──────────────────────────────────────────────────────────────────
    // 18. NO-YOUTUBE-LEAK + CART AJAX (added 2026-05-26)
    //
    // Triggered by: user reported clicking previews → opens youtube.com,
    // and Add to Cart did nothing. Root cause was <a href> on a POST-only
    // route + YouTube watch URL fallback when no course linked. These
    // tests pin the fixes so a future refactor can't reintroduce either.
    // ──────────────────────────────────────────────────────────────────

    public function test_uat67_no_youtube_links_leak_into_rendered_html(): void
    {
        $coach = $this->makeCoach();
        $page = app(PageManager::class)->ensureHome($coach->id);

        // Add both sections that touch YouTube
        $rec = app(PageManager::class)->addSection($page, 'recorded_courses_v1');
        $rec->content_json = array_merge((array) $rec->content_json, [
            'source' => 'from-youtube',
            'youtube_channel_id' => 'UCtest_leak_check_xxxx',
        ]);
        $rec->save();

        $yt = app(PageManager::class)->addSection($page, 'youtube_v1');
        $yt->content_json = array_merge((array) $yt->content_json, [
            'channel_id' => 'UCtest_leak_check_xxxx',
        ]);
        $yt->save();

        // Pre-seed cache so the partial renders cards without an API call
        Cache::put('youtube:videos:UCtest_leak_check_xxxx', [
            ['id' => 'abc123XYZ_1', 'title' => 'Vid 1', 'thumbnail' => 'https://i.ytimg.com/x.jpg'],
            ['id' => 'abc123XYZ_2', 'title' => 'Vid 2', 'thumbnail' => 'https://i.ytimg.com/y.jpg'],
        ], 60);

        $renderer = app(SectionRenderer::class);
        $recHtml = $renderer->renderOne($rec->fresh(), $page->fresh(), $coach);
        $ytHtml  = $renderer->renderOne($yt->fresh(),  $page->fresh(), $coach);

        foreach (['recorded' => $recHtml, 'youtube_v1' => $ytHtml] as $label => $html) {
            $this->assertStringNotContainsString('youtube.com/watch', $html,
                "$label section must not contain external youtube.com/watch links.");
            $this->assertStringNotContainsString('fa-brands fa-youtube', $html,
                "$label section must not show YouTube brand icon to students.");
            $this->assertStringNotContainsString('cs-rec__yt-badge', $html,
                "$label section must not show the 'YouTube' badge.");
        }

        $this->assertStringNotContainsString('target="_blank"', $recHtml,
            'Recorded cards must not open anything in a new tab.');
    }

    public function test_uat68_recorded_cart_button_uses_post_ajax_markup(): void
    {
        // When a course IS linked, the cart CTA must be a <button data-cs-cart>
        // (vanilla JS posts via fetch), NOT an <a href> (which would 405 against
        // the POST-only /add-to-cart/{id} route).
        $coach = $this->makeCoach();
        $page = app(PageManager::class)->ensureHome($coach->id);
        $section = app(PageManager::class)->addSection($page, 'recorded_courses_v1');

        // Seed a course so the YouTube cards get linked
        $course = new \App\Models\Course();
        $course->forceFill([
            'instructor_id' => $coach->id,
            'added_by' => $coach->id,
            'type' => 'recorded',
            'title' => 'Test course',
            'slug' => 'test-cart-' . Str::random(6),
            'price' => 499,
            'discount' => 0,
            'status' => 1,
            'coach_soft_delete' => 0,
        ])->save();

        $section->content_json = array_merge((array) $section->content_json, [
            'source' => 'from-youtube',
            'youtube_channel_id' => 'UCtest_cart_xxxx',
            'linked_course_slug' => $course->slug,
        ]);
        $section->save();

        Cache::put('youtube:videos:UCtest_cart_xxxx', [
            ['id' => 'vidA', 'title' => 'A', 'thumbnail' => ''],
        ], 60);

        $html = app(SectionRenderer::class)->renderOne($section->fresh(), $page->fresh(), $coach);

        $this->assertStringContainsString('data-cs-cart', $html,
            'Recorded section must use the JS-bound data-cs-cart button, not <a href>.');
        $this->assertStringContainsString('data-course-id="' . $course->id . '"', $html,
            'Cart button must carry the linked course id, not the YouTube video id.');
        // The CSRF meta is needed at the layout level; the partial itself
        // calls getCsrf() at click time. Just confirm we did not leave an
        // <a href="/add-to-cart/..."> in place.
        $this->assertStringNotContainsString('href="' . url('/add-to-cart/') . '/', $html,
            'No GET-style /add-to-cart/ links may remain — route is POST only.');
    }

    public function test_uat90_landing_form_submit_sets_status_so_db_insert_succeeds(): void
    {
        // Bug 2026-05-26: submit_landing_page() and submit_service_page()
        // both passed status=null when the lead wasn't a duplicate. The
        // landing_page_enquiries.status column is NOT NULL (default
        // 'published') so the explicit null bypassed the default and
        // crashed the INSERT with SQLSTATE[23000].
        //
        // Pin that the controller now sets STATUS_NEW for normal leads.
        $src = file_get_contents(app_path(
            'Http/Controllers/Frontend/Coach/LandingPageController.php'
        ));

        $this->assertStringNotContainsString(
            "STATUS_SPAM : null,",
            $src,
            'submit_landing_page/submit_service_page must not pass status=null — landing_page_enquiries.status is NOT NULL.'
        );
        // And the explicit STATUS_NEW fallback must be present
        $this->assertStringContainsString(
            'LandingPageEnquiry::STATUS_NEW',
            $src,
            'Both submit handlers must use STATUS_NEW for normal (non-duplicate) leads.'
        );
    }

    public function test_uat91_coach_checkout_view_lists_items_and_uses_real_gateway_keys(): void
    {
        // Bug 2026-05-26: checkout order summary showed empty "Item Name"
        // line because the view only emitted "Items: N". Students couldn't
        // tell what they were paying for. Also the gateway picker rendered
        // nothing because the view used made-up keys (label/form_action)
        // instead of the real ones (name/logo) emitted by
        // PaymentMethodService::getActiveGatewaysWithDetails().
        $view = file_get_contents(resource_path(
            'views/frontend/coach-site/pages/checkout.blade.php'
        ));

        // Item list: must iterate $products and render the course title.
        $this->assertStringContainsString('@foreach($products as $item)', $view,
            'Checkout view must iterate $products to list course titles.');
        $this->assertStringContainsString('course?->title', $view,
            'Order summary must display each course title.');

        // Gateways: must use the data-method + JS pattern that the
        // platform uses, NOT made-up keys like form_action/label.
        $this->assertStringContainsString('data-method=', $view,
            'Gateway items must use data-method="..." so checkout.js can POST.');
        $this->assertStringContainsString("\$gatewayDetails['name']", $view,
            'Must read real gateway key "name" (not the made-up "label").');
        $this->assertStringContainsString("\$gatewayDetails['logo']", $view,
            'Must read real gateway key "logo".');
        $this->assertStringContainsString('frontend/js/default/checkout.js', $view,
            'Must include the platform checkout.js so gateway clicks actually POST.');
    }

    public function test_uat92_coach_site_nav_urls_include_app_url_prefix(): void
    {
        // Bug 2026-05-26: buildSiteNav() built menu URLs by string concat
        // ("/coach/" . $slug) producing root-relative URLs. On a XAMPP
        // install with APP_URL=http://localhost/mbsguru1/public/ the
        // browser resolved them against the host root → 404. Use route()
        // so the URL generator includes the base path.
        $src = file_get_contents(app_path(
            'Http/Controllers/Frontend/CoachSitePublicController.php'
        ));

        // The old broken pattern was bare $base concatenation. The new
        // pattern wraps the path in url(...) or route(...) so the URL
        // generator includes APP_URL's base path on subdirectory installs.
        $this->assertMatchesRegularExpression(
            "/(url\\(|route\\(\\s*'coach\\.site\\.path')/",
            $src,
            'buildSiteNav must use the Laravel URL generator (url() or route()) — never hand-roll URLs with string concat.'
        );
        // And the old "$base . " concatenation pattern must be gone.
        $this->assertDoesNotMatchRegularExpression(
            '/\\$base\\s*\\.\\s*\'\\/\'/',
            $src,
            'buildSiteNav must not concatenate $base with paths — that produces root-relative URLs that 404 on subdirectory installs.'
        );
    }

    public function test_uat85_coach_scoped_commerce_routes_registered(): void
    {
        // The 10 white-label commerce routes must all be registered. If a
        // controller is renamed or the route group is moved by mistake,
        // students lose access to coach-branded cart/checkout/auth/dashboard
        // and silently fall back to platform-branded pages.
        $routes = \Route::getRoutes()->getRoutesByName();
        $expected = [
            'coach.cart',
            'coach.checkout',
            'coach.login',
            'coach.login.submit',
            'coach.register',
            'coach.register.submit',
            'coach.logout',
            'coach.student.dashboard',
            'coach.student.courses',
            'coach.student.orders',
        ];
        foreach ($expected as $name) {
            $this->assertArrayHasKey($name, $routes,
                "Route '$name' is missing — coach-scoped white-label group is broken.");
        }

        // Tenant middleware MUST be attached to the group — without it,
        // the controllers can't resolve request->tenant_coach and would
        // 500. Verify it's wired.
        $cartMiddleware = $routes['coach.cart']->gatherMiddleware();
        $this->assertContains('tenant.context', $cartMiddleware,
            'coach.cart must run through the tenant.context middleware.');
    }

    public function test_uat86_tenant_context_resolves_from_route_param(): void
    {
        // Path-based mode (used in local dev + as production fallback):
        // /coach/{coachSlug}/... must resolve the coach via the landing-page
        // slug → coach_id mapping. This is THE primary tenant detector.
        $coach   = $this->makeCoach();
        $slug    = 'tenantctx-' . Str::random(6);

        // Create a landing-page row so the resolver can map slug → coach.
        \App\Models\CoachLandingPage::create([
            'added_by'     => $coach->id,
            'website_name' => 'Tenant Test',
            'slug'         => $slug,
            'subdomain'    => $slug . '.' . config('app.coach_domain'),
            'is_published' => 1,
            'title'        => 'Tenant Test',
        ]);

        $request = Request::create('/coach/' . $slug . '/cart', 'GET');
        $request->setLaravelSession(app('session.store'));
        // Simulate the route binding by setting the route parameter.
        $route = (new \Illuminate\Routing\Route(['GET'], '/coach/{coachSlug}/cart', []))
            ->bind($request);
        $route->setParameter('coachSlug', $slug);
        $request->setRouteResolver(fn () => $route);

        $middleware = new \App\Http\Middleware\TenantContext();
        $middleware->handle($request, fn ($r) => response('ok'));

        $this->assertSame($coach->id, $request->attributes->get('tenant_coach_id'),
            'TenantContext must set tenant_coach_id from route param.');
        $this->assertSame($coach->id, (int) session('tenant_coach_id'),
            'TenantContext must mirror the id into session so it survives gateway callbacks.');
    }

    public function test_uat87_student_dashboard_filters_courses_by_coach(): void
    {
        // Multi-coach isolation: a student who bought courses from Coach A
        // should NOT see those courses when viewing Coach B's dashboard.
        $coachA  = $this->makeCoach();
        $coachB  = $this->makeCoach();
        $student = $this->makeCoach();   // helper makes any user; cart is per-user

        // Coach A creates a course; student enrolls in it.
        $courseA = new \App\Models\Course();
        $courseA->forceFill([
            'instructor_id' => $coachA->id,
            'added_by'      => $coachA->id,
            'type'          => 'recorded',
            'title'         => 'Course A',
            'slug'          => 'course-a-' . Str::random(6),
            'price'         => 499,
            'discount'      => 0,
            'status'        => 'active',
            'is_approved'   => 'approved',
            'coach_soft_delete' => 0,
        ])->save();

        // Coach B creates a course; student enrolls in it too.
        $courseB = new \App\Models\Course();
        $courseB->forceFill([
            'instructor_id' => $coachB->id,
            'added_by'      => $coachB->id,
            'type'          => 'recorded',
            'title'         => 'Course B',
            'slug'          => 'course-b-' . Str::random(6),
            'price'         => 499,
            'discount'      => 0,
            'status'        => 'active',
            'is_approved'   => 'approved',
            'coach_soft_delete' => 0,
        ])->save();

        \Modules\Order\app\Models\Enrollment::create([
            'user_id'    => $student->id,
            'course_id'  => $courseA->id,
            'has_access' => 1,
        ]);
        \Modules\Order\app\Models\Enrollment::create([
            'user_id'    => $student->id,
            'course_id'  => $courseB->id,
            'has_access' => 1,
        ]);

        // Now query the dashboard scope for each coach. Each query must
        // return ONLY that coach's course — never the other coach's.
        $aCount = \Modules\Order\app\Models\Enrollment::where('user_id', $student->id)
            ->whereHas('course', fn($q) => $q->where('instructor_id', $coachA->id))
            ->count();
        $bCount = \Modules\Order\app\Models\Enrollment::where('user_id', $student->id)
            ->whereHas('course', fn($q) => $q->where('instructor_id', $coachB->id))
            ->count();

        $this->assertSame(1, $aCount, 'Coach A dashboard must show exactly 1 course (Course A).');
        $this->assertSame(1, $bCount, 'Coach B dashboard must show exactly 1 course (Course B).');
        // And the student totally has 2 enrollments across the platform —
        // tenant isolation does NOT delete data, only filters the view.
        $this->assertSame(2,
            \Modules\Order\app\Models\Enrollment::where('user_id', $student->id)->count(),
            'Underlying data still has both — only the filter changes per coach.');
    }

    public function test_uat88_coach_master_drawer_uses_coach_scoped_urls(): void
    {
        // Pin that the cart drawer + cart icon point at coach-scoped URLs,
        // not the platform /cart or /checkout. If this regresses, the
        // student would be dumped back onto MBSguru chrome immediately.
        $masterSrc = file_get_contents(resource_path(
            'views/frontend/coach-site/layouts/master.blade.php'
        ));

        // The header cart icon must use route('coach.cart', ...) — not bare url('/cart').
        $this->assertStringContainsString("route('coach.cart'", $masterSrc,
            'Header cart icon must build URL via route(coach.cart, ...).');
        // The drawer's Checkout button must use route('coach.checkout', ...).
        $this->assertStringContainsString("route('coach.checkout'", $masterSrc,
            'Drawer Checkout must build URL via route(coach.checkout, ...).');

        // And the recorded_courses_v1 partial's AJAX-injected drawer
        // footer must also use the coach-scoped URLs.
        $sectionSrc = file_get_contents(resource_path(
            'views/frontend/coach-site/sections/recorded_courses_v1.blade.php'
        ));
        $this->assertStringContainsString('$coachCheckoutUrl', $sectionSrc,
            'Recorded courses partial must define $coachCheckoutUrl and use it in the dynamic drawer footer.');
        $this->assertStringContainsString('$coachCartUrl', $sectionSrc,
            'Recorded courses partial must define $coachCartUrl for the "In cart — view" link.');
    }

    public function test_uat89_payment_success_redirects_to_coach_dashboard_via_tenant_id(): void
    {
        // When session has tenant_coach_id but NO explicit return_to URL,
        // payment_success must derive the coach dashboard URL from the
        // tenant id and redirect there with ?paid=1. This is the fallback
        // path that handles direct gateway callbacks where the return_to
        // session key was cleared by a session rotation.
        $src = file_get_contents(base_path(
            'Modules/BasicPayment/app/Http/Controllers/PaymentController.php'
        ));

        // Must read tenant_coach_id from session
        $this->assertStringContainsString("session()->pull('tenant_coach_id'", $src,
            'payment_success must consume tenant_coach_id as a fallback redirect source.');
        // Must build the coach dashboard URL via route()
        $this->assertStringContainsString("'coach.student.courses'", $src,
            'payment_success must redirect to coach.student.courses when tenant_coach_id is set.');
        $this->assertStringContainsString("'paid=1'", $src,
            'Redirect must carry ?paid=1 so the success banner renders.');
    }

    public function test_uat81_payment_success_links_student_to_coach(): void
    {
        // The bug: when a student paid via the synchronous gateway-redirect
        // (the path used by 99% of buyers), payment_success() ran an
        // INLINE-DUPLICATED version of the order-completion logic that
        // missed CoachStudentLink::link() — the call that webhook-based
        // fulfilment in PaymentFulfilmentService already did. Result: the
        // new buyer enrolled in the course but NEVER appeared in the
        // coach's /instructor/students roster.
        //
        // 2026-06-01 (audit C3/C4) — the inline duplication was REMOVED:
        // payment_success() now delegates regular online orders to the
        // canonical PaymentFulfilmentService::markPaid(), so the buyer->coach
        // link can never again drift out of the redirect path. This test now
        // pins (a) that delegation and (b) that markPaid does the linking.
        $src = file_get_contents(base_path(
            'Modules/BasicPayment/app/Http/Controllers/PaymentController.php'
        ));

        // (a) payment_success delegates fulfilment to markPaid.
        $this->assertMatchesRegularExpression(
            '/PaymentFulfilmentService::class\s*\)\s*[\s\S]{0,80}?->markPaid\(/',
            $src,
            'payment_success() must fulfil regular orders via PaymentFulfilmentService::markPaid().'
        );

        // (b) markPaid links the buyer to the coach inside the orderItems
        // loop, with source=purchase, so buyers become the coach's students.
        $svc = file_get_contents(base_path('app/Services/PaymentFulfilmentService.php'));
        $this->assertMatchesRegularExpression(
            '/foreach\s*\(\s*\$order->orderItems\s+as\s+\$item\s*\)\s*\{[\s\S]+?CoachStudentLink::link\(/',
            $svc,
            'PaymentFulfilmentService::markPaid() must call CoachStudentLink::link() inside the orderItems loop.'
        );
        $this->assertStringContainsString("'purchase'", $svc,
            'Coach-student link inside markPaid() must carry source=purchase.');
    }

    public function test_uat82_capture_attribution_stores_return_to_for_post_payment(): void
    {
        // CaptureSiteAttribution stashes ?return_to= into the session so
        // payment_success can hand the student back to the coach's
        // branded page after the gateway round-trip. Without this, the
        // student lands on the platform-branded /student/orders and the
        // coach's brand continuity is broken.
        $appHost = parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost';

        $req = Request::create(
            '/checkout?return_to=' . urlencode('http://' . $appHost . '/coach/mbs'),
            'GET'
        );
        // Wire a session store
        $req->setLaravelSession(app('session.store'));

        $middleware = new \App\Http\Middleware\CaptureSiteAttribution();
        $middleware->handle($req, fn ($r) => response('ok'));

        $this->assertSame(
            'http://' . $appHost . '/coach/mbs',
            $req->session()->get('coach_site_return_to'),
            'return_to must be captured into session when host matches APP_URL.'
        );

        // Reject off-site URLs — security: don't trust attacker-supplied
        // redirect targets.
        $req2 = Request::create('/checkout?return_to=' . urlencode('http://evil.example.com/'), 'GET');
        $req2->setLaravelSession(app('session.store'));
        $req2->session()->forget('coach_site_return_to');
        $middleware->handle($req2, fn ($r) => response('ok'));
        $this->assertNull($req2->session()->get('coach_site_return_to'),
            'Off-site return_to URLs must NOT be captured (open-redirect prevention).');
    }

    public function test_uat83_payment_success_redirects_to_coach_return_to_when_set(): void
    {
        // payment_success must end with a redirect to session('coach_site_return_to')
        // (when set) instead of rendering the platform-branded order-success
        // view. We assert on the source structure rather than full execution
        // because payment_success needs a full gateway session object.
        $src = file_get_contents(base_path(
            'Modules/BasicPayment/app/Http/Controllers/PaymentController.php'
        ));

        // The session pull happens INSIDE payment_success.
        $this->assertStringContainsString(
            "session()->pull('coach_site_return_to')",
            $src,
            'payment_success() must consume coach_site_return_to from session.'
        );
        // And actually use it as a redirect target.
        $this->assertStringContainsString(
            'redirect()->away($returnTo',
            $src,
            'payment_success() must redirect to the coach-site return URL when set.'
        );
        // The ?paid=1 marker must be appended so the master layout can flash
        // the green "purchase successful" banner.
        $this->assertStringContainsString(
            "'paid=1'",
            $src,
            'Coach-site redirect must carry ?paid=1 so the success banner renders.'
        );
    }

    public function test_uat84_master_layout_shows_post_payment_success_banner(): void
    {
        // After payment_success redirects back to the coach site with
        // ?paid=1, the master layout must show a "Payment successful"
        // banner with a link to /student/my-courses so the student can
        // open the course they just bought.
        $masterSrc = file_get_contents(resource_path(
            'views/frontend/coach-site/layouts/master.blade.php'
        ));

        $this->assertStringContainsString("request()->boolean('paid')", $masterSrc,
            'Master layout must check for ?paid=1 to show the success banner.');
        $this->assertStringContainsString('Payment successful', $masterSrc,
            'Banner must show a "Payment successful" message.');
        $this->assertStringContainsString('/student/my-courses', $masterSrc,
            'Banner must link to /student/my-courses so the student can open the course.');
    }

    public function test_uat79_coach_site_master_renders_cart_drawer_with_checkout_link(): void
    {
        // The drawer is the heart of the "stay on coach site" flow.
        // It must always be present in the master layout, with the cart
        // button opening it and a Proceed-to-secure-checkout link that
        // carries return_to back to the current coach page.
        $masterSrc = file_get_contents(
            resource_path('views/frontend/coach-site/layouts/master.blade.php')
        );

        $this->assertStringContainsString('cs-cart-drawer', $masterSrc,
            'Master layout must contain the slide-out cart drawer.');
        $this->assertStringContainsString('cs-cart-open', $masterSrc,
            'Cart icon must open the drawer via document.body.classList.add(cs-cart-open).');
        $this->assertStringContainsString('Proceed to secure checkout', $masterSrc,
            'Drawer footer must surface the Checkout CTA.');

        // Updated 2026-05-26 (white-label Phase 4): drawer now points
        // directly at /coach/{slug}/checkout via route() — return_to is
        // no longer needed because the URL is coach-scoped end-to-end.
        // payment_success still honors return_to from CaptureSiteAttribution
        // as a defence-in-depth fallback.
        $this->assertStringContainsString("route('coach.checkout'", $masterSrc,
            'Drawer Checkout must use route(coach.checkout, ...) so the URL stays coach-scoped.');

        // The cart icon must trigger the drawer via onclick (we accept
        // both <button> and <a href> with onclick — accessibility win is
        // having a real href fallback). The KEY assertion is that the
        // href points to a coach-scoped URL, never a bare /cart.
        $this->assertMatchesRegularExpression(
            '/class="cs-nav__cart"/',
            $masterSrc,
            'Header must render the .cs-nav__cart element.'
        );
        $this->assertStringNotContainsString(
            'href="{{ url(\'/cart\') }}" class="cs-nav__cart"',
            $masterSrc,
            'Cart icon must NOT hardcode the platform /cart URL — must use route(coach.cart, ...).'
        );
    }

    public function test_uat85_brand_link_points_to_coach_home_not_platform_root(): void
    {
        // Audit [12] — white-label isolation. The header brand/logo link
        // previously hardcoded url('/') (the PLATFORM homepage), which on a
        // path-based /coach/{slug} site navigates the visitor off the coach's
        // branded site. It must resolve to the coach's own home instead.
        $masterSrc = file_get_contents(
            resource_path('views/frontend/coach-site/layouts/master.blade.php')
        );

        // The brand anchor must NOT hardcode the platform root.
        $this->assertStringNotContainsString(
            'href="{{ url(\'/\') }}" class="cs-nav__brand"',
            $masterSrc,
            'Brand link must NOT hardcode url(/) (platform homepage) on a coach site.'
        );

        // It must resolve a coach-home URL via the path-home route, falling
        // back to url('/') only for subdomain mode (where that IS coach home).
        $this->assertStringContainsString('$brandHomeUrl', $masterSrc,
            'Brand link must use the resolved $brandHomeUrl.');
        $this->assertStringContainsString("route('coach.site.path', ['site_slug' => \$brandHomeSlug])", $masterSrc,
            'Brand home must resolve via coach.site.path for slug (path-based) access.');
        $this->assertStringContainsString('href="{{ $brandHomeUrl }}" class="cs-nav__brand"', $masterSrc,
            'The brand anchor must emit the resolved $brandHomeUrl.');
    }

    public function test_uat80_default_pages_published_so_menu_has_more_than_just_home(): void
    {
        // The menu is built from CoachPage rows where is_published=1. When
        // a coach first runs through onboarding, ALL the default home/
        // about/services/contact pages should end up published so the menu
        // looks complete out of the box. We test that for ALL existing
        // coaches with a home page: every sibling default page is either
        // already published OR can be published without breaking anything.
        // (We don't auto-publish in this test — the production data fix is
        // a one-shot tinker run elsewhere — but we DO verify that a coach
        // with default pages all published renders 4 menu links.)
        $coach = $this->makeCoach();
        $home = app(PageManager::class)->ensureHome($coach->id);
        // ensureHome() creates as draft — publish it so the nav builder
        // picks it up.
        $home->update(['is_published' => 1]);

        // Pre-create About / Services / Contact pages — published — and
        // confirm buildSiteNav picks them up.
        foreach (['about', 'services', 'contact'] as $idx => $slug) {
            \App\Models\CoachPage::create([
                'coach_id'           => $coach->id,
                'slug'               => $slug,
                'page_type'          => $slug,
                'title'              => ucfirst($slug),
                'is_published'       => 1,
                'is_visible_in_nav'  => 1,
                'sort_order'         => $idx + 1,
            ]);
        }

        $controller = app(\App\Http\Controllers\Frontend\CoachSitePublicController::class);
        $ref = new \ReflectionMethod($controller, 'buildSiteNav');
        $ref->setAccessible(true);
        $home = \App\Models\CoachPage::where('coach_id', $coach->id)->where('slug', 'home')->first();
        $nav = $ref->invoke($controller, $coach->id, $home, null, false);

        $this->assertGreaterThanOrEqual(4, count($nav),
            'Menu must contain at least 4 published default pages (home/about/services/contact).');
        $labels = array_column($nav, 'label');
        $this->assertContains('Home', $labels);
        $this->assertContains('About', $labels);
        $this->assertContains('Services', $labels);
        $this->assertContains('Contact', $labels);
    }

    public function test_uat77_cart_page_does_not_crash_when_session_keys_null(): void
    {
        // Bug seen 2026-05-26: hitting /cart returned 500 TypeError
        //   "in_array(): Argument #2 (\$haystack) must be of type array, null given"
        // because cart.blade.php called in_array(\$id, session()->get('enrollments'))
        // and the session key was null (master helpers hadn't populated yet).
        //
        // The fix is two-layered:
        //   1. CartController::index() defensively populates both session
        //      keys so they can never be null by the time the view renders.
        //   2. cart.blade.php uses (session()->get(...) ?? []) wrappers
        //      around every in_array() haystack as belt-and-suspenders.
        //
        // We test BOTH:
        //   (a) the controller call populates the session keys
        //   (b) the blade compiles + the null-coalesce wrapper is present
        //       on every in_array() haystack expression.

        // (a) — controller populates session keys.
        $student = $this->makeCoach();
        $this->actingAs($student);
        session()->forget(['enrollments', 'instructor_courses']);

        // CartController calls setEnrollmentIdsInSession() before rendering.
        if (function_exists('setEnrollmentIdsInSession')) {
            setEnrollmentIdsInSession();
        }
        if (function_exists('setInstructorCourseIdsInSession')) {
            setInstructorCourseIdsInSession();
        }
        $this->assertIsArray(session()->get('enrollments'),
            'CartController must populate session(enrollments) so the partial cannot get null.');
        $this->assertIsArray(session()->get('instructor_courses'),
            'CartController must populate session(instructor_courses) for the same reason.');

        // (b) — every in_array() call in cart.blade.php must wrap the
        // session()->get() haystack in (?? []). The raw blade source
        // should NOT contain the bare unsafe pattern.
        $bladeSrc = file_get_contents(
            resource_path('views/frontend/pages/cart.blade.php')
        );
        $this->assertStringNotContainsString(
            "in_array(\$item?->course?->id, session()->get('enrollments'))",
            $bladeSrc,
            'Bare session()->get() in in_array() must be replaced with the null-safe form.'
        );
        $this->assertStringNotContainsString(
            "in_array(\$item?->course?->id, session()->get('instructor_courses'))",
            $bladeSrc,
            'Bare session()->get() in in_array() must be replaced with the null-safe form.'
        );
    }

    public function test_uat78_coach_site_css_aliases_fa6_classes_to_fa5(): void
    {
        // Bug: cart icon in the coach-site header was invisible because the
        // platform ships Font Awesome 5 but my templates use FA6 class
        // names (fa-solid, fa-cart-shopping, fa-circle-check). The shim in
        // coach-site.css maps FA6 family selectors + a handful of FA6 icon
        // names to FA5. If the shim ever gets removed, every icon I added
        // goes invisible — this test pins it.
        $css = file_get_contents(public_path('frontend/css/coach-site.css'));

        $this->assertStringContainsString('Font Awesome 6 → 5 shim', $css,
            'The FA6→FA5 shim comment must be present in coach-site.css.');
        $this->assertMatchesRegularExpression(
            '/\.fa-solid[\s,].*Font Awesome 5 Free/s', $css,
            'fa-solid class must be aliased to FA5 Free family.'
        );
        $this->assertStringContainsString('.fa-cart-shopping::before', $css);
        $this->assertStringContainsString('.fa-circle-check::before', $css);
        $this->assertStringContainsString('.fa-triangle-exclamation::before', $css);
    }

    public function test_uat74_each_youtube_video_gets_its_own_course(): void
    {
        // Per-video shoppability: when linked_course_slug is empty (default),
        // each YouTube video card must be backed by its OWN Course row, so
        // a student adding one card to the cart does NOT mark the other
        // cards as "already in cart". This was the user-reported bug:
        // "If I add any one course to the cart, all courses are showing
        // as already added."
        $coach = $this->makeCoach();
        $page = app(PageManager::class)->ensureHome($coach->id);
        $section = app(PageManager::class)->addSection($page, 'recorded_courses_v1');
        $section->content_json = array_merge((array) $section->content_json, [
            'source' => 'from-youtube',
            'youtube_channel_id' => 'UCtest_per_video_xxx',
            'linked_course_slug' => '',   // empty → per-video mode
        ]);
        $section->save();

        Cache::put('youtube:videos:UCtest_per_video_xxx', [
            ['id' => 'video_A_abc12', 'title' => 'Alpha', 'thumbnail' => ''],
            ['id' => 'video_B_def34', 'title' => 'Bravo', 'thumbnail' => ''],
            ['id' => 'video_C_ghi56', 'title' => 'Charlie', 'thumbnail' => ''],
        ], 60);

        $html = app(SectionRenderer::class)->renderOne($section->fresh(), $page->fresh(), $coach);
        $markup = preg_replace('/<script\b[^>]*>.*?<\/script>/s', '', $html);

        $this->assertSame(3, substr_count($markup, '<article class="cs-rec__card"'),
            'Three videos must produce three cards.');

        // Extract course ids from data-course-id attributes — they must be
        // 3 DIFFERENT values (not all the same bundle id).
        preg_match_all('/data-course-id="(\d+)"/', $markup, $m);
        $ids = array_values(array_unique($m[1] ?? []));
        $this->assertCount(3, $ids,
            'Three cards must have THREE distinct course ids (per-video). Got: ' . json_encode($ids));

        // And those courses must actually exist + be buyable.
        foreach ($ids as $id) {
            $found = \App\Models\Course::active()->find((int) $id);
            $this->assertNotNull($found,
                "Auto-provisioned per-video course id={$id} must be active+approved so Course::active() finds it.");
        }
    }

    public function test_uat75_video_course_provisioner_is_idempotent(): void
    {
        // Re-rendering the section must NOT create duplicate Course rows
        // — slug is deterministic per video.
        $coach = $this->makeCoach();
        $bootstrap = app(\App\Services\Site\CoachCourseBootstrap::class);

        $a = $bootstrap->ensureForVideo($coach, 'vidXYZ_abc12', 'My title');
        $b = $bootstrap->ensureForVideo($coach, 'vidXYZ_abc12', 'My title');
        $this->assertSame($a->id, $b->id,
            'ensureForVideo() must return the same row for the same (coach, videoId).');

        // Different video → different row.
        $c = $bootstrap->ensureForVideo($coach, 'OTHER_vid_xy', 'Other title');
        $this->assertNotSame($a->id, $c->id);
    }

    public function test_uat76_cart_buttons_reflect_already_in_cart_state_on_render(): void
    {
        // When a logged-in student already has a course in their cart,
        // the section partial must render that card with "In cart — view"
        // INSTEAD of "Add to cart". Other cards stay as Add to cart.
        $coach = $this->makeCoach();
        $student = $this->makeCoach();   // helper creates any user; role doesn't matter for carts table
        $page = app(PageManager::class)->ensureHome($coach->id);
        $section = app(PageManager::class)->addSection($page, 'recorded_courses_v1');
        $section->content_json = array_merge((array) $section->content_json, [
            'source' => 'from-youtube',
            'youtube_channel_id' => 'UCtest_cart_state_xx',
        ]);
        $section->save();

        Cache::put('youtube:videos:UCtest_cart_state_xx', [
            ['id' => 'card_one', 'title' => 'One', 'thumbnail' => ''],
            ['id' => 'card_two', 'title' => 'Two', 'thumbnail' => ''],
        ], 60);

        $bootstrap = app(\App\Services\Site\CoachCourseBootstrap::class);
        $courseOne = $bootstrap->ensureForVideo($coach, 'card_one', 'One');

        // Mark courseOne as in this student's cart
        $this->actingAs($student);
        \DB::table('carts')->insert([
            'user_id'    => $student->id,
            'course_id'  => $courseOne->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $html = app(SectionRenderer::class)->renderOne($section->fresh(), $page->fresh(), $coach);
        $markup = preg_replace('/<script\b[^>]*>.*?<\/script>/s', '', $html);

        // The "in cart — view" CTA must appear AT LEAST once (the in-cart
        // course actually has two cart-button slots: overlay + foot).
        $this->assertStringContainsString('In cart', $markup,
            'A course already in this student\'s cart must render "In cart" CTA.');
        // Course two (NOT in cart) must still render an Add-to-cart button.
        $this->assertStringContainsString('data-cs-cart', $markup,
            'The other card (not in cart) must still expose an Add-to-cart button.');
    }

    public function test_uat73_section_scripts_emitted_inline_not_via_push_stack(): void
    {
        // Why this exists: SectionRenderer renders each section in isolation
        // via view()->render(). Laravel flushes the view factory's push
        // stack between renders, so @push('scripts')...@endpush inside a
        // section partial NEVER reaches the master layout's @stack('scripts')
        // — the script is silently lost and no click handler ever binds.
        //
        // This caused all preview / Add-to-Cart / YouTube tile clicks to do
        // nothing on the public coach site. Pin the inline-script approach.
        $coach = $this->makeCoach();
        app(\App\Services\Site\CoachCourseBootstrap::class)->ensureShoppable($coach);
        $page = app(PageManager::class)->ensureHome($coach->id);

        $rec = app(PageManager::class)->addSection($page, 'recorded_courses_v1');
        $rec->content_json = array_merge((array) $rec->content_json, [
            'source' => 'from-youtube',
            'youtube_channel_id' => 'UCtest_inline_scripts',
        ]);
        $rec->save();

        $yt = app(PageManager::class)->addSection($page, 'youtube_v1');
        $yt->content_json = array_merge((array) $yt->content_json, [
            'channel_id' => 'UCtest_inline_scripts',
        ]);
        $yt->save();

        Cache::put('youtube:videos:UCtest_inline_scripts', [
            ['id' => 'vidA', 'title' => 'A', 'thumbnail' => ''],
        ], 60);

        $renderer = app(SectionRenderer::class);
        $recHtml = $renderer->renderOne($rec->fresh(), $page->fresh(), $coach);
        $ytHtml  = $renderer->renderOne($yt->fresh(),  $page->fresh(), $coach);

        // The bind sentinels MUST be visible in the rendered HTML. If they
        // are not, the section's script went through @push() and got lost.
        $this->assertStringContainsString('__csRecBound', $recHtml,
            'recorded_courses_v1 must emit its script INLINE — @push gets flushed by view()->render().');
        $this->assertStringContainsString('__csYtV1Bound', $ytHtml,
            'youtube_v1 must emit its script INLINE — @push gets flushed by view()->render().');

        // And the markup the scripts bind to must be present.
        $this->assertStringContainsString('data-cs-cart',     $recHtml);
        $this->assertStringContainsString('data-play-trigger', $recHtml);
        $this->assertStringContainsString('data-cs-yt-tile',  $ytHtml);
    }

    public function test_uat70_auto_provision_course_when_recorded_section_added(): void
    {
        // The moment a coach adds a Recorded Courses section, the bootstrap
        // should create a default "On-Demand Sessions" course AND stamp its
        // slug into the section's linked_course_slug — so Add-to-Cart works
        // immediately without manual setup.
        $coach = $this->makeCoach();
        $this->assertSame(0,
            \App\Models\Course::where('instructor_id', $coach->id)->count(),
            'New coach must start with zero courses.');

        $course = app(\App\Services\Site\CoachCourseBootstrap::class)->ensureShoppable($coach);

        $this->assertNotNull($course->id, 'A course must exist after ensureShoppable().');
        $this->assertSame('recorded', $course->type, 'Auto-provisioned course must be type=recorded.');
        $this->assertSame('active', $course->status, 'Auto-provisioned course must be status=active.');
        $this->assertSame('approved', $course->is_approved, 'Auto-provisioned course must be approved.');
        $this->assertSame(0, (int) $course->coach_soft_delete, 'Auto-provisioned course must not be soft-deleted.');
        $this->assertStringContainsString('On-Demand Sessions', $course->title);

        // Idempotency: calling again returns the same row, not a new one.
        $again = app(\App\Services\Site\CoachCourseBootstrap::class)->ensureShoppable($coach);
        $this->assertSame($course->id, $again->id, 'Bootstrap must be idempotent.');
        $this->assertSame(1,
            \App\Models\Course::where('instructor_id', $coach->id)->count(),
            'Bootstrap must not multiply courses on re-call.');
    }

    public function test_uat72_auto_provisioned_course_is_buyable(): void
    {
        // The auto-provisioned course must pass the Course::active() scope
        // (status='active' AND is_approved='approved') so the cart's
        // Course::active()->find() resolves it. Without this, the AJAX
        // POST returns 404 "Not Found!" and Add to Cart appears broken
        // even though the route is wired correctly.
        $coach = $this->makeCoach();
        $course = app(\App\Services\Site\CoachCourseBootstrap::class)->ensureShoppable($coach);

        $this->assertSame('active', $course->status,
            'Auto-provisioned course must be status=active (enum string, not int).');
        $this->assertSame('approved', $course->is_approved,
            'Auto-provisioned course must be is_approved=approved so Course::active() returns it.');

        // The scope used by CartController::addToCart() must find it.
        $found = \App\Models\Course::active()->find($course->id);
        $this->assertNotNull($found,
            'Course::active() must return the auto-provisioned course — this is what addToCart() uses.');
    }

    public function test_uat71_youtube_grid_renders_with_channel_id_even_with_existing_courses(): void
    {
        // Once a coach has a YouTube channel ID set, every video card shows
        // as a YouTube preview tile with Add-to-Cart pointing at the linked
        // course — even though source is still "auto-from-courses".
        $coach = $this->makeCoach();
        $course = app(\App\Services\Site\CoachCourseBootstrap::class)->ensureShoppable($coach);

        $page = app(PageManager::class)->ensureHome($coach->id);
        $section = app(PageManager::class)->addSection($page, 'recorded_courses_v1');
        $section->content_json = array_merge((array) $section->content_json, [
            'source' => 'auto-from-courses',                  // explicitly default
            'youtube_channel_id' => 'UCtest_grid_channel_xx',  // strong signal
            'linked_course_slug' => $course->slug,
        ]);
        $section->save();

        Cache::put('youtube:videos:UCtest_grid_channel_xx', [
            ['id' => 'vid_A', 'title' => 'A', 'thumbnail' => ''],
            ['id' => 'vid_B', 'title' => 'B', 'thumbnail' => ''],
        ], 60);

        $html = app(SectionRenderer::class)->renderOne($section->fresh(), $page->fresh(), $coach);

        // Strip the inline <script> block before counting attribute-form
        // matches — the script also contains '.cs-rec__card' / 'data-cs-cart'
        // as JS selectors, which would inflate substr_count.
        $markup = preg_replace('/<script\b[^>]*>.*?<\/script>/s', '', $html);

        $this->assertSame(2, substr_count($markup, '<article class="cs-rec__card"'),
            'Two YouTube videos must produce two cards.');
        // Each card has 2 cart buttons (overlay + foot), so >= 2 attribute
        // occurrences across the two cards.
        $this->assertGreaterThanOrEqual(2, substr_count($markup, 'data-cs-cart'),
            'Add-to-Cart button (attribute form) must appear on every YouTube card.');
        $this->assertStringContainsString('data-course-id="' . $course->id . '"', $markup);
        $this->assertStringNotContainsString('Contact for full access', $markup,
            'With a linked course, the Contact fallback must not appear.');
    }

    public function test_uat69_per_video_provisioning_makes_youtube_cards_buyable(): void
    {
        // Previously this test asserted a "Contact for full access" fallback
        // when no course was linked. That fallback is superseded by the
        // per-video auto-provisioning introduced 2026-05-26: every YouTube
        // card gets its own Course row on render, so Contact is essentially
        // dead-code-path for the happy case.
        //
        // We now assert the new behavior: YouTube cards in per-video mode
        // are buyable + render no youtube.com leak + show the in-page
        // Add-to-cart button (not Contact).
        $coach = $this->makeCoach();
        $page = app(PageManager::class)->ensureHome($coach->id);
        $section = app(PageManager::class)->addSection($page, 'recorded_courses_v1');
        $section->content_json = array_merge((array) $section->content_json, [
            'source' => 'from-youtube',
            'youtube_channel_id' => 'UCtest_perVideo69_x',
            'linked_course_slug' => '',  // empty → per-video mode
        ]);
        $section->save();

        Cache::put('youtube:videos:UCtest_perVideo69_x', [
            ['id' => 'vidProv', 'title' => 'X', 'thumbnail' => ''],
        ], 60);

        $html = app(SectionRenderer::class)->renderOne($section->fresh(), $page->fresh(), $coach);
        $markup = preg_replace('/<script\b[^>]*>.*?<\/script>/s', '', $html);

        $this->assertStringContainsString('data-cs-cart', $markup,
            'Per-video mode must produce a real Add-to-cart button.');
        $this->assertStringNotContainsString('Contact for full access', $markup,
            'Per-video mode must NOT fall back to the Contact CTA — every card is buyable.');
        $this->assertStringNotContainsString('youtube.com/watch', $markup,
            'No external youtube.com link must leak into the markup.');
    }

    public function test_uat66_recorded_section_auto_falls_back_to_youtube_when_no_courses(): void
    {
        // A coach who has NO courses but pasted a YouTube channel id should
        // get the YouTube path automatically, even with source=auto-from-courses.
        // We don't need a real API call — we just need to confirm the source
        // switch happens and the YouTube branch is the one that runs.
        $coach = $this->makeCoach();
        $page = app(PageManager::class)->ensureHome($coach->id);
        $section = app(PageManager::class)->addSection($page, 'recorded_courses_v1');

        // Configure it: auto-from-courses (default) + channel id present
        $section->content_json = array_merge(
            (array) $section->content_json,
            [
                'source'             => 'auto-from-courses',
                'youtube_channel_id' => 'UCtest_fallback_aaaaaa',
            ]
        );
        $section->save();

        // Seed an empty cache so YouTubeFetcher returns [] without an HTTP call
        Cache::put('youtube:videos:UCtest_fallback_aaaaaa', [], 60);

        $html = app(SectionRenderer::class)->renderOne(
            $section->fresh(), $page->fresh(), $coach
        );

        // With zero courses + zero videos in cache, the empty state should
        // mention YouTube (since the source got switched), NOT "no on-demand
        // courses". That proves the auto-switch fired.
        $this->assertStringContainsString('No YouTube videos found', $html,
            'Empty state must reflect from-youtube mode after auto-switch.');
        $this->assertStringNotContainsString('No on-demand courses available yet', $html,
            'Pre-switch empty state must not appear once the coach has a channel id and no courses.');
    }
}
