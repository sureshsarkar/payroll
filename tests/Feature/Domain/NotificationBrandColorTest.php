<?php

namespace Tests\Feature\Domain;

use App\Models\CoachBrandSetting;
use App\Models\User;
use App\Notifications\Concerns\BrandedNotificationMail;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * White-label QA (2026-07-07) — a branded notification email must use the COACH's
 * brand accent for its icon, not the legacy platform indigo (#5751e1). An empty
 * iconColor means "use the brand accent"; explicit semantic colours are kept.
 */
class NotificationBrandColorTest extends TestCase
{
    use DatabaseTransactions;

    /** Anonymous holder so we can call the protected trait method directly. */
    private function mailer(): object
    {
        return new class {
            use BrandedNotificationMail;

            public function build(object $notifiable, ?int $coachId, array $content)
            {
                return $this->buildBrandedMail($notifiable, $coachId, 'Subject', $content);
            }
        };
    }

    private function coach(): User
    {
        return User::find(DB::table('users')->insertGetId([
            'role' => 'instructor', 'name' => 'Coach', 'email' => 'c' . uniqid() . '@t.local',
            'password' => bcrypt('x'), 'status' => 'active', 'is_banned' => 'no',
            'created_at' => now(), 'updated_at' => now(),
        ]));
    }

    public function test_empty_icon_color_uses_the_coach_brand_accent(): void
    {
        $coach = $this->coach();
        $brand = CoachBrandSetting::firstOrCreateForCoach($coach->id);
        $brand->update(['primary_color' => '#abcdef']);

        $mail = $this->mailer()->build($coach, $coach->id, ['iconColor' => '', 'title' => 't', 'body' => 'b']);

        $this->assertSame('#abcdef', $mail->viewData['iconColor'], 'icon uses the coach brand colour');
        $this->assertSame('#abcdef', $mail->viewData['brandColor']);
        $this->assertStringNotContainsString('#5751e1', json_encode($mail->viewData), 'no legacy indigo leaks');
    }

    public function test_explicit_semantic_color_is_preserved(): void
    {
        $coach = $this->coach();
        CoachBrandSetting::firstOrCreateForCoach($coach->id)->update(['primary_color' => '#abcdef']);

        // A failure notification passes red explicitly — it must NOT be overwritten.
        $mail = $this->mailer()->build($coach, $coach->id, ['iconColor' => '#ef4444', 'title' => 't', 'body' => 'b']);

        $this->assertSame('#ef4444', $mail->viewData['iconColor'], 'semantic colour preserved');
    }

    public function test_platform_path_uses_emerald_not_indigo(): void
    {
        $user = User::find(DB::table('users')->insertGetId([
            'role' => 'student', 'name' => 'S', 'email' => 's' . uniqid() . '@t.local',
            'password' => bcrypt('x'), 'status' => 'active', 'is_banned' => 'no',
            'created_at' => now(), 'updated_at' => now(),
        ]));

        // No coach → platform default. Must be emerald, never the retired indigo.
        $mail = $this->mailer()->build($user, null, ['iconColor' => '', 'title' => 't', 'body' => 'b']);

        $this->assertSame('#10b981', $mail->viewData['iconColor']);
        $this->assertNotSame('#5751e1', $mail->viewData['brandColor']);
    }

    public function test_no_notification_class_hardcodes_the_retired_indigo(): void
    {
        $hits = [];
        foreach (glob(app_path('Notifications/*.php')) as $file) {
            if (str_contains((string) file_get_contents($file), '#5751e1')) {
                $hits[] = basename($file);
            }
        }
        $this->assertSame([], $hits, 'no notification class may hardcode #5751e1: ' . implode(', ', $hits));
    }
}
