<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Admin-side oversight of coach announcements.
 *
 * Audit 2026-05-18 — pairs with the new batch-scoped announcement workflow.
 * The coach owns creation/edit (Frontend\InstructorAnnouncementController);
 * admin's role here is read + filter + activate/deactivate + delete.
 *
 * Permissions seeded by migration 2026_05_18_140200_seed_announcement_admin_permissions:
 *   - announcement.view
 *   - announcement.toggle-status
 *   - announcement.delete
 */
class AnnouncementController extends Controller
{
    /**
     * Filterable listing: batch_id, instructor_id (coach), course_id,
     * status, date range (sent_at).
     */
    public function index(Request $request)
    {
        checkAdminHasPermissionAndThrowException('announcement.view');

        $query = Announcement::query()
            ->with([
                'course:id,title',
                'batch:id,title,start_date,end_date',
                'batches:id,title',  // Audit 2026-05-18 — multi-batch pivot
                'instructor:id,name,email',
            ]);

        if ($request->filled('course_id')) {
            $query->where('course_id', (int) $request->course_id);
        }
        if ($request->filled('batch_id')) {
            $query->where('batch_id', (int) $request->batch_id);
        }
        if ($request->filled('instructor_id')) {
            $query->where('instructor_id', (int) $request->instructor_id);
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
        if ($request->filled('q')) {
            $search = (string) $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('announcement', 'like', "%{$search}%");
            });
        }

        $announcements = $query
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        // Filter dropdown data
        $courses     = Course::select('id', 'title')->orderBy('title')->limit(500)->get();
        $coaches     = User::where('role', 'instructor')->select('id', 'name', 'email')->orderBy('name')->limit(500)->get();
        $batchOptions = CourseBatch::select('id', 'title')->where('status', 'active')->orderByDesc('start_date')->limit(500)->get();

        // Headline counters (respect current filter context — without paging)
        $countsBase = clone $query;
        $totalActive   = (clone $countsBase)->where('status', 'active')->count();
        $totalInactive = (clone $countsBase)->where('status', 'inactive')->count();

        return view('admin.announcements.index', compact(
            'announcements', 'courses', 'coaches', 'batchOptions',
            'totalActive', 'totalInactive'
        ));
    }

    /**
     * Read-only detail page.
     */
    public function show(int $id)
    {
        checkAdminHasPermissionAndThrowException('announcement.view');

        $announcement = Announcement::with([
                'course:id,title',
                'batch:id,title,start_date,end_date',
                'batches:id,title,start_date,end_date',  // Audit 2026-05-18
                'instructor:id,name,email',
            ])
            ->findOrFail($id);

        return view('admin.announcements.show', compact('announcement'));
    }

    /**
     * Activate / deactivate (toggle). Returns JSON for AJAX-driven row buttons.
     */
    public function toggleStatus(int $id)
    {
        checkAdminHasPermissionAndThrowException('announcement.toggle-status');

        $announcement = Announcement::findOrFail($id);
        $announcement->status = $announcement->status === 'active' ? 'inactive' : 'active';
        $announcement->save();

        return response()->json([
            'status'  => 'success',
            'message' => __('Announcement status updated'),
            'data'    => [
                'id'     => $announcement->id,
                'status' => $announcement->status,
            ],
        ]);
    }

    public function destroy(int $id)
    {
        checkAdminHasPermissionAndThrowException('announcement.delete');

        $announcement = Announcement::findOrFail($id);
        $announcement->delete();

        return response()->json([
            'status'  => 'success',
            'message' => __('Announcement deleted successfully'),
        ]);
    }

    /**
     * Audit 2026-05-20 — admin can now CREATE announcements.
     * Two audience types:
     *   - all_students   → reaches every student (admin-only)
     *   - batch_specific → admin picks any batch on the platform
     */
    public function create()
    {
        // FT-IDOR-2 fix (2026-05-27) — was `announcement.view` (READ).
        // Now gated by `announcement.store` so read-only sub-admins
        // can't reach the create form. Seeded by migration
        // 2026_05_27_150000_seed_announcement_store_update_permissions.
        checkAdminHasPermissionAndThrowException('announcement.store');
        $courses = Course::select('id', 'title')->orderBy('title')->limit(500)->get();
        return view('admin.announcements.create', compact('courses'));
    }

    public function store(Request $request)
    {
        // FT-IDOR-2 fix — was `announcement.view`. Announcements fan out
        // as push + email to every student when audience_type=all_students;
        // a read-only sub-admin must not be able to broadcast.
        checkAdminHasPermissionAndThrowException('announcement.store');

        $request->validate([
            'title'         => 'required|string|max:255',
            // FT-VAL-10 (admin sites, 2026-05-28) — cap body to 20KB.
            // For audience_type=all_students this fans out to EVERY
            // student on the platform — DOS amplification if oversized.
            'announcement'  => 'required|string|max:20000',
            'audience_type' => 'required|in:all_students,batch_specific',
            'course_id'     => 'required_if:audience_type,batch_specific|nullable|exists:courses,id',
            'batches'       => 'required_if:audience_type,batch_specific|nullable|array|min:1',
            'batches.*'     => 'integer|exists:course_batches,id',
            'status'        => 'required|in:active,inactive',
            'scheduled_at'  => 'nullable|date',
            'is_pinned'     => 'nullable',
        ], [
            'title.required'                 => __('Title is required'),
            'announcement.required'          => __('Message is required'),
            'audience_type.required'         => __('Audience type is required'),
            'course_id.required_if'          => __('Course is required for batch-wise announcements'),
            'batches.required_if'            => __('Pick at least one batch'),
        ]);

        $audienceType = $request->input('audience_type');
        $scheduledAt  = $request->filled('scheduled_at')
            ? \Carbon\Carbon::parse($request->scheduled_at)
            : now();
        $deliveredAt = $scheduledAt->greaterThan(now()->addMinute()) ? null : now();

        $batchIds = collect($request->input('batches', []))->map(fn ($id) => (int) $id)->unique()->values();

        // Verify batches all belong to the chosen course if batch-specific.
        if ($audienceType === 'batch_specific' && $batchIds->isNotEmpty()) {
            $validCount = CourseBatch::whereIn('id', $batchIds)
                ->where('course_id', $request->course_id)
                ->count();
            if ($validCount !== $batchIds->count()) {
                return back()->withErrors([
                    'batches' => __('One or more batches do not belong to this course.'),
                ])->withInput();
            }
        }

        $announcement = Announcement::create([
            // For audit/listing we still set instructor_id to a coach if
            // batch-specific (the batch's parent course's coach), so
            // existing coach-side lists keep working. For all_students,
            // we leave it as the acting admin's id (admins use the
            // 'admins' table — but the column accepts any user id;
            // sender_role distinguishes).
            'instructor_id' => $this->resolveInstructorIdForAdminAnnouncement($audienceType, $request),
            'sender_role'   => 'admin',
            'audience_type' => $audienceType,
            'course_id'     => $audienceType === 'all_students' ? null : $request->course_id,
            'batch_id'      => $batchIds->first(),
            'title'         => $request->title,
            'announcement'  => $request->announcement,
            'status'        => $request->status,
            'is_pinned'     => $request->boolean('is_pinned'),
            'sent_at'       => $scheduledAt,
            'scheduled_at'  => $scheduledAt,
            'delivered_at'  => $deliveredAt,
        ]);

        if ($audienceType === 'batch_specific' && $batchIds->isNotEmpty()) {
            $announcement->batches()->sync($batchIds->all());
        }

        return redirect()->route('admin.announcements.index')->with([
            'messege'    => __('Announcement created'),
            'alert-type' => 'success',
        ]);
    }

    public function edit(int $id)
    {
        // FT-IDOR-2 fix — was `announcement.view`. Edit form is a write
        // surface; gate on `announcement.update`.
        checkAdminHasPermissionAndThrowException('announcement.update');
        $announcement = Announcement::with('batches')->findOrFail($id);
        $courses = Course::select('id', 'title')->orderBy('title')->limit(500)->get();
        $batches = $announcement->course_id
            ? CourseBatch::where('course_id', $announcement->course_id)->get(['id', 'title', 'course_id'])
            : collect();
        return view('admin.announcements.edit', compact('announcement', 'courses', 'batches'));
    }

    public function update(Request $request, int $id)
    {
        // FT-IDOR-2 fix — was `announcement.view`. Read-only sub-admins
        // must not be able to mutate an announcement's audience or content.
        checkAdminHasPermissionAndThrowException('announcement.update');

        $announcement = Announcement::findOrFail($id);

        $request->validate([
            'title'         => 'required|string|max:255',
            // FT-VAL-10 (admin sites, 2026-05-28) — cap body to 20KB.
            // For audience_type=all_students this fans out to EVERY
            // student on the platform — DOS amplification if oversized.
            'announcement'  => 'required|string|max:20000',
            'audience_type' => 'required|in:all_students,batch_specific',
            'course_id'     => 'required_if:audience_type,batch_specific|nullable|exists:courses,id',
            'batches'       => 'required_if:audience_type,batch_specific|nullable|array|min:1',
            'batches.*'     => 'integer|exists:course_batches,id',
            'status'        => 'required|in:active,inactive',
        ]);

        $audienceType = $request->input('audience_type');
        $batchIds = collect($request->input('batches', []))->map(fn ($id) => (int) $id)->unique()->values();

        $announcement->update([
            'audience_type' => $audienceType,
            'course_id'     => $audienceType === 'all_students' ? null : $request->course_id,
            'batch_id'      => $batchIds->first(),
            'title'         => $request->title,
            'announcement'  => $request->announcement,
            'status'        => $request->status,
        ]);

        if ($audienceType === 'batch_specific' && $batchIds->isNotEmpty()) {
            $announcement->batches()->sync($batchIds->all());
        } else {
            $announcement->batches()->sync([]);
        }

        return redirect()->route('admin.announcements.index')->with([
            'messege'    => __('Announcement updated'),
            'alert-type' => 'success',
        ]);
    }

    /**
     * Reverse-AJAX helper: admin picks course → return that course's batches.
     */
    public function batchesForCourse(int $courseId)
    {
        checkAdminHasPermissionAndThrowException('announcement.view');
        $batches = CourseBatch::where('course_id', $courseId)
            ->orderBy('title')
            ->get(['id', 'title']);
        return response()->json(['batches' => $batches]);
    }

    /**
     * Pick a sensible instructor_id for the row even though admins
     * create it. For batch-specific: use the parent course's coach.
     * For all_students: use the first admin's User-table id (any
     * non-null bigint works since sender_role distinguishes the
     * audit trail).
     */
    private function resolveInstructorIdForAdminAnnouncement(string $audienceType, Request $request): int
    {
        if ($audienceType === 'batch_specific' && $request->filled('course_id')) {
            $course = Course::find($request->course_id);
            if ($course) {
                return (int) ($course->instructor_id ?? $course->added_by ?? 0)
                    ?: (int) (User::where('role', 'instructor')->value('id') ?? 1);
            }
        }
        // Fallback for all_students or missing course: first instructor.
        return (int) (User::where('role', 'instructor')->value('id') ?? 1);
    }
}
