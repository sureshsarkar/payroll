<?php

namespace Tests\Feature\Domain;

use App\Models\CoachBrandSetting;
use App\Models\User;
use App\Services\Brand;
use App\Services\BrandResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Per-coach white-label — Phase 1 (foundation).
 *
 * Five contracts under test:
 *
 *   1. Schema is present and indexed correctly.
 *   2. firstOrCreateForCoach is idempotent and creates a NULL row
 *      for new coaches (so unset coaches inherit platform brand).
 *   3. BrandResolver composes coach overrides + platform defaults
 *      correctly — coach value wins when set, platform fills gaps,
 *      hardcoded fallback covers the rest.
 *   4. resolver->current() resolves to the logged-in coach.
 *   5. The save event invalidates the cache so changes take effect
 *      on the next request without the 60s TTL delay.
 */
class CoachBrandWhiteLabelTest extends TestCase
{
    use DatabaseTransactions;

    /* ───────── schema ───────── */

    public function test_coach_brand_settings_table_exists_with_required_columns(): void
    {
        $this->assertTrue(\Schema::hasTable('coach_brand_settings'));
        foreach ([
            'id', 'coach_id', 'brand_name', 'logo_path', 'favicon_path',
            'primary_color', 'accent_color',
            'support_email', 'support_phone', 'terms_url', 'privacy_url',
            'footer_text', 'email_signature',
            'created_at', 'updated_at',
        ] as $col) {
            $this->assertTrue(
                \Schema::hasColumn('coach_brand_settings', $col),
                "coach_brand_settings must have $col column"
            );
        }
    }

    public function test_unique_index_enforces_one_row_per_coach(): void
    {
        $coach = User::factory()->create(['role' => 'instructor']);
        CoachBrandSetting::create(['coach_id' => $coach->id]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        CoachBrandSetting::create(['coach_id' => $coach->id]);
    }

    /* ───────── helper idempotency ───────── */

    public function test_first_or_create_for_coach_is_idempotent(): void
    {
        $coach = User::factory()->create(['role' => 'instructor']);

        $a = CoachBrandSetting::firstOrCreateForCoach($coach->id);
        // Force fresh fetch (cache may hold $a).
        CoachBrandSetting::forgetCacheForCoach($coach->id);
        $b = CoachBrandSetting::firstOrCreateForCoach($coach->id);

        $this->assertSame($a->id, $b->id);
        $this->assertNull($a->brand_name, 'new row must have NULL overrides so resolver falls back to platform');
    }

    /* ───────── resolver composition ───────── */

    public function test_resolver_returns_platform_defaults_when_coach_has_no_overrides(): void
    {
        $coach = User::factory()->create(['role' => 'instructor']);
        CoachBrandSetting::firstOrCreateForCoach($coach->id); // NULL row

        $brand = app(BrandResolver::class)->forCoach($coach->id);

        $this->assertTrue($brand->isPlatformDefault);
        $this->assertNotEmpty($brand->name, 'platform name must fall through');
        $this->assertNotEmpty($brand->primaryColor);
        $this->assertNotEmpty($brand->supportEmail);
    }

    public function test_resolver_uses_coach_override_when_set(): void
    {
        $coach = User::factory()->create(['role' => 'instructor']);
        $row = CoachBrandSetting::firstOrCreateForCoach($coach->id);
        $row->fill([
            'brand_name'   => 'Acme Academy',
            'primary_color'=> '#ff0000',
            'support_email'=> 'help@acme.test',
            'footer_text'  => 'Acme HQ',
        ])->save();

        $brand = app(BrandResolver::class)->forCoach($coach->id);

        $this->assertSame('Acme Academy', $brand->name);
        $this->assertSame('#ff0000',      $brand->primaryColor);
        $this->assertSame('help@acme.test', $brand->supportEmail);
        $this->assertSame('Acme HQ',      $brand->footerText);
        $this->assertFalse($brand->isPlatformDefault);
    }

    public function test_resolver_falls_back_per_field(): void
    {
        // Coach sets ONLY brand_name + primary_color. Everything else
        // should fall through to platform / hardcoded defaults.
        $coach = User::factory()->create(['role' => 'instructor']);
        $row = CoachBrandSetting::firstOrCreateForCoach($coach->id);
        $row->fill([
            'brand_name'    => 'Mixed Brand',
            'primary_color' => '#abcdef',
        ])->save();

        $brand = app(BrandResolver::class)->forCoach($coach->id);

        $this->assertSame('Mixed Brand', $brand->name, 'coach override wins');
        $this->assertSame('#abcdef', $brand->primaryColor);
        $this->assertNotEmpty($brand->supportEmail, 'support email must fall back to platform');
        $this->assertNotEmpty($brand->footerText,    'footer text must fall back to platform');
    }

    /* ───────── current() resolution ───────── */

    public function test_current_returns_logged_in_coach_brand(): void
    {
        $coach = User::factory()->create(['role' => 'instructor']);
        $row = CoachBrandSetting::firstOrCreateForCoach($coach->id);
        $row->update(['brand_name' => 'LoggedIn Brand']);

        Auth::guard('web')->loginUsingId($coach->id);
        $brand = app(BrandResolver::class)->current();

        $this->assertSame('LoggedIn Brand', $brand->name);
    }

    public function test_current_resolves_staff_via_parent_coach(): void
    {
        $coach = User::factory()->create(['role' => 'instructor']);
        $row = CoachBrandSetting::firstOrCreateForCoach($coach->id);
        $row->update(['brand_name' => 'Coach For Staff']);

        $staff = User::factory()->create([
            'role' => 'staff',
            'coach_id' => $coach->id,
        ]);

        Auth::guard('web')->loginUsingId($staff->id);
        $brand = app(BrandResolver::class)->current();

        $this->assertSame('Coach For Staff', $brand->name,
            'staff member should inherit their parent coach brand');
    }

    /* ───────── cache invalidation ───────── */

    public function test_save_invalidates_resolver_cache(): void
    {
        $coach = User::factory()->create(['role' => 'instructor']);
        $row = CoachBrandSetting::firstOrCreateForCoach($coach->id);

        // Prime cache via a read.
        $first = app(BrandResolver::class)->forCoach($coach->id);
        $this->assertTrue($first->isPlatformDefault);

        // Mutate — model's saved event should forget the cache.
        $row->update(['brand_name' => 'Cache Buster']);

        $second = app(BrandResolver::class)->forCoach($coach->id);
        $this->assertSame('Cache Buster', $second->name,
            'save() must invalidate the cache so next read sees the new brand');
    }

    /* ───────── Brand value object behaviour ───────── */

    public function test_brand_initials_default_to_first_two_words(): void
    {
        $row = new CoachBrandSetting([
            'brand_name'    => 'Acme Coaching',
            'primary_color' => '#000000',
            'accent_color'  => '#ffffff',
            'support_email' => 'a@b.c',
            'footer_text'   => 'x',
        ]);
        $brand = Brand::compose($row, []);
        $this->assertSame('AC', $brand->initials());
    }
}
