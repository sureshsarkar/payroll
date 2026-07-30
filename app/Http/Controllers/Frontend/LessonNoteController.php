<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\CourseChapterLesson;
use App\Models\LessonNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Order\app\Models\Enrollment;

/**
 * Per-user, per-lesson personal notes — server-side mirror of the
 * notes drawer in the live-class launcher.
 *
 * Authorization gate: caller must be the course instructor OR be
 * enrolled in the course (mirrors ZoomSignatureController +
 * LiveClassAttendanceController so the rules are uniform across the
 * three live-class endpoints).
 *
 * Endpoints:
 *   GET  /lesson-notes/{lesson_id}   → returns this user's note body
 *                                       (empty string if none yet)
 *   POST /lesson-notes/{lesson_id}   → upserts the note. Body capped
 *                                       to 50_000 chars to defend against
 *                                       paste-attack DOS.
 */
class LessonNoteController extends Controller
{
    private const MAX_BODY = 50000;

    public function show(int $lesson_id): JsonResponse
    {
        $user = userAuth();
        abort_unless($user, 401);

        $this->authorizeAccess($user, $lesson_id);

        $note = LessonNote::where('user_id', $user->id)
            ->where('lesson_id', $lesson_id)
            ->first();

        return response()->json([
            'body'       => (string) ($note->body ?? ''),
            'updated_at' => $note?->updated_at?->toIso8601String(),
        ]);
    }

    public function store(Request $request, int $lesson_id): JsonResponse
    {
        $user = userAuth();
        abort_unless($user, 401);

        $this->authorizeAccess($user, $lesson_id);

        $body = (string) $request->input('body', '');
        if (mb_strlen($body) > self::MAX_BODY) {
            return response()->json([
                'ok'    => false,
                'error' => 'note too long',
                'limit' => self::MAX_BODY,
            ], 422);
        }

        $note = LessonNote::updateOrCreate(
            ['user_id' => $user->id, 'lesson_id' => $lesson_id],
            ['body' => $body]
        );

        return response()->json([
            'ok'         => true,
            'updated_at' => $note->updated_at->toIso8601String(),
        ]);
    }

    private function authorizeAccess($user, int $lessonId): void
    {
        $lesson = CourseChapterLesson::with(['course:id,instructor_id'])
            ->select('id', 'course_id')
            ->findOrFail($lessonId);

        $isInstructor = (int) ($lesson->course?->instructor_id ?? 0) === (int) $user->id;
        if ($isInstructor) {
            return;
        }

        $enrolled = Enrollment::where('user_id', $user->id)
            ->where('course_id', $lesson->course_id)
            ->where('has_access', 1)
            ->exists();

        abort_unless($enrolled, 403, 'You are not enrolled in this course.');
    }
}
