<?php

namespace Tests\Feature\Domain;

use App\Http\Controllers\Frontend\CoachBatchAttendanceController;
use App\Models\CourseBatch;
use App\Models\LiveClassAttendance;
use App\Models\User;
use App\Services\BatchAttendanceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Order\app\Models\Enrollment;
use Tests\TestCase;

/**
 * 2026-06-02 — a student enrolled in a batch but with has_access=0 (fee
 * pending) is attending class. The coach must be able to SEE them in the
 * attendance roster and MARK them present. Before the fix the roster +
 * bulk-mark filtered on has_access=1, hiding exactly these students.
 *
 * Scope note: seat/capacity counting (CourseBatch::studentCount) stays
 * paid-only by design and is NOT changed here — see studentCount test.
 */
class AttendanceUnpaidMemberTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array{0:User,1:CourseBatch,2:int} coach, batch, courseId */
    private function makeCoachBatch(): array
    {
        $coach = User::factory()->create(['role' => 'instructor', 'coach_id' => null]);
        $courseId = DB::table('courses')->insertGetId([
            'title' => 'Att course ' . uniqid(), 'slug' => 'att-' . uniqid(),
            'instructor_id' => $coach->id, 'added_by' => $coach->id,
            'is_approved' => 'approved', 'status' => 'active',
            'type' => 'course', 'price' => 0, 'discount' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $batch = CourseBatch::create([
            'course_id' => $courseId, 'title' => 'Morning Batch',
            'start_date' => now()->subWeek(), 'end_date' => now()->addMonth(),
            'start_time' => '09:00:00', 'end_time' => '10:00:00',
            'capacity' => 30, 'days' => ['monday'], 'status' => 'active',
        ]);
        return [$coach, $batch, $courseId];
    }

    private function enrol(int $userId, int $courseId, int $batchId, int $hasAccess): void
    {
        $orderId = DB::table('orders')->insertGetId([
            'buyer_id' => $userId, 'payable_currency' => 'INR',
            'payable_amount' => 0, 'paid_amount' => 0, 'payment_method' => 'test',
            'payment_status' => 'completed', 'status' => 'completed',
            'invoice_id' => 'INV-' . uniqid(), 'transaction_id' => 'TXN-' . uniqid(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('enrollments')->insert([
            'user_id' => $userId, 'order_id' => $orderId, 'course_id' => $courseId,
            'batch_id' => $batchId, 'has_access' => $hasAccess,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function makeLessonId(int $courseId, int $coachId): int
    {
        $chapterId = DB::table('course_chapters')->insertGetId([
            'title' => 'Ch ' . uniqid(), 'course_id' => $courseId, 'order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $itemId = DB::table('course_chapter_items')->insertGetId([
            'chapter_id' => $chapterId, 'type' => 'live', 'order' => 1,
            'instructor_id' => $coachId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        return DB::table('course_chapter_lessons')->insertGetId([
            'title' => 'Live ' . uniqid(), 'slug' => 'live-' . uniqid(),
            'instructor_id' => $coachId, 'course_id' => $courseId,
            'chapter_id' => $chapterId, 'chapter_item_id' => $itemId,
            'storage' => 'live', 'file_type' => 'live', 'order' => 1,
            'is_free' => 0, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_unpaid_member_appears_in_attendance_roster(): void
    {
        [$coach, $batch, $courseId] = $this->makeCoachBatch();
        $paid   = User::factory()->create(['role' => 'student']);
        $unpaid = User::factory()->create(['role' => 'student']);
        $this->enrol($paid->id, $courseId, $batch->id, 1);
        $this->enrol($unpaid->id, $courseId, $batch->id, 0); // fee pending

        $roster = app(BatchAttendanceService::class)->studentRoster($batch, now()->toDateString());
        $ids = $roster->pluck('user_id')->all();

        $this->assertContains($paid->id, $ids);
        $this->assertContains($unpaid->id, $ids,
            'an unpaid (has_access=0) batch member must appear in the attendance roster');
    }

    public function test_coach_can_bulk_mark_unpaid_member_present(): void
    {
        [$coach, $batch, $courseId] = $this->makeCoachBatch();
        $unpaid = User::factory()->create(['role' => 'student']);
        $this->enrol($unpaid->id, $courseId, $batch->id, 0);
        // bulkMark's placeholder-class path needs a lesson on the course.
        $this->makeLessonId($courseId, $coach->id);

        Auth::guard('web')->loginUsingId($coach->id);
        $req = Request::create('/x', 'POST', [
            'action'   => 'mark_attended',
            'user_ids' => [$unpaid->id],
            'date'     => now()->toDateString(),
            'reason'   => 'Attended while fee pending',
        ]);
        $resp = app(CoachBatchAttendanceController::class)->bulkMark($req, $batch->id);

        $this->assertSame(200, $resp->getStatusCode(),
            'marking an unpaid batch member present must succeed (was 422 "no matching enrolled students")');

        $marked = LiveClassAttendance::where('user_id', $unpaid->id)
            ->where('is_manual', 1)->exists();
        $this->assertTrue($marked, 'a manual attendance row must be created for the unpaid member');
    }
}
