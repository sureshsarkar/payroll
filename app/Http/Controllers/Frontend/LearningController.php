<?php

namespace App\Http\Controllers\Frontend;

use Carbon\Carbon;
use App\Models\Quiz;
use App\Models\Course;
use App\Models\QuizResult;
use App\Models\Announcement;
use App\Models\CourseReview;
use App\Models\QuizQuestion;
use Illuminate\Http\Request;
use App\Models\CourseProgress;
use App\Rules\CustomRecaptcha;
use App\Models\CourseChapterItem;
use App\Models\CourseChapterLesson;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use App\Traits\GenerateSecureLinkTrait;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Modules\Order\app\Models\Enrollment;

class LearningController extends Controller {
    use GenerateSecureLinkTrait;
    function index(string $slug) {
        $user = userAuth();
        // 2026-06-10 — TENANT SCOPE: on a coach custom domain, only THIS
        // coach's course may be opened in the player. A course the student is
        // enrolled in under a DIFFERENT coach must not render on this domain
        // (white-label isolation). resolved_coach_id is 0 on the platform.
        $tenantCoachId = (int) request()->attributes->get('resolved_coach_id');
        // 2026-05-26 (bug-doc S4) — eager-load with a type filter that
        // hides live-class chapter items on RECORDED/course-type pages.
        // For a recorded course the student expects only video lessons,
        // not "Join Live Class" rows. Live/hybrid courses unchanged.
        $course = Course::active()->with([
            'chapters',
            'chapters.chapterItems' => function ($q) {
                // Defensive: orderBy keeps the lesson list stable.
                $q->orderBy('order');
            },
            'chapters.chapterItems.lesson',
            'chapters.chapterItems.quiz',
        ])->withTrashed()->where('slug', $slug)
            ->when($tenantCoachId > 0, fn ($q) => $q->where('instructor_id', $tenantCoachId))
            ->whereHas('enrollments', fn($q) => $q->where('user_id', $user->id))->first();

        // 2026-06-29 — Live classes are NOT part of the recorded chapter/lesson
        // list. Students join them ONLY from the dedicated Live Classes section.
        // Drop live-type chapter items from the DISPLAY for recorded AND hybrid
        // courses (the reported bug was hybrid still showing them). PURE 'live'
        // courses are intentionally left UNTOUCHED — their ONLY content is live
        // classes, so filtering them would leave an empty curriculum.
        // Display-only: progress tracking + completion counts are computed
        // separately below and are intentionally left UNTOUCHED.
        if ($course && $course->type !== 'live') {
            foreach ($course->chapters as $chapter) {
                $chapter->setRelation(
                    'chapterItems',
                    $chapter->chapterItems->reject(fn ($item) => ($item->type ?? null) === 'live')->values()
                );
            }
        }
        if(!$course){
            abort(404);
        }
        Session::put('course_slug', $slug);
        Session::put('course_title', $course->title);

        $currentProgress = CourseProgress::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('current', 1)
            ->orderBy('id', 'desc')
            ->first();

        $alreadyWatchedLectures = CourseProgress::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('type', 'lesson')
            ->where('watched', 1)
            ->pluck('lesson_id')
            ->toArray();

        $alreadyCompletedQuiz = CourseProgress::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('type', 'quiz')
            ->where('watched', 1)
            ->pluck('lesson_id')
            ->toArray();

        // Audit 2026-05-18 — scope announcements to the student's enrolled
        // batch + active status. Legacy course-wide rows (batch_id NULL)
        // remain visible to all enrolled students.
        $studentBatchId = (int) (\Modules\Order\app\Models\Enrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('has_access', 1)
            ->value('batch_id') ?? 0);
        $studentBatchId = $studentBatchId > 0 ? $studentBatchId : null;
        $announcements = Announcement::visibleToBatchStudent($course->id, $studentBatchId)
            ->orderByDesc('is_pinned')           // Audit 2026-05-18 phase 3
            ->orderByDesc('sent_at')
            ->orderBy('id', 'desc')
            ->get();

        $courseLectureCount = CourseChapterItem::whereHas('chapter', function ($q) use ($course) {
            $q->where('course_id', $course->id);
        })->count();

        $courseLectureCompletedByUser = CourseProgress::where('user_id', $user->id)
            ->where('course_id', $course->id)->where('watched', 1)->count();
        $courseCompletedPercent = $courseLectureCount > 0 ? ($courseLectureCompletedByUser / $courseLectureCount) * 100 : 0;

        if (!$currentProgress) {
            // Open the first RECORDED lesson (live items are filtered out of the
            // list above), so the player never auto-opens a live class.
            $firstChapter = null; $lessonId = null;
            foreach ($course->chapters as $chapter) {
                $item = $chapter->chapterItems->first(fn ($it) => optional($it->lesson)->id);
                if ($item) { $firstChapter = $chapter; $lessonId = $item->lesson->id; break; }
            }
            if ($lessonId) {
                $currentProgress = CourseProgress::create([
                    'user_id'    => $user->id,
                    'course_id'  => $course->id,
                    'chapter_id' => $firstChapter->id,
                    'lesson_id'  => $lessonId,
                    'current'    => 1,
                ]);
            }
        }
        return view('frontend.pages.learning-player.index', compact(
            'course',
            'currentProgress',
            'announcements',
            'courseCompletedPercent',
            'courseLectureCount',
            'courseLectureCompletedByUser',
            'alreadyWatchedLectures',
            'alreadyCompletedQuiz'
        ));
    }

    function getFileInfo(Request $request) {
        // FT-VAL-2 fix (2026-05-28) — tighten inputs. Without these
        // rules, lessonId/courseId/chapterId could be arrays or
        // overflow ints; type could be a Blade-string that breaks
        // downstream view rendering. Status whitelist on type also
        // documents the elseif chain below explicitly.
        $request->validate([
            'courseId'  => ['required', 'integer'],
            'chapterId' => ['nullable', 'integer'],
            'lessonId'  => ['required', 'integer'],
            'type'      => ['required', 'string', 'in:lesson,live,document,quiz'],
        ]);

        // Enrollment gate — without this, a student can guess any lessonId
        // and get the file info even for courses they never enrolled in.
        Enrollment::where('user_id', userAuth()->id)
            ->where('course_id', $request->courseId)
            ->where('has_access', 1)
            ->firstOrFail();

        // FT-IDOR-18 fix (2026-05-28) — cross-course lesson IDOR.
        //
        // The enrollment gate above verifies the user is enrolled in
        // $request->courseId. The lesson lookups further down then
        // call CourseChapterLesson::findOrFail($request->lessonId)
        // without verifying that lessonId actually belongs to
        // courseId. A student enrolled in Course A could:
        //   1. submit courseId = A (passes enrollment check),
        //   2. submit lessonId = X where X belongs to Course B
        //      (a paid course they never enrolled in),
        // and the JSON response would hand back file_path / file_type
        // / downloadable / signed temporaryUrl for Course B's
        // protected content. For wasabi/aws-backed videos this
        // includes a working signed URL valid for 30 seconds.
        //
        // Pre-verify the lesson belongs to the gated course before
        // any of the type branches run. Same check for quiz: a Quiz
        // belongs to a CourseChapterLesson which belongs to a Course;
        // the simplest invariant is "this lessonId is in this course".
        if ($request->type === 'quiz') {
            // Quizzes carry their own course_id (see 2024_04_24_093046
            // create_quizzes_table migration), so the check is direct.
            $quizOk = Quiz::where('id', $request->lessonId)
                ->where('course_id', $request->courseId)
                ->exists();
            if (! $quizOk) {
                abort(404);
            }
        } else {
            $lessonOk = CourseChapterLesson::where('id', $request->lessonId)
                ->where('course_id', $request->courseId)
                ->exists();
            if (! $lessonOk) {
                abort(404);
            }
        }

        // set progress status — scope to current user only.
        CourseProgress::where('course_id', $request->courseId)
            ->where('user_id', userAuth()->id)
            ->update(['current' => 0]);
        $progress = CourseProgress::updateOrCreate(
            [
                'user_id'    => userAuth()->id,
                'course_id'  => $request->courseId,
                'chapter_id' => $request->chapterId,
                'lesson_id'  => $request->lessonId,
                'type'       => $request->type,
            ],
            [
                'current' => 1,
            ]
        );

        if ($request->type == 'lesson') {
            $fileInfo = array_merge(CourseChapterLesson::select(['id', 'file_path', 'storage', 'file_type', 'downloadable', 'description'])->findOrFail($request->lessonId)->toArray(), ['type' => 'lesson']);
            if (in_array($fileInfo['storage'], ['wasabi', 'aws'])) {
                $fileInfo['file_path'] = Storage::disk($fileInfo['storage'])->temporaryUrl($fileInfo['file_path'], now()->addSeconds(30));
            }
            if($fileInfo['storage'] == 'upload'){
                $fileInfo['file_path'] = $this->generateSecureLink($fileInfo['file_path']);
            }
            return response()->json([
                'file_info' => $fileInfo,
            ]);
        } elseif ($request->type == 'live') {
            // The frontend only checks that the credential row EXISTS to decide
            // whether to render the "Open meeting" button. Do NOT pull secret
            // columns (client_secret, api_key) here — earlier this method ran
            // ->toArray()->json(), which leaked them to every enrolled student.
            // The cast=encrypted on ZoomCredential decrypts on read, so even
            // shipping the ciphertext via the JSON envelope was a leak.
            $fileInfo = array_merge(
                CourseChapterLesson::with([
                    'course:id,instructor_id,slug',
                    'course.instructor:id',
                    'course.instructor.zoom_credential:id,instructor_id',
                    'live:id,lesson_id,start_time,type,meeting_id,join_url,batch_id',
                ])->select([
                    'id', 'course_id', 'chapter_item_id', 'title', 'description',
                    'duration', 'file_path', 'storage', 'file_type', 'downloadable',
                ])->findOrFail($request->lessonId)->toArray(),
                ['type' => 'live']
            );

            // F4 (audit 2026-06-26) — BATCH SCOPING. The enrollment gate above only
            // proves course access; without this a Batch-A student could pass a
            // lessonId whose live class is scoped to Batch-B (same course) and pull
            // Batch-B's join_url / meeting_id. Mirror recipients(): a batch-scoped
            // class is reachable only by students of that batch (course-wide / null
            // enrollments stay allowed for backward compatibility).
            $liveBatchId = $fileInfo['live']['batch_id'] ?? null;
            if ($liveBatchId !== null) {
                $studentBatchId = Enrollment::where('user_id', userAuth()->id)
                    ->where('course_id', $request->courseId)
                    ->where('has_access', 1)
                    ->value('batch_id');
                if ($studentBatchId !== null && (int) $studentBatchId !== (int) $liveBatchId) {
                    abort(403);
                }
            }

            $now = Carbon::now();
            $startTime = Carbon::parse($fileInfo['live']['start_time']);
            $endTime = $startTime->clone()->addMinutes($fileInfo['duration']);
            $fileInfo['start_time'] = formattedDateTime($startTime);
            $fileInfo['end_time'] = formattedDateTime($endTime);
            $fileInfo['is_live_now'] = $now->between($startTime, $endTime);

            if ($now->lt($startTime)) {
                $fileInfo['is_live_now'] = 'not_started';
            } elseif ($now->between($startTime, $endTime)) {
                $fileInfo['is_live_now'] = 'started';
            } else {
                $fileInfo['is_live_now'] = 'ended';
            }

            return response()->json([
                'file_info' => $fileInfo,
            ]);
        } elseif ($request->type == 'document') {
            $fileInfo = array_merge(CourseChapterLesson::select(['id', 'file_path', 'storage', 'file_type', 'downloadable', 'description'])->findOrFail($request->lessonId)->toArray(), ['type' => 'document']);
            if ('pdf' == $fileInfo['file_type']) {
                return response()->json([
                    'view'      => view('frontend.pages.learning-player.partials.pdf-viewer', ['file_path' => $fileInfo['file_path']])->render(),
                    'file_info' => $fileInfo,
                ]);
            } elseif ('docx' == $fileInfo['file_type']) {
                return response()->json([
                    'view'      => view('frontend.pages.learning-player.partials.docx-viewer', ['file_path' => $fileInfo['file_path']])->render(),
                    'file_info' => $fileInfo,
                ]);
            } else {
                return response()->json([
                    'file_info' => $fileInfo,
                ]);
            }
        } else {
            $fileInfo = array_merge(Quiz::findOrFail($request->lessonId)->toArray(), ['type' => 'quiz']);

            return response()->json([
                'file_info' => $fileInfo,
            ]);
        }
    }

    function makeLessonComplete(Request $request) {
        $progress = CourseProgress::where(['lesson_id' => $request->lessonId, 'user_id' => userAuth()->id, 'type' => $request->type])->first();
        if ($progress) {
            $wasWatched = (int) $progress->watched === 1;
            $progress->watched = $request->status;
            $progress->save();

            // Course completion check — only when this update flips a lesson INTO watched.
            if (!$wasWatched && (int) $request->status === 1 && $progress->course_id) {
                try {
                    $courseId = $progress->course_id;
                    $totalItems = \App\Models\CourseChapterItem::whereHas('chapter', fn($q) => $q->where('course_id', $courseId))->count();
                    $watchedItems = CourseProgress::where('user_id', userAuth()->id)
                        ->where('course_id', $courseId)
                        ->where('watched', 1)
                        ->count();

                    if ($totalItems > 0 && $watchedItems >= $totalItems) {
                        // Avoid duplicate completion notifications — guard with a session-keyed cache flag.
                        $flag = 'course_completed_notified_' . userAuth()->id . '_' . $courseId;
                        if (!\Cache::has($flag)) {
                            \Cache::put($flag, 1, now()->addDays(30));
                            $course = \App\Models\Course::find($courseId);
                            if ($course) {
                                userAuth()->notify(new \App\Notifications\CourseCompletedToStudent($course));
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    \Log::warning('Course completion notification failed: ' . $e->getMessage());
                }
            }

            return response()->json(['status' => 'success', 'message' => __('Updated successfully.')]);
        } else {
            if ($request->status == 0) {
                return;
            }

            return response()->json(['status' => 'error', 'message' => __('You didnt watched this lesson')]);
        }
    }

    /**
     * Student-facing per-course attendance view (2026-05-11).
     *
     * Shows the calling student THEIR OWN attendance across all live
     * classes in the named course, plus whether they're currently above
     * or below the course's attendance_threshold_percent.
     *
     * Authorisation: the student must be enrolled in the course with
     * has_access=1. Same gate shape as the existing index() — we keep
     * it inline rather than centralising because the existing
     * LearningController has several enrollment checks in different
     * shapes already, and consistency with neighbours beats DRY here.
     *
     * Does NOT use the instructor-side computeAttendanceWatchlist():
     * that's a per-class × per-enrolled-student aggregation. Here we
     * need per-class detail (date, lesson title, my duration, did-I-
     * attend flag) for ONE user — different shape entirely.
     */
    function myAttendance(string $slug) {
        $user = userAuth();
        // 2026-06-10 — TENANT SCOPE: only this coach's course on a coach domain.
        $tenantCoachId = (int) request()->attributes->get('resolved_coach_id');
        $course = Course::active()->withTrashed()
            ->where('slug', $slug)
            ->when($tenantCoachId > 0, fn ($q) => $q->where('instructor_id', $tenantCoachId))
            ->whereHas('enrollments', fn ($q) => $q->where('user_id', $user->id)->where('has_access', 1))
            ->firstOrFail();

        // Every live class for this course, ordered chronologically so the
        // student reads it like a timeline. `with('lesson:id,title')` so
        // we can show what each class was about. #9: recordings eager-
        // loaded so a missed class surfaces a "Watch recording" link.
        // 2026-06-02 — batch scoping. A student in batch A must NOT see batch
        // B's live classes of the same course (their recordings AND the
        // attendance % they feed). Mirror the NULL-tolerant pair-wise rule
        // from StudentLiveClassController::index:
        //   - legacy enrollment (batch_id NULL) → sees every class (back-compat)
        //   - otherwise → course-wide classes (batch_id NULL) + own batch(es)
        $myBatchIds = \Modules\Order\app\Models\Enrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('has_access', 1)
            ->pluck('batch_id');
        $seesAllBatches = $myBatchIds->contains(null);
        $batchIds = $myBatchIds->filter()->unique()->values()->all();

        $classes = \App\Models\CourseLiveClass::where('course_id', $course->id)
            ->when(! $seesAllBatches, function ($q) use ($batchIds) {
                $q->where(function ($w) use ($batchIds) {
                    $w->whereNull('batch_id'); // course-wide class (all batches)
                    if (! empty($batchIds)) {
                        $w->orWhereIn('batch_id', $batchIds);
                    }
                });
            })
            ->with([
                'lesson:id,title',
                'recordings:id,course_live_class_id,play_url,duration_seconds',
            ])
            ->orderBy('start_time')
            ->get(['id', 'lesson_id', 'start_time', 'meeting_id', 'batch_id']);

        // Pull this user's attendance for those classes in ONE query
        // (avoid N+1) and group by class id for fast per-row lookup. We
        // pull ALL their rows including short ones — short rows are
        // shown in the table as "tried to join (Xs)" but don't count
        // toward the >= 60s "attended" threshold.
        $attendance = \App\Models\LiveClassAttendance::where('user_id', $user->id)
            ->whereIn('course_live_class_id', $classes->pluck('id'))
            ->get(['course_live_class_id', 'joined_at', 'left_at', 'duration_seconds'])
            ->groupBy('course_live_class_id');

        $threshold    = (int) ($course->attendance_threshold_percent ?? 75);
        $totalClasses = $classes->count();
        $attendedCount = 0;

        $rows = $classes->map(function ($class) use ($attendance, &$attendedCount) {
            $myRows  = $attendance->get($class->id, collect());
            $total   = (int) $myRows->sum('duration_seconds');
            // "Attended" same definition as the instructor watchlist:
            // total duration on this class ≥ 60s. Filters out flaky
            // reconnects that the launcher logs as join events.
            $attended = $total >= 60;
            if ($attended) {
                $attendedCount++;
            }
            // #9: pick the first playable recording for this class (if any).
            // Recordings are sorted by id ASC in the eager-load, so the
            // first one is typically the canonical full-meeting record.
            $rec = $class->recordings->first(fn ($r) => filled($r->play_url));
            return (object) [
                'class_id'        => (int) $class->id,
                'lesson_title'    => (string) ($class->lesson?->title ?? __('Live class')),
                'start_time'      => $class->start_time,
                'sessions'        => $myRows->count(),
                'duration_seconds' => $total,
                'attended'        => $attended,
                'recording_url'   => $rec?->play_url ?? null,
            ];
        });

        $percent = $totalClasses > 0 ? round(($attendedCount / $totalClasses) * 100, 1) : 0.0;
        $atRisk  = $threshold > 0 && $totalClasses > 0 && $percent < $threshold;

        return view('frontend.student-dashboard.learning.my-attendance', [
            'course'         => $course,
            'rows'           => $rows,
            'threshold'      => $threshold,
            'totalClasses'   => $totalClasses,
            'attendedCount'  => $attendedCount,
            'percent'        => $percent,
            'atRisk'         => $atRisk,
        ]);
    }

    function downloadResource(string $lessonId) {
        $resource = CourseChapterLesson::findOrFail($lessonId);

        // Enrollment gate. Without this any authenticated user could
        // download any course's lesson resource by guessing the lesson_id
        // — the route is just behind auth+verified, with no per-course
        // scope of its own.
        Enrollment::where('user_id', userAuth()->id)
            ->where('course_id', $resource->course_id)
            ->where('has_access', 1)
            ->firstOrFail();

        $rel = (string) $resource->file_path;
        if ($rel === '' || !\File::exists(public_path($rel))) {
            return redirect()->back()->with(['alert-type' => 'error', 'messege' => __('Links is broke or some thing went wrong')]);
        }

        // Path-traversal guard. The instructor-uploaded file_path is the
        // canonical source, but a malformed row (legacy uploads written
        // before the helper-side allowlist landed) could contain `..` and
        // resolve outside the uploads tree. Refuse anything that doesn't
        // sit under public/uploads/.
        $real    = realpath(public_path($rel));
        $allowed = realpath(public_path('uploads'));
        if (!$real || !$allowed || !str_starts_with($real, $allowed)) {
            \Log::warning('downloadResource: path-traversal blocked', [
                'lesson_id' => $resource->id, 'file_path' => $rel,
            ]);
            abort(403);
        }

        return response()->download($real);
    }

    function quizIndex(string $id) {
        // FT-IDOR-19 fix (2026-05-28) — was no enrollment gate.
        // Any authenticated user could /student/quiz/{id} for any
        // quiz on the platform — including quizzes from courses they
        // never paid for — and start an attempt. The attempt-count
        // check below is per-user-per-quiz and doesn't gate access.
        // Add the same enrollment check getFileInfo uses.
        $quiz = Quiz::withCount('questions')->findOrFail($id);
        Enrollment::where('user_id', userAuth()->id)
            ->where('course_id', $quiz->course_id)
            ->where('has_access', 1)
            ->firstOrFail();
        $attempt = QuizResult::where('user_id', userAuth()->id)->where('quiz_id', $id)->count();
        if ($attempt >= $quiz->attempt) {
            return redirect()->route('student.learning.index', Session::get('course_slug'))->with(['alert-type' => 'error', 'messege' => __('You reached maximum attempt')]);
        }

        // Record start time per (user, quiz) so we can enforce the time limit
        // server-side at submission. Persisting in session is enough — the
        // value is read once on submit and otherwise idle.
        Session::put("quiz_started_at:{$id}", now()->timestamp);

        return view('frontend.pages.learning-player.quiz-index', compact('quiz', 'attempt'));
    }

    function quizStore(Request $request, string $id) {
        $quiz = Quiz::findOrFail($id);

        // FT-IDOR-19 fix (2026-05-28) — was no enrollment gate.
        // Same threat model as quizIndex; required here too because
        // the submit endpoint can be hit directly (e.g. via curl)
        // without ever calling quizIndex first.
        Enrollment::where('user_id', userAuth()->id)
            ->where('course_id', $quiz->course_id)
            ->where('has_access', 1)
            ->firstOrFail();

        // Server-side time-limit guard. Quiz->time is in minutes. We allow a
        // 30-second grace period for last-second network jitter. If the user
        // never hit /quizIndex (no started_at in session), reject — they
        // must go through the start page.
        $startedAt = (int) Session::get("quiz_started_at:{$id}", 0);
        if ($quiz->time > 0) {
            if (!$startedAt) {
                return redirect()->route('student.quiz.index', $id)->with([
                    'alert-type' => 'error',
                    'messege'    => __('Please start the quiz from the beginning'),
                ]);
            }
            $elapsedSeconds = now()->timestamp - $startedAt;
            $allowedSeconds = ((int) $quiz->time * 60) + 30;
            if ($elapsedSeconds > $allowedSeconds) {
                Session::forget("quiz_started_at:{$id}");
                return redirect()->route('student.quiz.index', $id)->with([
                    'alert-type' => 'error',
                    'messege'    => __('Time is up — your submission was not accepted. Please retry.'),
                ]);
            }
        }

        // FT-IDOR-20 fix (2026-05-28) — cross-quiz question
        // contamination. The pre-fix scoring loop did:
        //     foreach ($request->question as $qid => $ansId) {
        //         $question = QuizQuestion::findOrFail($qid);
        //         ...grade against $question's answers...
        //     }
        // Both $qid and $ansId are attacker-controlled. An attacker
        // could swap in question IDs from an EASIER quiz (or a quiz
        // with fewer / always-correct answers) and have the platform
        // grade them against THOSE questions while still recording
        // the result against THIS quiz_id. Worst case: an attacker
        // farms perfect scores on a hard certification quiz by
        // submitting question IDs they cherry-picked from training
        // quizzes they passed.
        //
        // Fix: pre-load the list of question IDs that actually
        // belong to $quiz, and skip any submitted key that isn't
        // in that list. The grader still iterates only over real
        // question IDs of this quiz.
        $allowedQuestionIds = QuizQuestion::where('quiz_id', $quiz->id)
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $grad = 0;
        $result = [];
        foreach ($request->question ?? [] as $key => $questionAns) {
            if (! in_array((int) $key, $allowedQuestionIds, true)) {
                // Foreign question — ignore silently. Don't 404 the
                // whole submit; legitimate browsers don't send these
                // and a partial-submission UX is better than a hard fail.
                continue;
            }
            $question = QuizQuestion::findOrFail($key);
            $answer = $question->answers->where('correct', 1)->pluck('id')->toArray();

            if (in_array($questionAns, $answer)) {
                $grad += $question->grade;
            }
            $result[$key] = [
                "answer"  => $questionAns,
                "correct" => in_array($questionAns, $answer),
            ];
        }

        $quizResult = QuizResult::create([
            'user_id'    => userAuth()->id,
            'quiz_id'    => $id,
            'result'     => json_encode($result),
            'user_grade' => $grad,
            'status'     => $grad >= $quiz->pass_mark ? 'pass' : 'failed',
        ]);

        try {
            userAuth()->notify(new \App\Notifications\QuizResultToStudent($quizResult, $quiz));
        } catch (\Throwable $e) {
            \Log::warning('Notify student of quiz result failed: ' . $e->getMessage());
        }

        return redirect()->route('student.quiz.result', ['id' => $id, 'result_id' => $quizResult->id]);
    }

    function quizResult(string $id, string $resultId) {
        $attempt = QuizResult::where('user_id', userAuth()->id)->where('quiz_id', $id)->count();
        $quiz = Quiz::withCount('questions')->findOrFail($id);
        $quizResult = QuizResult::where('id', $resultId)
            ->where('user_id', userAuth()->id)
            ->where('quiz_id', $id)
            ->firstOrFail();

        return view('frontend.pages.learning-player.quiz-result', compact('quiz', 'attempt', 'quizResult'));
    }

    function addReview(Request $request) {
        $request->validate([
            'course_id'            => ['required', 'exists:courses,id'],
            'rating'               => ['required', 'integer', 'min:1', 'max:5'],
            'review'               => ['required', 'max: 1000', 'string'],
            'g-recaptcha-response' => Cache::get('setting')->recaptcha_status === 'active' ? ['required', new CustomRecaptcha()] : 'nullable',
        ], [
            'rating.required'               => __('rating filed is required'),
            'rating.integer'                => __('rating have to be an integer'),
            'review.required'               => __('review filed is required'),
            'g-recaptcha-response.required' => __('Please complete the recaptcha to submit the form'),
        ]);

        // FT-IDOR-21 fix (2026-05-28) — was no enrollment check.
        // Any authenticated user could POST a CourseReview to /add-review
        // for any course on the platform, including courses they never
        // paid for or even visited. Effects:
        //   • Pollutes coach dashboards with reviews from non-students
        //     (skewing average rating + social proof on the coach's
        //     course page).
        //   • Notifies the coach (NewCourseReviewToCoach mail/db
        //     notification) about a "student" who was never enrolled.
        //   • Bypasses the existing duplicate-check logic for organic
        //     review-bombing attacks: an attacker uses N fresh accounts
        //     to post 1-star reviews on a competitor's course.
        //
        // The duplicate-check at line below stops the SAME user from
        // double-reviewing, but it doesn't gate first-time access.
        // Add an enrollment gate so only students with has_access=1
        // on this course can review it — same primitive the rest of
        // this controller (quizStore, getFileInfo) already uses.
        Enrollment::where('user_id', userAuth()->id)
            ->where('course_id', $request->course_id)
            ->where('has_access', 1)
            ->firstOrFail();

        $review = CourseReview::where(['course_id' => $request->course_id, 'user_id' => userAuth()->id])->first();
        if ($review) {
            return redirect()->back()->with(['alert-type' => 'error', 'messege' => __('Already added review')]);
        }

        $review = CourseReview::create([
            'course_id' => $request->course_id,
            'user_id'   => userAuth()->id,
            'rating'    => $request->rating,
            'review'    => $request->review,
        ]);

        // Notify the course's coach of the new review.
        try {
            $course = Course::find($request->course_id);
            if ($course && $course->instructor) {
                $review->load(['user:id,name', 'course:id,title']);
                $course->instructor->notify(new \App\Notifications\NewCourseReviewToCoach($review));
            }
        } catch (\Throwable $e) {
            \Log::warning('Notify coach of new review failed: ' . $e->getMessage());
        }

        return redirect()->back()->with(['alert-type' => 'success', 'messege' => __('Review added successfully')]);

    }

    function fetchReviews(Request $request, string $courseId) {
        $reviews = CourseReview::where(['course_id' => $courseId, 'status' => 1])->whereHas('course')->whereHas('user')->orderBy('id', 'desc')->paginate(8, ['*'], 'page', $request->page ?? 1);
        return response()->json([
            'view'       => view('frontend.pages.learning-player.partials.review-card', compact('reviews'))->render(),
            'page'       => $request->page,
            'last_page'  => $reviews->lastPage(),
            'data_count' => $reviews->count(),
        ]);
    }
 

    function liveSession(Request $request, string $slug, string $lesson_id) {
        // NOTE: do NOT eager-load `client_secret` here — earlier this method pulled
        // the secret onto the lesson object so the view could render it into JS.
        // The new flow fetches the SDK signature from /zoom/sdk-signature/{id}
        // server-side, so the secret never leaves the backend.
        $lesson = CourseChapterLesson::select('id', 'course_id', 'chapter_item_id', 'title')->with(['course' => function ($q) {
            $q->select('id', 'instructor_id', 'slug', 'title');
        }, 'chapterItem' => function ($q) {
            $q->select('id', 'type');
        }, 'live' => function ($q) {
            // batch_id (2026-06-01 audit [8]) — needed for the per-batch
            // student gate below so the launcher page agrees with the
            // ZoomSignatureController gate that actually issues the JWT.
            $q->select('id', 'lesson_id', 'start_time', 'type', 'meeting_id', 'join_url', 'batch_id');
        }])->findOrFail($lesson_id);

        // 2026-06-10 — TENANT SCOPE: on a coach custom domain, a live session
        // of a DIFFERENT coach's course must not open here (white-label
        // isolation). resolved_coach_id is 0 on the platform → no effect.
        $tenantCoachId = (int) request()->attributes->get('resolved_coach_id');
        abort_if($tenantCoachId > 0 && (int) ($lesson->course?->instructor_id ?? 0) !== $tenantCoachId, 404);

        // Enrollment gate (P0-4). Without this any logged-in user could
        // guess a lesson_id and join any meeting.
        // 2026-06-06 — multi-batch: a student may be in several batches of the
        // course, so load the SET of their enrolled batches.
        $enrollments = Enrollment::where('user_id', userAuth()->id)
            ->where('course_id', $lesson->course_id)
            ->where('has_access', 1)
            ->get(['id', 'batch_id']);
        abort_if($enrollments->isEmpty(), 404);

        // 2026-05-26 (bug-doc S5) — Defensive: if the live row is missing
        // (instructor deleted it or never finalised), property access
        // below would throw on null. Redirect to the course learning page
        // with a friendly message instead of dumping a 500.
        if (! $lesson->live) {
            return redirect()->route('student.learning.index', ['slug' => $slug])->with([
                'messege'    => __('This live class is not available yet. Please check back when your instructor schedules it.'),
                'alert-type' => 'error',
            ]);
        }

        // 2026-06-01 (audit [8]) — per-batch gate, mirroring the authoritative
        // ZoomSignatureController gate (which issues the actual meeting JWT).
        // If this live class is batched and the student's enrollment is in a
        // DIFFERENT (non-null) batch, they may not join — show a friendly
        // notice instead of loading a launcher that the signature endpoint
        // will 403. NULL-batch (legacy/course-wide) enrollments are allowed.
        $liveBatchId = $lesson->live->batch_id ? (int) $lesson->live->batch_id : null;
        if ($liveBatchId !== null) {
            $enrBatchIds = $enrollments->pluck('batch_id')->map(fn ($b) => $b !== null ? (int) $b : null);
            if (! $enrBatchIds->contains(null) && ! $enrBatchIds->contains($liveBatchId)) {
                return redirect()->route('student.learning.index', ['slug' => $slug])->with([
                    'messege'    => __('This live class is for a different batch than the one you are enrolled in.'),
                    'alert-type' => 'error',
                ]);
            }
        }

        if ($lesson->live->type == 'zoom') {
            // Render the embedded Zoom Meeting SDK launcher. The launcher
            // POSTs to /zoom/sdk-signature/{id} which (a) re-checks
            // enrollment, (b) generates a server-signed JWT using the
            // instructor's SDK secret, and (c) returns the meeting
            // password pre-filled — so the student never sees Zoom's
            // own passcode/name prompt.
            return view('frontend.student-dashboard.live.zoom', compact('lesson'));
        }

        // Legacy non-zoom rows (Jitsi removed 2026-05-07). Show a clear
        // notice rather than 500. The instructor needs to recreate the
        // class — only Zoom is supported now.
        \Log::info('liveSession: legacy non-zoom live class', [
            'lesson_id' => $lesson->id,
            'type'      => $lesson->live->type ?? null,
        ]);
        abort(410, 'This live class uses a discontinued provider. Please ask the instructor to recreate it — only Zoom is supported now.');
    }

}