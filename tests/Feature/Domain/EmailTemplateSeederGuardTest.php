<?php

namespace Tests\Feature\Domain;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\GlobalSetting\database\seeders\EmailTemplateSeeder;
use Tests\TestCase;

/**
 * 2026-07-10 (Email/Notification audit follow-up).
 *
 * EmailTemplateSeeder used to EmailTemplate::truncate() the whole table, which
 * destroyed the migration-seeded notif_* templates. It now upserts by name;
 * this test guards that behaviour so the footgun can't silently return.
 */
class EmailTemplateSeederGuardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_seeder_upserts_and_preserves_notif_templates(): void
    {
        // A migration-seeded notif_* row that MUST survive a seeder run.
        DB::table('email_templates')->updateOrInsert(
            ['name' => 'notif_guard_probe'],
            ['subject' => 'keep', 'message' => '<p>keep me</p>', 'updated_at' => now(), 'created_at' => now()]
        );
        // A legacy row the seeder owns — should be UPDATED, not duplicated.
        DB::table('email_templates')->updateOrInsert(
            ['name' => 'password_reset'],
            ['subject' => 'old', 'message' => '<p>OLD BODY</p>', 'updated_at' => now(), 'created_at' => now()]
        );

        (new EmailTemplateSeeder())->run();

        // 1) The notif_* template survived (would have been wiped by truncate()).
        $this->assertTrue(
            DB::table('email_templates')->where('name', 'notif_guard_probe')->exists(),
            'notif_* templates must survive an EmailTemplateSeeder run'
        );
        $this->assertSame('<p>keep me</p>', DB::table('email_templates')->where('name', 'notif_guard_probe')->value('message'));

        // 2) The legacy template was upserted (updated, not duplicated).
        $this->assertSame(1, DB::table('email_templates')->where('name', 'password_reset')->count(), 'no duplicate legacy row');
        $this->assertStringNotContainsString('OLD BODY', (string) DB::table('email_templates')->where('name', 'password_reset')->value('message'));
    }

    public function test_seeder_is_idempotent(): void
    {
        (new EmailTemplateSeeder())->run();
        (new EmailTemplateSeeder())->run(); // second run must not duplicate any row

        foreach (['password_reset', 'order_completed', 'user_verification'] as $name) {
            $this->assertSame(1, DB::table('email_templates')->where('name', $name)->count(), "$name must not be duplicated");
        }
    }
}
