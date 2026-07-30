<?php

namespace App\Http\Controllers\Frontend\Coach;

use App\Http\Controllers\Controller;
use App\Models\CoachStaff;
use App\Models\CoachStaffPermission;
use App\Models\CoachStaffRole;
use App\Services\CoachPermissionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CoachStaffController extends Controller
{
    protected $pageName;

    public function __construct(CoachStaff $model)
    {
        $this->model = $model;
        $this->admin_base_url = 'instructor.coach-staff.index';
        $this->admin_view = 'frontend.instructor-dashboard.coach-staff';
        $this->admin_error_view = 'errors.403';
        $this->pageName = 'coach-staff';
    }

    public function index(Request $request): View
    {
        $flag = checkPermission($this->pageName);
        if ($flag != 1) {
            return view($this->admin_error_view);
        }

        $metadta['title'] = 'Staff';
        $coachId = auth()->id();

        // 2026-05-20 — corporate redesign: search + filter + KPI strip.
        $query = $this->model::query()
            ->where('added_by', $coachId)
            ->where('role', '!=', 'student');

        if ($request->filled('q')) {
            $q = '%' . trim($request->q) . '%';
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', $q)
                  ->orWhere('email', 'like', $q)
                  ->orWhere('phone', 'like', $q);
            });
        }
        if ($request->filled('status') && in_array($request->status, ['active', 'banned'], true)) {
            $query->where('status', $request->status);
        }
        if ($request->filled('role_id')) {
            // users.role_id links a staff user to a CoachStaffRole.
            $query->where('role_id', (int) $request->role_id);
        }

        $coachStaff = $query->orderByDesc('id')->paginate(10)->withQueryString();

        // KPI snapshot — totals computed on the full (unfiltered) scope
        // so the strip numbers stay stable across filter operations.
        $baseScope = $this->model::query()
            ->where('added_by', $coachId)
            ->where('role', '!=', 'student');
        $kpi = [
            'total'      => (clone $baseScope)->count(),
            'active'     => (clone $baseScope)->where('status', 'active')->count(),
            'banned'     => (clone $baseScope)->where('status', 'banned')->count(),
            'added_30d'  => (clone $baseScope)->where('created_at', '>=', now()->subDays(30))->count(),
        ];

        // Role list for the filter dropdown.
        $roles = CoachStaffRole::where('added_by', $coachId)
            ->where('status', 1)
            ->orderBy('role_name')
            ->get(['id', 'role_name']);

        return view('frontend.instructor-dashboard.coach-staff.index',
            compact('coachStaff', 'metadta', 'kpi', 'roles'));
    }

    public function create(Request $request)
    {
        $flag = checkPermission($this->pageName);
        if ($flag != 1) {
            return view($this->admin_error_view);
        }
        $userId = auth('web')->user()->id;

        if ($request->ajax()) {
            $role = CoachStaffRole::where('id', $request->role_id)
                ->where('added_by', $userId)
                ->with('permissions')
                ->first();

            if (!$role) {
                return response()->json(['html' => '']);
            }

            // 2026-05-20 enterprise upgrade — return rendered picker
            // HTML (matrix layout) instead of bare JSON, so the Staff
            // create form gets the same Resource×Action matrix the
            // Role forms use. Wire stays JSON for back-compat.
            $roleDefaultIds = $role->permissions->pluck('id')->map(fn ($v) => (int) $v)->all();
            $html = view('frontend.instructor-dashboard.settings.partials._permission-picker', [
                'pickerPermissions'  => $role->permissions,
                'pickerField'        => 'permissions[]',
                'pickerChecked'      => $roleDefaultIds,       // start from the role baseline
                'pickerRoleDefaults' => $roleDefaultIds,       // enables override badges + reset
                'pickerId'           => 'staff-perm-picker',
            ])->render();

            return response()->json([
                'html'        => $html,
                'permissions' => $role->permissions,  // legacy field — preserve back-compat
            ]);
        }

        $roles = CoachStaffRole::where(['added_by' => $userId, 'status' => 1])->get();
        return view($this->admin_view.'.create', compact('roles'));
    }

    public function store(Request $request)
    {
        $flag = checkPermission($this->pageName);
        if ($flag == 1) {
            // FT-IDOR-12 + FT-AUTH-7 + FT-VAL-1 (combined):
            //   • role_id was `exists:coach_staff_roles,id` (any
            //     platform-wide role); attacker could attach a foreign
            //     coach's role to their new staff. Scope to the
            //     current coach's roles via `where('added_by', $coachId)`.
            //   • password lacked `min:8` — the rest of the platform
            //     (signup, customer create, password reset) all use
            //     min:8. Bring this in line.
            //   • email had `string` but no `email` type rule, so
            //     malformed inputs survived the unique check until they
            //     hit the welcome-mail path and failed silently in the
            //     try/catch.
            // We need $coachId BEFORE validate() because the role-scoped
            // Rule::exists() reads it.
            $coachId = (int) (userAuth()->role === 'instructor' ? userAuth()->id : (userAuth()->coach_id ?? userAuth()->id));

            $rules = [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'min:8', 'max:255'],
                'role_id' => [
                    'required',
                    'integer',
                    Rule::exists('coach_staff_roles', 'id')->where('added_by', $coachId),
                ],
                'status' => ['required', 'string'],
            ];
            $messages = [
                'name.required' => __('Name is required'),
                'email.required' => __('Please enter a valid email address'),
                'email.email'    => __('Please enter a valid email address'),
                'email.unique' => __('This email is already taken'),
                'email.max' => __('Email must be less than 255 characters long'),
                'password.required' => __('Password is required'),
                'password.min'      => __('Password must be at least 8 characters'),
                'role_id.required' => __('Role is required'),
                'role_id.exists' => __('Selected role is invalid'),
                'status.required' => __('Status is required'),
            ];

            $request->validate($rules, $messages);

            // FT-OWN-1 fix (2026-05-28) — was `$coachId = auth('web')->user()->id`
            // which gave the STAFF id when a staff member (not the coach
            // themselves) was the actor, because auth('web') returns the
            // currently logged-in user regardless of role. That broke the
            // ownership-attribution downstream: added_by/coach_id columns
            // got set to the staff's id, so the new staff row was
            // orphaned from the coach's view (findOwnedStaffOrFail filters
            // by `added_by == coach->id`). Re-use the $coachId resolved
            // above for the role-scope check.
            $user = new $this->model;

            // Keep the plaintext password in scope so we can email it to the
            // new staff member after save() — without leaking it back into the
            // session or any log. Reported 2026-05-26 (bug-doc C10): staff
            // were created silently and never received a login email.
            $plaintextPassword = (string) $request->password;

            $user->name = $request->name;
            $user->email = $request->email;
            $user->password = Hash::make($request->password);
            $user->status = $request->status;
            $user->role_id = $request->role_id;
            $user->is_banned = 'no';
            $role = CoachStaffRole::find($request->role_id);
            if ($role) {
                // SECURITY (2026-06-01) defense-in-depth — a coach-staff user
                // accesses the coach panel via InstructorMiddleware's
                // $isCoachStaff branch (coach_id set AND role NOT IN
                // student/admin), NOT via role==='instructor'. But
                // checkPermission()/TeacherBatchAssignment treat
                // role==='instructor' as full-access + unbounded scope. So
                // never let a staff account's role become a reserved sentinel,
                // even if one slipped past role validation — clamp to the
                // neutral 'staff' marker (still passes $isCoachStaff, still
                // resolves permissions via role_id).
                $reserved = ['instructor', 'student', 'admin', 'super-admin', 'superadmin', 'institute-branch'];
                $user->role = in_array(strtolower(trim((string) $role->role_name)), $reserved, true)
                    ? 'staff'
                    : $role->role_name;
            }
            // Audit fix 2026-05-12 — mirror of the C2 pattern: hash the
            // verification_token before storage so a DB-read leak doesn't
            // hand out usable verification links. The User model hides
            // this column from serialization.
            $user->verification_token = hash('sha256', bin2hex(random_bytes(32)));
            $user->email_verified_at = Carbon::now();
            $user->added_by = $coachId;
            $user->coach_id = $coachId;

            $user->save();

            // Role + per-staff overrides resolved & materialised in one place
            // (override > role > deny). Replaces the old manual attach loops.
            $this->syncStaffPermissions($user, (int) $request->role_id, $request->permissions, $coachId);

            // 2026-05-26 (bug-doc C10) — email the new staff their login
            // email + temporary password so they can sign in. Wrapped in
            // try/catch so a mail-server hiccup doesn't roll back the
            // staff add. The plaintext password is held in $plaintextPassword
            // captured before Hash::make above; we never re-hash or log it.
            if (! empty($user->email)) {
                try {
                    $user->notify(new \App\Notifications\CoachStaffWelcomeToStaff(
                        $user,
                        $plaintextPassword,
                        auth('web')->user()
                    ));
                } catch (\Throwable $e) {
                    \Log::warning('coach-staff-welcome-mail-failed', [
                        'staff_id' => $user->id,
                        'coach_id' => $coachId,
                        'error'    => $e->getMessage(),
                    ]);
                }
            }

            return redirect()->route('instructor.coach-staff.index')->with(['messege' => __('Added successfully'), 'alert-type' => 'success']);
        } else {
            return view($this->admin_error_view);
        }
    }

    /**
     * Look up a staff record by id, scoped to the currently-logged-in coach.
     * Aborts with 404 if the staff doesn't belong to this coach (prevents IDOR).
     */
    private function findOwnedStaffOrFail($id)
    {
        $coachId = userAuth()->role === 'instructor' ? userAuth()->id : userAuth()->coach_id;
        return $this->model::where('added_by', $coachId)->where('id', $id)->firstOrFail();
    }

    /**
     * Persist a staff member's role + permission set through the single resolver
     * (2026-07-04). The coach submits the FINAL desired permission ids (pre-filled
     * from the role); we diff them against the role to derive per-staff OVERRIDES
     * (grant = beyond the role, revoke = removed from the role), record them
     * separately, and re-materialise the live users_permissions gate. The role
     * itself is never modified. All ids are validated against the catalog and the
     * role is validated as this coach's own (tenant-safe).
     */
    private function syncStaffPermissions(CoachStaff $user, int $roleId, $submitted, int $coachId): void
    {
        $role    = CoachStaffRole::where('id', $roleId)->where('added_by', $coachId)->with('permissions:id')->first();
        $roleIds = $role ? $role->permissions->pluck('id')->map(fn ($v) => (int) $v)->all() : [];

        $submitted = array_values(array_unique(array_map('intval', (array) ($submitted ?? []))));
        if (! empty($submitted)) {
            // Keep only real catalog ids — never let a crafted id become an override.
            $submitted = CoachStaffPermission::whereIn('id', $submitted)->pluck('id')->map(fn ($v) => (int) $v)->all();
        }

        $grants  = array_values(array_diff($submitted, $roleIds)); // ticked beyond the role
        $revokes = array_values(array_diff($roleIds, $submitted)); // role default the coach un-ticked

        app(CoachPermissionService::class)->syncStaff($user, $roleId, $grants, $revokes, $coachId);
    }

    public function edit(CoachStaff $user, Request $request)
    {
        $flag = checkPermission($this->pageName);
        if ($flag == 1) {
            $userId = auth('web')->user()->id;
            // Ensure the requested staff belongs to the current coach
            $this->findOwnedStaffOrFail($user->id);
            if ($request->ajax()) {
                $role = CoachStaffRole::where('id', $request->role_id)
                    ->where('added_by', $userId)
                    ->with('permissions') // eager load
                    ->first();
                if (! $role) {
                    return response()->json(['html' => '']);
                }

                // 2026-05-20 enterprise upgrade — return rendered matrix
                // HTML so Edit gets the same Resource×Action picker as
                // the rest of the cluster. Existing 'permissions' field
                // retained for any caller that still expects the JSON
                // array (back-compat).
                // Switching the role loads THAT role's baseline (overrides reset).
                $roleDefaultIds = $role->permissions->pluck('id')->map(fn ($v) => (int) $v)->all();
                $html = view('frontend.instructor-dashboard.settings.partials._permission-picker', [
                    'pickerPermissions'  => $role->permissions,
                    'pickerField'        => 'permissions[]',
                    'pickerChecked'      => $roleDefaultIds,
                    'pickerRoleDefaults' => $roleDefaultIds,
                    'pickerId'           => 'staff-perm-picker-edit',
                ])->render();

                return response()->json([
                    'html'        => $html,
                    'permissions' => $role->permissions,
                ]);
            }
 
            $roles = CoachStaffRole::where('added_by', $userId)->get();

            // get users_roles table data
            $userRole = $user->roles->first();

            if ($userRole != null) {
                // get all this user's permission
                $rolePermissions = $userRole->allRolePermissions;
            } else {
                $rolePermissions = null;
            }
            $userPermissions = $user->permissions;

            // get user data

            $user = $this->model::find($user->id);

            if ($user) {
                return view($this->admin_view.'.edit', compact('user', 'roles', 'userRole', 'rolePermissions', 'userPermissions'));
            }

            return redirect()->route($this->admin_base_url)->with('danger', 'Invalid Calling');
        } else {
            return view($this->admin_error_view);
        }
    }

    public function update($id, Request $request)
    {
        // FT-IDOR-12 fix (2026-05-28) — was missing
        // checkPermission() (every other method in this class calls
        // it). Without the gate a coach-staff user without the
        // `coach-staff` slug could still edit their own coach's
        // staff list, change names/emails/passwords, and — see
        // below — attach roles. Restore the gate.
        $flag = checkPermission($this->pageName);
        if ($flag != 1) {
            return view($this->admin_error_view);
        }

        $user = $this->findOwnedStaffOrFail($id);

        // FT-IDOR-12 (b) fix — cross-coach role contamination.
        //
        // Original rule: `role_id => required|integer|exists:coach_staff_roles,id`
        // i.e. any platform-wide role id was acceptable. Combined with
        // the attach() below, this let a coach attach ANOTHER coach's
        // CoachStaffRole to their own staff member. The staff member
        // then inherits permission slugs the receiving coach never
        // granted. Most slugs still gate on `coach_id == receivingCoach`
        // at query time so cross-tenant data isn't exposed, but role
        // configuration can drift in ways the receiving coach can't
        // audit (the row shows a role_name copied from the foreign
        // role, but the underlying role_id points outside the tenant).
        //
        // Scope the `exists:` check to roles owned by the current
        // coach via the `where('added_by', $coachId)` clause on
        // coach_staff_roles. A coach can still pick from their own
        // role catalogue freely; cross-coach picks are now rejected
        // at validation.
        $coachId = (int) (userAuth()->role === 'instructor' ? userAuth()->id : (userAuth()->coach_id ?? userAuth()->id));

        // Validation
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'status' => ['required', 'string'],
            'role_id' => [
                'required',
                'integer',
                Rule::exists('coach_staff_roles', 'id')->where('added_by', $coachId),
            ],

        ], [
            'name.required' => __('Name is required'),
            'email.unique' => __('This email is already taken'),
            'status.required' => __('Status is required'),
            'role_id.required' => __('Role is required'),
            'role_id.exists' => __('Selected role is invalid'),
        ]);

        // Update fields
        $user->name = $request->name;
        $user->email = $request->email;

        // password only if filled
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->status = $request->status;
        $user->is_banned = 'no';
        // Get role safely
        $role = CoachStaffRole::find($request->role_id);

        if ($role) {
            $user->role = $role->role_name;
        }
        $user->added_by = auth('web')->id();

        $user->save();

        // Role + per-staff overrides resolved & materialised (override > role >
        // deny). Replaces the old detach + re-attach loops.
        $this->syncStaffPermissions($user, (int) $request->role_id, $request->permissions, $coachId);

        return redirect()->route('instructor.coach-staff.index')->with('success', 'Staff updated successfully');
    }

    public function destroy($id)
    {
        $methodName = request()->route()->getActionMethod();
        $flag = checkPermission($this->pageName, $methodName);
        if ($flag == 1) {
            $user = $this->findOwnedStaffOrFail($id);
            $user->permissions()->detach();
            $user->delete();

            return redirect()->back()->with([
                'message' => 'Deleted Successfully',
                'alert-type' => 'success',
            ]);
        } else {
            return view($this->admin_error_view);
        }
    }

}
