<?php

namespace Tests\Feature\CoachSite;

use App\Models\CoachPage;
use App\Models\CoachPageSection;
use App\Models\CoachTrainer;
use App\Models\TrainerSessionPackage;
use App\Models\User;
use App\Services\Site\SectionRegistry;
use App\Services\Site\SectionRenderer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 2026-07-15 — Trainer feature Phase 1: entity + session packages + public
 * Trainer Detail Page + Trainers website-builder section. Locks the model
 * contracts (slug, tenant scope, package label) and the render behaviour.
 */
class TrainerPhase1Test extends TestCase
{
    use DatabaseTransactions;

    private function coach(): User
    {
        return User::factory()->create(['role' => 'instructor']);
    }

    private function trainer(User $coach, array $attrs = []): CoachTrainer
    {
        return CoachTrainer::create(array_merge([
            'coach_id'   => $coach->id,
            'name'       => 'Virendra Kumar',
            'slug'       => CoachTrainer::uniqueSlug($coach->id, 'Virendra Kumar'),
            'is_active'  => true,
            'sort_order' => 1,
        ], $attrs));
    }

    private function renderSection(?User $coach, array $content): string
    {
        $page = new CoachPage(['coach_id' => $coach->id ?? 1, 'slug' => 'home', 'page_type' => 'home']);
        $section = new CoachPageSection(['section_type' => 'trainers_v1', 'content_json' => $content, 'is_visible' => true]);
        $section->setRelation('page', $page);
        return app(SectionRenderer::class)->renderOne($section, $page, $coach);
    }

    // ───────────────── registry ─────────────────

    public function test_trainers_section_registered_with_clean_defaults(): void
    {
        $this->assertTrue(SectionRegistry::exists('trainers_v1'));
        $this->assertNotEmpty(SectionRegistry::defaults('trainers_v1'));
        $this->assertSame([], SectionRegistry::validate('trainers_v1', SectionRegistry::defaults('trainers_v1')));
    }

    // ───────────────── model contracts ─────────────────

    public function test_unique_slug_is_per_coach_and_deduplicates(): void
    {
        $coach = $this->coach();
        $a = $this->trainer($coach, ['name' => 'Virendra Kumar']);
        $b = $this->trainer($coach, ['name' => 'Virendra Kumar']);
        $this->assertSame('virendra-kumar', $a->slug);
        $this->assertNotSame($a->slug, $b->slug, 'second trainer with same name gets a distinct slug');

        // A different coach may reuse the same slug (tenant-scoped uniqueness).
        $other = $this->coach();
        $c = $this->trainer($other, ['name' => 'Virendra Kumar']);
        $this->assertSame('virendra-kumar', $c->slug);
    }

    public function test_for_coach_scope_isolates_tenants(): void
    {
        $c1 = $this->coach();
        $c2 = $this->coach();
        $this->trainer($c1);
        $this->assertSame(1, CoachTrainer::forCoach($c1->id)->count());
        $this->assertSame(0, CoachTrainer::forCoach($c2->id)->count(), 'coach 2 sees none of coach 1 trainers');
    }

    public function test_package_label_and_scope(): void
    {
        $coach = $this->coach();
        $t = $this->trainer($coach);
        $pkg = TrainerSessionPackage::create([
            'trainer_id' => $t->id, 'coach_id' => $coach->id,
            'sessions' => 5, 'validity_value' => 15, 'validity_unit' => 'days',
            'price' => 7000, 'currency' => 'INR', 'is_active' => true, 'sort_order' => 1,
        ]);
        $this->assertSame('5 Sessions · 15 Days · ₹7,000', $pkg->label());
        // Phase 5 — public doc-format label.
        $this->assertSame('5 Session Validity 15 days – ₹7,000', $pkg->sessionLabel());
        $this->assertSame(1, TrainerSessionPackage::forCoach($coach->id)->count());
        $this->assertSame(0, TrainerSessionPackage::forCoach($this->coach()->id)->count());
    }

    public function test_booking_taxonomy_defaults_and_overrides(): void
    {
        $coach = $this->coach();
        // Empty config → sensible defaults.
        $t = $this->trainer($coach);
        $this->assertSame(CoachTrainer::DEFAULT_PLAN_TYPES, $t->planTypeOptions());
        $this->assertSame(CoachTrainer::DEFAULT_COURSE_TYPES, $t->courseTypeOptions());
        $this->assertSame(CoachTrainer::DEFAULT_REASONS, $t->reasonOptions());

        // Coach-set config wins (white-label).
        $t2 = $this->trainer($coach, [
            'name' => 'Custom', 'plan_types' => ['In-Person'], 'course_types' => ['Group'], 'reasons' => ['Rehab'],
        ]);
        $this->assertSame(['In-Person'], $t2->planTypeOptions());
        $this->assertSame(['Group'], $t2->courseTypeOptions());
        $this->assertSame(['Rehab'], $t2->reasonOptions());
    }

    // ───────────────── section render ─────────────────

    public function test_section_renders_active_trainers_and_links_to_detail(): void
    {
        $coach = $this->coach();
        $t = $this->trainer($coach, ['name' => 'Mansi Rawat', 'specialisation' => 'Hatha Yoga', 'experience' => '8 yrs']);
        TrainerSessionPackage::create([
            'trainer_id' => $t->id, 'coach_id' => $coach->id, 'sessions' => 3,
            'validity_value' => 30, 'validity_unit' => 'days', 'price' => 3000, 'is_active' => true, 'sort_order' => 1,
        ]);

        $html = $this->renderSection($coach, ['title' => 'Our Trainers', 'columns' => '3', 'limit' => 0]);
        $this->assertStringContainsString('Mansi Rawat', $html);
        $this->assertStringContainsString('Hatha Yoga', $html);
        $this->assertStringContainsString('/trainers/' . $t->slug, $html, 'card links to detail page');
    }

    public function test_section_hides_inactive_trainers_and_is_tenant_safe(): void
    {
        $coach = $this->coach();
        $this->trainer($coach, ['name' => 'Hidden Coach', 'is_active' => false]);
        // Another coach's active trainer must never leak into this coach's section.
        $other = $this->coach();
        $this->trainer($other, ['name' => 'Other Coach Trainer', 'is_active' => true]);

        $html = $this->renderSection($coach, ['title' => 'Our Trainers']);
        $this->assertStringNotContainsString('Hidden Coach', $html);
        $this->assertStringNotContainsString('Other Coach Trainer', $html);
    }

    // ───────────────── detail page ─────────────────

    public function test_detail_view_renders_packages_and_book_button(): void
    {
        $coach = $this->coach();
        $t = $this->trainer($coach, ['name' => 'Yoga Guru', 'bio' => "Line1\nLine2"]);
        $packages = collect([
            TrainerSessionPackage::create([
                'trainer_id' => $t->id, 'coach_id' => $coach->id, 'name' => 'Starter',
                'sessions' => 5, 'validity_value' => 15, 'validity_unit' => 'days', 'price' => 7000, 'is_active' => true, 'sort_order' => 1,
            ]),
        ]);
        $brand = app(\App\Services\BrandResolver::class)->forCoach($coach->id);
        $html = view('frontend.coach-site.trainer-detail', compact('t', 'packages', 'coach', 'brand') + ['trainer' => $t, 'site' => null])->render();

        $this->assertStringContainsString('Yoga Guru', $html);
        $this->assertStringContainsString('td-book', $html, 'Book Now wired to the dedicated payment popup');
        $this->assertStringContainsString('data-package-id="' . $packages->first()->id . '"', $html);
        $this->assertStringContainsString('coach/trainer-booking', $html, 'popup posts to the trainer-booking endpoint');
        $this->assertStringContainsString('7,000', $html);
    }
}
