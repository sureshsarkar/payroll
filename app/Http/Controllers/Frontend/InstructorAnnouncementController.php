<?php

namespace App\Http\Controllers\Frontend;

use App\Enums\RedirectType;
use App\Exceptions\AccessPermissionDeniedException;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AnnouncementAttachment;
use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\TeacherBatchAssignment;
use App\Traits\RedirectHelperTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class InstructorAnnouncementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    use RedirectHelperTrait;

    /**
     * 2026-07-07 — server-side RBAC. Previously the announcement endpoints
     * carried only `requires.membership`, so a staff member WITHOUT the
     * announcements permission could still open the list/create form and
     * POST to store/update/destroy directly — the buttons were hidden but the
     * routes were wide open. Enforce the same granular slugs the menu/buttons
     * use (checkPermissionView) at the controller so hidden === blocked.
     *
     * A real coach (role=instructor, coach_id NULL) passes every check inside
     * the `permission` middleware, so coach flows are unchanged. Denied web
     * requests render the in-panel Access-Denied card (errors/403); AJAX/JSON
     * requests get a 403 JSON response.
     */
    public function __construct()
    {
        $this->middleware('permission:announcements')->only(['index', 'show', 'batchesForCourse', 'downloadAttachment']);
        $this->middleware('permission:announcements-create')->only(['create', 'store']);
        $this->middleware('permission:announcements-edit')->only(['edit', 'update']);
        $this->middleware('permission:announcements-delete')->only(['destroy', 'destroyAttachment']);
    }

    /**
     * The coach this user is acting on behalf of: their own id if they ARE a coach,
     * or coach_id if they're a staff member. Announcements/courses belong to the coach,
     * not to individual staff members.
     */
    private function effectiveCoachId(): int
    {
        return userAuth()->role === 'instructor' ? userAuth()->id : userAuth()->coach_id;
    }

    /**
     * 2026-07-10 (New Changes for UI #11) — batch ids the acting user is
     * allowed to announce to, or NULL for a real coach (unbounded, every
     * batch in their tenant). For a STAFF member this is exactly the set of
     * batches the coach ASSIGNED to them (TeacherBatchAssignment); [] means
     * "staff with no assignments" (sees nothing). Used to scope the course +
     * batch dropdowns AND to validate submissions server-side, so a staff
     * member can neither see nor POST an announcement for an unassigned batch.
     */
    private function assignedBatchIdsOrNull(): ?array
    {
        return TeacherBatchAssignment::assignedBatchIdsFor((int) userAuth()->id);
    }

    /**
     * Course-dropdown query for create()/edit(): the coach's live/hybrid
     * courses (recorded excluded — no batches to target), further restricted
     * for STAFF to courses that have ≥1 batch assigned to them.
     */
    private function announcementCourseQuery(int $coachId)
    {
        $query = Course::select(['id', 'title'])
            ->where(function ($q) use ($coachId) {
                $q->where('instructor_id', $coachId)->orWhere('added_by', $coachId);
            })
            ->whereIn('type', ['live', 'hybrid'])
            ->orderBy('title');

        $assigned = $this->assignedBatchIdsOrNull();
        if ($assigned !== null) {
            $assignedCourseIds = CourseBatch::whereIn('id', $assigned ?: [0])
                ->pluck('course_id')->unique()->all();
            $query->whereIn('id', $assignedCourseIds ?: [0]);
        }

        return $query;
    }

    /**
     * Reject a submission (store/update) when a STAFF member targets a batch
     * they were not assigned. Returns a redirect-back on violation, or null
     * when allowed (coach = always allowed; staff = all batch ids must be in
     * their assigned set). Enforced server-side so a tampered request cannot
     * announce to an unassigned batch.
     */
    private function denyUnassignedBatches($batchIds)
    {
        $assigned = $this->assignedBatchIdsOrNull();
        if ($assigned === null) {
            return null; // real coach — unbounded within their tenant
        }
        $allowed = array_map('intval', $assigned ?: []);
        foreach ($batchIds as $bid) {
            if (! in_array((int) $bid, $allowed, true)) {
                return back()
                    ->withErrors(['batches' => __('You can only send announcements to batches assigned to you.')])
                    ->withInput();
            }
        }
        return null;
    }

    function index(Request $request)
    {
        $coachId = $this->effectiveCoachId();

        // Audit 2026-05-18 phase 2/3 — search + filter, plus read count.
        $query = Announcement::with([
                'course:id,title',
                'batch:id,title',
                'batches:id,title',
            ])
            ->withCount('readers')      // phase 3 — read receipts
            ->where('instructor_id', $coachId);

        if ($request->filled('q')) {
            $search = (string) $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('announcement', 'like', "%{$search}%");
            });
        }
        if ($request->filled('course_id')) {
            $query->where('course_id', (int) $request->course_id);
        }
        if ($request->filled('batch_id')) {
            // match either legacy batch_id or any pivot entry
            $bid = (int) $request->batch_id;
            $query->where(function ($q) use ($bid) {
                $q->where('batch_id', $bid)
                  ->orWhereHas('batches', fn ($b) => $b->where('course_batches.id', $bid));
            });
        }
        if ($request->filled('status') && in_array($request->status, ['active', 'inactive'], true)) {
            $query->where('status', $request->status);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('sent_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('sent_at', '<=', $request->date_to);
        }

        $announcements = $query->orderByDesc('sent_at')->orderByDesc('id')->paginate(20)->withQueryString();

        // Dropdown lists scoped to this coach's courses/batches.
        $courseList = Course::select('id', 'title')
            ->where(function ($q) use ($coachId) {
                $q->where('instructor_id', $coachId)->orWhere('added_by', $coachId);
            })
            ->orderBy('title')->get();

        $batchList = CourseBatch::select('id', 'title')
            ->whereIn('course_id', $courseList->pluck('id'))
            ->where('status', 'active')
            ->orderByDesc('start_date')
            ->get();

        return view('frontend.instructor-dashboard.announcement.index', compact(
            'announcements', 'courseList', 'batchList'
        ));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $coachId = $this->effectiveCoachId();
        $courses = $this->announcementCourseQuery($coachId)->get();
        // Audit 2026-05-18 — batches are fetched lazily by /instructor/get-batches/{course_id}
        // (existing route), so the create view just needs the courses dropdown.
        return view('frontend.instructor-dashboard.announcement.create', compact('courses'));
    }

    /**
     * Audit 2026-05-18 — JSON endpoint for the create/edit form to load
     * a coach's batches when a course is selected. Returns active batches
     * of the selected course, owned by the requesting coach.
     */
    public function batchesForCourse(int $courseId)
    {
        $coachId = $this->effectiveCoachId();

        // Guard: course must belong to this coach
        Course::where('id', $courseId)
            ->where(function ($q) use ($coachId) {
                $q->where('instructor_id', $coachId)->orWhere('added_by', $coachId);
            })
            ->firstOrFail();

        $batchesQuery = CourseBatch::where('course_id', $courseId)
            ->where('status', 'active');

        // 2026-07-10 (New Changes for UI #11) — staff only see the batches of
        // this course that the coach assigned to them. Coach ($assigned===null)
        // sees every active batch.
        $assigned = $this->assignedBatchIdsOrNull();
        if ($assigned !== null) {
            $batchesQuery->whereIn('id', $assigned ?: [0]);
        }

        $batches = $batchesQuery
            ->orderByDesc('start_date')
            ->get(['id', 'title', 'start_date', 'end_date']);

        return response()->json(['batches' => $batches]);
    }

    /**
     * Store a newly created resource in storage.
     */
    function store(Request $request)
    {
        // Audit 2026-05-18 — multi-batch supported via 'batches[]'.
        //   - 'batch'    (legacy, single id) — still accepted.
        //   - 'batches'  (preferred, array of ids) — multi-select.
        // Empty/none → course-wide.
        // Audit 2026-05-18 phase 3 — scheduled_at can be a future timestamp
        // for "deliver later"; if blank, deliver immediately.
        //
        // Audit 2026-05-20 — audience_type added. Coaches can ONLY create
        // batch_specific rows (not all_students). The 'audience_type'
        // field is optional in the request and defaults to batch_specific;
        // any attempt to send all_students from this controller is
        // rejected with 403 below.
        $request->validate([
            'audience_type' => 'nullable|in:all_students,batch_specific',
            'course'        => 'required_unless:audience_type,all_students|nullable|integer',
            'batch'         => 'nullable|integer',
            'batches'       => 'nullable|array',
            'batches.*'     => 'integer',
            'title'         => ['required', 'max:255'],
            // FT-VAL-10 (web extension, 2026-05-28) — cap body to 20KB.
            // See API equivalents on Coach/StudentApi DashboardController
            // for the broadcast-DOS rationale.
            'announcement'  => ['required', 'string', 'max:20000'],
            'scheduled_at'  => 'nullable|date',
            'is_pinned'     => 'nullable',
            'attachments'   => 'nullable|array|max:5',
            'attachments.*' => 'file|max:8192|mimes:pdf,jpg,jpeg,png,gif,webp,doc,docx,xls,xlsx,ppt,pptx,txt',
        ], [
            'course.required_unless' => 'Course is required for batch-wise announcements',
            'announcement.required' => 'Announcement is required',
            'title.required'        => 'Title is required',
            'title.max'             => 'Title should not be more than 255 characters',
        ]);

        // Role gate: coaches cannot publish to all students. Hard 403,
        // even if the request manages to set audience_type=all_students
        // (e.g. via crafted POST).
        $audienceType = $request->input('audience_type', 'batch_specific');
        if ($audienceType === 'all_students') {
            abort(403, 'Coaches cannot publish all-students announcements. Ask an admin.');
        }

        $coachId = $this->effectiveCoachId();

        Course::where('id', $request->course)
            ->where(function ($q) use ($coachId) {
                $q->where('instructor_id', $coachId)->orWhere('added_by', $coachId);
            })
            ->firstOrFail();

        $batchIds = collect($request->input('batches', []))
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id);
        if ($request->filled('batch')) {
            $batchIds = $batchIds->push((int) $request->batch);
        }
        $batchIds = $batchIds->unique()->values();

        if ($batchIds->isNotEmpty()) {
            $validCount = CourseBatch::whereIn('id', $batchIds)
                ->where('course_id', $request->course)
                ->count();
            if ($validCount !== $batchIds->count()) {
                return back()->withErrors(['batches' => 'One or more batches do not belong to this course.'])->withInput();
            }
        }

        // 2026-07-10 (New Changes for UI #11) — staff may only announce to
        // their assigned batches (server-side; blocks tampered batch ids).
        if ($deny = $this->denyUnassignedBatches($batchIds)) {
            return $deny;
        }

        // Scheduling: scheduled_at = chosen time (defaults to now).
        // delivered_at = NULL if scheduled in the future, else now.
        $scheduledAt = $request->filled('scheduled_at')
            ? \Carbon\Carbon::parse($request->scheduled_at)
            : now();
        $isScheduledForLater = $scheduledAt->greaterThan(now()->addMinute());  // 1-min slop
        $deliveredAt = $isScheduledForLater ? null : now();

        $announcement = Announcement::create([
            'instructor_id' => $coachId,
            'sender_role'   => 'instructor',    // Audit 2026-05-20
            'audience_type' => 'batch_specific',// Audit 2026-05-20 — coach always batch-scoped
            'course_id'     => $request->course,
            'batch_id'      => $batchIds->first(),
            'title'         => $request->title,
            'announcement'  => $request->announcement,
            'status'        => 'active',
            'is_pinned'     => $request->boolean('is_pinned'),     // phase 3
            'sent_at'       => $scheduledAt,  // legacy alias
            'scheduled_at'  => $scheduledAt,
            'delivered_at'  => $deliveredAt,
        ]);

        if ($batchIds->isNotEmpty()) {
            $announcement->batches()->sync($batchIds->all());
        }

        // Audit 2026-05-18 phase 3 — handle uploaded attachments.
        $this->saveAttachments($request, $announcement);

        // Only fan out immediately if NOT scheduled for later.
        if (!$isScheduledForLater) {
            try {
                app(\App\Services\AnnouncementNotifier::class)->fanOut($announcement);
            } catch (\Throwable $e) {
                \Log::warning('Announcement fanout failed', [
                    'announcement_id' => $announcement->id,
                    'error'           => $e->getMessage(),
                ]);
            }
        }

        return $this->redirectWithMessage(RedirectType::CREATE->value, 'instructor.announcements.index');
    }

    /**
     * Bug fix 2026-05-20 — Route::resource() auto-binds a `show` route
     * (GET /instructor/announcements/{id}) but the coach-side panel
     * never built a detail view; clicking the link from a stale
     * bookmark / shared URL used to 500 with "Method show does not exist".
     *
     * Redirect to edit instead — that view shows the same data with
     * the IDOR scope already enforced (instructor_id match in edit()).
     * The admin side has its own real show view at admin.announcements.show.
     */
    public function show(string $id)
    {
        return redirect()->route('instructor.announcements.edit', $id);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $coachId = $this->effectiveCoachId();
        $announcement = Announcement::where('instructor_id', $coachId)->where('id', $id)->firstOrFail();
        $courses = $this->announcementCourseQuery($coachId)->get();
        return view('frontend.instructor-dashboard.announcement.edit', compact('courses', 'announcement'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'course'        => 'required|integer',
            'batch'         => 'nullable|integer',
            'batches'       => 'nullable|array',
            'batches.*'     => 'integer',
            'title'         => ['required', 'max:255'],
            // FT-VAL-10 (web extension, 2026-05-28) — cap body to 20KB.
            // See API equivalents on Coach/StudentApi DashboardController
            // for the broadcast-DOS rationale.
            'announcement'  => ['required', 'string', 'max:20000'],
            'is_pinned'     => 'nullable',
            'attachments'   => 'nullable|array|max:5',
            'attachments.*' => 'file|max:8192|mimes:pdf,jpg,jpeg,png,gif,webp,doc,docx,xls,xlsx,ppt,pptx,txt',
        ], [
            'course.required'       => 'Course is required',
            'announcement.required' => 'Announcement is required',
            'title.required'        => 'Title is required',
            'title.max'             => 'Title should not be more than 255 characters',
        ]);
        $coachId = $this->effectiveCoachId();
        $announcement = Announcement::where('instructor_id', $coachId)->where('id', $id)->firstOrFail();

        // Verify the new course (if changed) belongs to this coach.
        Course::where('id', $request->course)
            ->where(function ($q) use ($coachId) {
                $q->where('instructor_id', $coachId)->orWhere('added_by', $coachId);
            })
            ->firstOrFail();

        // Validate batches list (multi or single legacy field)
        $batchIds = collect($request->input('batches', []))
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id);
        if ($request->filled('batch')) {
            $batchIds = $batchIds->push((int) $request->batch);
        }
        $batchIds = $batchIds->unique()->values();

        if ($batchIds->isNotEmpty()) {
            $validCount = CourseBatch::whereIn('id', $batchIds)
                ->where('course_id', $request->course)
                ->count();
            if ($validCount !== $batchIds->count()) {
                return back()->withErrors(['batches' => 'One or more batches do not belong to this course.'])->withInput();
            }
        }

        // 2026-07-10 (New Changes for UI #11) — staff may only announce to
        // their assigned batches (server-side; blocks tampered batch ids).
        if ($deny = $this->denyUnassignedBatches($batchIds)) {
            return $deny;
        }

        $announcement->update([
            'course_id'    => $request->course,
            'batch_id'     => $batchIds->first(),  // first = primary (denormalized)
            'title'        => $request->title,
            'announcement' => $request->announcement,
            'is_pinned'    => $request->boolean('is_pinned'),  // phase 3
        ]);

        // sync() replaces the entire pivot set (idempotent).
        $announcement->batches()->sync($batchIds->all());

        // Append (don't replace) attachments on update.
        $this->saveAttachments($request, $announcement);

        return $this->redirectWithMessage(RedirectType::UPDATE->value, 'instructor.announcements.index');
    }

    /**
     * Audit 2026-05-18 phase 3 — persist uploaded files to the
     * announcements/ directory and create AnnouncementAttachment rows.
     */
    private function saveAttachments(Request $request, Announcement $announcement): void
    {
        if (!$request->hasFile('attachments')) return;

        foreach ($request->file('attachments') as $file) {
            if (!$file || !$file->isValid()) continue;

            // V1 fix (2026-06-16) — store on the PRIVATE disk (outside the web
            // root) so the attachment can never be fetched by a direct URL;
            // served only via the gated downloadAttachment() route below.
            $stored = \App\Support\PrivateMedia::store($file, 'announcements/'.$announcement->id);

            AnnouncementAttachment::create([
                'announcement_id' => $announcement->id,
                'filename'        => $file->getClientOriginalName(),
                'path'            => $stored,
                'mime_type'       => $file->getMimeType() ?: 'application/octet-stream',
                'size_bytes'      => $file->getSize() ?: 0,
            ]);
        }
    }

    /**
     * Coach deletes one attachment from an announcement they own.
     */
    public function destroyAttachment(int $attachmentId)
    {
        $coachId = $this->effectiveCoachId();
        $att = AnnouncementAttachment::with('announcement')->findOrFail($attachmentId);

        if (!$att->announcement || (int) $att->announcement->instructor_id !== (int) $coachId) {
            throw new AccessPermissionDeniedException();
        }

        // Delete the stored file (best-effort; private disk + legacy fallback).
        \App\Support\PrivateMedia::delete($att->path);

        $att->delete();
        return response()->json(['status' => 'success', 'message' => __('Attachment removed')]);
    }

    /**
     * Authorized download endpoint. Both coaches and enrolled students
     * can access; admin can access via the admin-side equivalent (not
     * built here).
     */
    public function downloadAttachment(int $attachmentId)
    {
        $user = userAuth();
        $att = AnnouncementAttachment::with('announcement')->findOrFail($attachmentId);
        $ann = $att->announcement;

        $allowed = false;
        // Coach who owns the announcement
        $coachId = $user->role === 'instructor' ? $user->id : ($user->coach_id ?? null);
        if ($coachId && (int) $ann->instructor_id === (int) $coachId) {
            $allowed = true;
        }
        // Student enrolled in course (+ batch if scoped)
        // 2026-06-02 — membership is the batch/course enrollment row, NOT
        // has_access=1. A fee-pending member who can SEE the announcement must
        // also be able to open its attachment. Final visibility is still gated
        // by visibleToBatchStudent (course/batch + status=active), so this does
        // not over-expose — it only stops blocking unpaid batch members.
        if (!$allowed) {
            $enrolled = \Modules\Order\app\Models\Enrollment::where('user_id', $user->id)
                ->where('course_id', $ann->course_id)
                ->exists();
            if ($enrolled) {
                // Re-use visibility scope
                $visible = Announcement::visibleToBatchStudent(
                    $ann->course_id,
                    (int) (\Modules\Order\app\Models\Enrollment::where('user_id', $user->id)
                        ->where('course_id', $ann->course_id)
                        ->value('batch_id') ?? 0) ?: null
                )->where('announcements.id', $ann->id)->exists();
                $allowed = $visible;
            }
        }

        if (!$allowed) {
            throw new AccessPermissionDeniedException();
        }

        // V1 fix — serve from the PRIVATE disk (with legacy fallback). The
        // access gate above (coach-ownership OR enrollment + batch visibility)
        // is the ONLY way to reach the bytes; no direct URL exists.
        return \App\Support\PrivateMedia::download($att->path, $att->filename);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
       $coachId = $this->effectiveCoachId();
       $announcement = Announcement::where('instructor_id', $coachId)->where('id', $id)->firstOrFail();
       $announcement->delete();
       return response()->json(['status' => 'success','message' => 'Announcement deleted successfully']);
    }
}
