<?php

/**
 * Idempotent fixture for the Featured Courses Carousel E2E (Task 4, 2026-07-08).
 * Seeds a featured_courses_v1 section (with the coach's own published course ids)
 * onto the e2e-instructor home page so the rendered carousel can be exercised.
 *
 *   php tests/e2e/seed-featured-courses.php
 */

$base = 'D:/xampp/htdocs/MBSGuru1';
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$coach = DB::table('users')->where('email', 'e2e-instructor@mbsguru.test')->value('id');
if (! $coach) { fwrite(STDERR, "e2e-instructor missing — run seed-fixtures.php first\n"); exit(1); }

$pageId = DB::table('coach_pages')->where('coach_id', $coach)->orderBy('id')->value('id');
if (! $pageId) { fwrite(STDERR, "no coach_page for e2e-instructor\n"); exit(1); }

// --empty seeds the section with NO courses (clean state for the editor-save
// round-trip regression test); default seeds 3 for the render test.
$empty = in_array('--empty', $argv ?? [], true);
$ids = $empty ? [] : DB::table('courses')
    ->where('instructor_id', $coach)->where('is_approved', 'approved')
    ->where('status', 'active')->where('coach_soft_delete', 0)
    ->orderByDesc('id')->limit(3)->pluck('id')->all();
if (! $empty && empty($ids)) { fwrite(STDERR, "e2e-instructor has no published courses\n"); exit(1); }

$content = json_encode([
    'title' => 'Featured Courses', 'badge' => 'Courses', 'intro' => 'Hand-picked for you.',
    'course_ids' => $ids, 'autoplay' => true, 'autoplay_speed' => 5,
    'arrows' => true, 'dots' => true, 'show_mode' => true, 'show_button' => true, 'btn_text' => 'View Course',
]);

$existing = DB::table('landing_sections')->where('coach_page_id', $pageId)->where('section_type', 'featured_courses_v1')->value('id');
if ($existing) {
    DB::table('landing_sections')->where('id', $existing)->update(['content_json' => $content, 'is_visible' => 1, 'updated_at' => now()]);
    $sid = $existing;
} else {
    $sid = DB::table('landing_sections')->insertGetId([
        'coach_page_id' => $pageId, 'section_type' => 'featured_courses_v1', 'section_version' => 'v1',
        'content_json' => $content, 'sort_order' => 50, 'is_visible' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

echo "OK page={$pageId} section={$sid} course_ids=" . implode(',', $ids) . "\n";
echo "preview: /coach-preview/{$pageId}\n";
