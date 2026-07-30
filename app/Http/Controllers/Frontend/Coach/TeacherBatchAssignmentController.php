<?php

namespace App\Http\Controllers\Frontend\Coach;

use App\Http\Controllers\Controller;
use App\Models\CoachStaff;
use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\TeacherBatchAssignment;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Coach-side CRUD for Teacher → Batch assignments.
 *
 * Every method enforces three ownership invariants because the
 * underlying table does not have SQL constraints for them:
 *   - The teacher (CoachStaff) must have coach_id == current coach
 *   - The course must be added_by OR instructor_id == current coach
 *   - The batch must belong to the course
 *
 * Soft remove semantics: destroy() flips status to 'inactive' so the
 * row survives for audit. A subsequent create() that targets the
 * same (coach, teacher, batch) re-activates the existing row instead
 * of inserting a duplicate (the unique index would block it anyway).
 */
class TeacherBatchAssignmentController extends Controller
{
    protected string $pageName = 'teacher-batches';
    protected string $baseRoute = 'instructor.teacher-batches.index';
    protected string $viewBase = 'frontend.instructor-dashboard.teacher-batches';

    /**
     * 2026-07-07 (QA audit) — server-side RBAC gate. Routes carried ONLY
     * `requires.membership`; without this a staff member could POST/PUT/DELETE
     * assignments directly — and, critically, grant THEMSELVES access to more
     * batches (self-escalating their own teacher scope). Reads need
     * `teacher-batches`; writes need `teacher-batches-edit` (the catalog has no
     * separate -create/-delete for this module). A real coach always passes.
     */
    public function __construct()
    {
        $this->middleware('permission:teacher-batches');
        $this->middleware('permission:teacher-batches-edit')->only(['store', 'update', 'destroy']);
    }

    /**
     * Resolve the effective coach id for the current request.
     * Staff acting on behalf of their coach get the coach's id.
     */
    protected function coachId(): int
    {
        return userAuth()->role === 'instructor'
            ? (int) userAuth()->id
            : (int) userAuth()->coach_id;
    }

    public function index(Request $request): View
    {
        $coachId = $this->coachId();

        $q = TeacherBatchAssignment::query()
            ->with(['teacher:id,name,email', 'course:id,title', 'batch:id,title,course_id'])
            ->forCoach($coachId);

        if ($request->filled('teacher_id')) {
            $q->where('teacher_id', (int) $request->teacher_id);
        }
        if ($request->filled('course_id')) {
            $q->where('course_id', (int) $request->course_id);
        }
        if ($request->filled('status') && in_array($request->status, ['active', 'inactive'], true)) {
            $q->where('status', $request->status);
        }

        $assignments = $q->orderByDesc('id')->paginate(15)->withQueryString();

        $base = TeacherBatchAssignment::query()->forCoach($coachId);
        $kpi = [
            'total'      => (clone $base)->count(),
            'active'     => (clone $base)->where('status', 'active')->count(),
            'inactive'   => (clone $base)->where('status', 'inactive')->count(),
            'teachers'   => (clone $base)->where('status', 'active')->distinct('teacher_id')->count('teacher_id'),
        ];

        // Filter dropdown data — only show the coach's own teachers
        // and courses (no cross-coach data exposure).
        $teachers = CoachStaff::where('coach_id', $coachId)
            ->where('id', '!=', $coachId)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $courses = Course::where(function ($w) use ($coachId) {
                $w->where('added_by', $coachId)->orWhere('instructor_id', $coachId);
            })
            ->where('coach_soft_delete', 0)
            ->orderBy('title')
            ->get(['id', 'title']);

        $metadta = ['title' => 'Teacher Batch Assignments'];

        return view($this->viewBase.'.index', compact('assignments', 'kpi', 'teachers', 'courses', 'metadta'));
    }

    public function create(): View
    {
        $coachId = $this->coachId();

        $teachers = CoachStaff::where('coach_id', $coachId)
            ->where('id', '!=', $coachId)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $courses = Course::where(function ($w) use ($coachId) {
                $w->where('added_by', $coachId)->orWhere('instructor_id', $coachId);
            })
            ->where('coach_soft_delete', 0)
            ->orderBy('title')
            ->get(['id', 'title']);

        $metadta = ['title' => 'Assign Batches to Teacher'];

        return view($this->viewBase.'.create', compact('teachers', 'courses', 'metadta'));
    }

    /**
     * AJAX dependent dropdown — return batches for a course the
     * caller actually owns. Returns only the coach's own batches;
     * stripping the coach scope would leak batch metadata across
     * coaches.
     */
    public function batchesForCourse(int $courseId): JsonResponse
    {
        $coachId = $this->coachId();
        $owns = Course::where('id', $courseId)
            ->where(function ($w) use ($coachId) {
                $w->where('added_by', $coachId)->orWhere('instructor_id', $coachId);
            })->exists();

        if (! $owns) {
            return response()->json(['batches' => []], 403);
        }

        $batches = CourseBatch::where('course_id', $courseId)
            ->orderBy('start_date', 'desc')
            ->get(['id', 'title', 'start_date', 'end_date', 'status']);

        // 2026-07-08 — attach the batch's CURRENT active teacher (if any) so the
        // assign form can warn + confirm before transferring ownership.
        $active = TeacherBatchAssignment::where('coach_id', $coachId)
            ->whereIn('batch_id', $batches->pluck('id'))
            ->where('status', 'active')
            ->with('teacher:id,name')
            ->get()
            ->keyBy('batch_id');

        $batches->transform(function ($b) use ($active) {
            $a = $active->get($b->id);
            $b->current_teacher_id   = $a?->teacher_id;
            $b->current_teacher_name = $a?->teacher?->name;
            return $b;
        });

        return response()->json(['batches' => $batches]);
    }

    public function store(Request $request): RedirectResponse
    {
        $coachId = $this->coachId();

        $request->validate([
            'teacher_id'      => ['required', 'integer'],
            'course_id'       => ['required', 'integer'],
            'batch_ids'       => ['required', 'array', 'min:1'],
            'batch_ids.*'     => ['integer'],
            'permission_type' => ['nullable', 'string', 'max:32'],
        ], [
            'teacher_id.required' => __('Please choose a teacher'),
            'course_id.required'  => __('Please choose a course'),
            'batch_ids.required'  => __('Please select at least one batch'),
        ]);

        $teacherId      = (int) $request->teacher_id;
        $courseId       = (int) $request->course_id;
        $batchIds       = array_unique(array_map('intval', $request->batch_ids));
        $permissionType = $request->input('permission_type', 'manage') ?: 'manage';

        // Ownership: teacher must be one of the coach's staff.
        $teacherOk = CoachStaff::where('id', $teacherId)
            ->where('coach_id', $coachId)
            ->exists();
        abort_unless($teacherOk, 403, 'Teacher does not belong to this coach.');

        // Ownership: course must be the coach's.
        $courseOk = Course::where('id', $courseId)
            ->where(function ($w) use ($coachId) {
                $w->where('added_by', $coachId)->orWhere('instructor_id', $coachId);
            })
            ->exists();
        abort_unless($courseOk, 403, 'Course does not belong to this coach.');

        // Ownership: every batch must belong to the chosen course.
        $validBatchIds = CourseBatch::where('course_id', $courseId)
            ->whereIn('id', $batchIds)
            ->pluck('id')
            ->all();
        $invalid = array_diff($batchIds, $validBatchIds);
        if (! empty($invalid)) {
            return back()->withInput()->with([
                'message'    => __('One or more selected batches do not belong to the chosen course.'),
                'alert-type' => 'error',
            ]);
        }

        // 2026-07-08 ("Restrict Batch Assignment to One Teacher at a Time") — a
        // batch may have only ONE active teacher. Detect batches already held by
        // a DIFFERENT teacher; without an explicit confirm_transfer flag we
        // refuse (backend guard for direct/API/manual posts). The UI shows a
        // confirmation dialog first and re-submits with the flag on Confirm.
        $conflicts = TeacherBatchAssignment::where('coach_id', $coachId)
            ->whereIn('batch_id', $validBatchIds)
            ->where('status', 'active')
            ->where('teacher_id', '!=', $teacherId)
            ->with(['teacher:id,name', 'batch:id,title'])
            ->get();

        if ($conflicts->isNotEmpty() && ! $request->boolean('confirm_transfer')) {
            $list = $conflicts
                ->map(fn ($c) => ($c->batch->title ?? ('Batch #' . $c->batch_id)) . ' → ' . ($c->teacher->name ?? __('another teacher')))
                ->implode('; ');
            return back()->withInput()->with([
                'message'    => __('These batches are already assigned to another teacher (:list). Confirm the ownership transfer to reassign them.', ['list' => $list]),
                'alert-type' => 'error',
            ]);
        }

        $created     = 0;
        $reactivated = 0;
        $transferred = 0;
        $displaced   = [];
        $now = Carbon::now();

        DB::transaction(function () use ($coachId, $teacherId, $courseId, $validBatchIds, $permissionType, $now, &$created, &$reactivated, &$transferred, &$displaced) {
            foreach ($validBatchIds as $batchId) {
                // Single active teacher per batch: deactivate any OTHER teacher's
                // active assignment for this batch (status flip — NO data lost;
                // the row survives for audit and can be re-granted later).
                $others = TeacherBatchAssignment::where('coach_id', $coachId)
                    ->where('batch_id', $batchId)
                    ->where('status', 'active')
                    ->where('teacher_id', '!=', $teacherId)
                    ->get();
                foreach ($others as $o) {
                    $o->update(['status' => 'inactive']);
                    $displaced[$o->teacher_id] = true;
                    $transferred++;
                }

                // Re-activate-or-insert for the chosen teacher. The unique index
                // makes a straight insert risky; this handles both first-time
                // grants and re-grants of a soft-removed assignment in one path.
                $existing = TeacherBatchAssignment::where('coach_id', $coachId)
                    ->where('teacher_id', $teacherId)
                    ->where('batch_id', $batchId)
                    ->first();

                if ($existing) {
                    if ($existing->status !== 'active') {
                        $existing->fill([
                            'course_id'       => $courseId,
                            'permission_type' => $permissionType,
                            'status'          => 'active',
                            'assigned_at'     => $now,
                        ])->save();
                        $reactivated++;
                    }
                    // else: already-active grant — skip silently,
                    // same (coach, teacher, batch) is harmless.
                    continue;
                }

                TeacherBatchAssignment::create([
                    'coach_id'        => $coachId,
                    'teacher_id'      => $teacherId,
                    'course_id'       => $courseId,
                    'batch_id'        => $batchId,
                    'permission_type' => $permissionType,
                    'status'          => 'active',
                    'assigned_at'     => $now,
                ]);
                $created++;
            }
        });

        // The chosen teacher's cache + every displaced teacher's cache are stale.
        TeacherBatchAssignment::forgetCacheFor($teacherId);
        foreach (array_keys($displaced) as $displacedTeacherId) {
            TeacherBatchAssignment::forgetCacheFor((int) $displacedTeacherId);
        }

        // 2026-05-26 (bug-doc C11) — email the teacher so they know about
        // their new batch assignments. Only fired when something actually
        // changed (created or reactivated) — silent skip on idempotent
        // re-saves. Wrapped in try/catch so a mail-server hiccup doesn't
        // void the DB transaction that already committed.
        if (($created + $reactivated) > 0) {
            try {
                $teacher = CoachStaff::find($teacherId);
                if ($teacher && ! empty($teacher->email)) {
                    $course  = Course::find($courseId);
                    $batches = CourseBatch::whereIn('id', $validBatchIds)->get();
                    $teacher->notify(new \App\Notifications\TeacherBatchAssignedToTeacher(
                        $course,
                        $batches->all(),
                        auth('web')->user(),
                        $permissionType
                    ));
                }
            } catch (\Throwable $e) {
                \Log::warning('teacher-batch-assign-notify-failed', [
                    'teacher_id' => $teacherId,
                    'course_id'  => $courseId,
                    'batch_ids'  => $validBatchIds,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        $bits = [];
        if ($created)     $bits[] = __(':n new', ['n' => $created]);
        if ($reactivated) $bits[] = __(':n re-activated', ['n' => $reactivated]);
        if ($transferred) $bits[] = __(':n transferred from another teacher', ['n' => $transferred]);
        $summary = $bits ? '('.implode(', ', $bits).')' : __('(no changes)');

        return redirect()->route($this->baseRoute)->with([
            'message'    => __('Assignments saved ').$summary,
            'alert-type' => 'success',
        ]);
    }

    public function edit(int $id): View
    {
        $coachId = $this->coachId();
        $assignment = TeacherBatchAssignment::with(['teacher:id,name,email', 'course:id,title', 'batch:id,title,course_id'])
            ->forCoach($coachId)
            ->where('id', $id)
            ->firstOrFail();

        $metadta = ['title' => 'Edit Assignment'];

        return view($this->viewBase.'.edit', compact('assignment', 'metadta'));
    }

    public function update(int $id, Request $request): RedirectResponse
    {
        $coachId = $this->coachId();
        $assignment = TeacherBatchAssignment::forCoach($coachId)
            ->where('id', $id)
            ->firstOrFail();

        $request->validate([
            'permission_type' => ['nullable', 'string', 'max:32'],
            'status'          => ['required', 'in:active,inactive'],
        ]);

        $displaced = [];

        DB::transaction(function () use ($assignment, $request, $coachId, &$displaced) {
            $assignment->fill([
                'permission_type' => $request->input('permission_type', 'manage') ?: 'manage',
                'status'          => $request->status,
                'assigned_at'     => $assignment->assigned_at ?: Carbon::now(),
            ])->save();

            // 2026-07-08 ("One teacher per batch") — enforce the invariant on the
            // edit path too: if this assignment is (re)activated, deactivate any
            // OTHER active teacher for the same batch (status flip — no data lost).
            if ($request->status === 'active') {
                $others = TeacherBatchAssignment::where('coach_id', $coachId)
                    ->where('batch_id', $assignment->batch_id)
                    ->where('status', 'active')
                    ->where('id', '!=', $assignment->id)
                    ->get();
                foreach ($others as $o) {
                    $o->update(['status' => 'inactive']);
                    $displaced[$o->teacher_id] = true;
                }
            }
        });

        TeacherBatchAssignment::forgetCacheFor($assignment->teacher_id);
        foreach (array_keys($displaced) as $displacedTeacherId) {
            TeacherBatchAssignment::forgetCacheFor((int) $displacedTeacherId);
        }

        return redirect()->route($this->baseRoute)->with([
            'message'    => $displaced
                ? __('Assignment updated — the batch was transferred from its previous teacher.')
                : __('Assignment updated'),
            'alert-type' => 'success',
        ]);
    }

    /**
     * Soft remove — flip status to 'inactive'. Row stays so the audit
     * trail is preserved and any future re-grant can re-use the row
     * (avoids unique-index churn).
     */
    public function destroy(int $id): RedirectResponse
    {
        $coachId = $this->coachId();
        $assignment = TeacherBatchAssignment::forCoach($coachId)
            ->where('id', $id)
            ->firstOrFail();

        $assignment->update(['status' => 'inactive']);
        TeacherBatchAssignment::forgetCacheFor($assignment->teacher_id);

        return redirect()->route($this->baseRoute)->with([
            'message'    => __('Assignment removed'),
            'alert-type' => 'success',
        ]);
    }
}
