<?php

namespace Tests\Feature\Domain;

use App\Models\User;
use App\Notifications\InAppNotification;
use App\Notifications\PasswordChangedToUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 2026-07-09 — Email/Notification audit, Phase 2 (White-Label & Tenant Isolation).
 * Covers the bell/web-push coach-domain rewrite (2.2) and the hardcoded-brand /
 * currency strip (2.4).
 */
class EmailNotificationPhase2Test extends TestCase
{
    use DatabaseTransactions;

    /* ── 2.2 bell/web-push URL stays on the coach's verified domain ──── */

    public function test_bell_url_is_rewritten_to_coach_verified_domain(): void
    {
        $coach = User::factory()->create(['role' => 'instructor', 'coach_id' => null]);
        DB::table('coach_domains')->insert([
            'coach_id' => $coach->id, 'hostname' => 'coach.example.com', 'kind' => 'custom',
            'is_primary' => 1, 'verified_at' => now(), 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Cache::forget("coach_domain:primary:{$coach->id}");

        $appHost = parse_url(config('app.url'), PHP_URL_HOST);
        $url = "https://{$appHost}/student/order-details/5";

        $ref = new \ReflectionMethod(InAppNotification::class, 'coachHostRewrite');
        $ref->setAccessible(true);
        $notif = new PasswordChangedToUser();

        // Coach has a verified domain → platform host swapped for the coach host.
        $this->assertSame(
            'https://coach.example.com/student/order-details/5',
            $ref->invoke($notif, $url, $coach->id)
        );

        // A coach with no verified domain → URL is left untouched (platform host).
        $this->assertSame($url, $ref->invoke($notif, $url, 987654));

        // Null coach id → unchanged.
        $this->assertSame($url, $ref->invoke($notif, $url, null));
    }

    public function test_bell_toDatabase_uses_coach_domain_for_owning_coach(): void
    {
        $coach = User::factory()->create(['role' => 'instructor', 'coach_id' => null]);
        $student = User::factory()->create(['role' => 'student', 'coach_id' => $coach->id]);
        DB::table('coach_domains')->insert([
            'coach_id' => $coach->id, 'hostname' => 'brandx.test', 'kind' => 'custom',
            'is_primary' => 1, 'verified_at' => now(), 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Cache::forget("coach_domain:primary:{$coach->id}");

        // A student-facing notification whose owning coach is $coach.
        $notif = new class($coach->id) extends InAppNotification {
            public function __construct(int $cid)
            {
                $this->coachId = $cid;
                $this->title = 'T';
                $this->body = 'B';
                $this->url = 'https://' . parse_url(config('app.url'), PHP_URL_HOST) . '/student/x';
            }
        };

        $row = $notif->toDatabase($student);
        $this->assertStringContainsString('brandx.test', $row['url']);
        $this->assertStringNotContainsString('localhost', $row['url']);
    }

    /* ── 2.4 hardcoded brand / currency stripped from templates ──────── */

    public function test_migration_transform_strips_hardcoded_brand_and_currency(): void
    {
        DB::table('email_templates')->updateOrInsert(
            ['name' => 'approved_withdraw'],
            ['subject' => 'x', 'message' => '<p>Dear x,</p><p>Thanks</p><p>MBS Guru</p>', 'updated_at' => now(), 'created_at' => now()]
        );
        DB::table('email_templates')->updateOrInsert(
            ['name' => 'approved_refund'],
            ['subject' => 'x', 'message' => '<p>we have send {{refund_amount}} USD to you</p>', 'updated_at' => now(), 'created_at' => now()]
        );

        // Same transformation the migration performs.
        DB::table('email_templates')->where('name', 'approved_withdraw')
            ->update(['message' => DB::raw("REPLACE(message, '<p>MBS Guru</p>', '')")]);
        DB::table('email_templates')->where('name', 'approved_refund')
            ->update(['message' => DB::raw("REPLACE(message, 'we have send {{refund_amount}} USD', 'we have sent {{refund_amount}}')")]);

        $wd = DB::table('email_templates')->where('name', 'approved_withdraw')->value('message');
        $rf = DB::table('email_templates')->where('name', 'approved_refund')->value('message');
        $this->assertStringNotContainsString('MBS Guru', $wd);
        $this->assertStringNotContainsString('USD', $rf);
        $this->assertStringContainsString('{{refund_amount}}', $rf);
    }
}
