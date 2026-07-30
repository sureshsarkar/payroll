<?php

namespace App\Http\Controllers\Frontend\Coach;

use App\Http\Controllers\Controller;
use App\Models\CoachStaff;
use App\Models\CoachStudentLink;
use App\Models\CourseBatch;
use App\Models\StudentBatchAssignment;
use App\Models\TeacherBatchAssignment;
use App\Models\User;
use App\Services\StudentBatchService;
use Illuminate\Http\Request;
use Modules\Order\app\Models\Enrollment;

/**
 * Coach-panel Student Batch Assignment / Reassignment (2026-07-15).
 *
 * Thin controller over StudentBatchService — assign a batch-less student, move a
 * student between batches of the SAME course, add an extra batch, and bulk-assign
 * from the student list. Every action is tenant-scoped (coach owns the student
 * via CoachStudentLink AND the batch via its course), permission-gated, and
 * (through the service) transaction-safe + audit-logged + notified.
 */
class CoachStudentBatchController extends Controller
{
    public function __construct(private StudentBatchService $svc)
    {
    }

    private function coachId(): int
    {
        return userAuth()->role === 'instructor' ? (int) userAuth()->id : (int) userAuth()->coach_id;
    }

    /** Same roster gate the student list/edit use — coach can only touch their own students. */
    private function ownedStudentOrFail(int $id): User
    {
        $rosterIds = CoachStudentLink::studentIdsForCoach($this->coachId());
        abort_unless(in_array($id, $rosterIds, true), 404);

        return User::where('id', $id)->where('role', 'student')->firstOrFail();
    }

    /** Real coach OR staff holding the given batch slug (or the broader student-edit). */
    private function authorise(string $slug): void
    {
        $u = userAuth();
        if ($u->role === 'instructor' && empty($u->coach_id)) {
            return; // real coach — full access
        }
        $staff = CoachStaff::find($u->id);
        $held  = $staff ? $staff->permissions->pluck('slug')->all() : [];
        abort_unless(in_array($slug, $held, true) || in_array('coach-students-edit', $held, true), 403,
            __('You are not allowed to manage batch assignments.'));
    }

    /**
     * JSON context for the "Manage batch" modal: for each course the student is
     * enrolled in (within this coach), the current batch(es) + the eligible
     * (active, same-course, not-current) batches with schedule / instructor /
     * capacity. Same-course only — the dropdown never crosses courses.
     */
    public function context(int $id)
    {
        $this->authorise('coach-students-batch-reassign');
        $student = $this->ownedStudentOrFail($id);
        $coachId = $this->coachId();

        // The student's enrollments in THIS coach's courses.
        $enrollments = Enrollment::where('user_id', $student->id)
            ->whereHas('course', fn ($q) => $q->where('instructor_id', $coachId)->orWhere('added_by', $coachId))
            ->with(['course:id,title', 'batch:id,title'])
            ->get();

        $courses = [];
        foreach ($enrollments->groupBy('course_id') as $courseId => $rows) {
            $courseId = (int) $courseId;
            $courseTitle = optional($rows->first()->course)->title ?? ('Course #' . $courseId);
            $currentIds  = $rows->pluck('batch_id')->filter()->map(fn ($v) => (int) $v)->unique()->values();

            $current = $rows->filter(fn ($e) => $e->batch_id)->map(fn ($e) => [
                'id' => (int) $e->batch_id, 'title' => optional($e->batch)->title ?? ('Batch #' . $e->batch_id),
            ])->unique('id')->values();

            $eligible = $this->svc->eligibleBatches($coachId, $courseId)
                ->reject(fn ($b) => $currentIds->contains((int) $b->id))
                ->map(fn ($b) => $this->batchCard($b))->values();

            $courses[] = [
                'course_id'    => $courseId,
                'course_title' => $courseTitle,
                'current'      => $current,
                'eligible'     => $eligible,
            ];
        }

        return response()->json([
            'student'      => ['id' => $student->id, 'name' => $student->name],
            'courses'      => $courses,
            'effective_at' => now()->format('d M Y, g:i A'),
        ]);
    }

    /** Human-readable batch card for the dropdown/summary. */
    private function batchCard(CourseBatch $b): array
    {
        // Short day labels + 12-hour times without seconds (corporate format).
        $days = is_array($b->days) ? implode(', ', array_map(fn ($d) => ucfirst(substr($d, 0, 3)), $b->days)) : '';
        $fmt  = fn ($t) => $t ? \Illuminate\Support\Carbon::parse($t)->format('g:i A') : '';
        $time = trim($fmt($b->start_time) . ($b->end_time ? ' – ' . $fmt($b->end_time) : ''));
        $teacherId = TeacherBatchAssignment::where('batch_id', $b->id)->where('status', 'active')->value('teacher_id');
        $instructor = $teacherId ? optional(User::find($teacherId))->name : null;

        return [
            'id'              => (int) $b->id,
            'title'           => $b->title,
            'schedule'        => trim($days . ($days && $time ? ' · ' : '') . $time),
            'instructor'      => $instructor,
            'status'          => $b->status,
            'capacity'        => $b->hasCapacityLimit() ? (int) $b->capacity : null,
            'seats_used'      => $b->seatsUsed(),
            'seats_remaining' => $b->hasCapacityLimit() ? $b->seatsRemaining() : null,
        ];
    }

    /**
     * Single student assign / move / add. `mode=move` requires a reason (spec);
     * `mode=add` (extra batch) reason optional. Server re-validates same-course,
     * tenant, active, capacity, duplicate.
     */
    public function reassign(Request $request, int $id)
    {
        $this->authorise('coach-students-batch-reassign');
        $student = $this->ownedStudentOrFail($id);
        $coachId = $this->coachId();

        $data = $request->validate([
            'new_batch_id' => 'required|integer',
            'mode'         => 'required|in:move,add',
            'from_batch_id'=> 'nullable|integer',
            'reason'       => 'nullable|string|max:500',
        ]);

        // Reason is MANDATORY on a move (remove-from-existing). Trim-validated.
        if ($data['mode'] === 'move' && trim((string) ($data['reason'] ?? '')) === '') {
            return $this->respond($request, ['ok' => false, 'message' => __('A reason is required when moving a student to another batch.')], 422);
        }

        $batch = CourseBatch::with('course:id,title,instructor_id,added_by')->find($data['new_batch_id']);
        if (! $batch) {
            return $this->respond($request, ['ok' => false, 'message' => __('The selected batch was not found.')], 404);
        }

        $res = $data['mode'] === 'add'
            ? $this->svc->addToBatch($coachId, $student->id, $batch, $data['reason'] ?? null, (int) userAuth()->id)
            : $this->svc->putIntoBatch($coachId, $student->id, $batch, $data['reason'] ?? null, (int) userAuth()->id, $data['from_batch_id'] ?? null);

        return $this->respond($request, $res, empty($res['ok']) ? 422 : 200);
    }

    /**
     * Bulk assign selected students into ONE batch (student list). Reports
     * successes + failures separately — never partially assigns silently.
     */
    public function bulkAssign(Request $request)
    {
        $this->authorise('coach-students-batch-assign');
        $coachId = $this->coachId();

        $data = $request->validate([
            'student_ids'   => 'required|array|min:1',
            'student_ids.*' => 'integer',
            'batch_id'      => 'required|integer',
            'reason'        => 'nullable|string|max:500',
        ]);

        $batch = CourseBatch::with('course:id,title,instructor_id,added_by')->find($data['batch_id']);
        if (! $batch) {
            return $this->respond($request, ['ok' => false, 'message' => __('The selected batch was not found.')], 404);
        }

        $rosterIds = CoachStudentLink::studentIdsForCoach($coachId);
        $assigned = 0;
        $results  = [];
        foreach (array_unique(array_map('intval', $data['student_ids'])) as $sid) {
            if (! in_array($sid, $rosterIds, true)) {
                $results[] = ['id' => $sid, 'ok' => false, 'message' => __('Not your student.')];
                continue;
            }
            $res = $this->svc->putIntoBatch($coachId, $sid, $batch, $data['reason'] ?? null, (int) userAuth()->id);
            if (! empty($res['ok'])) {
                $assigned++;
            }
            $results[] = ['id' => $sid, 'ok' => (bool) ($res['ok'] ?? false), 'message' => $res['message'] ?? ''];
        }

        return response()->json([
            'ok'       => true,
            'assigned' => $assigned,
            'failed'   => collect($results)->where('ok', false)->values(),
            'results'  => $results,
            'message'  => __('Student batch assignment updated successfully.'),
        ]);
    }

    private function respond(Request $request, array $payload, int $status = 200)
    {
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($payload, $status);
        }
        return redirect()->back()->with([
            'messege'    => $payload['message'] ?? '',
            'alert-type' => empty($payload['ok']) ? 'error' : 'success',
        ]);
    }
}
