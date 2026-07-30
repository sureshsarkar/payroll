<?php

namespace Modules\Course\app\Http\Controllers;

use App\Models\Quiz;
use App\Models\Course;
use App\Models\QuizQuestion;
use Illuminate\Http\Request;
use App\Models\CourseChapter;
use App\Models\CourseChapterItem;
use App\Models\CourseChapterLesson;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Session\Session;
use App\Http\Requests\Frontend\QuizLessonCreateRequest;
use Modules\Course\app\Http\Requests\ChapterLessonRequest;

class CourseContentController extends Controller
{
    /**
     * SECURITY (audit 2026-05-22) — coach ownership gate.
     *
     * Original chapter store/update/edit/destroy methods used
     * `Course::findOrFail()` / `CourseChapter::findOrFail()` then relied
     * only on `checkAdminHasPermissionAndThrowException('course.management')`,
     * which is the ADMIN permission system — it does NOT verify that
     * the chapter's course belongs to the requesting coach. Any
     * instructor could update/delete another coach's chapters by URL
     * guess.
     *
     * This helper resolves the current coach id (instructor or staff
     * impersonating an instructor) and returns the course/chapter
     * ONLY if it belongs to that coach. Throws 404 otherwise.
     */
    protected function currentCoachId(): int
    {
        $u = userAuth();
        return ($u && $u->role === 'instructor') ? (int) $u->id : (int) ($u->coach_id ?? $u->id);
    }

    protected function ownedCourseOrFail(string $courseId): Course
    {
        $coachId = $this->currentCoachId();
        return Course::where('id', $courseId)
            ->where(function ($q) use ($coachId) {
                $q->where('added_by', $coachId)
                  ->orWhere('instructor_id', $coachId);
            })
            ->firstOrFail();
    }

    protected function ownedChapterOrFail(string $chapterId): CourseChapter
    {
        $coachId = $this->currentCoachId();
        $chapter = CourseChapter::findOrFail($chapterId);
        // Match either via the chapter's own instructor_id OR by reaching
        // through to the parent course.
        $okChapter = (int) $chapter->instructor_id === $coachId;
        $okCourse  = Course::where('id', $chapter->course_id)
            ->where(function ($q) use ($coachId) {
                $q->where('added_by', $coachId)
                  ->orWhere('instructor_id', $coachId);
            })
            ->exists();
        abort_unless($okChapter || $okCourse, 404);
        return $chapter;
    }

    /**
     * FT-IDOR-34 (2026-05-28) — quiz ownership helper.
     *
     * Quiz rows carry course_id + chapter_id + instructor_id columns,
     * so the ownership check has two paths:
     *   1. quiz.instructor_id == current coach
     *   2. quiz.course_id belongs to a course the coach owns
     * Either path 200s; otherwise 404 (matching the ownedChapter idiom).
     */
    protected function ownedQuizOrFail(string $quizId): Quiz
    {
        $coachId = $this->currentCoachId();
        $quiz = Quiz::findOrFail($quizId);
        $okDirect = (int) ($quiz->instructor_id ?? 0) === $coachId;
        $okCourse = $quiz->course_id && Course::where('id', $quiz->course_id)
            ->where(function ($q) use ($coachId) {
                $q->where('added_by', $coachId)
                  ->orWhere('instructor_id', $coachId);
            })
            ->exists();
        abort_unless($okDirect || $okCourse, 404);
        return $quiz;
    }

    /**
     * FT-IDOR-34 — quiz-question ownership helper. Reaches through
     * the question's quiz_id to the quiz's course.
     */
    protected function ownedQuizQuestionOrFail(string $questionId): QuizQuestion
    {
        $question = QuizQuestion::findOrFail($questionId);
        // ownedQuizOrFail aborts 404 on mismatch.
        $this->ownedQuizOrFail((string) $question->quiz_id);
        return $question;
    }

    function chapterStore(Request $request, string $courseId): RedirectResponse
    {
        $request->validate([
            'title' => ['required', 'max:255'],
        ], [
            'title.required' => __('Title is required'),
            'title.max' => __('Title is too long'),
        ]);

        $course = $this->ownedCourseOrFail($courseId);
        $chapter = new CourseChapter();
        $chapter->title = $request->title;
        $chapter->course_id = $course->id;
        $chapter->instructor_id = $course->instructor_id;
        $chapter->status = 'active';
        $chapter->order = CourseChapter::where('course_id', $course->id)->max('order') + 1;
        $chapter->save();

        return redirect()->back()->with(['messege' => __('Chapter created successfully'), 'alert-type' => 'success']);
    }

    function chapterEdit(string $chapterId)
    {
        $chapter = $this->ownedChapterOrFail($chapterId);
        return view('course::course.partials.edit-section-modal', compact('chapter'))->render();
    }

    function chapterUpdate(Request $request, string $chapterId)
    {
        $chapter = $this->ownedChapterOrFail($chapterId);
        $chapter->title = $request->title;
        $chapter->save();
        return redirect()->back()->with(['messege' => __('Updated successfully'), 'alert-type' => 'success']);
    }

    function chapterDestroy(string $chapterId)
    {
        $chapter = $this->ownedChapterOrFail($chapterId);
        $chapterItems = CourseChapterItem::where('chapter_id', $chapterId)->get();
        $lessonFiles = CourseChapterLesson::whereIn('chapter_item_id', $chapterItems->pluck('id'))->get();
        $quizIds = Quiz::whereIn('chapter_item_id', $chapterItems->pluck('id'))->pluck('id');
        $questionIds = QuizQuestion::whereIn('quiz_id', $quizIds)->pluck('id');

        // delete quizzes, questions, answers and lesson files
        QuizQuestion::whereIn('id', $questionIds)->delete();
        Quiz::whereIn('id', $quizIds)->delete();
        CourseChapterLesson::whereIn('id', $lessonFiles->pluck('id'))->delete();
        foreach ($lessonFiles as $lesson) {
            // asset() returns a URL — File::exists/delete on it always no-ops.
            // Use public_path for local files; Storage::disk(...) for cloud.
            $path = $lesson->file_path;
            if (!$path) continue;
            if ($lesson->storage === 'upload') {
                $full = public_path($path);
                if (\File::exists($full)) \File::delete($full);
            } elseif (in_array($lesson->storage, ['wasabi', 'aws', 's3'])) {
                try {
                    \Illuminate\Support\Facades\Storage::disk($lesson->storage)->delete($path);
                } catch (\Throwable $e) { /* disk may not be configured */ }
            }
        }

        // delete chapter items and chapter
        CourseChapterItem::whereIn('id', $chapterItems->pluck('id'))->delete();
        $chapter->delete();

        return response()->json(['status' => 'success', 'message' => __('Question deleted successfully')]);
    }

    function chapterSorting(string $courseId)
    {
        $chapters = CourseChapter::where('course_id', $courseId)->orderBy('order', 'ASC')->get();
        return view('course::course.partials.chapter-sorting-index', compact('chapters', 'courseId'))->render();
    }

    function chapterSortingStore(Request $request, string $courseId)
    {
        $newOrder = $request->chapter_ids;

        foreach ($newOrder as $key => $value) {
            $chapter = CourseChapter::where('course_id', $courseId)->find($value);
            $chapter->order = $key + 1;
            $chapter->save();
        }

        return redirect()->back()->with(['messege' => __('Updated successfully'), 'alert-type' => 'success']);
    }

    function lessonCreate(Request $request)
    {
        $courseId = $request->courseId;
        $chapterId = $request->chapterId;
        $chapters = CourseChapter::where('course_id', $courseId)->get();
        $type = $request->type;
        if ($request->type == 'lesson') {
            return view('course::course.partials.lesson-create-modal', [
                'courseId' => $courseId,
                'chapterId' => $chapterId,
                'chapters' => $chapters,
                'type' => $type
            ])->render();
        }elseif ($request->type == 'document') {
            return view('course::course.partials.document-create-modal', [
                'courseId' => $courseId,
                'chapterId' => $chapterId,
                'chapters' => $chapters,
                'type' => $type
            ])->render();
        } elseif ($request->type == 'quiz') {
            return view('course::course.partials.quiz-create-modal', [
                'courseId' => $courseId,
                'chapterId' => $chapterId,
                'chapters' => $chapters,
                'type' => $type
            ])->render();
        }
    }

    function lessonStore(ChapterLessonRequest $request)
    {
        $chapterItem = CourseChapterItem::create([
            'instructor_id' => Course::find(session()->get('course_create'))->instructor_id,    
            'chapter_id' => $request->chapter_id,
            'type' => $request->type,
            'order' => CourseChapterItem::whereChapterId($request->chapter_id)->count() + 1,
        ]);

        if ($request->type == 'lesson') {
            CourseChapterLesson::create([
                'title' => $request->title,
                'description' => $request->description,
                'instructor_id' =>  $chapterItem->instructor_id,
                'course_id' => $request->course_id,
                'chapter_id' => $request->chapter_id,
                'chapter_item_id' => $chapterItem->id,
                'file_path' => $request->source == 'upload' ? $request->upload_path : $request->link_path,
                'storage' => $request->source,
                'file_type' => $request->file_type,
                'volume' => $request->volume,
                'duration' => $request->duration,
                'is_free' => $request->is_free,
            ]);
        }elseif ($request->type == 'document') {
            CourseChapterLesson::create([
                'title' => $request->title,
                'description' => $request->description,
                'instructor_id' =>  $chapterItem->instructor_id,
                'course_id' => $request->course_id,
                'chapter_id' => $request->chapter_id,
                'chapter_item_id' => $chapterItem->id,
                'file_path' => $request->upload_path,
                'file_type' => $request->file_type,
            ]);
        } elseif ($request->type == 'quiz') {
            Quiz::create([
                'chapter_item_id' => $chapterItem->id,
                'instructor_id' => $chapterItem->instructor_id,
                'chapter_id' => $request->chapter,
                'course_id' => $request->course_id,
                'title' => $request->title,
                'time' => $request->time_limit,
                'attempt' => $request->attempts,
                'pass_mark' => $request->pass_mark,
                'total_mark' => $request->total_mark,
            ]);
        }

        return response()->json(['status' => 'success', 'message' => __('Lesson created successfully')]);
    }

    function lessonEdit(Request $request)
    {
        // Audit 2026-05-18 fixture-aware smoke fix:
        // The admin frontend always passes ?courseId&chapterItemId&type via
        // AJAX. If any of those are missing the partials read a null
        // CourseChapterItem and 500. Validate up front and 400 instead so
        // the failure mode is debuggable and so the Tier-A smoke test can
        // assert "no 500 with a fixture" rather than relying on internal
        // null-safety.
        $courseId = $request->courseId;
        $chapterItemId = $request->chapterItemId;
        $type = $request->type;

        if (!$courseId || !$chapterItemId || !$type) {
            abort(400, 'lessonEdit requires courseId, chapterItemId, type');
        }

        $chapterItem = CourseChapterItem::with(['lesson', 'quiz'])->find($chapterItemId);
        if (!$chapterItem) {
            abort(404, 'Chapter item not found');
        }

        $chapters = CourseChapter::where('course_id', $courseId)->get();
        if ($type == 'lesson') {
            return view('course::course.partials.lesson-edit-modal', [
                'chapters' => $chapters,
                'courseId' => $courseId,
                'chapterItem' => $chapterItem
            ])->render();
        }elseif ($type == 'document') {
            return view('course::course.partials.document-edit-modal', [
                'chapters' => $chapters,
                'courseId' => $courseId,
                'chapterItem' => $chapterItem
            ])->render();
        } else {
            return view('course::course.partials.quiz-edit-modal', [
                'chapters' => $chapters,
                'courseId' => $courseId,
                'chapterItem' => $chapterItem
            ])->render();
        }
    }

    function lessonUpdate(ChapterLessonRequest $request)
    {
        checkAdminHasPermissionAndThrowException('course.management');

        $chapterItem = CourseChapterItem::findOrFail($request->chapter_item_id);

        $chapterItem->update([
            'chapter_id' => $request->chapter
        ]);

        if ($request->type == 'lesson') {
            $courseChapterLesson = CourseChapterLesson::where('chapter_item_id', $chapterItem->id)->first();

            $old_file_path = $courseChapterLesson->file_path;
            if (in_array($courseChapterLesson->storage, ['wasabi', 'aws']) && $old_file_path != $request->link_path) {
                $disk = Storage::disk($courseChapterLesson->storage);
                $disk->exists($old_file_path) && $disk->delete($old_file_path);
            }

            $courseChapterLesson->update([
                'title' => $request->title,
                'description' => $request->description,
                'course_id' => $chapterItem->course_id,
                'chapter_id' => $chapterItem->chapter_id,
                'chapter_item_id' => $chapterItem->id,
                'file_path' => $request->source == 'upload' ? $request->upload_path : $request->link_path,
                'storage' => $request->source,
                'file_type' => $request->file_type,
                'volume' => $request->volume,
                'duration' => $request->duration,
            ]);
        }elseif($request->type == 'document') {
            $courseChapterLesson = CourseChapterLesson::where('chapter_item_id', $chapterItem->id)->first();
            $courseChapterLesson->update([
                'title' => $request->title,
                'description' => $request->description,
                'course_id' => $chapterItem->course_id,
                'chapter_id' => $chapterItem->chapter_id,
                'chapter_item_id' => $chapterItem->id,
                'file_path' => $request->upload_path,
                'file_type' => $request->file_type,
            ]);
        } else {
            $quiz = Quiz::where('chapter_item_id', $chapterItem->id)->first();
            $quiz->update([
                'chapter_item_id' => $chapterItem->id,
                'title' => $request->title,
                'time' => $request->time_limit,
                'attempt' => $request->attempts,
                'pass_mark' => $request->pass_mark,
                'total_mark' => $request->total_mark,
            ]);
        }

        return response()->json(['status' => 'success', 'message' => __('Lesson updated successfully')]);
    }

    function sortLessons(Request $request, string $chapterId)
    {
        $newOrder = $request->orderIds;
        foreach ($newOrder as $key => $itemId) {
            $chapterItem = CourseChapterItem::where(['chapter_id' => $chapterId, 'id' => $itemId])->first();
            $chapterItem->order = $key + 1;
            $chapterItem->save();
        }

        return response()->json(['status' => 'success', 'message' => __('Lesson sorted successfully')]);
    }

    function chapterLessonDestroy(string $chapterItemId)
    {
        checkAdminHasPermissionAndThrowException('course.management');
        $chapterItem = CourseChapterItem::findOrFail($chapterItemId);

        if ($chapterItem->type == 'quiz') {
            $quiz = $chapterItem->quiz;
            $question = $quiz->questions;
            foreach ($question as $key => $question) {
                $question->answers()->delete();
                $question->delete();
            }
            $quiz->delete();
            $chapterItem->delete();
        } else {
            if (in_array($chapterItem->lesson->storage, ['wasabi', 'aws'])) {
                $disk = Storage::disk($chapterItem->lesson->storage);
                $filePath = $chapterItem->lesson->file_path;
                $disk->exists($filePath) && $disk->delete($filePath);
            } elseif ($chapterItem->lesson->storage === 'upload') {
                $full = public_path($chapterItem->lesson->file_path);
                if (\File::exists($full)) \File::delete($full);
            }
            // delete lesson row
            $chapterItem->lesson()->delete();
            $chapterItem->delete();
        }

        return response()->json(['status' => 'success', 'message' => __('Lesson deleted successfully')]);
    }

    function createQuizQuestion(string $quizId)
    {
        // FT-IDOR-34 — gate the create-modal too. Without this, a
        // foreign coach could render the modal for the victim's
        // quiz and see the form action URL / quizId set up — minor
        // info disclosure but easier to fix here than to track as
        // a separate exception.
        $this->ownedQuizOrFail($quizId);
        return view('course::course.partials.quiz-question-create-modal', ['quizId' => $quizId])->render();
    }

    function storeQuizQuestion(Request $request, string $quizId)
    {
        // FT-IDOR-34 fix (2026-05-28) — was no ownership check on
        // $quizId. An attacker could POST to
        // /instructor/quiz-question/{victim_quiz_id}/store and add
        // bogus questions to a victim coach's certification quiz
        // (or fill it with intentionally-wrong "correct" answers to
        // sabotage their students' pass rates).
        $this->ownedQuizOrFail($quizId);

        $request->validate([
            'title' => ['required', 'max:255'],
            'answers.*' => ['required', 'max:255'],
            'grade' => ['required', 'numeric', 'min:0']
        ], [
            'title.required' => __('Question title is required'),
            'title.max' => __('Question title should not be more than 255 characters'),
            'answers.*.required' => __('At least one answer is required'),
            'answers.*.max' => __('Answer should not be more than 255 characters'),
            'grade.required' => __('Grade is required'),
            'grade.numeric' => __('Grade should be a number'),
            'grade.min' => __('Grade should be greater than or equal to 0'),
        ]);

        $question = QuizQuestion::create([
            'quiz_id' => $quizId,
            'title' => $request->title,
            'grade' => $request->grade
        ]);

        foreach ($request->answers as $key => $answer) {
            $question->answers()->create([
                'title' => $answer,
                'correct' => isset($request->correct[$key]) ? 1 : 0,
                'question_id' => $question->id
            ]);
        }

        return response()->json(['status' => 'success', 'message' => __('Question created successfully')]);
    }

    function editQuizQuestion(string $questionId)
    {
        // FT-IDOR-34 fix — was QuizQuestion::findOrFail($questionId)
        // with no ownership check. The edit modal renders the
        // question's title + correct answer pre-populated; without
        // the check, an attacker could harvest every other coach's
        // quiz questions + answer keys.
        $question = $this->ownedQuizQuestionOrFail($questionId);
        return view('course::course.partials.quiz-question-edit-modal', ['question' => $question])->render();
    }

    function updateQuizQuestion(Request $request, string $questionId)
    {
        // FT-IDOR-34 fix — was no ownership check. updateQuizQuestion
        // deletes ALL existing answers (line below) then re-creates
        // attacker-controlled ones. Effect on a victim coach's quiz:
        // every question's "correct" answer becomes whatever the
        // attacker submitted — i.e. every student attempting that
        // certification quiz now passes (or fails, depending on the
        // attacker's intent) regardless of their actual answers.
        $question = $this->ownedQuizQuestionOrFail($questionId);

        $request->validate([
            'title' => ['required', 'max:255'],
            'answers.*' => ['required', 'max:255'],
            'grade' => ['required', 'numeric', 'min:0']
        ], [
            'title.required' => __('Question title is required'),
            'title.max' => __('Question title should not be more than 255 characters'),
            'answers.*.required' => __('At least one answer is required'),
            'answers.*.max' => __('Answer should not be more than 255 characters'),
            'grade.required' => __('Grade is required'),
            'grade.numeric' => __('Grade should be a number'),
            'grade.min' => __('Grade should be greater than or equal to 0'),
        ]);

        $question->update([
            'title' => $request->title,
            'grade' => $request->grade
        ]);
        // update or delete answers
        $question->answers()->delete();
        foreach ($request->answers as $key => $answer) {
            $question->answers()->create([
                'title' => $answer,
                'correct' => isset($request->correct[$key]) ? 1 : 0,
                'question_id' => $question->id
            ]);
        }

        return response()->json(['status' => 'success', 'message' => __('Question updated successfully')]);
    }

    function destroyQuizQuestion(string $questionId)
    {
        // FT-IDOR-34 fix — was no ownership check. An attacker could
        // delete questions from any quiz on the platform; combined
        // with no admin audit log on QuizQuestion::delete(), the
        // victim coach has no signal that their quiz contents are
        // being thinned out.
        $question = $this->ownedQuizQuestionOrFail($questionId);
        $question->answers()->delete();
        $question->delete();
        return response()->json(['status' => 'success', 'message' => __('Question deleted successfully')]);
    }
}
