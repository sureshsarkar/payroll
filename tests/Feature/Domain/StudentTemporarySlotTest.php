<?php

namespace Tests\Feature\Domain;

use App\Exceptions\AccessPermissionDeniedException;
use App\Models\CourseBatch;
use App\Models\StudentTemporarySlot;
use App\Models\User;
use App\Services\BatchAttendanceService;
use App\Services\TemporarySlotService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\Order\app\Models\Enrollment;
use Tests\TestCase;

/**
 * Temporary (date-specific) batch slots (2026-07-15). Covers: assign keeps
 * primary unchanged, same-course gate, cross-coach, inactive, past date,
 * duplicate, date-wise capacity, own-batch no-op, cancel, roster guest/away,
 * notification.
 */
class StudentTemporarySlotTest extends TestCase
{
    use DatabaseTransactions;

    private function svc(): TemporarySlotService
    {
        return app(TemporarySlotService::class);
    }

    /** @return array{0:User,1:int,2:CourseBatch,3:CourseBatch} */
    private function scenario(): array
    {
        $coach = User::factory()->create(['role' => 'instructor', 'coach_id' => null]);
        $courseId = DB::table('courses')->insertGetId([
            'title' => 'C ' . uniqid(), 'slug' => 'c-' . uniqid(),
            'instructor_id' => $coach->id, 'added_by' => $coach->id,
            'is_approved' => 'approved', 'status' => 'active', 'type' => 'course',
            'price' => 0, 'discount' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $mk = fn ($status = 'active', $cap = 30) => CourseBatch::create([
            'course_id' => $courseId, 'title' => 'B ' . uniqid(),
            'start_date' => now()->subWeek(), 'end_date' => now()->addMonth(),
            'start_time' => '09:00:00', 'end_time' => '10:00:00',
            'capacity' => $cap, 'days' => ['monday'], 'status' => $status,
        ]);
        return [$coach, $courseId, $mk(), $mk()];
    }

    private function student(): User
    {
        return User::factory()->create(['role' => 'student']);
    }

    private function enrol(User $s, int $courseId, ?int $batchId): Enrollment
    {
        return Enrollment::create(['user_id' => $s->id, 'course_id' => $courseId, 'batch_id' => $batchId, 'order_id' => null, 'has_access' => 1]);
    }

    public function test_assign_keeps_primary_and_records_slot(): void
    {
        Notification::fake();
        [$coach, $courseId, $A, $B] = $this->scenario();
        $s = $this->student();
        $enr = $this->enrol($s, $courseId, $A->id);
        $date = now()->addDay()->toDateString();

        $res = $this->svc()->assign($coach->id, $s->id, $B, $date, 'office shift', $coach->id);

        $this->assertTrue($res['ok']);
        $this->assertSame($A->id, (int) $enr->refresh()->batch_id, 'primary batch must stay unchanged');
        $this->assertDatabaseHas('student_temporary_slots', [
            'coach_id' => $coach->id, 'student_id' => $s->id, 'primary_batch_id' => $A->id,
            'target_batch_id' => $B->id, 'status' => 'scheduled', 'reason' => 'office shift',
        ]);
        Notification::assertSentTo($s, \App\Notifications\StudentTemporarySlotAssigned::class);
    }

    public function test_not_enrolled_in_course_is_rejected(): void
    {
        [$coach, , , $B] = $this->scenario();
        $outsider = $this->student();
        $res = $this->svc()->assign($coach->id, $outsider->id, $B, now()->addDay()->toDateString(), null, $coach->id);
        $this->assertFalse($res['ok']);
        $this->assertSame(0, StudentTemporarySlot::where('student_id', $outsider->id)->count());
    }

    public function test_cross_coach_batch_throws(): void
    {
        [, $courseId, , $B] = $this->scenario();
        $other = User::factory()->create(['role' => 'instructor', 'coach_id' => null]);
        $s = $this->student();
        $this->enrol($s, $courseId, null);
        $this->expectException(AccessPermissionDeniedException::class);
        $this->svc()->assign($other->id, $s->id, $B, now()->addDay()->toDateString(), null, $other->id);
    }

    public function test_inactive_batch_is_rejected(): void
    {
        [$coach, $courseId] = $this->scenario();
        $inactive = CourseBatch::create(['course_id' => $courseId, 'title' => 'X', 'start_date' => now(), 'end_date' => now()->addMonth(),
            'start_time' => '09:00:00', 'end_time' => '10:00:00', 'capacity' => 30, 'days' => ['monday'], 'status' => 'inactive']);
        $s = $this->student(); $this->enrol($s, $courseId, null);
        $res = $this->svc()->assign($coach->id, $s->id, $inactive, now()->addDay()->toDateString(), null, $coach->id);
        $this->assertFalse($res['ok']);
    }

    public function test_past_date_is_rejected(): void
    {
        [$coach, $courseId, $A, $B] = $this->scenario();
        $s = $this->student(); $this->enrol($s, $courseId, $A->id);
        $res = $this->svc()->assign($coach->id, $s->id, $B, now()->subDay()->toDateString(), null, $coach->id);
        $this->assertFalse($res['ok']);
    }

    public function test_duplicate_slot_is_rejected(): void
    {
        Notification::fake();
        [$coach, $courseId, $A, $B] = $this->scenario();
        $s = $this->student(); $this->enrol($s, $courseId, $A->id);
        $date = now()->addDay()->toDateString();
        $this->assertTrue($this->svc()->assign($coach->id, $s->id, $B, $date, null, $coach->id)['ok']);
        $this->assertFalse($this->svc()->assign($coach->id, $s->id, $B, $date, null, $coach->id)['ok']);
        $this->assertSame(1, StudentTemporarySlot::where('student_id', $s->id)->where('status', 'scheduled')->count());
    }

    public function test_date_wise_capacity_is_enforced(): void
    {
        Notification::fake();
        [$coach, $courseId, $A] = $this->scenario();
        $full = CourseBatch::create(['course_id' => $courseId, 'title' => 'Full', 'start_date' => now(), 'end_date' => now()->addMonth(),
            'start_time' => '09:00:00', 'end_time' => '10:00:00', 'capacity' => 1, 'days' => ['monday'], 'status' => 'active']);
        $this->enrol($this->student(), $courseId, $full->id);   // 1 permanent seat used

        $s = $this->student(); $this->enrol($s, $courseId, $A->id);
        $res = $this->svc()->assign($coach->id, $s->id, $full, now()->addDay()->toDateString(), null, $coach->id);
        $this->assertFalse($res['ok']);
    }

    public function test_own_primary_batch_is_noop(): void
    {
        [$coach, $courseId, $A] = $this->scenario();
        $s = $this->student(); $this->enrol($s, $courseId, $A->id);
        $res = $this->svc()->assign($coach->id, $s->id, $A, now()->addDay()->toDateString(), null, $coach->id);
        $this->assertFalse($res['ok']);
    }

    public function test_cancel_marks_cancelled(): void
    {
        Notification::fake();
        [$coach, $courseId, $A, $B] = $this->scenario();
        $s = $this->student(); $this->enrol($s, $courseId, $A->id);
        $r = $this->svc()->assign($coach->id, $s->id, $B, now()->addDay()->toDateString(), null, $coach->id);
        $this->assertTrue($this->svc()->cancel($coach->id, $r['slot_id'])['ok']);
        $this->assertSame('cancelled', StudentTemporarySlot::find($r['slot_id'])->status);
    }

    public function test_roster_shows_guest_in_target_and_away_in_primary(): void
    {
        Notification::fake();
        [$coach, $courseId, $A, $B] = $this->scenario();
        $s = $this->student(); $this->enrol($s, $courseId, $A->id);
        $date = now()->addDay()->toDateString();
        $this->svc()->assign($coach->id, $s->id, $B, $date, null, $coach->id);

        $svc = app(BatchAttendanceService::class);
        $targetRoster = $svc->studentRoster($B, $date);
        $guest = $targetRoster->firstWhere('user_id', $s->id);
        $this->assertNotNull($guest, 'student appears as a guest in the target batch roster');
        $this->assertTrue($guest['is_guest']);

        $primaryRoster = $svc->studentRoster($A, $date);
        $home = $primaryRoster->firstWhere('user_id', $s->id);
        $this->assertTrue($home['is_away']);
        $this->assertSame('away', $home['verification_status'], 'away, not counted absent');
    }
}
