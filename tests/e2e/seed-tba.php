<?php

/**
 * Idempotent fixture for the "one teacher per batch" E2E (Task 3, 2026-07-08).
 * Sets up, for the e2e-instructor coach: two staff teachers, a live course with
 * one batch, and an ACTIVE assignment of that batch to Teacher A — so the assign
 * form shows a transfer conflict when the coach re-assigns it to Teacher B.
 *
 *   php tests/e2e/seed-tba.php
 */

$base = 'D:/xampp/htdocs/MBSGuru1';
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$coach = DB::table('users')->where('email', 'e2e-instructor@mbsguru.test')->first();
if (! $coach) { fwrite(STDERR, "e2e-instructor missing — run seed-fixtures.php first\n"); exit(1); }
$coachId = (int) $coach->id;

function ensureTeacher(int $coachId, string $email, string $name): int
{
    $id = DB::table('users')->where('email', $email)->value('id');
    if ($id) { DB::table('users')->where('id', $id)->update(['coach_id' => $coachId, 'role' => 'Manager', 'name' => $name]); return (int) $id; }
    return (int) DB::table('users')->insertGetId([
        'role' => 'Manager', 'name' => $name, 'email' => $email, 'coach_id' => $coachId,
        'password' => bcrypt('e2e!Test#2026'), 'status' => 'active', 'is_banned' => 'no',
        'email_verified_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

$teacherA = ensureTeacher($coachId, 'e2e-teacher-a@mbsguru.test', 'E2E Teacher A');
$teacherB = ensureTeacher($coachId, 'e2e-teacher-b@mbsguru.test', 'E2E Teacher B');

$courseId = DB::table('courses')->where('slug', 'e2e-tba-course')->value('id');
if (! $courseId) {
    $courseId = DB::table('courses')->insertGetId([
        'title' => 'E2E TBA Course', 'slug' => 'e2e-tba-course',
        'instructor_id' => $coachId, 'added_by' => $coachId,
        'is_approved' => 'approved', 'status' => 'active', 'type' => 'live',
        'price' => 0, 'discount' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
}
$courseId = (int) $courseId;

$batchId = DB::table('course_batches')->where('course_id', $courseId)->where('title', 'E2E TBA Batch')->value('id');
if (! $batchId) {
    $batchId = DB::table('course_batches')->insertGetId([
        'course_id' => $courseId, 'title' => 'E2E TBA Batch', 'start_time' => '09:00:00',
        'start_date' => date('Y-m-d', strtotime('+2 days')), 'end_date' => date('Y-m-d', strtotime('+60 days')),
        'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
    ]);
}
$batchId = (int) $batchId;

// Reset: batch owned (active) by Teacher A only.
DB::table('teacher_batch_assignments')->where('coach_id', $coachId)->where('batch_id', $batchId)->update(['status' => 'inactive']);
$existing = DB::table('teacher_batch_assignments')->where('coach_id', $coachId)->where('teacher_id', $teacherA)->where('batch_id', $batchId)->first();
if ($existing) {
    DB::table('teacher_batch_assignments')->where('id', $existing->id)->update(['status' => 'active', 'course_id' => $courseId, 'assigned_at' => now()]);
} else {
    DB::table('teacher_batch_assignments')->insert([
        'coach_id' => $coachId, 'teacher_id' => $teacherA, 'course_id' => $courseId, 'batch_id' => $batchId,
        'permission_type' => 'manage', 'status' => 'active', 'assigned_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

echo "OK course={$courseId} batch={$batchId} teacherA={$teacherA} teacherB={$teacherB}\n";
