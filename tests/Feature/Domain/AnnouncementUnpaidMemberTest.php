<?php

namespace Tests\Feature\Domain;

use App\Models\Announcement;
use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\User;
use App\Notifications\NewBatchAnnouncement;
use App\Services\AnnouncementNotifier;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\Order\app\Models\Enrollment;
use Tests\TestCase;

/**
 * 2026-06-02 — a student enrolled in a batch but with has_access=0 (fee
 * pending) must SEE and RECEIVE that batch's announcements. Before the fix
 * both the read scope (scopeVisibleToStudent) and the notifier (fanOut)
 * filtered on has_access=1, so a "fees due Friday" announcement never
 * reached the very students who owed.
 *
 * Decision confirmed with the product owner before implementing.
 */
class AnnouncementUnpaidMemberTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array{0:Course,1:CourseBatch} */
    private function makeBatch(User $coach): array
    {
        $courseId = DB::table('courses')->insertGetId([
            'title' => 'Ann ' . uniqid(), 'slug' => 'ann-' . uniqid(),
            'instructor_id' => $coach->id, 'added_by' => $coach->id,
            'is_approved' => 'approved', 'status' => 'active', 'type' => 'live',
            'price' => 0, 'discount' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $course = Course::find($courseId);
        $batch = CourseBatch::create([
            'course_id' => $course->id, 'title' => 'Batch X',
            'start_date' => now(), 'end_date' => now()->addMonth(),
            'start_time' => '09:00:00', 'end_time' => '11:00:00',
            'capacity' => 30, 'days' => ['monday'], 'status' => 'active',
        ]);
        return [$course, $batch];
    }

    private function batchAnnouncement(User $coach, Course $course, CourseBatch $batch): Announcement
    {
        $ann = Announcement::create([
            'instructor_id' => $coach->id, 'sender_role' => 'instructor',
            'audience_type' => 'batch_specific', 'course_id' => $course->id,
            'batch_id' => $batch->id, 'title' => 'Fees due Friday',
            'announcement' => 'Please clear pending fees.', 'status' => 'active',
        ]);
        $ann->batches()->sync([$batch->id]);
        return $ann;
    }

    public function test_unpaid_member_sees_batch_announcement(): void
    {
        $coach = User::factory()->create(['role' => 'instructor']);
        [$course, $batch] = $this->makeBatch($coach);

        $unpaid = User::factory()->create(['role' => 'student']);
        Enrollment::create([
            'user_id' => $unpaid->id, 'course_id' => $course->id,
            'batch_id' => $batch->id, 'order_id' => null, 'has_access' => 0,
        ]);

        $ann = $this->batchAnnouncement($coach, $course, $batch);

        $visible = Announcement::visibleToStudent($unpaid)->pluck('id')->all();
        $this->assertContains($ann->id, $visible,
            'a fee-pending (has_access=0) batch member must see the batch announcement');
    }

    public function test_fanout_notifies_unpaid_member(): void
    {
        Notification::fake();
        $coach = User::factory()->create(['role' => 'instructor']);
        [$course, $batch] = $this->makeBatch($coach);

        $paid   = User::factory()->create(['role' => 'student']);
        $unpaid = User::factory()->create(['role' => 'student']);
        Enrollment::create(['user_id' => $paid->id, 'course_id' => $course->id,
            'batch_id' => $batch->id, 'order_id' => null, 'has_access' => 1]);
        Enrollment::create(['user_id' => $unpaid->id, 'course_id' => $course->id,
            'batch_id' => $batch->id, 'order_id' => null, 'has_access' => 0]);

        $ann = $this->batchAnnouncement($coach, $course, $batch);
        $count = app(AnnouncementNotifier::class)->fanOut($ann);

        $this->assertSame(2, $count, 'fanout must reach both the paid and the fee-pending member');
        Notification::assertSentTo($paid, NewBatchAnnouncement::class);
        Notification::assertSentTo($unpaid, NewBatchAnnouncement::class);
    }

    public function test_non_member_still_excluded(): void
    {
        // The fix must not over-expose: a student in no batch of the course
        // still sees / receives nothing.
        Notification::fake();
        $coach = User::factory()->create(['role' => 'instructor']);
        [$course, $batch] = $this->makeBatch($coach);
        $outsider = User::factory()->create(['role' => 'student']);

        $ann = $this->batchAnnouncement($coach, $course, $batch);

        $visible = Announcement::visibleToStudent($outsider)->pluck('id')->all();
        $this->assertNotContains($ann->id, $visible,
            'a non-member must NOT see the batch announcement');

        app(AnnouncementNotifier::class)->fanOut($ann);
        Notification::assertNotSentTo($outsider, NewBatchAnnouncement::class);
    }
}
