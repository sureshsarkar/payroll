<?php

namespace App\Http\Controllers\Frontend\Coach;

use App\Http\Controllers\Controller;
use App\Models\LandingPageEnquiry;
use App\Models\TeacherBatchAssignment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LandingPageEnquiryController extends Controller
{
    protected $pageName;

    public function __construct(LandingPageEnquiry $model)
    {
     
        $this->model = $model;
        $this->admin_base_url = 'instructor.landing-page-enquiry.index';
        $this->admin_view = 'frontend.instructor-dashboard.landing-page-enquiry';
        $this->admin_error_view = 'errors.403';
        $this->pageName = 'landing-page-enquiry';
    }

    /**
     * Allowed sort columns. Mapped explicitly to avoid building an
     * ORDER BY from raw request input (SQL injection vector if we used
     * $request->input('sort') directly in orderBy()).
     */
    private const SORTABLE = [
        'created_at' => 'created_at',
        'name'       => 'first_name',
        'status'     => 'status',
        'email'      => 'email',
    ];

    public function index(\Illuminate\Http\Request $request): View
    {
        // FT-IDOR-10 fix (2026-05-28) — was `$flag = 1;` hard-coded
        // bypass with the real call commented out. Every other method
        // in this class (create / store / edit / update / destroy)
        // calls checkPermission($this->pageName) and returns the
        // error view on flag != 1; index() was the only outlier.
        //
        // Effect of the bypass: a coach-staff user without the
        // `landing-page-enquiry` permission slug could still list ALL
        // of their coach's enquiries (containing lead names, emails,
        // phones, notes) — a permission-boundary leak inside the same
        // coach tenant. Cross-tenant IDOR is separately prevented by
        // the `where('coach_id', $coachId)` scope further down, so
        // this is a within-tenant least-privilege issue, not a
        // tenant-isolation break.
        $flag = checkPermission($this->pageName);
        if ($flag !== 1) {
            return view($this->admin_error_view);
        }

        $coachId = (userAuth()->role !== 'instructor') ? userAuth()->coach_id : userAuth()->id;

        // 2026-07-10 (New Changes for UI #8.1) — staff visibility is now driven
        // by explicit ASSIGNMENT. A staff member sees only enquiries the coach
        // assigned to them (assigned_to = staff id); coaches see every lead in
        // their tenant. This replaces the old course-batch heuristic — leads are
        // now surfaced to the person the coach actually handed them to, and the
        // same scope is reused for the list, the stats banner, export, kanban and
        // bulk actions via $applyStaffScope() below.

        // -------- Filters: q / status / from / to --------
        // Wrapped in a closure so we can apply the SAME filter set twice
        // — once for the paginated list, once for the stats banner — and
        // know the numbers in the banner reflect what the user is filtering by.
        $applyFilters = function ($query) use ($request) {
            $q = trim((string) $request->query('q', ''));
            if ($q !== '') {
                // Multi-column fuzzy across the fields a coach would type
                // ("rohan" / "rohan@" / partial phone). Wrapped in a
                // sub-where so this doesn't OR-leak past the coach_id scope.
                $query->where(function ($w) use ($q) {
                    $like = '%' . $q . '%';
                    $w->where('first_name', 'like', $like)
                      ->orWhere('last_name',  'like', $like)
                      ->orWhere('email',      'like', $like)
                      ->orWhere('phone',      'like', $like)
                      ->orWhere('service',    'like', $like);
                });
            }
            $status = (string) $request->query('status', '');
            if ($status !== '' && in_array($status, LandingPageEnquiry::validStatuses(), true)) {
                $query->where('status', $status);
            }
            $from = $request->query('from');
            if ($from && strtotime($from)) {
                $query->where('created_at', '>=', date('Y-m-d 00:00:00', strtotime($from)));
            }
            $to = $request->query('to');
            if ($to && strtotime($to)) {
                $query->where('created_at', '<=', date('Y-m-d 23:59:59', strtotime($to)));
            }
            return $query;
        };

        // -------- Sort (allowlist-guarded) --------
        $sortKey = (string) $request->query('sort', 'created_at');
        $sortCol = self::SORTABLE[$sortKey] ?? 'created_at';
        $sortDir = strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        // Apply the staff-assignment scope consistently across the paginated
        // query AND the stats banner (so the banner counts what the staff can
        // actually see). Coaches are unaffected. Chainable like $applyFilters.
        $applyStaffScope = fn ($query) => $this->scopeAssignedForStaff($query);

        // -------- Paginated rows --------
        $enquiry = $this->model::where('coach_id', $coachId);
        $applyStaffScope($enquiry);
        $applyFilters($enquiry);
        $enquiry = $enquiry->orderBy($sortCol, $sortDir)
            ->paginate(10)
            ->withQueryString();   // preserves filter+sort on page links

        // -------- Stats banner (counts respect the active filters) --------
        $statsQuery = $this->model::where('coach_id', $coachId);
        $applyStaffScope($statsQuery);
        $applyFilters($statsQuery);
        $countsByStatus = (clone $statsQuery)
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->all();

        // Normalize legacy 'published' rows into 'new' for the stat tile
        // — matches the accessor on the model so display is consistent.
        if (isset($countsByStatus['published'])) {
            $countsByStatus[LandingPageEnquiry::STATUS_NEW] =
                ($countsByStatus[LandingPageEnquiry::STATUS_NEW] ?? 0) + $countsByStatus['published'];
            unset($countsByStatus['published']);
        }

        $total       = array_sum($countsByStatus);
        $wonCount    = (int) ($countsByStatus[LandingPageEnquiry::STATUS_WON] ?? 0);
        $lostCount   = (int) ($countsByStatus[LandingPageEnquiry::STATUS_LOST] ?? 0);
        $closed      = $wonCount + $lostCount;
        $winRate     = $closed > 0 ? round(($wonCount / $closed) * 100, 1) : 0.0;

        // 2026-06-12 Phase 2 — value-weighted funnel metrics (respect active
        // filters). Pipeline value = sum of OPEN leads' deal value; won value =
        // closed-won revenue; conversion = won / ALL leads (vs win_rate which is
        // won / closed only).
        $openStatuses  = [
            LandingPageEnquiry::STATUS_NEW, LandingPageEnquiry::STATUS_CONTACTED,
            LandingPageEnquiry::STATUS_FOLLOWUP, LandingPageEnquiry::STATUS_QUALIFIED,
            LandingPageEnquiry::STATUS_PROPOSAL, 'published',
        ];
        $pipelineValue = (float) (clone $statsQuery)->whereIn('status', $openStatuses)->sum('value');
        $wonValue      = (float) (clone $statsQuery)->where('status', LandingPageEnquiry::STATUS_WON)->sum('value');
        $conversion    = $total > 0 ? round(($wonCount / $total) * 100, 1) : 0.0;

        // Leads in the last 7 days — uses the unfiltered base scope so the
        // "fresh leads" stat doesn't change as the user filters by status.
        $newThisWeek = (int) $applyStaffScope($this->model::where('coach_id', $coachId))
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $stats = [
            'total'          => $total,
            'new_this_week'  => $newThisWeek,
            'win_rate'       => $winRate,
            'conversion'     => $conversion,
            'pipeline_value' => $pipelineValue,
            'won_value'      => $wonValue,
            'by_status'      => $countsByStatus,
        ];

        $metadta['title']  = 'Landing Page Enquiry';
        $statusOptions     = LandingPageEnquiry::statusOptions();

        // Echo back the request so the view can show the active filter
        // values without re-reading the request from Blade.
        $filters = [
            'q'      => (string) $request->query('q', ''),
            'status' => (string) $request->query('status', ''),
            'from'   => (string) $request->query('from', ''),
            'to'     => (string) $request->query('to', ''),
            'sort'   => $sortKey,
            'dir'    => $sortDir,
        ];

        return view($this->admin_view . '.index', compact(
            'enquiry', 'metadta', 'stats', 'statusOptions', 'filters'
        ));
    }

    /**
     * AJAX endpoint — in-line quick status change from the index table.
     * Returns the new badge HTML so the front-end can patch the row
     * without a full page reload. IDOR-gated via findOwnedEnquiryOrFail().
     */
    public function updateStatus(\Illuminate\Http\Request $request, $id): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'status'      => ['required', 'string', \Illuminate\Validation\Rule::in(LandingPageEnquiry::validStatuses())],
            'lost_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $enquiry = $this->findOwnedEnquiryOrFail($id);
        $previousStatus = $enquiry->status;
        $newStatus      = $request->input('status');
        $enquiry->status = $newStatus;
        // 2026-06-12 Phase 2 — stamp the conversion date once, and capture the
        // structured lost reason for win/loss analytics.
        if ($newStatus === LandingPageEnquiry::STATUS_WON && empty($enquiry->won_at)) {
            $enquiry->won_at = now();
        }
        if ($newStatus === LandingPageEnquiry::STATUS_LOST) {
            $enquiry->lost_reason = $request->input('lost_reason');
        }
        $enquiry->save();

        // Tier-3 audit trail: record the transition. Skipped when no
        // actual change so a stutter-click doesn't fill the log with
        // identical rows.
        if ($previousStatus !== $newStatus) {
            \App\Models\LeadAuditLog::create([
                'enquiry_id' => $enquiry->id,
                'actor_id'   => userAuth()->id,
                'event'      => 'status_changed',
                'from_value' => (string) $previousStatus,
                'to_value'   => (string) $newStatus,
                'created_at' => now(),
            ]);
        }

        $badge = $enquiry->statusBadge();
        return response()->json([
            'ok'     => true,
            'status' => $enquiry->status,
            'badge'  => [
                'label' => $badge['label'],
                'color' => $badge['color'],
                'icon'  => $badge['icon'],
            ],
        ]);
    }

    /**
     * CSV export of the filtered enquiry list. Streamed via streamDownload
     * — large sales pipelines (10k+ leads) shouldn't OOM the request.
     * Same filter set as index() so what you export === what you see.
     */
    public function export(\Illuminate\Http\Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $coachId = (userAuth()->role !== 'instructor') ? userAuth()->coach_id : userAuth()->id;

        $query = $this->model::where('coach_id', $coachId);
        // 2026-07-10 (New Changes for UI #8.1) — staff export only their
        // assigned leads (what they see === what they export).
        $this->scopeAssignedForStaff($query);

        // Apply the same filters as index() — copy/paste to keep the
        // export self-contained. Inlining is OK because there are only
        // two call sites and a shared trait would be heavier than the
        // duplication saves.
        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $like = '%' . $q . '%';
                $w->where('first_name', 'like', $like)
                  ->orWhere('last_name',  'like', $like)
                  ->orWhere('email',      'like', $like)
                  ->orWhere('phone',      'like', $like)
                  ->orWhere('service',    'like', $like);
            });
        }
        $status = (string) $request->query('status', '');
        if ($status !== '' && in_array($status, LandingPageEnquiry::validStatuses(), true)) {
            $query->where('status', $status);
        }
        $from = $request->query('from');
        if ($from && strtotime($from)) {
            $query->where('created_at', '>=', date('Y-m-d 00:00:00', strtotime($from)));
        }
        $to = $request->query('to');
        if ($to && strtotime($to)) {
            $query->where('created_at', '<=', date('Y-m-d 23:59:59', strtotime($to)));
        }

        $rows = $query->orderBy('created_at', 'desc')->get([
            'id', 'first_name', 'last_name', 'email', 'phone',
            'enquiry_type', 'service', 'status', 'message', 'created_at',
        ]);

        $filename = 'landing-page-enquiries_' . now()->format('Y-m-d') . '.csv';
        $callback = function () use ($rows) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM for Excel — same rationale as the attendance exports.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'Name', 'Email', 'Phone', 'Enquiry type', 'Service / course',
                'Status', 'Message', 'Created at',
            ]);
            $statusOpts = LandingPageEnquiry::statusOptions();
            foreach ($rows as $r) {
                $normalized = $r->status === 'published' ? 'new' : ($r->status ?? 'new');
                $label = $statusOpts[$normalized]['label'] ?? ucfirst($normalized);
                fputcsv($out, [
                    trim(((string) $r->first_name) . ' ' . (string) $r->last_name),
                    (string) $r->email,
                    (string) $r->phone,
                    (string) $r->enquiry_type,
                    (string) $r->service,
                    $label,
                    (string) $r->message,
                    $r->created_at ? $r->created_at->format('Y-m-d H:i:s') : '',
                ]);
            }
            fclose($out);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type'           => 'text/csv; charset=UTF-8',
            'Cache-Control'          => 'no-cache, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function create(Request $request)
    {
        $flag = checkPermission($this->pageName);
        if ($flag == 1) { 
            return view($this->admin_view.'.create');
        } else {
            return view($this->admin_error_view);
        }
    }

    public function store(Request $request)
    {
      
        $flag = checkPermission($this->pageName);
        if ($flag == 1) { 
             // Validation
        // FT-VAL-1 fix (2026-05-28) — added `email` / `string` / `max`
        // rules and a status whitelist. The original accepted any
        // string for email/phone/service which then got persisted as
        // a lead — corrupting the CRM with non-email-shaped rows that
        // later break sendEmail's filter_var() check.
        $request->validate([
            'first_name' => ['required', 'string', 'max:190'],
            'last_name'  => ['nullable', 'string', 'max:190'],
            'email'      => ['required', 'email', 'max:190'],
            'phone'      => ['required', 'string', 'max:32'],
            'service'    => ['required', 'string', 'max:190'],
            'value'      => ['nullable', 'numeric', 'min:0'],
            'message'    => ['nullable', 'string', 'max:5000'],
            'status'     => ['required', 'string', \Illuminate\Validation\Rule::in(\App\Models\LandingPageEnquiry::validStatuses())],
        ], [
            'first_name.required' => __('Name is required'),
            'phone.required'      => __('Phone is required'),
            'email.required'      => __('Email is required'),
            'email.email'         => __('Please enter a valid email'),
            'status.in'           => __('Please choose a valid status'),
        ]);
        // Update fields
        $data['first_name'] = $request->first_name;
        $data['last_name'] = $request->last_name ?: '';
        $data['email'] = $request->email;
        $data['phone'] = $request->phone;
        $data['service'] = $request->service;
        $data['value'] = $request->value !== null && $request->value !== '' ? $request->value : null;
        $data['message'] = $request->message;
        $data['status'] = $request->status;
        $data['source'] = 'manual';     // manually-added leads are tagged for funnel analytics
        if ($request->status === \App\Models\LandingPageEnquiry::STATUS_WON) { $data['won_at'] = now(); }
        $coachId = (userAuth()->role !='instructor')?userAuth()->coach_id:userAuth()->id;
        $data['coach_id'] = $coachId;
        $data['added_by'] = userAuth()->id;
        $this->model::create($data);
 
 
        return redirect()->route('instructor.landing-page-enquiry.index')->with('success', 'Added successfully');
        } else {
            return view($this->admin_error_view);
        }
    }
    /**
     * 2026-07-10 (New Changes for UI #8.1) — restrict a landing_page_enquiries
     * query to the rows the current user may see. A real coach sees every
     * enquiry in their tenant (the caller has already scoped by coach_id); a
     * STAFF member sees ONLY enquiries the coach ASSIGNED to them (assigned_to
     * = staff id). Because assignment drives visibility, reassigning or
     * unassigning a lead moves it between listings immediately, and one staff
     * member never sees another's leads. Applied on EVERY read path (list,
     * stats, export, kanban, bulk) so the boundary can't be bypassed.
     */
    private function scopeAssignedForStaff($query)
    {
        if (userAuth()->role !== 'instructor') {
            $query->where('assigned_to', (int) userAuth()->id);
        }
        return $query;
    }

    /**
     * Find an enquiry that belongs to the current coach. Aborts on cross-coach access.
     */
    private function findOwnedEnquiryOrFail($id)
    {
        $coachId = userAuth()->role === 'instructor' ? userAuth()->id : userAuth()->coach_id;
        $enquiry = $this->model::where('id', $id)
            ->where(function ($q) use ($coachId) {
                $q->where('coach_id', $coachId)->orWhere('added_by', $coachId);
            })
            ->firstOrFail();

        // 2026-07-10 (New Changes for UI #8.1) — a STAFF member may only open an
        // enquiry the coach ASSIGNED to them. 403 (not 404) so the boundary
        // reads as "access denied" rather than "missing"; this also blocks a
        // staff member from opening another staff member's lead — or any
        // unassigned lead — by guessing the id. Coaches keep full visibility.
        if (userAuth()->role !== 'instructor') {
            abort_unless(
                (int) ($enquiry->assigned_to ?? 0) === (int) userAuth()->id,
                403,
                'This enquiry is not assigned to you.'
            );
        }

        return $enquiry;
    }

    public function edit($id)
    {
        $flag = checkPermission($this->pageName);
        if ($flag == 1) {
            try {
                $data = $this->findOwnedEnquiryOrFail($id);
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                return redirect()->route('instructor.landing-page-enquiry.index')
                    ->with(['messege' => __('That lead no longer exists or is not in your account.'), 'alert-type' => 'error']);
            }
            return view($this->admin_view.'.edit', compact('data'));
        } else {
            return view($this->admin_error_view);
        }
    }

    public function update($id, Request $request)
    {
        // Verify ownership BEFORE applying any update
        $enquiry = $this->findOwnedEnquiryOrFail($id);
        $previousStatus = $enquiry->status;

        // Validation
        $request->validate([
            'first_name' => ['required', 'string', 'max:190'],
            'last_name'  => ['nullable', 'string', 'max:190'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['required', 'string', 'max:32'],
            'service' => ['required', 'string', 'max:190'],
            'value'   => ['nullable', 'numeric', 'min:0'],
            'lost_reason' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', \Illuminate\Validation\Rule::in(\App\Models\LandingPageEnquiry::validStatuses())],
        ], [
            'first_name.required' => __('Name is required'),
            'email.required' => __('Email is required'),
            'email.email' => __('Please enter a valid email'),
            'status.in' => __('Please choose a valid status'),
        ]);

        // Update fields
        $data['first_name'] = $request->first_name;
        $data['last_name'] = $request->last_name ?: '';
        $data['email'] = $request->email;
        $data['phone'] = $request->phone;
        $data['service'] = $request->service;
        $data['value'] = $request->value !== null && $request->value !== '' ? $request->value : null;
        $data['status'] = $request->status;
        // Phase 2 — funnel stamps: won date once, structured lost reason.
        if ($request->status === \App\Models\LandingPageEnquiry::STATUS_WON && empty($enquiry->won_at)) {
            $data['won_at'] = now();
        }
        $data['lost_reason'] = $request->status === \App\Models\LandingPageEnquiry::STATUS_LOST
            ? $request->lost_reason
            : null;

        $enquiry->update($data);

        // 2026-06-12 — record stage changes made via the edit form in the
        // activity log too (previously only the inline/kanban path logged them).
        if ($previousStatus !== $request->status) {
            \App\Models\LeadAuditLog::create([
                'enquiry_id' => $enquiry->id,
                'actor_id'   => userAuth()->id,
                'event'      => 'status_changed',
                'from_value' => (string) $previousStatus,
                'to_value'   => (string) $request->status,
                'created_at' => now(),
            ]);
        }

        return redirect()->route('instructor.landing-page-enquiry.index')->with('success', __('Enquiry updated successfully'));
    }

    public function destroy($id)
    {
        $methodName = request()->route()->getActionMethod();
        $flag = checkPermission($this->pageName, $methodName);
        if ($flag == 1) {
            $user = $this->findOwnedEnquiryOrFail($id);
            $user->delete();
            return redirect()->back()->with([
                'message' => 'Deleted Successfully',
                'alert-type' => 'success',
            ]);
        } else {
            return view($this->admin_error_view);
        }
    }

    /**
     * Lead detail page — Tier 2 addition. Shows the full enquiry with the
     * notes timeline and inline forms for adding a note + setting a
     * follow-up date. Edit form remains for canonical field changes
     * (name, email, phone, etc.).
     */
    public function show($id)
    {
        // A lead that was deleted, or that isn't in this account, should send the
        // coach back to their list with a clear message — never a bare 404 page
        // (which reads as "the site is broken"). The teacher 403 still propagates.
        try {
            $enquiry = $this->findOwnedEnquiryOrFail($id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return redirect()->route('instructor.landing-page-enquiry.index')
                ->with(['messege' => __('That lead no longer exists or is not in your account.'), 'alert-type' => 'error']);
        }
        $enquiry->load(['notes.author:id,name', 'assignee:id,name']);
        $statusOptions = LandingPageEnquiry::statusOptions();

        return view($this->admin_view . '.show', compact('enquiry', 'statusOptions'));
    }

    /**
     * Append a note to the timeline. Notes are immutable from the UI —
     * once posted, they stay. Authors can only post under their own id.
     */
    public function addNote(\Illuminate\Http\Request $request, $id): \Illuminate\Http\RedirectResponse
    {
        $enquiry = $this->findOwnedEnquiryOrFail($id);

        $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        \App\Models\LeadNote::create([
            'enquiry_id' => $enquiry->id,
            'author_id'  => userAuth()->id,
            'body'       => trim((string) $request->input('body')),
        ]);

        // Denormalized counter — used by the index table's "💬 N" badge
        // so we don't N+1-query notes for every row.
        $enquiry->increment('notes_count');

        // 2026-06-12 — record in the activity timeline (the convention documented
        // 'note_added' but it was never written).
        \App\Models\LeadAuditLog::create([
            'enquiry_id' => $enquiry->id,
            'actor_id'   => userAuth()->id,
            'event'      => 'note_added',
            'created_at' => now(),
        ]);

        return redirect()
            ->route('instructor.landing-page-enquiry.show', $enquiry->id)
            ->with(['message' => __('Note added'), 'alert-type' => 'success']);
    }

    /**
     * 2026-06-12 Phase 2 — CONVERT a won lead into a student (funnel
     * completion). Finds/creates the student account by email, links them to
     * THIS coach, optionally grants access to the lead's target course, and
     * marks the lead WON + linked. Fully coach-scoped + audited; works for
     * every coach (no hardcoding).
     */
    public function convertToStudent($id): \Illuminate\Http\RedirectResponse
    {
        $enquiry = $this->findOwnedEnquiryOrFail($id);
        $coachId = userAuth()->role === 'instructor' ? userAuth()->id : userAuth()->coach_id;

        if (empty($enquiry->email)) {
            return redirect()->back()->with(['message' => __('Add an email to this lead before converting.'), 'alert-type' => 'error']);
        }

        // Find the student by email, or create a fresh student account.
        $student = \App\Models\User::where('email', $enquiry->email)->first();
        if (! $student) {
            $name = trim(($enquiry->first_name ?? '') . ' ' . ($enquiry->last_name ?? '')) ?: __('Student');
            $student = \App\Models\User::create([
                'coach_unique_id'   => 'MBS' . ((int) \App\Models\User::max('id') + 1),
                'role'              => 'student',
                'name'              => $name,
                'username'          => \Illuminate\Support\Str::slug($name) . '-' . \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(5)),
                'email'             => $enquiry->email,
                'phone'             => $enquiry->phone,
                'status'            => 'active',
                'is_banned'         => 'no',
                'password'          => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(20)),
                'email_verified_at' => now(),
            ]);
        }

        // Attribute the student to THIS coach (idempotent).
        \App\Models\CoachStudentLink::link((int) $coachId, (int) $student->id, 'converted');

        // If the lead targeted a course THIS coach owns, grant access
        // (manual coach-side enrolment — a free conversion).
        $enrolledCourseId = null;
        if (! empty($enquiry->product_id)) {
            $course = \App\Models\Course::where('id', $enquiry->product_id)
                ->where(function ($q) use ($coachId) {
                    $q->where('instructor_id', $coachId)->orWhere('added_by', $coachId);
                })->first();
            if ($course) {
                \Modules\Order\app\Models\Enrollment::firstOrCreate(
                    ['user_id' => $student->id, 'course_id' => $course->id, 'batch_id' => null],
                    ['has_access' => 1]
                );
                $enrolledCourseId = $course->id;
            }
        }

        // Mark the lead WON + linked to the new student.
        $previousStatus = $enquiry->status;
        $enquiry->update([
            'status'            => LandingPageEnquiry::STATUS_WON,
            'converted_user_id' => $student->id,
            'won_at'            => now(),
        ]);

        \App\Models\LeadAuditLog::create([
            'enquiry_id' => $enquiry->id,
            'actor_id'   => userAuth()->id,
            'event'      => 'converted',
            'from_value' => (string) $previousStatus,
            'to_value'   => 'won',
            'created_at' => now(),
        ]);

        return redirect()
            ->route('instructor.landing-page-enquiry.show', $enquiry->id)
            ->with([
                'message'    => $enrolledCourseId
                    ? __('Lead converted to a student and enrolled in their course.')
                    : __('Lead converted to a student.'),
                'alert-type' => 'success',
            ]);
    }

    /**
     * Persist the follow-up reminder date. Clearing it (empty input)
     * removes the reminder. Past-dated values are accepted — useful
     * when a coach is logging historical follow-ups.
     */
    public function setFollowUp(\Illuminate\Http\Request $request, $id): \Illuminate\Http\RedirectResponse
    {
        $enquiry = $this->findOwnedEnquiryOrFail($id);

        $request->validate([
            'follow_up_at' => ['nullable', 'date'],
        ]);

        $followUpAt = $request->input('follow_up_at') ?: null;
        $enquiry->follow_up_at = $followUpAt;

        // Phase 8 — auto-flip the status to Follow-up when the coach
        // schedules a FUTURE reminder, but only from the early-funnel
        // statuses where it makes sense ('new', 'contacted', legacy
        // 'published'). We deliberately don't overwrite the late-funnel
        // statuses (qualified / proposal_sent / won / lost / spam) — those
        // represent a coach's explicit decision and the follow-up date
        // is just bookkeeping at that point.
        if ($followUpAt
            && \Carbon\Carbon::parse($followUpAt)->isFuture()
            && in_array($enquiry->status, [null, '', 'new', 'contacted', 'published'], true)
        ) {
            $enquiry->status = LandingPageEnquiry::STATUS_FOLLOWUP;
        }

        $enquiry->save();

        return redirect()
            ->route('instructor.landing-page-enquiry.show', $enquiry->id)
            ->with(['message' => __('Follow-up updated'), 'alert-type' => 'success']);
    }

    /**
     * Bulk actions endpoint — coaches select multiple rows and apply
     * a status change or delete in one request. All operations are
     * IDOR-gated: the WHERE coach_id clause filters out any IDs that
     * don't belong to the caller, so a forged ID list can't reach
     * other coaches' rows.
     */
    public function bulk(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'action' => ['required', 'string', \Illuminate\Validation\Rule::in(['status', 'delete'])],
            'ids'    => ['required', 'array', 'min:1'],
            'ids.*'  => ['integer'],
            'status' => [
                'required_if:action,status',
                'string',
                \Illuminate\Validation\Rule::in(LandingPageEnquiry::validStatuses()),
            ],
        ]);

        $coachId = userAuth()->role === 'instructor' ? userAuth()->id : userAuth()->coach_id;
        $ids     = array_map('intval', $request->input('ids', []));
        $base    = LandingPageEnquiry::whereIn('id', $ids)->where('coach_id', $coachId);

        // 2026-07-10 (New Changes for UI #8.1) — a staff member can only
        // bulk-mutate leads ASSIGNED to them (was a course-batch heuristic);
        // coaches are unrestricted within their tenant. Blocks a staff member
        // from mass-editing another staff member's or unassigned leads by
        // submitting their ids.
        $this->scopeAssignedForStaff($base);

        $action = $request->input('action');
        if ($action === 'status') {
            $newStatus = $request->input('status');
            $rows = (clone $base)->get(['id', 'status']);
            $count = 0;
            foreach ($rows as $row) {
                if ($row->status === $newStatus) {
                    continue;
                }
                LandingPageEnquiry::where('id', $row->id)->update(['status' => $newStatus]);
                \App\Models\LeadAuditLog::create([
                    'enquiry_id' => $row->id,
                    'actor_id'   => userAuth()->id,
                    'event'      => 'status_changed',
                    'from_value' => (string) $row->status,
                    'to_value'   => (string) $newStatus,
                    'created_at' => now(),
                ]);
                $count++;
            }
            $msg = __(':n enquiries updated', ['n' => $count]);
        } else {
            $count = (clone $base)->delete();
            $msg   = __(':n enquiries deleted', ['n' => $count]);
        }

        return redirect()
            ->route('instructor.landing-page-enquiry.index')
            ->with(['message' => $msg, 'alert-type' => 'success']);
    }

    /**
     * Kanban pipeline view — groups enquiries by status into columns
     * matching the configured statusOptions order. Same coach-scope
     * + filter set as index() so the toggle feels seamless.
     */
    public function kanban(\Illuminate\Http\Request $request): View
    {
        $coachId = userAuth()->role !== 'instructor' ? userAuth()->coach_id : userAuth()->id;

        $base = $this->model::where('coach_id', $coachId);
        // 2026-07-10 (New Changes for UI #8.1) — staff kanban shows only their
        // assigned leads (parity with the list view).
        $this->scopeAssignedForStaff($base);

        // Honour the same filters as index for visual continuity when
        // a coach toggles table ↔ kanban.
        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $base->where(function ($w) use ($q) {
                $like = '%' . $q . '%';
                $w->where('first_name', 'like', $like)
                  ->orWhere('last_name',  'like', $like)
                  ->orWhere('email',      'like', $like)
                  ->orWhere('phone',      'like', $like)
                  ->orWhere('service',    'like', $like);
            });
        }
        // 2026-06-12 — carry the table's date-range filter into the board too,
        // so the table ↔ pipeline toggle is genuinely seamless. (Status isn't a
        // filter here — the board is grouped BY status.)
        $from = $request->query('from');
        $to   = $request->query('to');
        if ($from) { $base->whereDate('created_at', '>=', $from); }
        if ($to)   { $base->whereDate('created_at', '<=', $to); }

        $statusOptions = LandingPageEnquiry::statusOptions();

        // Hard cap per column — Kanban becomes unreadable with 1000s
        // of cards. Sort newest first so freshest leads are on top
        // of each column.
        $perColumnLimit = 50;
        $columns = [];
        foreach (array_keys($statusOptions) as $key) {
            $rows = (clone $base)
                ->where(function ($w) use ($key) {
                    $w->where('status', $key);
                    // Treat legacy 'published' as 'new' for the new column.
                    if ($key === LandingPageEnquiry::STATUS_NEW) {
                        $w->orWhere('status', 'published');
                    }
                })
                ->orderByDesc('created_at')
                ->limit($perColumnLimit)
                ->get([
                    'id', 'first_name', 'last_name', 'email', 'phone',
                    'service', 'status', 'follow_up_at', 'notes_count', 'created_at',
                ]);
            // Total count (unlimited) — shown as "showing 50 of 213" in the header.
            $total = (clone $base)
                ->where(function ($w) use ($key) {
                    $w->where('status', $key);
                    if ($key === LandingPageEnquiry::STATUS_NEW) {
                        $w->orWhere('status', 'published');
                    }
                })
                ->count();
            $columns[$key] = ['rows' => $rows, 'total' => $total];
        }

        $filters = ['q' => $q, 'from' => $from, 'to' => $to];

        return view($this->admin_view . '.kanban', compact('columns', 'statusOptions', 'filters'));
    }

    // ============== TIER 3 ==============

    /**
     * Assign a lead to a staff member (or unassign). Records an audit
     * event. The assignee must be either the coach themself or one of
     * their coach-staff records — we don't validate against a global
     * user list, otherwise a coach could assign a lead to anyone in
     * the LMS, exposing their CRM data.
     */
    public function assign(\Illuminate\Http\Request $request, $id): \Illuminate\Http\RedirectResponse
    {
        $enquiry = $this->findOwnedEnquiryOrFail($id);

        $request->validate([
            'assigned_to' => ['nullable', 'integer'],
        ]);

        $newAssignee = $request->input('assigned_to') ? (int) $request->input('assigned_to') : null;

        // Authorize the target user — coach themself or staff of the coach.
        // Skip the check when unassigning (null is always safe).
        if ($newAssignee !== null) {
            $coachId = userAuth()->role === 'instructor' ? userAuth()->id : userAuth()->coach_id;
            $isCoach = $newAssignee === (int) $coachId;
            // 2026-07-10 fix: staff live on the users table (added_by = coach), NOT
            // a non-existent coach_staff table (which 500'd the assign action).
            $isStaff = \App\Models\User::where('added_by', $coachId)
                ->where('id', $newAssignee)
                ->where('role', '!=', 'student')
                ->exists();
            abort_unless($isCoach || $isStaff, 403, 'You can only assign to your own staff.');
        }

        $previous = $enquiry->assigned_to;
        $enquiry->assigned_to = $newAssignee;
        $enquiry->save();

        if ((int) $previous !== (int) $newAssignee) {
            \App\Models\LeadAuditLog::create([
                'enquiry_id' => $enquiry->id,
                'actor_id'   => userAuth()->id,
                'event'      => 'assigned',
                'from_value' => $previous !== null ? (string) $previous : null,
                'to_value'   => $newAssignee !== null ? (string) $newAssignee : null,
                'created_at' => now(),
            ]);

            // Notify the new assignee (coach-branded), unless they assigned the
            // lead to themselves — no point emailing yourself.
            if ($newAssignee !== null && $newAssignee !== (int) userAuth()->id) {
                try {
                    $assignee = \App\Models\User::find($newAssignee);
                    if ($assignee) {
                        $assignee->notify(new \App\Notifications\LeadAssignedToStaff($enquiry));
                    }
                } catch (\Throwable $e) {
                    \Log::warning('Lead-assigned notify failed: ' . $e->getMessage());
                }
            }
        }

        return redirect()
            ->route('instructor.landing-page-enquiry.show', $enquiry->id)
            ->with(['message' => __('Assignment updated'), 'alert-type' => 'success']);
    }

    /**
     * Send an email to the lead. Renders the template against the lead
     * model (so {first_name}, {service}, etc. substitute), records the
     * rendered text in `email_sends`, dispatches via Laravel's Mail
     * facade. Failures are caught and stored on the row so coaches can
     * see why a send didn't land.
     */
    public function sendEmail(\Illuminate\Http\Request $request, $id): \Illuminate\Http\RedirectResponse
    {
        $enquiry = $this->findOwnedEnquiryOrFail($id);

        $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body'    => ['required', 'string', 'max:20000'],
        ]);

        // Minimal templating — {first_name}, {last_name}, {email},
        // {phone}, {service}. Replace via strtr() so order doesn't
        // matter and there's no regex-injection risk.
        $vars = [
            '{first_name}' => (string) ($enquiry->first_name ?? ''),
            '{last_name}'  => (string) ($enquiry->last_name ?? ''),
            '{email}'      => (string) ($enquiry->email ?? ''),
            '{phone}'      => (string) ($enquiry->phone ?? ''),
            '{service}'    => (string) ($enquiry->service ?? ''),
        ];
        $renderedSubject = strtr((string) $request->input('subject'), $vars);
        $renderedBody    = strtr((string) $request->input('body'),    $vars);

        // 2026-07-10 (New Changes for UI #8.2) — the lead email now goes out
        // through the COACH's own SMTP when they've configured valid
        // credentials in the Brand module, else the platform (MBSGuru) default.
        // We resolve the intended source here (mirrors CoachMailer::resolveMode,
        // WITHOUT touching credentials) purely so the audit row records which
        // server was used. CoachMailer itself performs the send + a graceful
        // platform fallback if the coach's SMTP refuses, and never exposes any
        // credential/host detail to the UI.
        $emailCoachId = (int) ($enquiry->coach_id
            ?: (userAuth()->role === 'instructor' ? userAuth()->id : userAuth()->coach_id));
        $brandRow = \App\Models\CoachBrandSetting::firstOrCreateForCoach($emailCoachId);
        $usesCoachSmtp = $brandRow->smtp_host && $brandRow->smtp_username && $brandRow->smtp_password_encrypted;
        $smtpSource = $usesCoachSmtp ? 'coach' : 'mbsguru';

        // Pre-create the row so we always have a record even if the
        // mail driver throws. Status starts as 'queued', flips to
        // 'sent' or 'failed' below.
        $send = \App\Models\EmailSend::create([
            'enquiry_id'  => $enquiry->id,
            'coach_id'    => $emailCoachId,
            'sender_id'   => userAuth()->id,
            'to_email'    => (string) ($enquiry->email ?? ''),
            'subject'     => $renderedSubject,
            'body'        => $renderedBody,
            'smtp_source' => $smtpSource,
            'status'      => 'queued',
        ]);

        try {
            if (!filter_var($enquiry->email, FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('Lead has no valid email address.');
            }
            // Send via the coach-SMTP-else-platform pipeline. The body is
            // authored as plain text; wrap it as minimal, escaped HTML so line
            // breaks survive and no markup is injected.
            $html = nl2br(e($renderedBody));
            $ok = app(\App\Services\CoachMailer::class)
                ->send($emailCoachId, (string) $enquiry->email, $renderedSubject, $html);
            if (! $ok) {
                throw new \RuntimeException('The mail server rejected the message.');
            }
            $send->forceFill(['status' => 'sent'])->save();

            \App\Models\LeadAuditLog::create([
                'enquiry_id' => $enquiry->id,
                'actor_id'   => userAuth()->id,
                'event'      => 'email_sent',
                'to_value'   => $send->to_email,
                'meta'       => ['subject' => $renderedSubject, 'send_id' => $send->id, 'smtp_source' => $smtpSource],
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $send->forceFill([
                'status' => 'failed',
                'error'  => substr($e->getMessage(), 0, 1000),
            ])->save();
            return redirect()
                ->route('instructor.landing-page-enquiry.show', $enquiry->id)
                ->with(['message' => __('Email could not be sent:') . ' ' . $e->getMessage(), 'alert-type' => 'error']);
        }

        return redirect()
            ->route('instructor.landing-page-enquiry.show', $enquiry->id)
            ->with(['message' => __('Email sent'), 'alert-type' => 'success']);
    }

    /**
     * CSV import — two-step flow:
     *   GET  importForm  → upload + dry-run preview
     *   POST importCommit → actually writes rows
     *
     * Two-step prevents accidental large imports. Preview shows the
     * first N rows + the column mapping the parser inferred so a
     * coach sees what would be created.
     */
    public function importForm(): View
    {
        return view($this->admin_view . '.import');
    }

    public function importPreview(\Illuminate\Http\Request $request): View
    {
        $request->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $rows    = $this->parseCsvUpload($request->file('csv'));
        $preview = array_slice($rows, 0, 25);   // show first 25 rows
        $total   = count($rows);

        // Stash the uploaded file path so commit can re-read it without
        // requiring the user to upload again. Public disk → temp.
        $stashPath = $request->file('csv')->store('lpe-import-tmp');

        return view($this->admin_view . '.import-preview', [
            'preview'   => $preview,
            'total'     => $total,
            'stashPath' => $stashPath,
        ]);
    }

    public function importCommit(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'stash_path' => ['required', 'string'],
        ]);

        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        $stashPath = (string) $request->input('stash_path');
        abort_unless($disk->exists($stashPath), 404, 'Stashed import file missing.');

        // Re-parse from the stashed file rather than trusting the
        // preview to survive a session round-trip.
        $tmpAbs = $disk->path($stashPath);
        $rows   = $this->parseCsvFile($tmpAbs);

        $coachId = userAuth()->role === 'instructor' ? userAuth()->id : userAuth()->coach_id;
        $created = 0;
        $skipped = 0;

        foreach ($rows as $r) {
            // Minimum-viable row: must have at least an email OR phone,
            // and at least a name. Anything sparser is junk; skip.
            $name = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
            if ($name === '' || (empty($r['email']) && empty($r['phone']))) {
                $skipped++;
                continue;
            }
            $enquiry = LandingPageEnquiry::create([
                'coach_id'     => $coachId,
                'added_by'     => userAuth()->id,
                'first_name'   => $r['first_name']   ?? '',
                'last_name'    => $r['last_name']    ?? '',
                'email'        => $r['email']        ?? '',
                'phone'        => $r['phone']        ?? '',
                'service'      => $r['service']      ?? '',
                'source'       => $r['source']       ?? 'csv-import',
                'enquiry_type' => $r['enquiry_type'] ?? '',
                'status'       => $r['status']       ?? LandingPageEnquiry::STATUS_NEW,
                'message'      => $r['message']      ?? '',
            ]);
            \App\Models\LeadAuditLog::create([
                'enquiry_id' => $enquiry->id,
                'actor_id'   => userAuth()->id,
                'event'      => 'imported',
                'meta'       => ['from' => 'csv-import'],
                'created_at' => now(),
            ]);
            $created++;
        }

        // Clean up the stashed file — we have no further use for it.
        $disk->delete($stashPath);

        return redirect()
            ->route('instructor.landing-page-enquiry.index')
            ->with([
                'message'    => __(':c imported, :s skipped', ['c' => $created, 's' => $skipped]),
                'alert-type' => 'success',
            ]);
    }

    /**
     * CSV parser shared by previewing + committing. Maps any
     * recognized header name (case-insensitive, with common synonyms)
     * to the canonical enquiry field. Unknown columns are ignored.
     */
    private function parseCsvUpload($file): array
    {
        return $this->parseCsvFile($file->getRealPath());
    }
    private function parseCsvFile(string $absPath): array
    {
        $fh = fopen($absPath, 'r');
        if (!$fh) {
            return [];
        }
        $header = fgetcsv($fh);
        if (!$header) {
            fclose($fh);
            return [];
        }

        // Header synonyms → canonical field. Make matches case-insensitive
        // and tolerant of common variations ("Phone Number" / "phone_no").
        $synonyms = [
            'first_name'   => ['first name', 'firstname', 'name', 'first'],
            'last_name'    => ['last name',  'lastname',  'surname', 'last'],
            'email'        => ['email', 'email address', 'e-mail', 'mail'],
            'phone'        => ['phone', 'phone number', 'phone no', 'phone_no', 'mobile', 'contact'],
            'service'      => ['service', 'interested in', 'course'],
            'source'       => ['source', 'utm source', 'utm_source', 'channel'],
            'enquiry_type' => ['enquiry type', 'type', 'category'],
            'status'       => ['status', 'pipeline', 'stage'],
            'message'      => ['message', 'note', 'notes', 'comments'],
        ];
        $colMap = [];
        foreach ($header as $i => $col) {
            $needle = strtolower(trim((string) $col));
            foreach ($synonyms as $canonical => $alts) {
                if ($needle === $canonical || in_array($needle, $alts, true)) {
                    $colMap[$i] = $canonical;
                    break;
                }
            }
        }

        $out = [];
        while (($row = fgetcsv($fh)) !== false) {
            $rec = [];
            foreach ($row as $i => $val) {
                if (isset($colMap[$i])) {
                    $rec[$colMap[$i]] = trim((string) $val);
                }
            }
            if (!empty($rec)) {
                $out[] = $rec;
            }
        }
        fclose($fh);
        return $out;
    }
}
