<?php

namespace Tests\Feature\Domain;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 2 — the global permission catalog must expose EVERY code-enforced
 * coach-panel slug (so it can be delegated to staff) plus each Settings
 * sub-page, and must contain no duplicates. Seeding is idempotent.
 */
class CoachPermissionCatalogTest extends TestCase
{
    use DatabaseTransactions;

    /** Slugs that are gated in code (sidebar / controllers) — must be assignable. */
    private const ENFORCED = [
        'dashboard', 'analytics',
        'courses', 'courses-create', 'courses-edit', 'courses-delete',
        'course-batches', 'course-batches-create',
        'live-classes', 'instant-meetings',
        'coach-students', 'coach-students-create',
        'landing-page-enquiry', 'landing-page-enquiry-create',
        'teacher-batches',
        'coach-staff', 'roles',
        'coach-orders', 'coach-coupons',
        'trial-sessions', 'pricing-enquiries', 'payment-gateways',
        'blogs', 'membership', 'referral',
    ];

    /** Every Settings sub-page named in Step 8. */
    private const SETTINGS = [
        'settings-general', 'settings-profile', 'settings-website', 'settings-payment-gateway',
        'settings-email', 'settings-notification', 'settings-sms-whatsapp', 'settings-tax',
        'settings-theme', 'settings-domain', 'settings-security', 'settings-brand',
        'settings-zoom', 'settings-youtube',
    ];

    private function seedCatalog(): void
    {
        (require base_path('database/migrations/2026_07_04_120100_seed_coach_permission_catalog.php'))->up();
    }

    public function test_every_enforced_slug_is_assignable(): void
    {
        $this->seedCatalog();
        $have = DB::table('coach_staff_permissions')->pluck('slug')->flip();
        foreach (self::ENFORCED as $slug) {
            $this->assertTrue($have->has($slug), "Enforced permission '{$slug}' is missing from the catalog — it could never be delegated to staff.");
        }
    }

    public function test_every_settings_subpage_has_a_permission(): void
    {
        $this->seedCatalog();
        $have = DB::table('coach_staff_permissions')->pluck('slug')->flip();
        foreach (self::SETTINGS as $slug) {
            $this->assertTrue($have->has($slug), "Settings page permission '{$slug}' is missing.");
            $this->assertTrue($have->has($slug . '-edit'), "Settings write permission '{$slug}-edit' is missing.");
        }
    }

    public function test_catalog_has_no_duplicate_slugs(): void
    {
        $this->seedCatalog();
        $dupes = DB::table('coach_staff_permissions')
            ->select('slug')->groupBy('slug')->havingRaw('COUNT(*) > 1')->pluck('slug')->all();
        $this->assertSame([], $dupes, 'Duplicate slug(s): ' . implode(', ', $dupes));
    }

    public function test_seed_is_idempotent(): void
    {
        $this->seedCatalog();
        $count = DB::table('coach_staff_permissions')->count();
        $this->seedCatalog();   // run again
        $this->assertSame($count, DB::table('coach_staff_permissions')->count(), 'Re-seeding changed the row count — not idempotent.');
    }
}
