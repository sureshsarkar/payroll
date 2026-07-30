<?php

namespace Tests\Feature\Audit;

use App\Models\CourseChapterLesson;
use App\Models\CourseLiveClass;
use App\Models\User;
use App\Notifications\ZoomMeetingMissingForUpcomingClass;
use App\Notifications\ZoomReconnectRequired;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Verifies that the two operational notifications added on 2026-05-07
 * actually flow through the InAppNotification base class to the right
 * delivery channels.
 *
 * Without these tests we'd discover at 3 AM (when zoom:health-check
 * runs) that the notification class loads fine but the via() method
 * returns the wrong channels, or toMail() throws on missing data, or
 * the URL in the bell-icon entry routes to a 404. Cheap to assert here.
 */
class ZoomNotificationDeliveryTest extends TestCase
{
    use DatabaseTransactions;

    public function test_zoom_reconnect_required_dead_state_payload(): void
    {
        $n = new ZoomReconnectRequired('dead', 'HTTP 400 invalid_grant');

        $this->assertStringContainsString(
            'Zoom not connected',
            $n->title,
            'Reconnect notification title must call out the connectivity issue'
        );
        $this->assertNotEmpty($n->body, 'Notification body cannot be empty');
        $this->assertStringContainsString(
            '/instructor/zoom-setting',
            (string) $n->url,
            'Reconnect URL must point at the zoom-setting page where the user can fix it'
        );
    }

    public function test_zoom_reconnect_required_expiring_state_payload(): void
    {
        $n = new ZoomReconnectRequired('expiring', 'expires in 6 days');

        $this->assertStringContainsString(
            'expiring',
            strtolower($n->title),
            'Expiring-state notification title should mention expiring/expiry'
        );
    }

    public function test_zoom_reconnect_required_routes_to_mail_and_database_for_real_user(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'instructor-test-' . uniqid() . '@example.com',
        ]);

        $user->notify(new ZoomReconnectRequired('dead', 'HTTP 400 invalid_grant'));

        Notification::assertSentTo(
            $user,
            ZoomReconnectRequired::class,
            function ($notification, $channels) {
                $this->assertContains('database', $channels, 'Bell-dropdown channel missing — instructor would never see the alert in-app');
                $this->assertContains('mail',     $channels, 'Mail channel missing — instructor would not get an email about dead Zoom token');
                return true;
            }
        );
    }

    public function test_zoom_reconnect_required_to_database_payload_has_minimum_keys(): void
    {
        $user = User::factory()->create();
        $notification = new ZoomReconnectRequired('dead', 'invalid_grant');

        $payload = $notification->toDatabase($user);
        $this->assertIsArray($payload);
        foreach (['title', 'body', 'url'] as $required) {
            $this->assertArrayHasKey(
                $required,
                $payload,
                "toDatabase() payload missing '$required' — bell dropdown won't render correctly"
            );
        }
    }

    /**
     * Build a real CourseLiveClass row inside DatabaseTransactions so
     * notifications under test can dereference its fields. Avoid tests
     * skipping when the test DB doesn't have seed data.
     */
    private function makeLiveClassFixture(User $user, string $meetingId = '99999999999'): CourseLiveClass
    {
        // forceFill bypasses fillable — Course/CourseChapter on this codebase
        // don't declare $fillable so default-guarded blocks plain create().
        $course = (new \App\Models\Course())->forceFill([
            'instructor_id' => $user->id,
            'title'         => 'Notification fixture course',
            'slug'          => 'notif-fixture-' . uniqid(),
            'price'         => 0,
            'status'        => 'active',
            'is_approved'   => 'approved',
        ]);
        $course->save();

        $chapter = (new \App\Models\CourseChapter())->forceFill([
            'instructor_id' => $user->id,
            'course_id'     => $course->id,
            'title'         => 'Chapter 1',
            'order'         => 1,
            'status'        => 'active',
        ]);
        $chapter->save();

        $lesson = (new CourseChapterLesson())->forceFill([
            'title'         => 'Test live lesson',
            'instructor_id' => $user->id,
            'course_id'     => $course->id,
            'chapter_id'    => $chapter->id,
            'duration'      => 30,
            'storage'       => 'live',
            'file_type'     => 'live',
        ]);
        $lesson->save();

        return CourseLiveClass::create([
            'course_id'  => $course->id,
            'lesson_id'  => $lesson->id,
            'start_time' => now()->addMinutes(15),
            'meeting_id' => $meetingId,
            'type'       => 'zoom',
            'password'   => 'test-pwd',
        ]);
    }

    public function test_zoom_meeting_missing_notification_payload_includes_meeting_context(): void
    {
        $user = User::factory()->create();
        $live = $this->makeLiveClassFixture($user, '99999999999');

        $n = new ZoomMeetingMissingForUpcomingClass($live);

        $this->assertStringContainsString(
            'Zoom',
            $n->title,
            'Missing-meeting notification must mention Zoom in the title for clarity'
        );
        $this->assertStringContainsString(
            '99999999999',
            $n->body,
            'Body must include the meeting_id so the instructor knows which class is broken'
        );

        $payload = $n->toDatabase($user);
        $this->assertArrayHasKey('title', $payload);
        $this->assertArrayHasKey('body',  $payload);
    }

    public function test_zoom_meeting_missing_notification_routes_to_database_and_mail(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $live = $this->makeLiveClassFixture($user, '88888888888');

        $user->notify(new ZoomMeetingMissingForUpcomingClass($live));

        Notification::assertSentTo(
            $user,
            ZoomMeetingMissingForUpcomingClass::class,
            function ($notification, $channels) {
                $this->assertContains('database', $channels);
                $this->assertContains('mail',     $channels);
                return true;
            }
        );
    }

    public function test_health_check_command_does_not_send_repeat_notifications_for_unchanged_status(): void
    {
        // The zoom:health-check command must only notify when status
        // *transitions* (ok→dead, ok→expiring). A daily probe that
        // always returns the same 'dead' state should NOT fire a fresh
        // email each day — operators stop reading repeat emails.
        $src = (string) file_get_contents(app_path('Console/Commands/ZoomHealthCheck.php'));

        $this->assertMatchesRegularExpression(
            '/\$status\s*!==\s*\$previous/',
            $src,
            'ZoomHealthCheck must compare new status against the previously-stored status before notifying — daily nag protection'
        );
    }
}
