<?php

namespace Tests\Feature\Domain;

use App\Models\User;
use App\Services\Site\SectionRegistry;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * "Featured Courses Carousel" builder section (2026-07-08). The coach stores an
 * ORDERED list of course ids; the section pulls course data LIVE and renders a
 * carousel. Tenant-safe: only the coach's OWN approved+active courses render,
 * in the stored order, regardless of what ids are in content_json.
 */
class FeaturedCoursesCarouselTest extends TestCase
{
    use DatabaseTransactions;

    private function coach(): User
    {
        return User::find(DB::table('users')->insertGetId([
            'role' => 'instructor', 'name' => 'Coach', 'email' => 'c' . uniqid() . '@t.local',
            'password' => bcrypt('x'), 'status' => 'active', 'is_banned' => 'no',
            'created_at' => now(), 'updated_at' => now(),
        ]));
    }

    private function course(User $coach, string $title, array $over = []): int
    {
        return DB::table('courses')->insertGetId(array_merge([
            'title' => $title, 'slug' => \Illuminate\Support\Str::slug($title) . '-' . uniqid(),
            'instructor_id' => $coach->id, 'added_by' => $coach->id,
            'is_approved' => 'approved', 'status' => 'active', 'type' => 'recorded',
            'coach_soft_delete' => 0, 'price' => 0, 'discount' => 0,
            'description' => 'About ' . $title, 'created_at' => now(), 'updated_at' => now(),
        ], $over));
    }

    private function render(User $coach, array $courseIds): string
    {
        $page = (object) ['slug' => 'home'];
        return view('frontend.coach-site.sections.featured_courses_v1', [
            'content' => ['title' => 'Featured', 'course_ids' => $courseIds],
            'sectionId' => 999,
            'coach' => $coach,
            'page' => $page,
            'isOwnerPreview' => false,
            'appearanceStyle' => '',
        ])->render();
    }

    /* ── registry ────────────────────────────────────────────────────── */

    public function test_section_is_registered_with_a_course_picker(): void
    {
        $this->assertTrue(SectionRegistry::exists('featured_courses_v1'));
        $def = SectionRegistry::get('featured_courses_v1');
        $this->assertSame('course_picker', $def['schema']['course_ids']['type'] ?? null);
        foreach (['autoplay', 'autoplay_speed', 'slides_desktop', 'arrows', 'dots', 'show_mode', 'btn_text'] as $f) {
            $this->assertArrayHasKey($f, $def['schema'], "schema has {$f}");
        }
    }

    /* ── rendering: order + live data ────────────────────────────────── */

    public function test_renders_selected_courses_in_stored_order(): void
    {
        $coach = $this->coach();
        $a = $this->course($coach, 'Alpha Course');
        $b = $this->course($coach, 'Bravo Course');

        // Stored order B then A → B must appear first in the HTML.
        $html = $this->render($coach, [$b, $a]);

        $this->assertStringContainsString('Bravo Course', $html);
        $this->assertStringContainsString('Alpha Course', $html);
        $this->assertLessThan(
            strpos($html, 'Alpha Course'),
            strpos($html, 'Bravo Course'),
            'coach-chosen order (B before A) must be preserved'
        );
        // CTA links to the course detail page (where add-to-cart works).
        $this->assertStringContainsString('/course/', $html);
        $this->assertStringContainsString('data-fc', $html, 'carousel wrapper present');
    }

    public function test_tenant_isolation_and_publish_filter(): void
    {
        $coach = $this->coach();
        $other = $this->coach();

        $mine    = $this->course($coach, 'My Public Course');
        $draft   = $this->course($coach, 'My Draft Course', ['status' => 'is_draft']);
        $unappr  = $this->course($coach, 'My Pending Course', ['is_approved' => 'pending']);
        $foreign = $this->course($other, 'Rival Coach Course');

        // All four ids stuffed in — only the coach's own published one may show.
        $html = $this->render($coach, [$mine, $draft, $unappr, $foreign]);

        $this->assertStringContainsString('My Public Course', $html);
        $this->assertStringNotContainsString('My Draft Course', $html, 'draft excluded');
        $this->assertStringNotContainsString('My Pending Course', $html, 'unapproved excluded');
        $this->assertStringNotContainsString('Rival Coach Course', $html, 'another coach\'s course must NEVER render');
    }

    public function test_empty_selection_renders_nothing_visible(): void
    {
        $coach = $this->coach();
        $html = $this->render($coach, []);
        $this->assertStringNotContainsString('cs-fc__track', $html, 'no carousel when nothing selected');
    }
}
