<?php

namespace App\Http\Controllers\Frontend;

use App\Models\Quiz;
use App\Models\User;
use App\Models\Course;
use App\Models\QuizQuestion;
use Illuminate\Http\Request;
use App\Models\CourseChapter;
use App\Models\CourseLiveClass;
use App\Models\CourseChapterItem;
use App\Models\CourseChapterLesson;
use App\Services\MailSenderService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Modules\Order\app\Models\Enrollment;
use App\Http\Requests\Frontend\ChapterLessonRequest;

class CourseContentController extends Controller {
    private function ownerCoachId(): int {
        $u = userAuth();
        return $u->role === 'instructor' ? (int) $u->id : (int) ($u->coach_id ?? $u->id);
    }

    private function findOwnedCourseOrFail($id): Course {
        $coachId = $this->ownerCoachId();
        return Course::withTrashed()
            ->where(function ($q) use ($coachId) {
                $q->where('added_by', $coachId)->orWhere('instructor_id', $coachId);
            })
            ->findOrFail($id);
    }

    private function findOwnedChapterOrFail($id): CourseChapter {
        $coachId = $this->ownerCoachId();
        return CourseChapter::whereHas('course', function ($q) use ($coachId) {
            $q->withTrashed()
              ->where(function ($qq) use ($coachId) {
                  $qq->where('added_by', $coachId)->orWhere('instructor_id', $coachId);
              });
        })->findOrFail($id);
    }

    private function findOwnedChapterItemOrFail($id): CourseChapterItem {
        $coachId = $this->ownerCoachId();
        return CourseChapterItem::whereHas('chapter.course', function ($q) use ($coachId) {
            $q->withTrashed()
              ->where(function ($qq) use ($coachId) {
                  $qq->where('added_by', $coachId)->orWhere('instructor_id', $coachId);
              });
        })->findOrFail($id);
    }

    /**
     * SECURITY (audit 2026-06-12) — quiz ownership: a quiz belongs to this
     * coach only when its course's added_by/instructor_id matches. The
     * quiz-question CRUD below previously used bare QuizQuestion::findOrFail,
     * letting a coach read/edit/delete another coach's quiz questions by id.
     */
    private function findOwnedQuizOrFail($id): Quiz {
        $coachId = $this->ownerCoachId();
        return Quiz::whereHas('course', function ($q) use ($coachId) {
            $q->withTrashed()
              ->where(function ($qq) use ($coachId) {
                  $qq->where('added_by', $coachId)->orWhere('instructor_id', $coachId);
              });
        })->findOrFail($id);
    }

    private function findOwnedQuizQuestionOrFail($id): QuizQuestion {
        $question = QuizQuestion::findOrFail($id);
        // Verifies the parent quiz (→course) is owned; 404s otherwise.
        $this->findOwnedQuizOrFail($question->quiz_id);

        return $question;
    }

    function chapterStore(Request $request, string $courseId): RedirectResponse {
        $request->validate([
            'title' => ['required', 'max:255'],
        ], [
            'title.required' => __('Title is required'),
            'title.max'      => __('Title is too long'),
        ]);

        $course = $this->findOwnedCourseOrFail($courseId);

        $chapter = new CourseChapter();
        $chapter->title = $request->title;
        $chapter->course_id = $course->id;
        $chapter->instructor_id = auth('web')->id();
        $chapter->status = 'active';
        $chapter->order = CourseChapter::where('course_id', $course->id)->max('order') + 1;
        $chapter->save();

        return redirect()->back()->with(['messege' => __('Chapter created successfully'), 'alert-type' => 'success']);
    }

    function chapterEdit(string $chapterId) {
        $chapter = $this->findOwnedChapterOrFail($chapterId);
        return view('frontend.instructor-dashboard.course.partials.edit-section-modal', compact('chapter'))->render();
    }

    function chapterUpdate(Request $request, string $chapterId) {
        $request->validate(['title' => 'required|string|max:255']);
        $chapter = $this->findOwnedChapterOrFail($chapterId);
        $chapter->title = $request->title;
        $chapter->save();
        return redirect()->back()->with(['messege' => __('Updated successfully'), 'alert-type' => 'success']);
    }

    function chapterDestroy(string $chapterId) {
        $chapter = $this->findOwnedChapterOrFail($chapterId);
        $chapterItems = CourseChapterItem::where('chapter_id', $chapterId)
            ->where('instructor_id', auth('web')->user()->id)
            ->get();
        $lessonFiles = CourseChapterLesson::whereIn('chapter_item_id', $chapterItems->pluck('id'))->get();
        $quizIds = Quiz::whereIn('chapter_item_id', $chapterItems->pluck('id'))->pluck('id');
        $questionIds = QuizQuestion::whereIn('quiz_id', $quizIds)->pluck('id');

        // delete quizzes, questions, answers and lesson files
        QuizQuestion::whereIn('id', $questionIds)->delete();
        Quiz::whereIn('id', $quizIds)->delete();
        CourseChapterLesson::whereIn('id', $lessonFiles->pluck('id'))->delete();
        foreach ($lessonFiles as $lesson) {
            // asset() returns a URL, not a filesystem path — File::exists/delete
            // were silently no-oping on this branch and leaving orphaned files
            // forever. For local-disk lessons use public_path; for cloud disks
            // delete via the Storage facade. Skip remote URLs / external links.
            $path = $lesson->file_path;
            if (!$path) continue;
            if ($lesson->storage === 'upload') {
                $full = public_path($path);
                if (\File::exists($full)) \File::delete($full);
            } elseif (in_array($lesson->storage, ['wasabi', 'aws', 's3'])) {
                try {
                    \Illuminate\Support\Facades\Storage::disk($lesson->storage)->delete($path);
                } catch (\Throwable $e) { /* swallow — disk might not be configured */ }
            }
        }

        // delete chapter items and chapter
        CourseChapterItem::whereIn('id', $chapterItems->pluck('id'))->delete();
        $chapter->delete();

        return response()->json(['status' => 'success', 'message' => __('Question deleted successfully')]);
    }

    function chapterSorting(string $courseId) {
        // FT-IDOR-35 fix (2026-05-28) — was no ownership check. An
        // attacker could submit any platform courseId and the modal
        // would render the victim coach's full chapter list +
        // ordering — coach IP disclosure.
        $this->findOwnedCourseOrFail($courseId);
        $chapters = CourseChapter::where('course_id', $courseId)->orderBy('order', 'ASC')->get();
        return view('frontend.instructor-dashboard.course.partials.chapter-sorting-index', compact('chapters', 'courseId'))->render();
    }

    function chapterSortingStore(Request $request, string $courseId) {
        $course = $this->findOwnedCourseOrFail($courseId);
        $newOrder = (array) $request->chapter_ids;

        foreach ($newOrder as $key => $value) {
            $chapter = CourseChapter::where('course_id', $course->id)->find($value);
            if (!$chapter) continue;
            $chapter->order = $key + 1;
            $chapter->save();
        }

        return redirect()->back()->with(['messege' => __('Updated successfully'), 'alert-type' => 'success']);
    }

    function lessonCreate(Request $request) {
        $courseId = $request->courseId;
        $chapterId = $request->chapterId;
        // FT-IDOR-35 fix — same disclosure as chapterSorting. Without
        // the ownership check the modal renders the victim coach's
        // chapter list (used to populate the "move to chapter"
        // dropdown). Also abort if chapterId belongs to a foreign
        // chapter, since the modal seeds hidden form fields that the
        // subsequent lessonStore POST trusts.
        $this->findOwnedCourseOrFail($courseId);
        if ($chapterId) {
            $this->findOwnedChapterOrFail($chapterId);
        }
        $chapters = CourseChapter::where('course_id', $courseId)->orderBy('order')->get();
        $type = $request->type;
        if ($request->type == 'lesson') {
            return view('frontend.instructor-dashboard.course.partials.lesson-create-modal', [
                'courseId'  => $courseId,
                'chapterId' => $chapterId,
                'chapters'  => $chapters,
                'type'      => $type,
            ])->render();
        } elseif ($request->type == 'document') {
            return view('frontend.instructor-dashboard.course.partials.document-create-modal', [
                'courseId'  => $courseId,
                'chapterId' => $chapterId,
                'chapters'  => $chapters,
                'type'      => $type,
            ])->render();
        } elseif ($request->type == 'quiz') {
            return view('frontend.instructor-dashboard.course.partials.quiz-create-modal', [
                'courseId'  => $courseId,
                'chapterId' => $chapterId,
                'chapters'  => $chapters,
                'type'      => $type,
            ])->render();
        } elseif ($request->type == 'live') {
            return view('frontend.instructor-dashboard.course.partials.live-create-modal', [
                'courseId'  => $courseId,
                'chapterId' => $chapterId,
                'chapters'  => $chapters,
                'type'      => $type,
            ])->render();
        }
    }

    function lessonStore(ChapterLessonRequest $request) {
        $ownedChapter = $this->findOwnedChapterOrFail($request->chapter_id);

        // FT-IDOR-33 fix (2026-05-28) — course_id/chapter_id mismatch
        // injection. Even though findOwnedChapterOrFail verifies the
        // attacker owns the chapter, $request->course_id is then
        // written verbatim onto the new CourseChapterLesson row
        // (line ~196 and the same pattern in the document/live/quiz
        // branches below). An attacker could submit:
        //   chapter_id = <their own chapter>   ← passes ownership
        //   course_id  = <victim's course_id>  ← unchecked
        //
        // The lesson is then stored with course_id pointing to the
        // victim's course while its chapter_id stays in the attacker's
        // chapter. Downstream side effects:
        //  • LearningController::getFileInfo (the file-info endpoint)
        //    gates on `Enrollment::where(course_id = ?, user_id = me)`,
        //    so victim's students could fetch this lesson's file_path /
        //    signed wasabi URLs by submitting courseId=victim and
        //    lessonId=attacker_lesson (covered by FT-IDOR-18, but only
        //    if the lesson's course_id matches the gate — which this
        //    write makes true).
        //
        // Force $request->course_id to match the verified chapter's
        // course_id so the lesson row is always consistent with its
        // parent chapter.
        $request->merge(['course_id' => (int) $ownedChapter->course_id]);

        $chapterItem = CourseChapterItem::create([
            'instructor_id' => auth('web')->id(),
            'chapter_id'    => $request->chapter_id,
            'type'          => $request->type,
            'order'         => CourseChapterItem::whereChapterId($request->chapter_id)->count() + 1,
        ]);

        if ($request->type == 'lesson') {
            CourseChapterLesson::create([
                'title'           => $request->title,
                'description'     => $request->description,
                'instructor_id'   => auth('web')->id(),
                'course_id'       => $request->course_id,
                'chapter_id'      => $request->chapter_id,
                'chapter_item_id' => $chapterItem->id,
                'file_path'       => $request->source == 'upload' ? $request->upload_path : $request->link_path,
                'storage'         => $request->source,
                'file_type'       => $request->file_type,
                'volume'          => $request->volume,
                'duration'        => $request->duration,
                'is_free'         => $request->is_free,
            ]);
        } elseif ($request->type == 'document') {
            CourseChapterLesson::create([
                'title'           => $request->title,
                'description'     => $request->description,
                'instructor_id'   => auth('web')->id(),
                'course_id'       => $request->course_id,
                'chapter_id'      => $request->chapter_id,
                'chapter_item_id' => $chapterItem->id,
                'file_path'       => $request->upload_path,
                'file_type'       => $request->file_type,
            ]);
        } elseif ($request->type == 'live') {
            $chapter_lesson = CourseChapterLesson::create([
                'title'           => $request->title,
                'description'     => $request->description,
                'instructor_id'   => auth('web')->id(),
                'course_id'       => $request->course_id,
                'chapter_id'      => $request->chapter_id,
                'chapter_item_id' => $chapterItem->id,
                'duration'        => $request->duration,
                'storage'         => 'live',
                'file_type'       => 'live',
            ]);
            $start_time = date('Y-m-d H:i:s', strtotime($request->start_time));
            $join_url = $request?->live_type === 'zoom' ? $request->join_url : null;
            CourseLiveClass::create([
                'course_id'  => $chapter_lesson->course_id,
                'lesson_id'  => $chapter_lesson->id,
                'start_time' => $start_time,
                'meeting_id' => $request->meeting_id,
                'password'   => '',
                'join_url'   => $join_url,
                'type'       => $request->live_type,
            ]);
            if ($request?->student_mail_sent == 'on') {
                $user_ids = Enrollment::where('course_id',$chapter_lesson->course_id)->pluck('user_id')->toArray();
                $users = User::select('name', 'email')->whereIn('id', $user_ids)->get();
                $data = (object)[
                    'course' => Course::select('title')->where('id',$chapter_lesson->course_id)->first()->title,
                    'lesson' => $chapter_lesson->title,
                    'start_time' => formattedDateTime($start_time),
                    'join_url' => $join_url,
                ];
                (new MailSenderService)->sendLiveClassNotificationMailTrait($users,$data);

            }
        } elseif ($request->type == 'quiz') {
            Quiz::create([
                'chapter_item_id' => $chapterItem->id,
                'instructor_id'   => auth('web')->id(),
                'chapter_id'      => $request->chapter,
                'course_id'       => $request->course_id,
                'title'           => $request->title,
                'time'            => $request->time_limit,
                'attempt'         => $request->attempts,
                'pass_mark'       => $request->pass_mark,
                'total_mark'      => $request->total_mark,
            ]);
        }

        return response()->json(['status' => 'success', 'message' => __('Lesson created successfully')]);
    }

    function lessonEdit(Request $request) {
        // Audit 2026-05-18 — mirror the defensive guard from the admin
        // controller (Modules\Course\...\CourseContentController::lessonEdit).
        // Without these, the partial reads ->id on null and 500s.
        $courseId = $request->courseId;
        $chapterItemId = $request->chapterItemId;
        $type = $request->type;
        if (!$courseId || !$chapterItemId || !$type) {
            abort(400, 'lessonEdit requires courseId, chapterItemId, type');
        }

        // FT-IDOR-26 fix (2026-05-28) — pre-fix used
        //     CourseChapterItem::find($chapterItemId)
        // with NO ownership check, then rendered the edit modal
        // with the chapter item's data + sibling chapters of
        // $courseId. A coach could URL-guess any chapter_item_id
        // (sequential IDs across the platform) and view another
        // coach's lesson/quiz/document/live-class titles + content.
        //
        // The companion `findOwnedChapterItemOrFail` helper exists
        // for exactly this case. Use it. Also gate the sibling-chapter
        // listing through findOwnedCourseOrFail so the
        // CourseChapter::where('course_id', $courseId) join doesn't
        // leak another coach's course structure.
        $chapterItem = $this->findOwnedChapterItemOrFail($chapterItemId);
        $chapterItem->load(['lesson', 'quiz']);
        $course = $this->findOwnedCourseOrFail($courseId);
        $chapters = CourseChapter::where('course_id', $course->id)->get();
        if ($request->type == 'lesson') {
            return view('frontend.instructor-dashboard.course.partials.lesson-edit-modal', [
                'chapters'    => $chapters,
                'courseId'    => $courseId,
                'chapterItem' => $chapterItem,
            ])->render();
        } elseif ($request->type == 'document') {
            return view('frontend.instructor-dashboard.course.partials.document-edit-modal', [
                'chapters'    => $chapters,
                'courseId'    => $courseId,
                'chapterItem' => $chapterItem,
            ])->render();
        } elseif ($request->type == 'live') {
            return view('frontend.instructor-dashboard.course.partials.live-edit-modal', [
                'chapters'    => $chapters,
                'courseId'    => $courseId,
                'chapterItem' => $chapterItem,
            ])->render();
        } else {
            return view('frontend.instructor-dashboard.course.partials.quiz-edit-modal', [
                'chapters'    => $chapters,
                'courseId'    => $courseId,
                'chapterItem' => $chapterItem,
            ])->render();
        }
    }

    function lessonUpdate(ChapterLessonRequest $request) {

        $chapterItem = $this->findOwnedChapterItemOrFail($request->chapter_item_id);

        // FT-IDOR-32 fix (2026-05-28) — cross-coach chapter injection.
        //
        // Pre-fix: $request->chapter was validated by ChapterLessonRequest
        // with `exists:course_chapters,id` — ANY chapter on the
        // platform passes, including chapters from other coaches'
        // courses. The next line then moved the attacker's owned
        // chapter_item under the foreign chapter_id. Result: the
        // attacker's lesson row appears inside a victim coach's
        // chapter tree — a content-injection vector (the victim's
        // students would see / can play / can download the
        // attacker's lesson alongside the legitimate lessons in
        // that chapter).
        //
        // The companion findOwnedChapterOrFail helper exists for
        // exactly this kind of cross-relation check. Reject moves
        // to a chapter the current coach doesn't own; legitimate
        // re-parenting within the coach's own course chapters
        // continues to work.
        if ($request->filled('chapter')) {
            $this->findOwnedChapterOrFail($request->chapter);
        }

        $chapterItem->update([
            'chapter_id' => $request->chapter,
        ]);
// pre($request->all());die;
        if ($request->type == 'lesson') {
            $courseChapterLesson = CourseChapterLesson::where('chapter_item_id', $chapterItem->id)->first();
            
            $old_file_path = $courseChapterLesson->file_path;
            if (in_array($courseChapterLesson->storage, ['wasabi', 'aws']) && $old_file_path != $request->link_path) {
                $disk = Storage::disk($courseChapterLesson->storage);
                $disk->exists($old_file_path) && $disk->delete($old_file_path);
            }

            $courseChapterLesson->update([
                'title'           => $request->title,
                'description'     => $request->description,
                'course_id'       => $chapterItem->course_id,
                'chapter_id'      => $chapterItem->chapter_id,
                'chapter_item_id' => $chapterItem->id,
                'file_path'       => $request->source == 'upload' ? $request->upload_path : $request->link_path,
                'storage'         => $request->source,
                'file_type'       => $request->file_type,
                'volume'          => $request->volume,
                'duration'        => $request->duration,
                'is_free'         => $request->is_free,
            ]);
        } elseif ($request->type == 'live') {

            $courseChapterLesson = CourseChapterLesson::where('chapter_item_id', $chapterItem->id)->first();
            $courseChapterLesson->update([
                'title'           => $request->title,
                'description'     => $request->description,
                'chapter_id'      => $chapterItem->chapter_id,
                'chapter_item_id' => $chapterItem->id,
                'duration'        => $request->duration,
                'storage'         => $request->source ? $request->source : 'live',
                'file_type'       => $request->source ? 'video' : 'live',
                'file_path'       => $request->source ? ($request->source == 'upload' ? $request->upload_path : $request->link_path) : null,
                // 'file_path'       => $request->source ? ($request->source == 'upload' ? $request->upload_path : $request->link_path) : $request->link_path??null,
            ]);

            $start_time = date('Y-m-d H:i:s', strtotime($request->start_time));
            $join_url = $request?->live_type === 'zoom' ? $request->join_url : null;
            CourseLiveClass::where('lesson_id', $courseChapterLesson->id)->update([
                'course_id'  => $courseChapterLesson->course_id,
                'start_time' => $start_time,
                'meeting_id' => $request->meeting_id,
                'password'   => '',
                'join_url'   => $join_url,
                'type'       => $request->live_type,
            ]);

            if ($request?->student_mail_sent == 'on') {
                $user_ids = Enrollment::where('course_id',$courseChapterLesson->course_id)->pluck('user_id')->toArray();
                $users = User::select('name', 'email')->whereIn('id', $user_ids)->get();
                $data = (object)[
                    'course' => Course::select('title')->where('id',$courseChapterLesson->course_id)->first()->title,
                    'lesson' => $courseChapterLesson->title,
                    'start_time' => formattedDateTime($start_time),
                    'join_url' => $join_url,
                ];
                (new MailSenderService)->sendLiveClassNotificationMailTrait($users,$data);

            }
        } elseif ($request->type == 'document') {
            $courseChapterLesson = CourseChapterLesson::where('chapter_item_id', $chapterItem->id)->first();
            $courseChapterLesson->update([
                'title'           => $request->title,
                'description'     => $request->description,
                'course_id'       => $chapterItem->course_id,
                'chapter_id'      => $chapterItem->chapter_id,
                'chapter_item_id' => $chapterItem->id,
                'file_path'       => $request->upload_path,
                'file_type'       => $request->file_type,
            ]);
        } else {
            $quiz = Quiz::where('chapter_item_id', $chapterItem->id)->first();
            $quiz->update([
                'chapter_item_id' => $chapterItem->id,
                'title'           => $request->title,
                'time'            => $request->time_limit,
                'attempt'         => $request->attempts,
                'pass_mark'       => $request->pass_mark,
                'total_mark'      => $request->total_mark,
            ]);
        }

        return response()->json(['status' => 'success', 'message' => __('Lesson updated successfully')]);
    }

    function sortLessons(Request $request, string $chapterId) {
        $this->findOwnedChapterOrFail($chapterId);
        $newOrder = (array) $request->orderIds;
        foreach ($newOrder as $key => $itemId) {
            $chapterItem = CourseChapterItem::where(['chapter_id' => $chapterId, 'id' => $itemId])->first();
            if (!$chapterItem) continue;
            $chapterItem->order = $key + 1;
            $chapterItem->save();
        }

        return response()->json(['status' => 'success', 'message' => __('Lesson sorted successfully')]);
    }

    function chapterLessonDestroy(string $chapterItemId) {
        $chapterItem = $this->findOwnedChapterItemOrFail($chapterItemId);

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
                // Local-disk lesson: use public_path, not asset() (asset returns a URL).
                $full = public_path($chapterItem->lesson->file_path);
                if (\File::exists($full)) \File::delete($full);
            }

            // If this lesson is a scheduled (future) live class, tell the batch
            // students it's cancelled BEFORE we delete it (the FK cascade
            // removes the live-class row, so notify first). Batch-scoped + coach
            // branded via the shared service.
            try {
                $liveClass = \App\Models\CourseLiveClass::where('lesson_id', $chapterItem->lesson->id)->first();
                if ($liveClass && $liveClass->start_time
                    && \Carbon\Carbon::parse($liveClass->start_time)->isFuture()) {
                    app(\App\Services\LiveClassNotificationService::class)->notifyCancelled($liveClass);
                }
                // Free the coach's active-meeting slot if this live class held it.
                if ($liveClass) {
                    app(\App\Services\LiveMeetingGuard::class)->releaseByLiveClass((int) $liveClass->id);
                }
            } catch (\Throwable $e) {
                \Log::warning('live-class cancel-on-delete notify failed: ' . $e->getMessage());
            }

            // delete lesson row
            $chapterItem->lesson()->delete();
            $chapterItem->delete();
        }

        return response()->json(['status' => 'success', 'message' => __('Lesson deleted successfully')]);
    }

    function createQuizQuestion(string $quizId) {
        $this->findOwnedQuizOrFail($quizId); // SECURITY (audit 2026-06-12)
        return view('frontend.instructor-dashboard.course.partials.quiz-question-create-modal', ['quizId' => $quizId])->render();
    }

    function storeQuizQuestion(Request $request, string $quizId) {
        $this->findOwnedQuizOrFail($quizId); // SECURITY (audit 2026-06-12)
        $request->validate([
            'title'     => ['required', 'max:255'],
            'answers.*' => ['required', 'max:255'],
            'grade'     => ['required', 'numeric', 'min:0'],
        ], [
            'title.required'     => __('Question title is required'),
            'title.max'          => __('Question title should not be more than 255 characters'),
            'answers.*.required' => __('At least one answer is required'),
            'answers.*.max'      => __('Answer should not be more than 255 characters'),
            'grade.required'     => __('Grade is required'),
            'grade.numeric'      => __('Grade should be a number'),
            'grade.min'          => __('Grade should be greater than or equal to 0'),
        ]);

        $question = QuizQuestion::create([
            'quiz_id' => $quizId,
            'title'   => $request->title,
            'grade'   => $request->grade,
        ]);

        foreach ($request->answers as $key => $answer) {
            $question->answers()->create([
                'title'       => $answer,
                'correct'     => isset($request->correct[$key]) ? 1 : 0,
                'question_id' => $question->id,
            ]);
        }

        return response()->json(['status' => 'success', 'message' => __('Question created successfully')]);

    }

    function editQuizQuestion(string $questionId) {
        $question = $this->findOwnedQuizQuestionOrFail($questionId); // SECURITY (audit 2026-06-12)
        return view('frontend.instructor-dashboard.course.partials.quiz-question-edit-modal', ['question' => $question])->render();
    }

    function updateQuizQuestion(Request $request, string $questionId) {
        $request->validate([
            'title'     => ['required', 'max:255'],
            'answers.*' => ['required', 'max:255'],
            'grade'     => ['required', 'numeric', 'min:0'],
        ], [
            'title.required'     => __('Question title is required'),
            'title.max'          => __('Question title should not be more than 255 characters'),
            'answers.*.required' => __('At least one answer is required'),
            'answers.*.max'      => __('Answer should not be more than 255 characters'),
            'grade.required'     => __('Grade is required'),
            'grade.numeric'      => __('Grade should be a number'),
            'grade.min'          => __('Grade should be greater than or equal to 0'),
        ]);

        $question = $this->findOwnedQuizQuestionOrFail($questionId); // SECURITY (audit 2026-06-12)
        $question->update([
            'title' => $request->title,
            'grade' => $request->grade,
        ]);
        // update or delete answers
        $question->answers()->delete();
        foreach ($request->answers as $key => $answer) {
            $question->answers()->create([
                'title'       => $answer,
                'correct'     => isset($request->correct[$key]) ? 1 : 0,
                'question_id' => $question->id,
            ]);
        }

        return response()->json(['status' => 'success', 'message' => __('Question updated successfully')]);
    }

    function destroyQuizQuestion(string $questionId) {
        $question = $this->findOwnedQuizQuestionOrFail($questionId); // SECURITY (audit 2026-06-12)
        $question->answers()->delete();
        $question->delete();
        return response()->json(['status' => 'success', 'message' => __('Question deleted successfully')]);
    }
}
