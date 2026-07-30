<?php

namespace Tests\Feature\Audit;

use App\Models\CoachPage;
use App\Models\Theme;
use App\Models\ThemeApplication;
use App\Models\ThemeCategory;
use App\Models\User;
use App\Services\Theme\ThemeApplicator;
use App\Services\Theme\ThemeTokenResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 1 — Theme System Foundation tests.
 *
 * Covers:
 *  - Schema sanity (8 new tables + extended columns)
 *  - Token resolver (recursive walk, multiple tokens, no-token strings)
 *  - Applicator (clone theme → coach, with snapshot, supersede chain,
 *    onboarding stamp, brand defaults applied if blank)
 *  - Seeded starter themes integrity (3 themes, 4 pages each)
 */
class ThemeSystemPhase1Test extends TestCase
{
    use DatabaseTransactions;

    private function makeCoach(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role'              => 'instructor',
            'email_verified_at' => now(),
        ], $overrides));
    }

    // ──────────────────────────────────────────────────────────────────
    // SCHEMA SANITY
    // ──────────────────────────────────────────────────────────────────

    public function test_theme_tables_exist(): void
    {
        $this->assertTrue(\Schema::hasTable('themes'));
        $this->assertTrue(\Schema::hasTable('theme_pages'));
        $this->assertTrue(\Schema::hasTable('theme_sections'));
        $this->assertTrue(\Schema::hasTable('theme_categories'));
        $this->assertTrue(\Schema::hasTable('theme_category_pivot'));
        $this->assertTrue(\Schema::hasTable('theme_applications'));
        $this->assertTrue(\Schema::hasTable('theme_audit_log'));
    }

    public function test_coach_landing_pages_extended_with_theme_columns(): void
    {
        $this->assertTrue(\Schema::hasColumn('coach_landing_pages', 'theme_id'));
        $this->assertTrue(\Schema::hasColumn('coach_landing_pages', 'theme_applied_at'));
        $this->assertTrue(\Schema::hasColumn('coach_landing_pages', 'theme_version'));
    }

    public function test_landing_sections_has_theme_origin_column(): void
    {
        $this->assertTrue(\Schema::hasColumn('landing_sections', 'theme_section_origin_id'));
    }

    public function test_users_has_onboarding_theme_chosen_at(): void
    {
        $this->assertTrue(\Schema::hasColumn('users', 'onboarding_theme_chosen_at'));
    }

    // ──────────────────────────────────────────────────────────────────
    // TOKEN RESOLVER
    // ──────────────────────────────────────────────────────────────────

    public function test_token_resolver_replaces_simple_string(): void
    {
        $coach = $this->makeCoach(['name' => 'Priya Sharma', 'email' => 'priya@example.com']);
        $resolver = app(ThemeTokenResolver::class);
        $out = $resolver->resolve(['title' => 'Welcome to {{brand_name}}'], $coach);
        $this->assertStringContainsString('Priya Sharma', $out['title']);
    }

    public function test_token_resolver_walks_nested_arrays(): void
    {
        $coach = $this->makeCoach(['name' => 'Ravi K']);
        $resolver = app(ThemeTokenResolver::class);
        $out = $resolver->resolve([
            'title' => '{{coach_name}}',
            'cards' => [
                ['headline' => 'By {{coach_name}}'],
                ['headline' => 'Trusted teacher'],
            ],
            'count' => 5,
        ], $coach);
        $this->assertEquals('Ravi K', $out['title']);
        $this->assertEquals('By Ravi K', $out['cards'][0]['headline']);
        $this->assertEquals('Trusted teacher', $out['cards'][1]['headline']);
        $this->assertEquals(5, $out['count'], 'Non-string values must passthrough unchanged');
    }

    public function test_token_resolver_leaves_unknown_tokens_intact(): void
    {
        $coach = $this->makeCoach();
        $out = app(ThemeTokenResolver::class)->resolve([
            'x' => '{{totally_made_up_token}}',
        ], $coach);
        $this->assertEquals('{{totally_made_up_token}}', $out['x']);
    }

    public function test_token_resolver_accepts_extra_overrides(): void
    {
        $coach = $this->makeCoach();
        $out = app(ThemeTokenResolver::class)->resolve(
            ['x' => 'Theme: {{theme_name}}'],
            $coach,
            ['theme_name' => 'Modern Yoga Studio']
        );
        $this->assertEquals('Theme: Modern Yoga Studio', $out['x']);
    }

    // ──────────────────────────────────────────────────────────────────
    // APPLICATOR
    // ──────────────────────────────────────────────────────────────────

    public function test_applicator_clones_theme_pages_to_coach(): void
    {
        $theme = Theme::where('slug', 'modern-yoga-studio')->firstOrFail();
        $coach = $this->makeCoach(['name' => 'Anjali V']);

        $result = app(ThemeApplicator::class)->applyToCoach($theme, $coach);

        $this->assertTrue($result['ok']);
        $this->assertEquals(4, $result['pages_created'], 'Modern Yoga Studio seeded with 4 pages');
        $this->assertGreaterThan(10, $result['sections_created']);

        // Coach should now have 4 CoachPage rows
        $this->assertEquals(4, CoachPage::forCoach($coach->id)->count());
    }

    public function test_applicator_resolves_tokens_in_section_content(): void
    {
        $theme = Theme::where('slug', 'modern-yoga-studio')->firstOrFail();
        $coach = $this->makeCoach(['name' => 'Priya Sharma']);

        app(ThemeApplicator::class)->applyToCoach($theme, $coach);

        $home = CoachPage::forCoach($coach->id)->where('page_type', 'home')->firstOrFail();
        $hero = $home->sections()->where('section_type', 'hero_v1')->first();

        $this->assertNotNull($hero);
        $this->assertStringContainsString('Priya Sharma', $hero->content_json['headline']);
        $this->assertStringNotContainsString('{{', $hero->content_json['headline']);
    }

    public function test_applicator_creates_theme_application_record(): void
    {
        $theme = Theme::where('slug', 'holistic-wellness')->firstOrFail();
        $coach = $this->makeCoach();

        app(ThemeApplicator::class)->applyToCoach($theme, $coach);

        $app = ThemeApplication::where('coach_id', $coach->id)->first();
        $this->assertNotNull($app);
        $this->assertEquals($theme->id, $app->theme_id);
        $this->assertNotNull($app->applied_at);
        $this->assertNull($app->superseded_at, 'Latest application must be active (no superseded_at)');
    }

    public function test_applicator_supersedes_previous_application_on_reapply(): void
    {
        $theme1 = Theme::where('slug', 'modern-yoga-studio')->firstOrFail();
        $theme2 = Theme::where('slug', 'executive-business-coach')->firstOrFail();
        $coach = $this->makeCoach();

        app(ThemeApplicator::class)->applyToCoach($theme1, $coach);
        app(ThemeApplicator::class)->applyToCoach($theme2, $coach);

        $apps = ThemeApplication::where('coach_id', $coach->id)->orderBy('id')->get();
        $this->assertCount(2, $apps);
        $this->assertNotNull($apps[0]->superseded_at, 'First application must be superseded');
        $this->assertNull($apps[1]->superseded_at,    'Second application must be active');
    }

    public function test_applicator_stamps_onboarding_completion(): void
    {
        $theme = Theme::where('slug', 'modern-yoga-studio')->firstOrFail();
        $coach = $this->makeCoach();

        $this->assertNull($coach->onboarding_theme_chosen_at);
        app(ThemeApplicator::class)->applyToCoach($theme, $coach);
        $this->assertNotNull($coach->fresh()->onboarding_theme_chosen_at);
    }

    public function test_applicator_stores_origin_section_id_on_each_clone(): void
    {
        $theme = Theme::where('slug', 'modern-yoga-studio')->firstOrFail();
        $coach = $this->makeCoach();

        app(ThemeApplicator::class)->applyToCoach($theme, $coach);

        $page = CoachPage::forCoach($coach->id)->first();
        $section = $page->sections()->first();
        $this->assertNotNull($section->theme_section_origin_id,
            'Each cloned section must record which theme_section it came from');
    }

    public function test_applicator_links_site_to_theme(): void
    {
        $theme = Theme::where('slug', 'modern-yoga-studio')->firstOrFail();
        $coach = $this->makeCoach();

        app(ThemeApplicator::class)->applyToCoach($theme, $coach);

        $site = \App\Models\CoachLandingPage::where('added_by', $coach->id)->first();
        $this->assertEquals($theme->id, $site->theme_id);
        $this->assertNotNull($site->theme_applied_at);
        $this->assertEquals($theme->version, $site->theme_version);
    }

    public function test_applicator_replaces_existing_pages_in_replace_mode(): void
    {
        $coach = $this->makeCoach();
        // First create some pages manually
        \App\Models\CoachPage::create([
            'coach_id' => $coach->id, 'slug' => 'leftover', 'page_type' => 'custom',
            'title' => 'Leftover', 'is_published' => 0, 'sort_order' => 0,
        ]);
        $this->assertEquals(1, CoachPage::forCoach($coach->id)->count());

        $theme = Theme::where('slug', 'holistic-wellness')->firstOrFail();
        app(ThemeApplicator::class)->applyToCoach($theme, $coach, 'replace');

        $pages = CoachPage::forCoach($coach->id)->get();
        $this->assertEquals(4, $pages->count(), 'Replace mode wipes existing + applies theme');
        $this->assertFalse($pages->pluck('slug')->contains('leftover'));
    }

    // ──────────────────────────────────────────────────────────────────
    // SEEDED THEMES INTEGRITY
    // ──────────────────────────────────────────────────────────────────

    public function test_three_starter_themes_seeded_and_enabled(): void
    {
        foreach (['modern-yoga-studio', 'executive-business-coach', 'holistic-wellness'] as $slug) {
            $t = Theme::where('slug', $slug)->first();
            $this->assertNotNull($t, "Theme $slug must be seeded");
            $this->assertTrue($t->is_enabled, "Theme $slug must be enabled at launch");
            $this->assertNotEmpty($t->default_colors);
            $this->assertNotEmpty($t->default_fonts);
        }
    }

    public function test_categories_seeded_and_linked(): void
    {
        $this->assertGreaterThanOrEqual(4, ThemeCategory::count());
        $yoga = Theme::where('slug', 'modern-yoga-studio')->first();
        $this->assertGreaterThan(0, $yoga->categories()->count());
    }

    public function test_each_theme_has_required_page_set(): void
    {
        foreach (['modern-yoga-studio', 'executive-business-coach', 'holistic-wellness'] as $slug) {
            $t = Theme::where('slug', $slug)->first();
            $pages = $t->pages()->pluck('page_type')->toArray();
            $this->assertContains('home',     $pages, "$slug must have Home page");
            $this->assertContains('about',    $pages, "$slug must have About page");
            $this->assertContains('services', $pages, "$slug must have Services page");
            $this->assertContains('contact',  $pages, "$slug must have Contact page");
        }
    }

    public function test_theme_home_has_required_sections(): void
    {
        $home = Theme::where('slug', 'modern-yoga-studio')->first()
            ->pages()->where('page_type', 'home')->first();
        $types = $home->sections()->pluck('section_type')->toArray();

        // Home should at minimum have hero, services, lead form, footer
        $this->assertContains('hero_v1', $types);
        $this->assertContains('services_grid_v1', $types);
        $this->assertContains('lead_form_v1', $types);
        $this->assertContains('footer_v1', $types);
    }

    public function test_theme_only_visible_to_coach_when_enabled(): void
    {
        $theme = Theme::create([
            'name' => 'Hidden Test Theme',
            'slug' => 'hidden-test-' . Str::random(6),
            'is_enabled' => false,
            'version' => '1.0',
        ]);
        $enabledIds = Theme::enabled()->pluck('id')->toArray();
        $this->assertNotContains($theme->id, $enabledIds);

        $theme->update(['is_enabled' => true]);
        $enabledIds = Theme::enabled()->pluck('id')->toArray();
        $this->assertContains($theme->id, $enabledIds);
    }

    // ──────────────────────────────────────────────────────────────────
    // PHASE 2 — ADMIN ROUTES REGISTERED
    // ──────────────────────────────────────────────────────────────────

    public function test_admin_theme_routes_registered(): void
    {
        $names = collect(\Route::getRoutes())->mapWithKeys(fn ($r) => [$r->getName() => true])->toArray();
        foreach ([
            'admin.themes.index', 'admin.themes.create', 'admin.themes.store',
            'admin.themes.edit',  'admin.themes.update', 'admin.themes.toggle',
            'admin.themes.duplicate', 'admin.themes.destroy', 'admin.themes.preview',
            'admin.themes.usage',
            'admin.themes.categories.index', 'admin.themes.categories.store',
            'admin.themes.categories.update', 'admin.themes.categories.destroy',
        ] as $name) {
            $this->assertArrayHasKey($name, $names, "Admin route {$name} not registered");
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // PHASE 3 — ONBOARDING + JOB
    // ──────────────────────────────────────────────────────────────────

    public function test_onboarding_routes_registered(): void
    {
        $names = collect(\Route::getRoutes())->mapWithKeys(fn ($r) => [$r->getName() => true])->toArray();
        foreach ([
            'onboarding.theme-picker', 'onboarding.apply-theme',
            'onboarding.progress',     'onboarding.skip',
        ] as $name) {
            $this->assertArrayHasKey($name, $names, "Onboarding route {$name} not registered");
        }
    }

    public function test_apply_theme_job_executes_synchronously(): void
    {
        $theme = Theme::where('slug', 'modern-yoga-studio')->firstOrFail();
        $coach = $this->makeCoach();

        $job = new \App\Jobs\ApplyThemeJob($theme->id, $coach->id);
        $job->handle(app(\App\Services\Theme\ThemeApplicator::class));

        $this->assertEquals(4, CoachPage::forCoach($coach->id)->count());
        $this->assertNotNull(\App\Models\ThemeApplication::where('coach_id', $coach->id)->first());
    }

    public function test_apply_theme_job_skips_disabled_theme(): void
    {
        $theme = Theme::create([
            'name' => 'Disabled Test', 'slug' => 'disabled-' . Str::random(6),
            'is_enabled' => false, 'version' => '1.0',
        ]);
        $coach = $this->makeCoach();

        $job = new \App\Jobs\ApplyThemeJob($theme->id, $coach->id);
        $job->handle(app(\App\Services\Theme\ThemeApplicator::class));

        // Disabled theme should NOT have applied
        $this->assertEquals(0, CoachPage::forCoach($coach->id)->count());
    }

    // ──────────────────────────────────────────────────────────────────
    // PHASE 4 — THEME SWITCHING ROUTES
    // ──────────────────────────────────────────────────────────────────

    public function test_coach_theme_picker_routes_registered(): void
    {
        $names = collect(\Route::getRoutes())->mapWithKeys(fn ($r) => [$r->getName() => true])->toArray();
        $this->assertArrayHasKey('instructor.web-page.theme-picker', $names);
        $this->assertArrayHasKey('instructor.web-page.change-theme', $names);
    }

    public function test_changing_theme_replaces_existing_pages(): void
    {
        $theme1 = Theme::where('slug', 'modern-yoga-studio')->firstOrFail();
        $theme2 = Theme::where('slug', 'executive-business-coach')->firstOrFail();
        $coach = $this->makeCoach();

        app(\App\Services\Theme\ThemeApplicator::class)->applyToCoach($theme1, $coach);
        $beforeCount = CoachPage::forCoach($coach->id)->count();

        // Switch to theme2
        app(\App\Services\Theme\ThemeApplicator::class)->applyToCoach($theme2, $coach, 'replace');

        // New pages should match theme2's count
        $afterCount = CoachPage::forCoach($coach->id)->count();
        $this->assertEquals(4, $afterCount, 'Business theme has 4 pages');

        // Site's theme_id should now be theme2
        $site = \App\Models\CoachLandingPage::where('added_by', $coach->id)->first();
        $this->assertEquals($theme2->id, $site->theme_id);
    }

    public function test_active_usage_count_returns_only_active_applications(): void
    {
        $theme = Theme::where('slug', 'modern-yoga-studio')->firstOrFail();
        $coach1 = $this->makeCoach();
        $coach2 = $this->makeCoach();
        $coach3 = $this->makeCoach();

        app(ThemeApplicator::class)->applyToCoach($theme, $coach1);
        app(ThemeApplicator::class)->applyToCoach($theme, $coach2);
        app(ThemeApplicator::class)->applyToCoach($theme, $coach3);

        // Now coach2 switches away
        $otherTheme = Theme::where('slug', 'holistic-wellness')->firstOrFail();
        app(ThemeApplicator::class)->applyToCoach($otherTheme, $coach2);

        $this->assertEquals(2, $theme->fresh()->activeUsageCount(),
            'Only coach1 + coach3 should count as active on Yoga theme');
    }
}
