<?php

namespace App\Http\Controllers\Frontend\Coach;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\CourseChapter;
use App\Models\CourseChapterItem;
use App\Models\CourseChapterLesson;
use App\Models\CourseLiveClass;
use App\Models\TeacherBatchAssignment;
use App\Models\User;
use App\Services\MailSenderService;
use App\Services\ZoomApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;
use Modules\Order\app\Models\Enrollment;

class LiveClassController extends Controller
{
    protected $pageName;

    public function __construct(CourseLiveClass $model)
    {
        $this->model = $model;
        $this->admin_base_url = 'instructor.live-classes.index';
        $this->admin_view = 'frontend.instructor-dashboard.live-classes';
        $this->admin_error_view = 'errors.403';
        $this->pageName = 'live-classes';
    }

    public function index(): View
    {
        // FT-IDOR-11 fix (2026-05-28) — was `$flag = 1;` hard-coded
        // bypass with the real checkPermission() commented out.
        // Same dev-time-bypass pattern as FT-IDOR-10 on
        // LandingPageEnquiryController. Threat model is identical:
        // within-tenant least-privilege leak. Cross-coach isolation
        // is still enforced by the lesson->instructor_id scope below;
        // the bypass only let staff-without-`live-classes`-permission
        // see their own coach's live-class roster.
        $flag = checkPermission($this->pageName);
        if ($flag == 1) {
            $metadta['title'] = 'Live Classes';
            $coachId = (userAuth()->role != 'instructor') ? userAuth()->coach_id : userAuth()->id;

            // 2026-05-20 — per-teacher batch scope. For coach (returns
            // null) nothing is filtered; for staff with N assigned
            // batches, the list is constrained to those rows only.
            $assignedBatchIds = $this->assignedBatchIds();

            // 2026-07-09 (Some Changes.docx) — listing filters + search.
            // Server-side so it spans every page, not just the 10 on screen.
            $search = trim((string) request('q', ''));
            $status = in_array(request('status'), ['scheduled', 'live', 'completed'], true) ? request('status') : null;
            $date   = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) request('date')) ? request('date') : null;
            // "over" = past the scheduled slot (+5 min grace), mirroring the badge logic in the view.
            $overExpr = 'DATE_ADD(start_time, INTERVAL (COALESCE(expected_duration_minutes,60)+5) MINUTE)';

            $liveClasses = $this->model::with([
                'lesson:id,title,course_id,instructor_id',
                'lesson.course:id,title',
            ])
                ->withCount(['attendances as currently_in_count' => function ($q) {
                    $q->whereNull('left_at')
                      ->select(\DB::raw('count(distinct user_id)'));
                }])
                ->whereHas('lesson', function ($query) use ($coachId) {
                    $query->where('instructor_id', $coachId);
                })
                ->when($assignedBatchIds !== null, function ($q) use ($assignedBatchIds) {
                    // Staff with empty assignment list → no rows; with
                    // explicit IDs → match only those. NULL batch_id
                    // (legacy / course-wide class) is excluded for staff.
                    $q->whereIn('batch_id', $assignedBatchIds ?: [0]);
                })
                // Search by Course name OR Live Class (lesson) title.
                ->when($search !== '', function ($q) use ($search) {
                    $q->whereHas('lesson', function ($l) use ($search) {
                        $l->where('title', 'like', "%{$search}%")
                          ->orWhereHas('course', fn ($c) => $c->where('title', 'like', "%{$search}%"));
                    });
                })
                // Date (scheduled day).
                ->when($date !== null, fn ($q) => $q->whereDate('start_time', $date))
                // Status (derived from time, matching the row badges). Compare
                // against a bound PHP now() (app timezone) — NOT MySQL NOW(),
                // whose value follows the DB server timezone and would drift.
                ->when($status !== null, function ($q) use ($status, $overExpr) {
                    $now = now();
                    if ($status === 'completed') {
                        $q->where(fn ($w) => $w->whereNotNull('ended_at')->orWhereRaw("{$overExpr} <= ?", [$now]));
                    } elseif ($status === 'scheduled') {
                        $q->whereNull('ended_at')->where('start_time', '>', $now);
                    } elseif ($status === 'live') {
                        $q->whereNull('ended_at')->where('start_time', '<=', $now)->whereRaw("{$overExpr} > ?", [$now]);
                    }
                })
                ->orderBy('start_time', 'desc')->paginate(10)->withQueryString();

            // Course dropdown — the coach sees all their live+hybrid
            // courses. A teacher only sees courses they have at least
            // one active batch assignment for. Without this, a staff
            // member could pick a course they have no batches in and
            // get an empty batch dropdown.
            $coursesQuery = Course::where(function ($query) use ($coachId) {
                $query->where('added_by', $coachId)
                    ->orWhere('instructor_id', $coachId);
            })
                ->where('coach_soft_delete', 0)
                ->whereIn('type', ['live', 'hybrid']); // recorded courses have no live classes

            if ($assignedBatchIds !== null) {
                // 2026-07-10 (Staff Panel) — a staff/teacher's live-class course
                // dropdown was empty because it only showed courses with an ASSIGNED
                // batch. Include the courses they AUTHORED (added_by = them) too, so
                // a staff member can schedule live classes for their own courses.
                $allowedCourseIds = CourseBatch::whereIn('id', $assignedBatchIds ?: [0])
                    ->pluck('course_id')->unique()->map(fn ($v) => (int) $v)->all();
                $authoredCourseIds = Course::where('added_by', (int) userAuth()->id)
                    ->whereIn('type', ['live', 'hybrid'])
                    ->pluck('id')->map(fn ($v) => (int) $v)->all();
                $allowed = array_values(array_unique(array_merge($allowedCourseIds, $authoredCourseIds)));
                $coursesQuery->whereIn('id', $allowed ?: [0]);
            }

            $courses = $coursesQuery->orderBy('id', 'desc')->get();

            // 2026-05-26 (bug-doc C5) — surface a proactive UX hint on the
            // list page when the coach hasn't configured Zoom yet. Without
            // this, the user only discovers the missing credentials when
            // they click "Create" and hit the 400 error. The view checks
            // $zoomConfigured to render a yellow callout with a direct
            // link to Zoom Settings.
            $zoomConfigured = false;
            try {
                $coachUser = User::find($coachId);
                $cred = $coachUser?->zoom_credential;
                $zoomConfigured = (bool) ($cred && $cred->account_id
                    && $cred->client_id && $cred->client_secret);
            } catch (\Throwable $e) {
                $zoomConfigured = false;
            }

            $filters = ['q' => $search, 'status' => $status, 'date' => $date,
                'active' => $search !== '' || $status !== null || $date !== null];

            return view($this->admin_view.'.index', compact(
                'liveClasses', 'metadta', 'courses', 'zoomConfigured', 'filters'
            ));
        } else {
            return view($this->admin_error_view);
        }
    }

    /**
     * 2026-05-20 — Teacher batch gating.
     *
     * Returns the batch IDs the current request is allowed to act on:
     *   - null  → caller is the coach themselves, no batch filter
     *   - []    → caller is a staff with NO assignments — block all
     *   - int[] → caller is a staff with specific grants
     *
     * Used by index/edit dropdowns + every write-side ownership check.
     */
    private function assignedBatchIds(): ?array
    {
        return TeacherBatchAssignment::assignedBatchIdsFor((int) userAuth()->id);
    }

    /**
     * Abort 403 if the current caller is a teacher and the requested
     * batch is not in their active assignment list. Coach calls pass
     * through unchanged (assignedBatchIds() returns null).
     */
    private function enforceTeacherCanAccessBatch(int $batchId): void
    {
        $allowed = $this->assignedBatchIds();
        if ($allowed === null) {
            return; // coach — unbounded
        }
        abort_unless(in_array($batchId, $allowed, true), 403,
            'You are not assigned to this batch. Ask your coach to grant access.');
    }

    /**
     * Shared ownership + batch-assignment gate for the read endpoints
     * around a live class (attendance log, attendance export, manual
     * attendance mark). Allows either:
     *   - the owning coach (course.instructor_id matches), OR
     *   - a CoachStaff teacher whose coach_id matches the course owner
     *     AND who has an active assignment for the live class's batch.
     *
     * Pre-2026-05-20 only the first branch existed; assigned teachers
     * could see the attendance log via a direct URL only if they were
     * a coach. After this change they can manage their assigned-batch
     * classes end-to-end.
     */
    private function enforceCanAccessLiveClass(CourseLiveClass $liveClass): void
    {
        $courseInstructorId = (int) $liveClass->lesson?->course?->instructor_id;
        $callerId           = (int) userAuth()->id;
        $callerCoachId      = userAuth()->role === 'instructor' ? $callerId : (int) userAuth()->coach_id;

        if ($courseInstructorId === $callerId) {
            return; // owning coach
        }

        $isAssignedTeacher = false;
        if ($callerCoachId === $courseInstructorId && $liveClass->batch_id) {
            $allowed = $this->assignedBatchIds();
            $isAssignedTeacher = is_array($allowed) && in_array((int) $liveClass->batch_id, $allowed, true);
        }

        abort_unless($isAssignedTeacher, 403, 'You are not assigned to this live class.');
    }

    /**
     * Find a live class by id, verified to belong to a course owned by the current coach.
     * Aborts 404 if the live class is for someone else's course (IDOR protection).
     *
     * 2026-05-20 — also enforces the per-teacher batch gate. Staff who
     * lack an active assignment for the live class's batch get a 403
     * (vs the 404 that fires for cross-coach lookups), so the UI can
     * show a "you don't have access to this batch" message rather than
     * a generic "not found".
     */
    private function findOwnedLiveClassOrFail($id): CourseLiveClass
    {
        $coachId = userAuth()->role === 'instructor' ? userAuth()->id : userAuth()->coach_id;
        $courseIds = Course::where(function ($q) use ($coachId) {
            $q->where('added_by', $coachId)->orWhere('instructor_id', $coachId);
        })->pluck('id');

        // Two ownership paths:
        //   1. course_live_classes.course_id directly in the coach's courses
        //      (newer rows write this column at create time)
        //   2. course_live_classes.lesson_id → course_chapter_lessons.course_id
        //      (legacy rows have course_id=NULL on this table; the linkage
        //      is through the lesson)
        // Both must be checked or older live classes 404 on edit/show.
        $liveClass = CourseLiveClass::where('id', $id)
            ->where(function ($q) use ($courseIds) {
                $q->whereIn('course_id', $courseIds)
                  ->orWhereHas('lesson', fn ($l) => $l->whereIn('course_id', $courseIds));
            })
            ->firstOrFail();

        // Per-teacher batch gate. Skipped when the live class has no
        // batch_id (legacy / course-wide class) so we don't accidentally
        // lock out a teacher from an unbatched class their coach owns.
        if ($liveClass->batch_id) {
            $this->enforceTeacherCanAccessBatch((int) $liveClass->batch_id);
        }

        return $liveClass;
    }

    /**
     * Single source of truth for the Zoom meeting payload — used by
     * create (POST /users/me/meetings) and update (PATCH /meetings/{id}).
     * Pre-2026-05-09 these were duplicated across store/update with
     * subtly different `settings` blocks; an edited class quietly lost
     * the no-passcode + AV defaults the create path applied. Don't
     * reintroduce that drift — change settings here, not at a call site.
     */
    private function zoomMeetingPayload(string $topic, string $startIso, int $durationMinutes): array
    {
        return [
            'topic'      => $topic,
            'type'       => 2,
            'start_time' => $startIso,
            'duration'   => $durationMinutes,
            'timezone'   => 'Asia/Kolkata',
            // Frictionless join — auth lives in the LMS (signature
            // endpoint enforces enrollment + role). A Zoom passcode adds
            // friction without strengthening security.
            'password'   => '',
            'settings'   => [
                'password'                       => false,
                'meeting_authentication'         => false,
                'waiting_room'                   => false,
                'approval_type'                  => 2,
                'join_before_host'               => true,
                'jbh_time'                       => 0,
                'host_video'                     => true,
                'participant_video'              => true,
                'mute_upon_entry'                => true,
                'audio'                          => 'both',
                'registrants_email_notification' => false,
                'registration_type'              => 1,
                'auto_recording'                 => 'none',
            ],
        ];
    }

    /**
     * Verify the caller owns the course AND that the supplied batch and
     * chapter belong to that course. Closes an IDOR where a coach could
     * pass another coach's chapter_id / batch_id and write a lesson
     * underneath it — the validation rules only checked existence, not
     * ownership.
     */
    private function assertOwnsCreationContext(int $courseId, int $batchId, int $chapterId): void
    {
        $coachId = userAuth()->role === 'instructor' ? userAuth()->id : userAuth()->coach_id;

        $ownsCourse = Course::where('id', $courseId)
            ->where(function ($q) use ($coachId) {
                $q->where('added_by', $coachId)->orWhere('instructor_id', $coachId);
            })
            ->exists();
        abort_unless($ownsCourse, 403, 'You do not own this course.');

        $batchInCourse = CourseBatch::where('id', $batchId)
            ->where('course_id', $courseId)
            ->exists();
        abort_unless($batchInCourse, 403, 'Batch does not belong to this course.');

        $chapterInCourse = CourseChapter::where('id', $chapterId)
            ->where('course_id', $courseId)
            ->exists();
        abort_unless($chapterInCourse, 403, 'Chapter does not belong to this course.');

        // 2026-05-20 — per-teacher batch gate. Coach calls pass through
        // (assignedBatchIds returns null). Staff without an active
        // assignment for this batch get a 403 — they cannot create or
        // edit a live class on a batch they were not granted.
        $this->enforceTeacherCanAccessBatch($batchId);
    }

    /**
     * Audit 2026-05-19 phase 4 — Chapter selection removed from the
     * Add Live Class UI per SRS-CLC-001.
     *
     * The DB schema still requires a chapter for the supporting lesson
     * row (course_chapter_lessons.chapter_id NOT NULL), so we resolve
     * one server-side instead of asking the coach:
     *
     *   1. First existing chapter for this course (most common path
     *      since coaches typically have at least one chapter), OR
     *   2. Auto-create a hidden "Live Sessions" chapter on the fly.
     *
     * Either way the live class anchor stays valid and the coach never
     * sees the dependency.
     */
    private function resolveDefaultChapter(int $courseId): CourseChapter
    {
        $existing = CourseChapter::where('course_id', $courseId)
            ->orderBy('id', 'asc')
            ->first();
        if ($existing) {
            return $existing;
        }

        return CourseChapter::create([
            'course_id' => $courseId,
            'title'     => 'Live Sessions',
            'order'     => 1,
        ]);
    }

    public function edit($id)
    {
        $editdata = $this->findOwnedLiveClassOrFail($id);
        $lessiondata = CourseChapterLesson::where('id', $editdata->lesson_id)->first();

        // 2026-05-20 — per-teacher gate on dropdowns. A staff member
        // editing one of their assigned live classes can only re-target
        // it to ANOTHER batch they were also assigned (re-targeting to
        // an unassigned batch would silently leak access).
        $assignedBatchIds = $this->assignedBatchIds();

        $batchesQuery = CourseBatch::where('course_id', $lessiondata->course_id);
        if ($assignedBatchIds !== null) {
            $batchesQuery->whereIn('id', $assignedBatchIds ?: [0]);
        }
        $batches = $batchesQuery->get();

        $chapters = CourseChapter::where('course_id', $lessiondata->course_id)->get();

        $metadta['title'] = 'Live Classes';
        $coachId = userAuth()->role === 'instructor' ? userAuth()->id : userAuth()->coach_id;
        $coursesQuery = Course::where(function ($query) use ($coachId) {
            $query->where('added_by', $coachId)
                ->orWhere('instructor_id', $coachId);
        })
            ->where('coach_soft_delete', 0)
            ->whereIn('type', ['live', 'hybrid']);

        if ($assignedBatchIds !== null) {
            // 2026-07-10 (Staff Panel) — include staff-authored courses (parity with index()).
            $allowedCourseIds = CourseBatch::whereIn('id', $assignedBatchIds ?: [0])
                ->pluck('course_id')->unique()->map(fn ($v) => (int) $v)->all();
            $authoredCourseIds = Course::where('added_by', (int) userAuth()->id)
                ->whereIn('type', ['live', 'hybrid'])
                ->pluck('id')->map(fn ($v) => (int) $v)->all();
            $allowed = array_values(array_unique(array_merge($allowedCourseIds, $authoredCourseIds)));
            $coursesQuery->whereIn('id', $allowed ?: [0]);
        }

        $courses = $coursesQuery->orderBy('id', 'desc')->get();

        return view($this->admin_view.'.edit', compact('editdata', 'lessiondata', 'batches', 'chapters', 'metadta', 'courses'));

    }

    public function store(Request $request)
    {
        $rules = [
            'live_class_title' => 'required|string|max:255',
            'course_id' => 'required|exists:courses,id',
            'batch_id' => 'required|exists:course_batches,id',
            // Audit 2026-05-19 phase 4 — SRS-CLC-001:
            // Chapter is no longer required from the UI. We still
            // accept it (back-compat with any legacy caller), but
            // auto-resolve a default when missing.
            'chapter_id' => 'nullable|exists:course_chapters,id',
            // 'status' => 'nullable|in:active,inactive',
            'live_type' => 'required|string',
            'start_time' => 'required|date',
            'duration' => 'required|numeric|min:1',
        ];

        $messages = [
            'live_class_title.required' => __('Live Class Title is required'),
            'course_id.required' => __('Course is required'),
            'batch_id.required' => __('Batch is required'),
            'live_type.required' => __('Live Type is required'),
            'start_time.required' => __('Start Time is required'),
            'duration.required' => __('Duration is required'),
        ];

        // Manual validator (better for AJAX)
        $validator = \Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
            ], 422);
        }

        // 2026-06-23 — ONE LIVE CLASS PER TIME SLOT (per coach).
        // Owning coach = tenant scope; instructor acts as self, staff act for
        // their coach. Serialise concurrent same-coach submits with a per-coach
        // advisory lock so two requests can't both pass the overlap check.
        $coachOwnerId = (userAuth()->role == 'instructor')
            ? (int) userAuth()->id
            : (int) userAuth()->coach_id;
        $start_time = date('Y-m-d H:i:s', strtotime($request->start_time));
        $duration   = (int) $request->duration;

        return app(\App\Services\LiveMeetingGuard::class)->withCoachLock($coachOwnerId, function () use ($request, $coachOwnerId, $start_time, $duration) {

        // TIME-OVERLAP guard — block a class that clashes with another active
        // class in the same window (non-overlapping future classes stay
        // allowed). Checked BEFORE any row is written so a blocked request
        // leaves no orphan chapter/lesson rows.
        try {
            app(\App\Services\LiveMeetingGuard::class)->assertNoOverlap(
                $coachOwnerId, \Carbon\Carbon::parse($start_time), $duration
            );
        } catch (\App\Exceptions\ActiveMeetingExistsException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        // SRS-CLC-001 — resolve chapter server-side when not supplied.
        $chapterId = $request->filled('chapter_id')
            ? (int) $request->chapter_id
            : $this->resolveDefaultChapter((int) $request->course_id)->id;

        $this->assertOwnsCreationContext(
            (int) $request->course_id,
            (int) $request->batch_id,
            $chapterId,
        );

        // ========================================================

        // 2026-07-06 (Role Permission Test doc) — own the lesson/item by the
        // COACH (tenant owner), not the acting user. When a staff member
        // creates a live class, auth('web')->id() is the staff id; the list
        // in index() filters lesson.instructor_id by the coach id, so a
        // staff-owned lesson was invisible to both the coach and the staff.
        // $coachOwnerId is the coach for staff and self for a real coach, so
        // coach-created classes are unaffected.
        $chapterItem = CourseChapterItem::create([
            'instructor_id' => $coachOwnerId,
            'chapter_id' => $chapterId,
            'type' => 'live',
            'order' => CourseChapterItem::whereChapterId($chapterId)->count() + 1,
        ]);

        $chapter_lesson = CourseChapterLesson::create([
            'title' => $request->live_class_title,
            'description' => $request->description,
            'instructor_id' => $coachOwnerId,
            'course_id' => $request->course_id,
            'chapter_id' => $chapterId,
            'chapter_item_id' => $chapterItem->id,
            'duration' => $request->duration,
            'storage' => 'live',
            'file_type' => 'live',
        ]);
        $start_time = date('Y-m-d H:i:s', strtotime($request->start_time));
        // $join_url = null; $request?->live_type === 'zoom' ? $request->join_url : null;

        // *********************************************************

        if ($request->live_type == 'zoom') {
            $coachId = (userAuth()->role == 'instructor') ? userAuth()->id : userAuth()->coach_id;
            $user = User::find($coachId);
            $cred = $user?->zoom_credential;

            if (!$cred || !$cred->account_id || !$cred->client_id || !$cred->client_secret) {
                return response()->json([
                    'error' => 'Please configure Zoom credentials first (Account ID + Client ID + Client Secret).',
                ], 400);
            }

            $accessToken = app(ZoomApiService::class)->ensureFreshAccessToken($cred);
            if (!$accessToken) {
                return response()->json([
                    'error' => 'Zoom rejected the credentials. Verify Account ID, Client ID, Client Secret on zoom.us → Marketplace → your Server-to-Server OAuth app.',
                ], 400);
            }

            $response = Http::withToken($accessToken)
                ->timeout(20)
                ->post(
                    'https://api.zoom.us/v2/users/me/meetings',
                    $this->zoomMeetingPayload(
                        (string) $request->live_class_title,
                        date('c', strtotime($request->start_time)),
                        (int) $request->duration,
                    ),
                );

            $meeting = $response->json();
            $meeting_id = $meeting['id'] ?? null;
            $join_url = $meeting['join_url'] ?? null;
            // We explicitly request no-password meetings above. Zoom may
            // still return a password if the account profile enforces one;
            // store whatever it sends so the SDK can still pass it along.
            $password = $meeting['password'] ?? null;

            // SECURITY/INTEGRITY (audit 2026-05-22) — roll back the parent
            // lesson+item if Zoom couldn't create the meeting. The sibling
            // instantStart() method already does this; store() was missed.
            // Without rollback, a transient Zoom failure leaves an orphan
            // "live class with no meeting" row that confuses the coach.
            if (! $meeting_id) {
                if (isset($chapter_lesson) && $chapter_lesson) $chapter_lesson->delete();
                if (isset($chapter_item)   && $chapter_item)   $chapter_item->delete();
                \Log::warning('coach-live-class-store-zoom-fail', [
                    'coach_id' => userAuth()?->id,
                    'response' => $meeting,
                ]);
                return response()->json([
                    'status'  => 'error',
                    'message' => __('Zoom rejected the meeting creation. The live class was not saved. Please verify Zoom credentials in Zoom Settings.'),
                ], 400);
            }
        }
        // *********************************************************

        $liveClass = CourseLiveClass::create([
            'batch_id' => $request->batch_id,
            // course_id mirrors the parent lesson's course so the row isn't
            // orphaned. The 8 historical rows that hit the migration on
            // 2026-05-07 with course_id=NULL came from this path before
            // this line was added.
            'course_id' => $chapter_lesson->course_id,
            'lesson_id' => $chapter_lesson->id,
            'start_time' => $start_time,
            // Persist the duration so later overlap checks are exact rather
            // than relying on the lesson-duration fallback.
            'expected_duration_minutes' => $duration,
            'meeting_id' => $meeting_id ?? '',
            'password' => $password ?? null,
            'join_url' => $join_url ?? null,
            'type' => $request->live_type,
        ]);

        // 2026-06-05/06 — BATCH-SCOPED notification via the single-source-of-
        // truth service (course + batch + paid + active, deduped). In-app bell
        // is always delivered; email is always sent on CREATE to every eligible
        // batch student (per-student opt-out still respected). Coach- and
        // staff-created classes share identical rules. See LiveClassController
        // ::update() which reuses the same service so create/edit emails match.
        $recipientCount = app(\App\Services\LiveClassNotificationService::class)
            ->notifyScheduled($liveClass, (string) $chapter_lesson->title, true);

        // ========================================================

        return response()->json([
            'status'  => 'success',
            'message' => $recipientCount > 0
                ? __('Live class created successfully. Email notification has been sent to eligible students of the selected batch.')
                : __('Live class created successfully, but no eligible students were found in the selected batch for notification.'),
        ]);
        }); // end withCoachLock — one-live-class-per-time-slot
    }

    /**
     * Audit 2026-05-19 phase 4 (post-SRS) — "Instant Live Class".
     *
     * One-click start: the coach picks a Course + Batch and we
     * immediately create the supporting chapter/lesson rows, ask
     * Zoom for a meeting, persist the live class row with
     * start_time=now, and return the join URL the FE can navigate to.
     *
     * Acceptance:
     *   - No date/time/title picker required (title auto-generated)
     *   - Zoom meeting created via the same path store() uses (no
     *     drift in credentials handling)
     *   - Returns JSON { status, live_class_id, redirect_url }
     */
    public function instantStart(Request $request)
    {
        $rules = [
            'course_id' => 'required|exists:courses,id',
            'batch_id'  => 'required|exists:course_batches,id',
            'duration'  => 'nullable|numeric|min:5|max:480',
        ];
        $messages = [
            'course_id.required' => __('Course is required'),
            'batch_id.required'  => __('Batch is required'),
        ];
        $validator = \Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $chapterId = $this->resolveDefaultChapter((int) $request->course_id)->id;
        $this->assertOwnsCreationContext(
            (int) $request->course_id,
            (int) $request->batch_id,
            $chapterId,
        );

        $title    = 'Instant — ' . now()->format('M j, g:i A');
        $duration = (int) ($request->duration ?? 60);

        // Verify Zoom credentials BEFORE we write anything to the DB —
        // we don't want orphaned lesson/chapter rows if Zoom rejects.
        $coachId = (userAuth()->role == 'instructor') ? userAuth()->id : userAuth()->coach_id;
        $user = User::find($coachId);
        $cred = $user?->zoom_credential;
        if (!$cred || !$cred->account_id || !$cred->client_id || !$cred->client_secret) {
            return response()->json([
                'error' => __('Please configure Zoom credentials first (Account ID + Client ID + Client Secret).'),
            ], 400);
        }
        $accessToken = app(ZoomApiService::class)->ensureFreshAccessToken($cred);
        if (!$accessToken) {
            return response()->json([
                'error' => __('Zoom rejected the credentials. Verify Account ID, Client ID, Client Secret.'),
            ], 400);
        }

        // 2026-06-23 — TIME-OVERLAP guard. An instant class goes live NOW, so
        // block it if its [now, now+duration] window clashes with another
        // active class (e.g. one scheduled to start shortly). The slot claim
        // below still arbitrates concurrent instant starts; this additionally
        // catches the instant-vs-upcoming-scheduled clash. Tenant-scoped.
        try {
            app(\App\Services\LiveMeetingGuard::class)->assertNoOverlap(
                $coachId, now(), $duration
            );
        } catch (\App\Exceptions\ActiveMeetingExistsException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 409);
        }

        // Create supporting chapter/item/lesson — same shape as store().
        // 2026-07-10 (New Changes for UI #10) — stamp the supporting lesson/item
        // with the COACH OWNER id ($coachId, resolved above), NOT the acting
        // user's id. When a STAFF member starts an instant class, auth('web')->id()
        // is the staff id, so the lesson's instructor_id no longer matched the
        // coach and index()'s `whereHas('lesson', instructor_id = coachId)` filter
        // excluded it from BOTH the coach and staff Live Classes listings. Using
        // $coachId gives parity with the scheduled store() path (which was fixed
        // the same way on 2026-07-06) so the row is visible in both panels.
        $chapterItem = CourseChapterItem::create([
            'instructor_id' => $coachId,
            'chapter_id'    => $chapterId,
            'type'          => 'live',
            'order'         => CourseChapterItem::whereChapterId($chapterId)->count() + 1,
        ]);
        $chapter_lesson = CourseChapterLesson::create([
            'title'           => $title,
            'description'     => 'Instant live session',
            'instructor_id'   => $coachId,
            'course_id'       => (int) $request->course_id,
            'chapter_id'      => $chapterId,
            'chapter_item_id' => $chapterItem->id,
            'duration'        => $duration,
            'storage'         => 'live',
            'file_type'       => 'live',
        ]);

        // 2026-06-23 — ONE COACH = ONE ACTIVE LIVE MEETING. An instant meeting
        // is live the moment it's created, so claim the coach's single active
        // slot BEFORE spinning up Zoom. Create the live-class row first (with a
        // placeholder meeting id) so the claim is atomic + race-safe via
        // UNIQUE(coach_id); roll the whole thing back if the coach already has
        // an active meeting. Tenant-scoped by the owning coach.
        $liveClass = CourseLiveClass::create([
            'batch_id'   => (int) $request->batch_id,
            'course_id'  => $chapter_lesson->course_id,
            'lesson_id'  => $chapter_lesson->id,
            'start_time' => now()->toDateTimeString(),
            'type'       => 'zoom',
            'expected_duration_minutes' => $duration,
        ]);

        try {
            app(\App\Services\LiveMeetingGuard::class)->claim($coachId, (int) $liveClass->id);
        } catch (\App\Exceptions\ActiveMeetingExistsException $e) {
            $liveClass->delete();
            $chapter_lesson->delete();
            $chapterItem->delete();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 409);
        }

        // Ask Zoom for the meeting. Type 1 = instant (vs 2 = scheduled),
        // but the SDK Component View also works fine on type=2 with
        // start_time=now, which is what zoomMeetingPayload() builds.
        $response = \Illuminate\Support\Facades\Http::withToken($accessToken)
            ->timeout(20)
            ->post(
                'https://api.zoom.us/v2/users/me/meetings',
                $this->zoomMeetingPayload(
                    $title,
                    now()->toIso8601String(),
                    $duration,
                ),
            );
        $meeting = $response->json();
        $meeting_id = $meeting['id']       ?? null;
        $join_url   = $meeting['join_url'] ?? null;
        $password   = $meeting['password'] ?? null;

        if (!$meeting_id) {
            // Roll back everything (incl. the claimed slot) so a failed Zoom
            // call doesn't leave orphans or a stuck active-meeting slot.
            app(\App\Services\LiveMeetingGuard::class)->releaseByLiveClass((int) $liveClass->id);
            $liveClass->delete();
            $chapter_lesson->delete();
            $chapterItem->delete();
            return response()->json([
                'error' => __('Zoom did not return a meeting ID. Try again or check Zoom app permissions.'),
                'zoom_response' => $meeting,
            ], 502);
        }

        // Zoom is ready — fill in the meeting details on the row we created above.
        $liveClass->update([
            'meeting_id' => (string) $meeting_id,
            'password'   => $password,
            'join_url'   => $join_url,
        ]);

        // Redirect target — the coach's existing Zoom session page.
        $redirectUrl = route('instructor.live-class', $chapter_lesson->id);

        return response()->json([
            'status'        => 'success',
            'message'       => __('Instant live class started.'),
            'live_class_id' => $liveClass->id,
            'redirect_url'  => $redirectUrl,
        ]);
    }

    // update

    public function update(Request $request, $id)
    {
        $liveClass = $this->findOwnedLiveClassOrFail($id);

        // Capture the pre-edit start time so we can tell a RESCHEDULE (time
        // changed) from a minor edit and notify students appropriately.
        $previousStartTime = $liveClass->start_time
            ? \Carbon\Carbon::parse($liveClass->start_time)->format('Y-m-d H:i:s')
            : null;

        $rules = [
            'live_class_title' => 'required|string|max:255',
            'course_id' => 'required|exists:courses,id',
            'batch_id' => 'required|exists:course_batches,id',
            // SRS-CLC-001 — Chapter no longer required from UI on update.
            'chapter_id' => 'nullable|exists:course_chapters,id',
            'live_type' => 'required|string',
            'start_time' => 'required|date',
            'duration' => 'required|numeric|min:1',
        ];

        $messages = [
            'live_class_title.required' => __('Live Class Title is required'),
            'course_id.required' => __('Course is required'),
            'batch_id.required' => __('Batch is required'),
            'live_type.required' => __('Live Type is required'),
            'start_time.required' => __('Start Time is required'),
            'duration.required' => __('Duration is required'),
        ];

        // Manual validator (better for AJAX)
        $validator = \Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        // SRS-CLC-001 — preserve the existing chapter if the UI didn't
        // send one (Chapter dropdown has been removed). Falls back to
        // the default-chapter resolver if there isn't one yet.
        $chapterId = $request->filled('chapter_id')
            ? (int) $request->chapter_id
            : (int) ($liveClass->lesson?->chapter_id
                ?? $this->resolveDefaultChapter((int) $request->course_id)->id);

        $this->assertOwnsCreationContext(
            (int) $request->course_id,
            (int) $request->batch_id,
            $chapterId,
        );

        // 2026-06-23 — TIME-OVERLAP guard on edit. Block the save if the new
        // window clashes with ANOTHER of this coach's active classes (the row
        // being edited is excluded, so simply re-saving is always allowed).
        // Tenant-scoped by the owning coach; never affects other coaches.
        $coachOwnerId = (userAuth()->role == 'instructor')
            ? (int) userAuth()->id
            : (int) userAuth()->coach_id;
        try {
            app(\App\Services\LiveMeetingGuard::class)->assertNoOverlap(
                $coachOwnerId,
                \Carbon\Carbon::parse($request->start_time),
                (int) $request->duration,
                (int) $liveClass->id
            );
        } catch (\App\Exceptions\ActiveMeetingExistsException $e) {
            return redirect()->back()
                ->withErrors(['start_time' => $e->getMessage()])
                ->withInput();
        }

        // FT-IDOR-31 fix (2026-05-28) — cross-coach lesson-row hijack.
        //
        // Pre-fix: $request->chapter_item_id and $request->lession_id
        // were trusted at face value, then CourseChapterItem::find /
        // CourseChapterLesson::find found those rows (anywhere on the
        // platform) and the subsequent update() overwrote:
        //   chapterItem: instructor_id = me, chapter_id = mine, type = live
        //   chapter_lesson: title/description = mine, instructor_id = me,
        //                   course_id = mine, chapter_id = mine
        //
        // i.e. an attacker who owned ANY live class could rewrite the
        // chapter_item_id / lession_id hidden form fields to point at
        // a victim coach's lesson row and STEAL it — that lesson row
        // would have its instructor_id flipped to the attacker AND its
        // course_id pointed at the attacker's course. The victim's
        // course catalogue effectively loses a lesson.
        //
        // Fix: ignore the request-supplied chapter_item_id and
        // lession_id entirely. The live class is already ownership-
        // verified (findOwnedLiveClassOrFail above); its lesson_id is
        // the authoritative source, and that lesson's chapter_item_id
        // is reachable through the relation. Both lookups now derive
        // from the verified $liveClass.
        $chapter_lesson = $liveClass->lesson_id
            ? CourseChapterLesson::find($liveClass->lesson_id)
            : null;
        $chapterItem = $chapter_lesson?->chapter_item_id
            ? CourseChapterItem::find($chapter_lesson->chapter_item_id)
            : null;

        if ($chapterItem) {
            $chapterItem->update([
                'instructor_id' => auth('web')->id(),
                'chapter_id' => $chapterId,
                'type' => 'live',
            ]);
        }

        $duration = $chapter_lesson?->duration;

        if ($chapter_lesson) {
            $chapter_lesson->update([
                'title' => $request->live_class_title,
                'description' => $request->description,
                'instructor_id' => auth('web')->id(),
                'course_id' => $request->course_id,
                'chapter_id' => $chapterId,
                'duration' => $request->duration,
            ]);
        }
        $start_time = date('Y-m-d H:i:s', strtotime($request->start_time));
 
        // ********************************************************* 
        if ($liveClass->start_time != $start_time || $duration != $request->duration) {

            if ($request->live_type == 'zoom') {
                $coachId = (userAuth()->role == 'instructor') ? userAuth()->id : userAuth()->coach_id;
                $user = User::find($coachId);
                $cred = $user?->zoom_credential;

                if (!$cred || !$cred->account_id || !$cred->client_id || !$cred->client_secret) {
                    return response()->json([
                        'error' => 'Please configure Zoom credentials first (Account ID + Client ID + Client Secret).',
                    ], 400);
                }

                $accessToken = app(ZoomApiService::class)->ensureFreshAccessToken($cred);
                if (!$accessToken) {
                    return response()->json([
                        'error' => 'Zoom rejected the credentials. Verify them on zoom.us → Marketplace.',
                    ], 400);
                }

                $payload = $this->zoomMeetingPayload(
                    (string) $request->live_class_title,
                    date('c', strtotime($request->start_time)),
                    (int) $request->duration,
                );

                // Default the locals to the existing row so the later
                // $liveClass->update([...]) call doesn't clobber meeting_id
                // / join_url / password with null when PATCH succeeds (PATCH
                // returns 204 No Content, not the meeting body).
                $meeting_id = $liveClass->meeting_id;
                $join_url   = $liveClass->join_url;
                $password   = $liveClass->password;

                // PATCH the existing meeting rather than POST a new one.
                // Pre-2026-05-09 the edit path called POST /users/me/meetings
                // which silently created a fresh meeting and orphaned the
                // original on Zoom — counted toward meeting limits, broke
                // recordings:sync (followed the new id), and left bookmarked
                // join_urls pointing at an unmonitored meeting.
                $patched = false;
                if ($liveClass->meeting_id) {
                    $patchResp = Http::withToken($accessToken)
                        ->timeout(20)
                        ->patch(
                            'https://api.zoom.us/v2/meetings/' . urlencode((string) $liveClass->meeting_id),
                            $payload,
                        );

                    if ($patchResp->successful()) {
                        $patched = true;
                    } elseif ($patchResp->status() !== 404) {
                        $body = $patchResp->json();
                        $msg  = is_array($body) && isset($body['message'])
                            ? (string) $body['message']
                            : 'HTTP ' . $patchResp->status();
                        return response()->json([
                            'error' => 'Zoom rejected the meeting update: ' . $msg,
                        ], 400);
                    }
                    // 404 → meeting was deleted on Zoom's side. Fall
                    // through to create so the class still has a working
                    // meeting (same recovery path as zoom:recreate-meetings).
                }

                if (!$patched) {
                    $createResp = Http::withToken($accessToken)
                        ->timeout(20)
                        ->post('https://api.zoom.us/v2/users/me/meetings', $payload);

                    $meeting    = $createResp->json();
                    $meeting_id = $meeting['id'] ?? $meeting_id;
                    $join_url   = $meeting['join_url'] ?? $join_url;
                    // Zoom doesn't always return a password (free-plan, or
                    // require_password off). Fall back to existing.
                    $password   = $meeting['password'] ?? $password;
                }
            }
            // *********************************************************
        }


        if ($liveClass) {
            // SECURITY/DATA-INTEGRITY (audit 2026-05-22) — preserve existing
            // password + join_url when the Zoom branch above didn't run
            // (i.e. start_time and duration unchanged). The original code
            // used `$password ?? null` / `$join_url ?? null` which silently
            // wiped both fields on every "minor edit" (title-only changes
            // etc.), breaking student join links.
            $liveClass->update([
                'batch_id'   => $request->batch_id,
                'course_id'  => $chapter_lesson->course_id,
                'lesson_id'  => $chapter_lesson->id,
                'start_time' => $start_time,
                'meeting_id' => $meeting_id ?? $liveClass->meeting_id,
                'password'   => $password   ?? $liveClass->password,
                'join_url'   => $join_url   ?? $liveClass->join_url,
                'type'       => $request->live_type,
            ]);

        }
        // 2026-06-06 — On edit, re-notify the SAME batch-scoped recipients via
        // the shared service (was course-wide MailSenderService with an empty
        // "Meeting Link" — the local $join_url was only set inside the Zoom
        // branch, so a minor edit left it blank). Gated by the coach's "email
        // students" choice so trivial edits don't spam students; the enriched
        // email carries Course, Batch, Live Class, Date & Time and Coach, and
        // its CTA opens the student panel (so the wait-for-coach gate holds).
        $liveClass->refresh();
        $timeChanged = $previousStartTime !== (
            $liveClass->start_time ? \Carbon\Carbon::parse($liveClass->start_time)->format('Y-m-d H:i:s') : null
        );

        if ($timeChanged) {
            // Dedicated RESCHEDULE notification (in-app always; email per the
            // student's preference). Batch-scoped via the shared service.
            app(\App\Services\LiveClassNotificationService::class)
                ->notifyRescheduled($liveClass, $previousStartTime);
        } elseif ($request?->student_mail_sent == 'on') {
            // Minor edit + coach chose to email students → re-send the
            // scheduled notification (unchanged behaviour).
            app(\App\Services\LiveClassNotificationService::class)
                ->notifyScheduled($liveClass, (string) $chapter_lesson->title, true);
        }

        // ========================================================

        return redirect()->route('instructor.live-classes.index')->with('success', 'Updated successfully');

    }

    public function coachLiveSession(string $lesson_id)
    {
        // NOTE: do NOT eager-load client_secret. The view fetches the
        // signature from /zoom/sdk-signature/{id} where the secret is used
        // server-side and never returned to the browser.
        $lesson = CourseChapterLesson::select('id', 'course_id', 'chapter_item_id', 'title')->with(['course' => function ($q) {
            $q->select('id', 'instructor_id', 'slug', 'title');
        }, 'chapterItem' => function ($q) {
            $q->select('id', 'type');
        }, 'live' => function ($q) {
            $q->select('id', 'lesson_id', 'start_time', 'type', 'meeting_id', 'join_url', 'batch_id');
        }])->findOrFail($lesson_id);

        // 2026-05-20 — Ownership gate now accepts either:
        //   (a) the coach who owns the course, OR
        //   (b) a CoachStaff teacher with an active assignment for the
        //       lesson's live-class batch.
        // Without (b), assigned teachers could create live classes but
        // not actually host them — meeting the spec's "teacher can
        // conduct live classes for assigned batches" requirement.
        $courseInstructorId = (int) $lesson->course?->instructor_id;
        $callerId           = (int) userAuth()->id;
        $callerCoachId      = userAuth()->role === 'instructor' ? $callerId : (int) userAuth()->coach_id;

        $isOwningCoach   = $courseInstructorId === $callerId;
        $isAssignedTeacher = false;
        if (! $isOwningCoach && $callerCoachId === $courseInstructorId && $lesson->live?->batch_id) {
            $allowed = $this->assignedBatchIds();
            $isAssignedTeacher = is_array($allowed) && in_array((int) $lesson->live->batch_id, $allowed, true);
        }

        abort_unless(
            $isOwningCoach || $isAssignedTeacher,
            403,
            'You are not assigned to host this live class.'
        );

        // 2026-05-26 (bug-doc C6) — Defensive: if the live row was deleted
        // (e.g. coach removed via Edit) or never created (orphan lesson),
        // bail out gracefully instead of "Attempt to read property on null".
        if (! $lesson->live) {
            return redirect()->route('instructor.live-classes.index')->with([
                'messege'    => __('This live class has no Zoom session yet. Recreate it from the Live Classes page.'),
                'alert-type' => 'error',
            ]);
        }

        if ($lesson->live->type == 'zoom') {
            // Render the embedded Zoom Meeting SDK launcher (host role —
            // ZoomSignatureController decides role=1 because the caller
            // matches course.instructor_id). Same UX as the student
            // launcher, just with host privileges in the meeting.
            return view('frontend.instructor-dashboard.live-classes.zoom', compact('lesson'));
        }

        // Legacy Jitsi rows (pre-2026-05-07 Jitsi removal) land here. Show a
        // clear "this provider is no longer supported" page rather than a
        // blank screen or 500. Operator can recreate the live class via
        // the UI which now produces a Zoom meeting only.
        \Log::info('coachLiveSession: legacy non-zoom live class', [
            'lesson_id' => $lesson->id,
            'type'      => $lesson->live->type ?? null,
        ]);
        abort(410, 'This live class uses a discontinued provider (' . e($lesson->live->type) . '). Please recreate the live class — only Zoom is supported now.');
    }

    /**
     * 2026-06-05 — Coach marks a live class COMPLETED.
     *
     * Replaces the old student-side "Mark lesson complete" button (which sat on
     * a studentrole-only route and logged a coach out — bug #5). Completion is
     * now an INSTRUCTOR action that:
     *   1. sets ended_at → the class moves to the "completed" lifecycle status
     *      and is no longer joinable (CourseLiveClass::isFinished()), and
     *   2. auto-credits the live lesson to every student who ATTENDED, so it
     *      counts toward course completion without the student clicking
     *      anything (attendance is the completion signal).
     *
     * Authorisation: owning coach OR an assigned teacher for the class's batch
     * (findOwnedLiveClassOrFail is IDOR- and per-teacher-batch gated).
     */
    public function markCompleted(string $live_class_id): \Illuminate\Http\JsonResponse
    {
        $liveClass = $this->findOwnedLiveClassOrFail($live_class_id);
        $liveClass->loadMissing('lesson:id,course_id,chapter_id');

        $alreadyEnded = $liveClass->ended_at !== null;
        if (! $alreadyEnded) {
            $liveClass->forceFill(['ended_at' => now()])->save();
        }

        // 2026-06-22 — meeting ended → free the coach's active-meeting slot so
        // they can start another (one coach = one active meeting).
        app(\App\Services\LiveMeetingGuard::class)->releaseByLiveClass((int) $liveClass->id);

        // Credit the live lesson to every distinct student who attended.
        $credited = 0;
        $lesson   = $liveClass->lesson;
        if ($lesson && $lesson->course_id) {
            $attendeeIds = \App\Models\LiveClassAttendance::query()
                ->where('course_live_class_id', $liveClass->id)
                ->where('role', 'student')
                ->distinct()
                ->pluck('user_id');

            foreach ($attendeeIds as $uid) {
                \App\Models\CourseProgress::updateOrCreate(
                    [
                        'user_id'    => (int) $uid,
                        'course_id'  => (int) $lesson->course_id,
                        'chapter_id' => $lesson->chapter_id,
                        'lesson_id'  => (int) $lesson->id,
                        'type'       => 'live',
                    ],
                    ['watched' => 1]
                );
                $credited++;
            }
        }

        return response()->json([
            'ok'        => true,
            'completed' => true,
            'credited'  => $credited,
            'message'   => $alreadyEnded
                ? __('This live class was already completed.')
                : __('Live class marked as completed. Attendance has been credited to students.'),
        ]);
    }

    /**
     * AJAX endpoint — returns the currently-in-meeting count for every
     * live class owned by the calling coach. The instructor dashboard
     * polls this every 30 seconds so the "X in meeting" pulse stays
     * accurate without a hard page reload during an active class.
     *
     * Response: {"counts": {"<live_class_id>": <distinct user count>, …}}
     */
    public function liveCounts(): \Illuminate\Http\JsonResponse
    {
        $coachId = (userAuth()->role !== 'instructor') ? userAuth()->coach_id : userAuth()->id;

        // One query: for every live class belonging to this coach, count
        // distinct users with an open attendance session. Filter to
        // classes scheduled within ±24h to avoid scanning historical rows.
        // 2026-05-20 — staff polling sees only their assigned batches'
        // counts. Coach (null) is unbounded.
        $assignedBatchIds = $this->assignedBatchIds();

        $rows = \DB::table('course_live_classes as c')
            ->join('course_chapter_lessons as l', 'c.lesson_id', '=', 'l.id')
            ->leftJoin('live_class_attendances as a', function ($j) {
                $j->on('a.course_live_class_id', '=', 'c.id')
                  ->whereNull('a.left_at');
            })
            ->where('l.instructor_id', $coachId)
            ->when($assignedBatchIds !== null, function ($q) use ($assignedBatchIds) {
                $q->whereIn('c.batch_id', $assignedBatchIds ?: [0]);
            })
            ->whereBetween('c.start_time', [now()->subHours(24), now()->addHours(24)])
            ->groupBy('c.id')
            ->select('c.id', \DB::raw('COUNT(DISTINCT a.user_id) as cnt'))
            ->get();

        $counts = [];
        foreach ($rows as $r) $counts[(string) $r->id] = (int) $r->cnt;

        return response()->json(['counts' => $counts]);
    }

    /**
     * Attendance log for a single live class. Coach sees who joined,
     * when, and how long they stayed. Per-row shows multiple sessions
     * if the user disconnected and rejoined.
     */
    /**
     * Mark a student manually present on a live class (#7, 2026-05-12).
     *
     * Use case: tech failure prevented the launcher from logging
     * attendance for a student the instructor knows was there. Creates
     * an attendance row with is_manual=true, role=attendee, and a
     * mandatory `manual_reason` so the override is auditable.
     *
     * Ownership gate: same as attendance() — only the course's
     * instructor can mark for that course.
     */
    public function attendanceManualMark(\Illuminate\Http\Request $request, string $live_class_id): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'user_id'       => ['required', 'integer'],
            'reason'        => ['required', 'string', 'min:3', 'max:255'],
            'duration_min'  => ['nullable', 'integer', 'min:1', 'max:480'],
        ]);

        $liveClass = \App\Models\CourseLiveClass::with([
            'lesson:id,course_id,instructor_id,title',
            'lesson.course:id,instructor_id',
        ])->findOrFail($live_class_id);

        // 2026-05-20 — extended to also allow assigned teachers.
        $this->enforceCanAccessLiveClass($liveClass);

        $userId = (int) $request->input('user_id');
        $courseId = (int) ($liveClass->course_id ?: $liveClass->lesson?->course?->id);
        $liveBatchId = (int) ($liveClass->batch_id ?? 0);

        // The marked user must actually be enrolled in the course —
        // marking a random user as present would be data corruption.
        $enrollments = \Modules\Order\app\Models\Enrollment::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->where('has_access', 1)
            ->get(['batch_id']);
        abort_unless($enrollments->isNotEmpty(), 403, 'That user is not enrolled in this class.');

        // 2026-06-01 (audit [7]) — batch scope, mirroring the authoritative
        // ZoomSignatureController student gate: when the live class is
        // batched, deny only if the student's enrollment is in a DIFFERENT
        // (non-null) batch. A NULL-batch (legacy / course-wide) enrollment is
        // allowed into any batch's class — so a student who CAN join the
        // meeting can also be manually marked present. Otherwise a coach
        // running Batch A's class could mark a Batch B student present,
        // polluting the wrong batch's attendance.
        // 2026-06-06 — multi-batch: deny only if the class is batched AND the
        // student is in NONE of: that batch / a null-batch (course-wide) enrollment.
        if ($liveBatchId > 0) {
            $enrBatchIds = $enrollments->pluck('batch_id')->map(fn ($b) => $b !== null ? (int) $b : null);
            if (! $enrBatchIds->contains(null) && ! $enrBatchIds->contains($liveBatchId)) {
                abort(403, 'That user belongs to a different batch.');
            }
        }

        // Default 60 minutes if no duration provided — long enough to clear
        // the ≥60s "attended" threshold used by the watchlist + student view.
        $durationMin = (int) ($request->input('duration_min') ?: 60);
        $durationSec = $durationMin * 60;
        $startedAt   = $liveClass->start_time ? \Illuminate\Support\Carbon::parse($liveClass->start_time) : now();

        \App\Models\LiveClassAttendance::create([
            'course_live_class_id' => $liveClass->id,
            'user_id'              => $userId,
            // 2026-06-01 (audit H7) — was 'attendee'; counters/verifier filter
            // role='student'. A coach manually marking a student present must
            // write 'student' so it's actually counted.
            'role'                 => 'student',
            'joined_at'            => $startedAt,
            'left_at'              => $startedAt->copy()->addSeconds($durationSec),
            'duration_seconds'     => $durationSec,
            'is_manual'            => true,
            'manual_reason'        => (string) $request->input('reason'),
            'marked_by'            => userAuth()->id,
        ]);

        return redirect()
            ->route('instructor.live-class.attendance', ['live_class_id' => $liveClass->id])
            ->with(['message' => __('Attendance recorded'), 'alert-type' => 'success']);
    }

    public function attendance(string $live_class_id): View
    {
        $liveClass = \App\Models\CourseLiveClass::with([
            'lesson:id,course_id,instructor_id,title,duration',
            'lesson.course:id,title,instructor_id,slug',
            // #9 cloud recording link per attendance row (2026-05-12) —
            // recordings are per-live-class, not per-attendance-row.
            // Load them here so the attendance view can surface them
            // in a "Recordings" panel above the session log.
            'recordings:id,course_live_class_id,file_type,play_url,download_url,file_size,duration_seconds,recording_start',
        ])->findOrFail($live_class_id);

        // Ownership gate — same shape as coachLiveSession.
        // 2026-05-20 — extended to also allow assigned teachers.
        $this->enforceCanAccessLiveClass($liveClass);

        // Class start + end timestamps, used by the #5 late/early
        // calculations below. start_time is on course_live_classes;
        // duration lives on the lesson row (minutes). Falls back to
        // 60 minutes if a legacy lesson has no duration.
        $classStart = $liveClass->start_time
            ? \Illuminate\Support\Carbon::parse($liveClass->start_time)
            : null;
        $classDurMin = (int) ($liveClass->lesson?->duration ?: 60);
        $classEnd    = $classStart ? $classStart->copy()->addMinutes($classDurMin) : null;

        $attendances = \App\Models\LiveClassAttendance::with(['user:id,name,email'])
            ->where('course_live_class_id', $liveClass->id)
            ->orderByDesc('joined_at')
            ->get();

        // Annotate each row with late_seconds / early_leave_seconds —
        // null when we don't have enough info (no class start, or no
        // left_at yet). Negative late_seconds (joined before scheduled
        // start) is normalized to 0.
        $attendances->transform(function ($a) use ($classStart, $classEnd) {
            $a->late_seconds = null;
            $a->early_leave_seconds = null;
            if ($classStart && $a->joined_at) {
                $diff = \Illuminate\Support\Carbon::parse($a->joined_at)->diffInSeconds($classStart, false);
                // diffInSeconds(false) is signed: negative when joined_at is AFTER classStart.
                $a->late_seconds = $diff < 0 ? abs($diff) : 0;
            }
            if ($classEnd && $a->left_at) {
                $diff = \Illuminate\Support\Carbon::parse($a->left_at)->diffInSeconds($classEnd, false);
                $a->early_leave_seconds = $diff > 0 ? $diff : 0;
            }
            return $a;
        });

        // Aggregate by user for the summary at the top.
        $perUser = $attendances->groupBy('user_id')->map(function ($rows) {
            $first = $rows->last(); // earliest joined_at (since we ordered desc)
            // Late = MINIMUM late_seconds across the user's sessions for
            // this class — best showing wins, so a student who reconnected
            // doesn't get penalized for a network blip on the 2nd join.
            $lateValues = $rows->pluck('late_seconds')->filter(fn ($v) => $v !== null);
            // Early-leave = MAXIMUM left-before-end across sessions —
            // worst showing wins (we want to know if they bounced).
            // Excludes rows still open (left_at null).
            $earlyValues = $rows->pluck('early_leave_seconds')->filter(fn ($v) => $v !== null);
            return (object) [
                'user'              => $first->user,
                'role'              => $rows->first()->role,
                'sessions'          => $rows->count(),
                'first_joined_at'   => $rows->min('joined_at'),
                'total_seconds'     => $rows->sum('duration_seconds') ?: 0,
                'is_currently_in'   => $rows->whereNull('left_at')->isNotEmpty(),
                'late_seconds'      => $lateValues->isEmpty()  ? null : (int) $lateValues->min(),
                'early_leave_seconds' => $earlyValues->isEmpty() ? null : (int) $earlyValues->max(),
            ];
        })->values();

        // Enrolled-but-absent list — feeds the "Mark present" dropdown
        // (#7 manual override). Excludes students who already have any
        // attendance row for this class, since marking them would
        // create a duplicate. Includes those who joined briefly (<60s)
        // because the instructor may want to upgrade them to "really
        // attended" with an explicit reason.
        $attendedUserIds = $attendances->pluck('user_id')->unique()->all();
        $courseId = (int) ($liveClass->course_id ?: $liveClass->lesson?->course?->id);
        $absentees = \Illuminate\Support\Facades\DB::table('enrollments')
            ->join('users', 'users.id', '=', 'enrollments.user_id')
            ->where('enrollments.course_id', $courseId)
            ->where('enrollments.has_access', 1)
            ->whereNotIn('users.id', $attendedUserIds)
            ->select('users.id', 'users.name', 'users.email')
            ->orderBy('users.name')
            ->get();

        return view('frontend.instructor-dashboard.live-classes.attendance', compact('liveClass', 'attendances', 'perUser', 'absentees'));
    }

    /**
     * Export the per-user attendance summary as CSV. Streams directly to
     * the response so large classes (1000s of rows) don't allocate the
     * whole sheet in memory before sending. Filename embeds lesson slug +
     * scheduled date so an instructor's Downloads folder doesn't fill
     * with `attendance.csv (1).csv (2).csv`.
     *
     * Auth gate is identical to the HTML attendance view above — only
     * the course instructor sees / exports their own classes.
     */
    public function attendanceExport(string $live_class_id): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $liveClass = \App\Models\CourseLiveClass::with([
            'lesson:id,course_id,instructor_id,title',
            'lesson.course:id,title,instructor_id,slug',
        ])->findOrFail($live_class_id);

        // 2026-05-20 — extended to also allow assigned teachers.
        $this->enforceCanAccessLiveClass($liveClass);

        // Enterprise H-A — audit attendance CSV export (data export action).
        \App\Services\ActivityLogger::log(
            \App\Models\ActivityLog::EXPORTED, 'live-class', $liveClass,
            null, null,
            'Exported attendance CSV for "' . ($liveClass->lesson?->title ?? ('class #' . $liveClass->id)) . '"'
        );

        // Class start/end for late/early calculations (#5).
        $classStart = $liveClass->start_time
            ? \Illuminate\Support\Carbon::parse($liveClass->start_time) : null;
        $classDurMin = (int) ($liveClass->lesson?->duration ?: 60);
        $classEnd    = $classStart ? $classStart->copy()->addMinutes($classDurMin) : null;

        $attendances = \App\Models\LiveClassAttendance::with(['user:id,name,email'])
            ->where('course_live_class_id', $liveClass->id)
            ->orderBy('joined_at')        // ASC for the CSV — chronological reads
            ->get();                       //                   better than reverse

        $perUser = $attendances->groupBy('user_id')->map(function ($rows) use ($classStart, $classEnd) {
            $first = $rows->first();       // earliest joined_at (we ordered asc)
            // Best late: smallest minutes late across sessions.
            $late = null;
            if ($classStart) {
                $candidates = [];
                foreach ($rows as $r) {
                    if ($r->joined_at) {
                        $d = \Illuminate\Support\Carbon::parse($r->joined_at)->diffInSeconds($classStart, false);
                        $candidates[] = $d < 0 ? abs($d) : 0;
                    }
                }
                $late = $candidates ? min($candidates) : null;
            }
            // Worst early-leave: largest seconds left before class end.
            $early = null;
            if ($classEnd) {
                $candidates = [];
                foreach ($rows as $r) {
                    if ($r->left_at) {
                        $d = \Illuminate\Support\Carbon::parse($r->left_at)->diffInSeconds($classEnd, false);
                        $candidates[] = $d > 0 ? $d : 0;
                    }
                }
                $early = $candidates ? max($candidates) : null;
            }
            return [
                'name'             => $first->user?->name ?? '',
                'email'            => $first->user?->email ?? '',
                'role'             => $first->role,
                'first_joined_at'  => $rows->min('joined_at'),
                'sessions'         => $rows->count(),
                'total_seconds'    => (int) ($rows->sum('duration_seconds') ?: 0),
                'is_currently_in'  => $rows->whereNull('left_at')->isNotEmpty(),
                'late_seconds'         => $late,
                'early_leave_seconds'  => $early,
                'is_manual'        => (bool) $rows->where('is_manual', true)->count(),
            ];
        })->values();

        $courseTitle = (string) ($liveClass->lesson?->course?->title ?? '');
        $lessonTitle = (string) ($liveClass->lesson?->title ?? 'live-class');
        $scheduledAt = $liveClass->start_time
            ? \Illuminate\Support\Carbon::parse($liveClass->start_time)
            : now();

        $filename = sprintf(
            'attendance_%s_%s.csv',
            \Illuminate\Support\Str::slug($lessonTitle, '-') ?: 'live-class',
            $scheduledAt->format('Y-m-d'),
        );

        $callback = function () use ($courseTitle, $lessonTitle, $scheduledAt, $liveClass, $perUser) {
            $out = fopen('php://output', 'w');

            // UTF-8 BOM so Excel auto-detects encoding for non-ASCII names.
            // Without it, names like "Rohan कुमार" render as mojibake in
            // Excel on Windows (Excel defaults to the system code page).
            fwrite($out, "\xEF\xBB\xBF");

            // Metadata header — gives the CSV self-documenting context so
            // an instructor can open a file from months ago and know what
            // class it was without consulting the LMS.
            fputcsv($out, ['Course',      $courseTitle]);
            fputcsv($out, ['Lesson',      $lessonTitle]);
            fputcsv($out, ['Scheduled',   $scheduledAt->format('Y-m-d H:i')]);
            fputcsv($out, ['Meeting ID',  (string) ($liveClass->meeting_id ?? '')]);
            fputcsv($out, ['Exported at', now()->format('Y-m-d H:i:s')]);
            fputcsv($out, []); // blank row separates metadata from table

            // Data table header — added Late + Left early + Manual columns
            // for the #5 + #7 features. Order chosen so the existing
            // CSV consumers (Excel filters, etc.) keep working on the
            // left columns and the new ones are appended on the right.
            fputcsv($out, [
                'Name', 'Email', 'Role', 'First join',
                'Sessions', 'Total minutes', 'Status',
                'Late by (min)', 'Left early by (min)', 'Manual override',
            ]);

            foreach ($perUser as $u) {
                $late  = isset($u['late_seconds'])         ? round((int) $u['late_seconds']         / 60, 1) : '';
                $early = isset($u['early_leave_seconds'])  ? round((int) $u['early_leave_seconds']  / 60, 1) : '';
                fputcsv($out, [
                    $u['name'],
                    $u['email'],
                    ucfirst((string) $u['role']),
                    $u['first_joined_at']
                        ? \Illuminate\Support\Carbon::parse($u['first_joined_at'])->format('Y-m-d H:i:s')
                        : '',
                    $u['sessions'],
                    round($u['total_seconds'] / 60, 1),
                    $u['is_currently_in'] ? 'In meeting' : 'Left',
                    $late,
                    $early,
                    !empty($u['is_manual']) ? 'Yes' : '',
                ]);
            }

            fclose($out);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Cache-Control'       => 'no-cache, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
