<?php

namespace Tests\Feature\Domain;

use App\Http\Controllers\Frontend\Coach\TeacherBatchAssignmentController;
use App\Models\TeacherBatchAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * "Restrict Batch Assignment to One Teacher at a Time" (2026-07-08).
 * A batch may have only ONE active teacher. Re-assigning a batch already owned
 * by a different teacher requires an explicit confirm_transfer (the UI shows a
 * dialog first); on confirm the old assignment is deactivated (row kept — no
 * data lost) and the new one activated. The backend enforces this even for a
 * direct POST without the UI.
 */
class OneTeacherPerBatchTest extends TestCase
{
    use DatabaseTransactions;

    private User $coach;
    private int $courseId;
    private int $batchId;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->coach = $this->user('instructor', null);
        $this->courseId = DB::table('courses')->insertGetId([
            'title' => 'C ' . uniqid(), 'slug' => 'c-' . uniqid(),
            'instructor_id' => $this->coach->id, 'added_by' => $this->coach->id,
            'is_approved' => 'approved', 'status' => 'active', 'type' => 'live',
            'price' => 0, 'discount' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->batchId = DB::table('course_batches')->insertGetId([
            'course_id' => $this->courseId, 'title' => 'Morning', 'start_time' => '09:00:00',
            'start_date' => '2026-07-10', 'end_date' => '2026-09-10',
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function user(string $role, ?int $coachId): User
    {
        return User::find(DB::table('users')->insertGetId([
            'role' => $role, 'name' => ucfirst($role) . ' ' . uniqid(), 'email' => $role . uniqid() . '@t.local',
            'password' => bcrypt('x'), 'status' => 'active', 'is_banned' => 'no',
            'coach_id' => $coachId, 'created_at' => now(), 'updated_at' => now(),
        ]));
    }

    private function teacher(): int
    {
        return $this->user('Manager', $this->coach->id)->id;
    }

    private function assign(int $teacherId, bool $confirm = false)
    {
        Auth::guard('web')->login($this->coach);
        $data = ['teacher_id' => $teacherId, 'course_id' => $this->courseId, 'batch_ids' => [$this->batchId]];
        if ($confirm) $data['confirm_transfer'] = '1';
        $res = app(TeacherBatchAssignmentController::class)->store(Request::create('/x', 'POST', $data));
        Auth::guard('web')->logout();
        return $res;
    }

    private function activeTeacherIds(): array
    {
        return TeacherBatchAssignment::where('batch_id', $this->batchId)->where('status', 'active')
            ->pluck('teacher_id')->map(fn ($v) => (int) $v)->sort()->values()->all();
    }

    public function test_first_assignment_needs_no_confirmation(): void
    {
        $a = $this->teacher();
        $this->assign($a);
        $this->assertSame([$a], $this->activeTeacherIds(), 'batch now has exactly one active teacher');
    }

    public function test_reassign_without_confirm_is_blocked_and_keeps_owner(): void
    {
        $a = $this->teacher();
        $b = $this->teacher();
        $this->assign($a);

        $res = $this->assign($b, false);   // no confirm_transfer
        $this->assertSame('error', session('alert-type'), 'must ask for confirmation, not assign');

        // Owner unchanged; B did NOT get an active grant.
        $this->assertSame([$a], $this->activeTeacherIds(), 'still only teacher A, unchanged');
        $this->assertFalse(
            TeacherBatchAssignment::where('batch_id', $this->batchId)->where('teacher_id', $b)->where('status', 'active')->exists()
        );
    }

    public function test_confirm_transfers_ownership_and_keeps_history(): void
    {
        $a = $this->teacher();
        $b = $this->teacher();
        $this->assign($a);

        $this->assign($b, true);   // confirm_transfer

        // Exactly one active teacher, and it's B.
        $this->assertSame([$b], $this->activeTeacherIds(), 'ownership transferred to B, single active');

        // A's row still exists (no data lost) but is now inactive.
        $old = TeacherBatchAssignment::where('batch_id', $this->batchId)->where('teacher_id', $a)->first();
        $this->assertNotNull($old, 'old assignment row is kept for audit');
        $this->assertSame('inactive', $old->status, 'old owner deactivated, not deleted');
    }

    public function test_backend_never_leaves_two_active_teachers(): void
    {
        $a = $this->teacher();
        $b = $this->teacher();
        $this->assign($a);
        $this->assign($b, true);
        $this->assign($a, true);   // transfer back

        $this->assertCount(1, $this->activeTeacherIds(), 'at most one active teacher at any time');
        $this->assertSame([$a], $this->activeTeacherIds());
    }

    public function test_update_reactivation_also_enforces_single_active(): void
    {
        $a = $this->teacher();
        $b = $this->teacher();
        $this->assign($a);   // A active

        // B has an inactive row for the same batch; editing it back to active
        // must steal ownership from A (backend invariant on the edit path).
        $bRow = TeacherBatchAssignment::create([
            'coach_id' => $this->coach->id, 'teacher_id' => $b, 'course_id' => $this->courseId,
            'batch_id' => $this->batchId, 'status' => 'inactive', 'assigned_at' => now(),
        ]);

        Auth::guard('web')->login($this->coach);
        app(TeacherBatchAssignmentController::class)->update($bRow->id, Request::create('/x', 'POST', ['status' => 'active']));
        Auth::guard('web')->logout();

        $this->assertSame([$b], $this->activeTeacherIds(), 'edit→active transferred ownership to B, single active');
    }

    public function test_batches_for_course_exposes_current_teacher(): void
    {
        $a = $this->teacher();
        $this->assign($a);

        Auth::guard('web')->login($this->coach);
        $json = app(TeacherBatchAssignmentController::class)->batchesForCourse($this->courseId)->getData(true);
        Auth::guard('web')->logout();

        $batch = collect($json['batches'])->firstWhere('id', $this->batchId);
        $this->assertSame($a, (int) $batch['current_teacher_id'], 'UI can see the current owner to warn before transfer');
        $this->assertNotEmpty($batch['current_teacher_name']);
    }
}
