<?php

namespace Tests\Feature\Domain;

use App\Exceptions\ActiveMeetingExistsException;
use App\Http\Controllers\Frontend\Coach\InstantMeetingController;
use App\Http\Controllers\Frontend\InstantMeetingRoomController;
use App\Models\CoachStudentLink;
use App\Models\InstantMeeting;
use App\Models\User;
use App\Models\ZoomCredential;
use App\Notifications\InstantMeetingInviteToStudent;
use App\Services\LiveMeetingGuard;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * 1:1 Instant Meeting — start flow, tenant isolation, the shared one-active-
 * meeting mutex (across batch + 1:1), role/status gating, and the white-label
 * student invite. Zoom is stubbed via Http::fake so the full path is exercised.
 */
class InstantMeetingTest extends TestCase
{
    use DatabaseTransactions;

    private function coach(): User
    {
        $id = DB::table('users')->insertGetId([
            'role' => 'instructor', 'name' => 'Coach ' . uniqid(), 'email' => 'c' . uniqid() . '@t.local',
            'password' => bcrypt('x'), 'status' => 'active', 'is_banned' => 'no', 'created_at' => now(), 'updated_at' => now(),
        ]);
        return User::find($id);
    }

    private function student(): User
    {
        $id = DB::table('users')->insertGetId([
            'role' => 'student', 'name' => 'Stu ' . uniqid(), 'email' => 's' . uniqid() . '@t.local',
            'password' => bcrypt('x'), 'status' => 'active', 'is_banned' => 'no', 'created_at' => now(), 'updated_at' => now(),
        ]);
        return User::find($id);
    }

    private function link(User $coach, User $student): void
    {
        DB::table('coach_student_links')->insert([
            'coach_id' => $coach->id, 'student_id' => $student->id, 'source' => 'added',
            'status' => 'active', 'joined_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        Cache::flush();
    }

    private function withZoom(User $coach): void
    {
        ZoomCredential::create([
            'instructor_id' => $coach->id, 'account_id' => 'acc', 'client_id' => 'cid',
            'client_secret' => 'secret', 'sdk_key' => 'sdkkey', 'sdk_secret' => 'sdksecret',
            'health_status' => 'ok',
        ]);
    }

    private function fakeZoom(): void
    {
        Http::fake([
            'zoom.us/oauth/token'              => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            'api.zoom.us/v2/users/me/meetings' => Http::response(['id' => '987654321', 'join_url' => 'https://zoom.us/j/987', 'password' => '']),
            'api.zoom.us/v2/users/me/token*'   => Http::response(['token' => 'zak-123']),
        ]);
    }

    private function coachStart(User $coach, array $body)
    {
        $this->actingAs($coach);
        $req = Request::create('/', 'POST', $body);
        app()->instance('request', $req);
        return app(InstantMeetingController::class)->start($req);
    }

    /* ───────────── start flow ───────────── */

    public function test_coach_starts_meeting_and_student_is_notified(): void
    {
        Notification::fake();
        $this->fakeZoom();
        $coach = $this->coach();
        $student = $this->student();
        $this->link($coach, $student);
        $this->withZoom($coach);

        $res = $this->coachStart($coach, ['student_id' => $student->id, 'purpose' => 'doubt', 'duration' => 30]);
        $this->assertSame(200, $res->getStatusCode());
        $this->assertTrue($res->getData()->ok);

        $m = InstantMeeting::forCoach($coach->id)->first();
        $this->assertNotNull($m);
        $this->assertSame('active', $m->status);
        $this->assertSame('987654321', $m->meeting_id);        // Zoom meeting created
        $this->assertSame($student->id, (int) $m->student_id);

        // Shared mutex claimed for this coach.
        $this->assertSame(1, DB::table('coach_active_meetings')->where('coach_id', $coach->id)->whereNotNull('instant_meeting_id')->count());

        Notification::assertSentTo($student, InstantMeetingInviteToStudent::class);
    }

    public function test_foreign_student_is_rejected(): void
    {
        $this->fakeZoom();
        $coach = $this->coach();
        $stranger = $this->student();          // NOT linked to this coach
        $this->withZoom($coach);

        $res = $this->coachStart($coach, ['student_id' => $stranger->id]);
        $this->assertSame(422, $res->getStatusCode());
        $this->assertFalse($res->getData()->ok);
        $this->assertSame(0, InstantMeeting::forCoach($coach->id)->count());
    }

    public function test_start_without_zoom_credentials_fails_gracefully(): void
    {
        Notification::fake();
        $coach = $this->coach();
        $student = $this->student();
        $this->link($coach, $student);        // no zoom credential configured

        $res = $this->coachStart($coach, ['student_id' => $student->id]);
        $this->assertSame(400, $res->getStatusCode());
        $this->assertFalse($res->getData()->ok);
        // No dangling meeting row or mutex slot.
        $this->assertSame(0, InstantMeeting::forCoach($coach->id)->count());
        $this->assertSame(0, DB::table('coach_active_meetings')->where('coach_id', $coach->id)->count());
    }

    /* ───────────── shared one-active-meeting mutex ───────────── */

    public function test_instant_meeting_blocks_a_second_meeting_and_a_batch_claim(): void
    {
        $guard = app(LiveMeetingGuard::class);
        $coach = $this->coach();
        $m = InstantMeeting::create(['coach_id' => $coach->id, 'student_id' => $this->student()->id, 'status' => 'active', 'expected_duration_minutes' => 30]);

        $guard->claimInstant($coach->id, $m->id);

        // A DIFFERENT instant meeting can't claim.
        $this->assertThrows(fn () => $guard->claimInstant($coach->id, $m->id + 999), ActiveMeetingExistsException::class);
        // A BATCH live class can't claim the same coach's slot either.
        $this->assertThrows(fn () => $guard->claim($coach->id, 555), ActiveMeetingExistsException::class);

        // Ending releases the slot → a batch class can now claim.
        app(\App\Services\InstantMeetingService::class)->end($m->fresh());
        $this->assertSame(0, DB::table('coach_active_meetings')->where('coach_id', $coach->id)->count());
    }

    /* ───────────── room role + status gating ───────────── */

    private function statusFor(User $viewer, InstantMeeting $m)
    {
        $this->actingAs($viewer);
        $req = Request::create('/', 'GET');
        app()->instance('request', $req);
        return app(InstantMeetingRoomController::class)->status($req, $m->id);
    }

    public function test_status_gates_student_until_coach_joins_and_blocks_strangers(): void
    {
        $coach = $this->coach();
        $student = $this->student();
        $stranger = $this->student();
        $m = InstantMeeting::create(['coach_id' => $coach->id, 'student_id' => $student->id, 'status' => 'active',
            'meeting_id' => '123', 'expected_duration_minutes' => 30]);

        // Host can always join an active meeting.
        $this->assertTrue($this->statusFor($coach, $m)->getData()->can_join);

        // Student waits until the coach has joined.
        $this->assertFalse($this->statusFor($student, $m)->getData()->can_join);
        $m->forceFill(['coach_joined_at' => now()])->save();
        $this->assertTrue($this->statusFor($student, $m->fresh())->getData()->can_join);

        // A stranger has no access.
        $this->assertSame(403, $this->statusFor($stranger, $m)->getStatusCode());
    }

    public function test_signature_issues_for_participants_and_403_after_end(): void
    {
        $coach = $this->coach();
        $student = $this->student();
        $this->withZoom($coach);
        $m = InstantMeeting::create(['coach_id' => $coach->id, 'student_id' => $student->id, 'status' => 'active',
            'meeting_id' => '123456', 'coach_joined_at' => now(), 'expected_duration_minutes' => 30]);

        Http::fake(['api.zoom.us/v2/users/me/token*' => Http::response(['token' => 'zak']), 'zoom.us/oauth/token' => Http::response(['access_token' => 't', 'expires_in' => 3600])]);

        $this->actingAs($coach);
        $req = Request::create('/', 'POST');
        app()->instance('request', $req);
        $hostRes = app(InstantMeetingRoomController::class)->issue($req, $m->id);
        $this->assertSame(200, $hostRes->getStatusCode());
        $this->assertSame(1, $hostRes->getData()->role);          // host
        $this->assertNotEmpty($hostRes->getData()->signature);

        // Ended meeting → 403.
        $m->update(['status' => 'ended']);
        $this->assertSame(403, app(InstantMeetingRoomController::class)->issue($req, $m->id)->getStatusCode());
    }
}
