<?php

namespace Tests\Feature\Domain;

use App\Models\InstantMeeting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Task 2 — 1:1 instant meetings surface in the student's Live Classes list,
 * strictly private to the invited student and tenant-scoped by coach.
 */
class StudentInstantMeetingVisibilityTest extends TestCase
{
    use DatabaseTransactions;

    private function user(string $role): int
    {
        return DB::table('users')->insertGetId([
            'role' => $role, 'name' => ucfirst($role) . ' ' . uniqid(), 'email' => substr($role, 0, 1) . uniqid() . '@t.local',
            'password' => bcrypt('x'), 'status' => 'active', 'is_banned' => 'no', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function meeting(int $coach, int $student, string $status = 'active'): InstantMeeting
    {
        return InstantMeeting::create([
            'coach_id' => $coach, 'student_id' => $student, 'status' => $status,
            'expected_duration_minutes' => 30, 'started_at' => now(),
        ]);
    }

    public function test_only_the_invited_student_sees_the_meeting(): void
    {
        $coach = $this->user('instructor');
        $a = $this->user('student');
        $b = $this->user('student');
        $this->meeting($coach, $a);

        $this->assertSame(1, InstantMeeting::visibleToStudent($a, 0)->count());  // invited
        $this->assertSame(0, InstantMeeting::visibleToStudent($b, 0)->count());  // 1:1 privacy
    }

    public function test_tenant_scoping_on_a_coach_domain(): void
    {
        $coachA = $this->user('instructor');
        $coachB = $this->user('instructor');
        $student = $this->user('student');
        $this->meeting($coachA, $student);

        // On coach A's domain the student sees it; on coach B's domain they don't.
        $this->assertSame(1, InstantMeeting::visibleToStudent($student, $coachA)->count());
        $this->assertSame(0, InstantMeeting::visibleToStudent($student, $coachB)->count());
    }

    public function test_ended_and_cancelled_meetings_are_not_listed(): void
    {
        $coach = $this->user('instructor');
        $student = $this->user('student');
        $this->meeting($coach, $student, 'ended');
        $this->meeting($coach, $student, 'cancelled');
        $this->meeting($coach, $student, 'active');

        $this->assertSame(1, InstantMeeting::visibleToStudent($student, 0)->count());
    }
}
