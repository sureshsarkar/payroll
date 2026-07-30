<?php

namespace Tests\Feature\Frontend;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Item #2 — live classes must NOT appear in the recorded chapter/lesson list of
 * the student learning panel (they belong only in the dedicated Live Classes
 * section). Verified for a HYBRID course (recorded lessons + live), which
 * previously still showed the live rows.
 */
class LearningLiveClassExclusionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_live_items_are_excluded_from_the_learning_chapter_list(): void
    {
        $coachId = DB::table('users')->insertGetId([
            'role' => 'instructor', 'name' => 'C', 'email' => 'c' . uniqid() . '@t.local',
            'password' => bcrypt('x'), 'status' => 'active', 'is_banned' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $student = User::query()->create([
            'role' => 'student', 'name' => 'S', 'email' => 's' . uniqid() . '@t.local',
            'password' => bcrypt('x'), 'status' => 'active', 'is_banned' => 0,
        ]);

        $slug = 'hybrid-' . uniqid();
        $courseId = DB::table('courses')->insertGetId([
            'instructor_id' => $coachId, 'added_by' => $coachId, 'type' => 'hybrid',
            'title' => 'Hybrid Course', 'slug' => $slug, 'price' => 100, 'discount' => 0,
            'status' => 'active', 'is_approved' => 'approved', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $chapterId = DB::table('course_chapters')->insertGetId([
            'title' => 'Chapter 1', 'instructor_id' => $coachId, 'course_id' => $courseId,
            'order' => 1, 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        // Recorded lesson item + its lesson row.
        $lessonItemId = DB::table('course_chapter_items')->insertGetId([
            'instructor_id' => $coachId, 'chapter_id' => $chapterId, 'type' => 'lesson',
            'order' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('course_chapter_lessons')->insert([
            'title' => 'Recorded Lesson 1', 'slug' => 'rec-' . uniqid(), 'instructor_id' => $coachId,
            'course_id' => $courseId, 'chapter_id' => $chapterId, 'chapter_item_id' => $lessonItemId,
            'file_path' => 'x.mp4', 'storage' => 'local', 'file_type' => 'video', 'status' => 1,
            'order' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        // Live-class item — must NOT show in the chapter list.
        $liveItemId = DB::table('course_chapter_items')->insertGetId([
            'instructor_id' => $coachId, 'chapter_id' => $chapterId, 'type' => 'live',
            'order' => 2, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('enrollments')->insert([
            'user_id' => $student->id, 'course_id' => $courseId, 'coach_id' => $coachId,
            'has_access' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Call the controller directly and inspect the view DATA (avoids rendering
        // the heavy learning-player template; we only care about the filtered list).
        $this->actingAs($student);
        $view = app(\App\Http\Controllers\Frontend\LearningController::class)->index($slug);
        $course = $view->getData()['course'];

        $ids = $course->chapters->flatMap->chapterItems->pluck('id')->all();
        $this->assertContains($lessonItemId, $ids, 'recorded lesson must remain in the list');
        $this->assertNotContains($liveItemId, $ids, 'live class must be excluded from the chapter list');

        // And the completion count is untouched (still counts every chapter item).
        $this->assertSame(2, $view->getData()['courseLectureCount'],
            'completion count must be unchanged (display-only filter)');
    }
}
