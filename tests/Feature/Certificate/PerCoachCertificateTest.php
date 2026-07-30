<?php

namespace Tests\Feature\Certificate;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\CertificateBuilder\app\Models\CertificateBuilder;
use Modules\CertificateBuilder\app\Models\CertificateBuilderItem;
use Tests\TestCase;

/**
 * 2026-06-12 — per-coach branded certificate resolution.
 *
 * A coach with their own template gets it; a coach without one falls back to
 * the platform/global default (coach_id NULL). Backward-compatible.
 */
class PerCoachCertificateTest extends TestCase
{
    use DatabaseTransactions;

    private function seedGlobal(): void
    {
        DB::table('certificate_builders')->insert([
            'coach_id' => null, 'title' => 'GLOBAL TITLE', 'sub_title' => 'g', 'description' => 'g',
            'background' => 'global-bg.png', 'signature' => 'global-sig.png',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('certificate_builder_items')->insert([
            'coach_id' => null, 'element_id' => 'title', 'x_position' => '10', 'y_position' => '10',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_coach_with_own_template_gets_it(): void
    {
        $this->seedGlobal();
        $coach = User::factory()->create(['role' => 'instructor', 'coach_id' => null]);
        DB::table('certificate_builders')->insert([
            'coach_id' => $coach->id, 'title' => 'COACH TITLE', 'sub_title' => 'c', 'description' => 'c',
            'background' => 'coach-bg.png', 'signature' => 'coach-sig.png',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('certificate_builder_items')->insert([
            'coach_id' => $coach->id, 'element_id' => 'title', 'x_position' => '99', 'y_position' => '99',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $cert = CertificateBuilder::forCoach($coach->id);
        $this->assertSame('COACH TITLE', $cert->title, 'coach must get their OWN template');

        $items = CertificateBuilderItem::forCoach($coach->id);
        $this->assertSame('99', (string) $items->firstWhere('element_id', 'title')->x_position);
    }

    public function test_partial_coach_layout_backfills_missing_elements(): void
    {
        // 2026-07-09 — a coach who positioned SOME elements (title) but not
        // others (description) used to get ONLY their own rows, so the
        // description rendered un-positioned (top:0) and overlapped the header.
        // forCoach() must now backfill the missing element from the global default.
        $this->seedGlobal(); // global 'title' @ 10,10
        DB::table('certificate_builder_items')->insert([
            'coach_id' => null, 'element_id' => 'description', 'x_position' => '0', 'y_position' => '281',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $coach = User::factory()->create(['role' => 'instructor', 'coach_id' => null]);
        DB::table('certificate_builder_items')->insert([
            'coach_id' => $coach->id, 'element_id' => 'title', 'x_position' => '99', 'y_position' => '99',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $items = CertificateBuilderItem::forCoach($coach->id);

        // Coach keeps their own title position…
        $this->assertSame('99', (string) $items->firstWhere('element_id', 'title')->x_position);
        // …and the un-positioned description is backfilled from the global default.
        $desc = $items->firstWhere('element_id', 'description');
        $this->assertNotNull($desc, 'missing element must be backfilled, not dropped');
        $this->assertSame('281', (string) $desc->y_position, 'backfilled description sits at the global default, not top:0');
    }

    public function test_reset_layout_restores_clean_default_positions(): void
    {
        // A coach whose text has been dragged into a mess (description at the top,
        // overlapping the header) can restore a clean layout in one click.
        $coach = User::factory()->create(['role' => 'instructor', 'coach_id' => null]);
        DB::table('certificate_builder_items')->insert([
            ['coach_id' => $coach->id, 'element_id' => 'description', 'x_position' => '0', 'y_position' => '20',
                'created_at' => now(), 'updated_at' => now()],
            ['coach_id' => $coach->id, 'element_id' => 'title', 'x_position' => '0', 'y_position' => '480',
                'created_at' => now(), 'updated_at' => now()],
        ]);
        $this->actingAs($coach);

        $req = \Illuminate\Http\Request::create('/', 'POST');
        $req->headers->set('Accept', 'application/json');
        app(\App\Http\Controllers\Frontend\Coach\CoachCertificateBuilderController::class)->resetLayout($req);

        $items = CertificateBuilderItem::where('coach_id', $coach->id)->get()->keyBy('element_id');
        $this->assertSame(210, (int) $items['title']->y_position, 'title back to default');
        $this->assertSame(245, (int) $items['sub_title']->y_position, 'sub_title default seeded');
        $this->assertSame(281, (int) $items['description']->y_position, 'description no longer at the top');
        $this->assertSame(392, (int) $items['signature']->y_position, 'signature default');
        // Only THIS coach is affected.
        $this->assertSame(0, CertificateBuilderItem::whereNotNull('coach_id')->where('coach_id', '!=', $coach->id)->count());
    }

    public function test_coach_without_template_falls_back_to_global(): void
    {
        $this->seedGlobal();
        $coach = User::factory()->create(['role' => 'instructor', 'coach_id' => null]);

        $cert = CertificateBuilder::forCoach($coach->id);
        $this->assertSame('GLOBAL TITLE', $cert->title, 'a coach without a template uses the global default');

        $items = CertificateBuilderItem::forCoach($coach->id);
        $this->assertSame('10', (string) $items->firstWhere('element_id', 'title')->x_position);
    }

    public function test_coach_without_artwork_inherits_global_background_and_signature(): void
    {
        // The "plain box" fix: a coach who only edited TEXT (no background /
        // signature uploaded) must still render with the platform artwork.
        $this->seedGlobal(); // background 'global-bg.png', signature 'global-sig.png'
        $coach = User::factory()->create(['role' => 'instructor', 'coach_id' => null]);
        DB::table('certificate_builders')->insert([
            'coach_id' => $coach->id, 'title' => 'COACH WORDS', 'sub_title' => 's', 'description' => 'd',
            'background' => null, 'signature' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $cert = CertificateBuilder::forCoach($coach->id);
        $this->assertSame('COACH WORDS', $cert->title, 'coach keeps their own wording');
        $this->assertSame('global-bg.png', $cert->background, 'empty background falls back to the global artwork');
        $this->assertSame('global-sig.png', $cert->signature, 'empty signature falls back to the global artwork');
    }

    public function test_null_coach_returns_global(): void
    {
        $this->seedGlobal();
        $this->assertSame('GLOBAL TITLE', CertificateBuilder::forCoach(null)->title);
    }

    public function test_coach_template_does_not_leak_to_another_coach(): void
    {
        $this->seedGlobal();
        $coachA = User::factory()->create(['role' => 'instructor', 'coach_id' => null]);
        $coachB = User::factory()->create(['role' => 'instructor', 'coach_id' => null]);
        DB::table('certificate_builders')->insert([
            'coach_id' => $coachA->id, 'title' => 'A ONLY', 'sub_title' => '', 'description' => '',
            'background' => '', 'signature' => '', 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Coach B (no own template) must NOT see Coach A's — gets global.
        $this->assertSame('GLOBAL TITLE', CertificateBuilder::forCoach($coachB->id)->title);
        $this->assertSame('A ONLY', CertificateBuilder::forCoach($coachA->id)->title);
    }
}
