<?php

namespace App\Http\Controllers\Frontend\Coach;

use App\Http\Controllers\Controller;
use App\Models\CoachStaff;
use App\Models\CoachStaffRole;
use App\Services\CoachPermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CoachStaffRoleController extends Controller
{
    protected $pageName;

    public function __construct(CoachStaffRole $model)
    {
        $this->model = $model;
        $this->admin_base_url = 'instructor.coach-staff-role.index';
        $this->admin_view = 'frontend.instructor-dashboard.coach-staff-role';
        $this->admin_error_view = 'errors.403';
        $this->pageName = 'roles';
    }

    public function index(Request $request)
    {
        $page = $this->pageName;
        // FT-IDOR-11 fix (2026-05-28) — was `$flag = 1;` hard-coded
        // bypass. Cross-coach IDOR is separately prevented by
        // findOwnedRoleOrFail / `where('added_by', $coachId)`, but
        // the bypass let a staff member without the `roles`
        // permission slug manage their own coach's staff-role
        // structure (rename, delete, change perms on the role they
        // belong to themselves — potential privilege escalation
        // within-tenant). Restore the real permission check.
        $flag = checkPermission($this->pageName);
        if ($flag != 1) {
            return view($this->admin_error_view);
        }

        // IDOR fix 2026-05-12 — pre-fix index() listed every coach's
        // roles to every coach. Scoped to current coach's own rows
        // (added_by).
        $coachId = userAuth()->role === 'instructor' ? userAuth()->id : userAuth()->coach_id;

        // 2026-05-20 corporate redesign: search + KPI strip + per-row
        // staff/permission counts.
        $query = $this->model::query()->where('added_by', $coachId);

        if ($request->filled('q')) {
            $query->where('role_name', 'like', '%' . trim($request->q) . '%');
        }
        if ($request->filled('status') && in_array($request->status, ['1', '0'], true)) {
            $query->where('status', (int) $request->status);
        }

        $coachStaffRole = $query
            ->withCount(['permissions as permissions_count'])
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        // Per-row staff count. CoachStaff is just a User row scoped
        // to (added_by=this coach, role!='student') with users.role_id
        // pointing at CoachStaffRole.
        $roleIds = $coachStaffRole->pluck('id')->all();
        $staffCounts = empty($roleIds) ? collect() : \DB::table('users')
            ->whereIn('role_id', $roleIds)
            ->where('added_by', $coachId)
            ->select('role_id', \DB::raw('COUNT(*) as c'))
            ->groupBy('role_id')
            ->pluck('c', 'role_id');
        $coachStaffRole->getCollection()->transform(function ($r) use ($staffCounts) {
            $r->staff_count = (int) ($staffCounts[$r->id] ?? 0);
            return $r;
        });

        // KPI snapshot — full unfiltered scope.
        $baseScope = $this->model::query()->where('added_by', $coachId);
        $kpi = [
            'total'   => (clone $baseScope)->count(),
            'active'  => (clone $baseScope)->where('status', 1)->count(),
            'pending' => (clone $baseScope)->where('status', '!=', 1)->count(),
            'staffed' => (int) \DB::table('users')
                ->where('added_by', $coachId)
                ->whereIn('role_id', (clone $baseScope)->select('id'))
                ->distinct('role_id')
                ->count('role_id'),
        ];

        return view($this->admin_view.'.index', compact('coachStaffRole', 'kpi'));
    }

    public function create()
    {
        // FT-IDOR-11 fix (2026-05-28) — was `$flag = 1;` hard-coded
        // bypass. Cross-coach IDOR is separately prevented by
        // findOwnedRoleOrFail / `where('added_by', $coachId)`, but
        // the bypass let a staff member without the `roles`
        // permission slug manage their own coach's staff-role
        // structure (rename, delete, change perms on the role they
        // belong to themselves — potential privilege escalation
        // within-tenant). Restore the real permission check.
        $flag = checkPermission($this->pageName);
        $arr = [];
        if ($flag == 1) {
            return view($this->admin_view.'.create', compact('arr'));
        } else {
            return view($this->admin_error_view);
        }
    }

    public function store(Request $request)
    {
        // FT-IDOR-11 follow-up (2026-06-17) — store() was the one mutating
        // sibling missing the permission gate that create/edit/show/destroy all
        // carry. Without it, a coach-staff member WITHOUT the 'roles' permission
        // could create custom roles (then grant themselves permissions) =
        // within-tenant privilege escalation. Restore the gate.
        if (checkPermission($this->pageName) != 1) {
            return view($this->admin_error_view);
        }

        $userId = auth('web')->id();

        $rules = [
            'role_name' => [
                'required',
                'string',
                'max:255',
                // SECURITY (2026-06-01) — a coach-staff member's users.role is
                // derived from this role_name (CoachStaffController::store).
                // checkPermission() returns 1 for ANY users.role==='instructor'
                // and TeacherBatchAssignment::assignedBatchIdsFor() returns
                // null (unbounded) for it — so a staff role literally named
                // 'instructor' (or another reserved sentinel) would grant full
                // coach-panel access + bypass teacher batch scoping. Reject the
                // reserved system role values (case-insensitive).
                function ($attribute, $value, $fail) {
                    $reserved = ['instructor', 'student', 'admin', 'super-admin', 'superadmin', 'institute-branch'];
                    if (in_array(strtolower(trim((string) $value)), $reserved, true)) {
                        $fail(__('This role name is reserved by the system and cannot be used.'));
                    }
                },
                Rule::unique('coach_staff_roles')
                    ->where(function ($query) use ($userId) {
                        return $query->where('added_by', $userId);
                    }),
            ],
              'role_slug' => [
                'required',
                'string',
                'max:255',
                     Rule::unique('coach_staff_roles')
                    ->where(function ($query) use ($userId) {
                        return $query->where('added_by', $userId);
                    }),
            ],
            'status' => ['required', 'in:1,0'],
        ];

        $messages = [
            'role_name.required' => __('Name is required'),
            'role_name.unique' => __('This role already exists for you'),
            'role_slug.required' => __('Slug is required'),
            'role_slug.unique' => __('This Slug already exists for you'),
            'status.required' => __('Status is required'),
        ];

        $request->validate($rules, $messages);

        $role = new CoachStaffRole;

        $role->role_name = $request->role_name;
        $role->role_slug = $request->role_slug;
        $role->added_by = $userId;
        $role->status = $request->status;
        $role->save();

        // 2026-05-26 (bug-doc C9) — when the coach submits the form
        // without checking any permission, the request field is null
        // and `foreach (null as ...)` throws TypeError in PHP 8. Default
        // to an empty array so a permission-less role is allowed (the
        // coach can edit it later to add permissions).
        $listOfPermissions = $request->input('roles_permissions_id', []) ?? [];
        if (! is_array($listOfPermissions)) {
            $listOfPermissions = [];
        }

        foreach ($listOfPermissions as $permission) {
            $role->permissions()->attach($permission);
        }

        return redirect()->route($this->admin_base_url)->with('success', 'Successfully Added');

    }

    public function show($id)
    {
        // FT-IDOR-11 fix (2026-05-28) — was `$flag = 1;` hard-coded
        // bypass. Cross-coach IDOR is separately prevented by
        // findOwnedRoleOrFail / `where('added_by', $coachId)`, but
        // the bypass let a staff member without the `roles`
        // permission slug manage their own coach's staff-role
        // structure (rename, delete, change perms on the role they
        // belong to themselves — potential privilege escalation
        // within-tenant). Restore the real permission check.
        $flag = checkPermission($this->pageName);
        if ($flag == 1) {
            return redirect()->route($this->admin_base_url);
        } else {
            return view($this->admin_error_view);
        }
    }

    /**
     * Find a role belonging to the current coach. Aborts 404 on cross-coach access.
     */
    private function findOwnedRoleOrFail($id)
    {
        $coachId = userAuth()->role === 'instructor' ? userAuth()->id : userAuth()->coach_id;
        return $this->model::where('id', $id)->where('added_by', $coachId)->firstOrFail();
    }

    public function edit($id)
    {
        // FT-IDOR-11 fix (2026-05-28) — was `$flag = 1;` hard-coded
        // bypass. Cross-coach IDOR is separately prevented by
        // findOwnedRoleOrFail / `where('added_by', $coachId)`, but
        // the bypass let a staff member without the `roles`
        // permission slug manage their own coach's staff-role
        // structure (rename, delete, change perms on the role they
        // belong to themselves — potential privilege escalation
        // within-tenant). Restore the real permission check.
        $flag = checkPermission($this->pageName);
        if ($flag == 1) {
            $role = $this->findOwnedRoleOrFail($id);

            $arr = [];
            if (isset($role->permissions) && $role->permissions->count() > 0) {
                foreach ($role->permissions as $permission) {
                    $arr[] = $permission->id;
                }
            }

            if ($role) {
                return view($this->admin_view.'.edit', compact('role', 'arr'));
            }

            return redirect()->route($this->admin_base_url)->with('danger', 'Invalid Calling');
        } else {
            return view($this->admin_error_view);
        }
    }

    public function update(Request $request, $id)
    {
        // FT-IDOR-11 follow-up (2026-06-17) — same missing gate as store().
        // IDOR is already prevented by findOwnedRoleOrFail, but a staff member
        // lacking the 'roles' permission must not be able to rewrite a role's
        // permission set. Restore the gate (matches edit/show/destroy).
        if (checkPermission($this->pageName) != 1) {
            return view($this->admin_error_view);
        }

        $role = $this->findOwnedRoleOrFail($id);

        $userId = auth('web')->id();

        $validated = $request->validate([
            'role_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('coach_staff_roles')
                    ->where(fn ($query) => $query->where('added_by', $userId))
                    ->ignore($role->id),
            ],
            'role_slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('coach_staff_roles')
                    ->where(fn ($query) => $query->where('added_by', $userId))
                    ->ignore($role->id),
            ],
            'status' => ['required', 'in:1,0'],
            'roles_permissions_id' => ['required', 'array'],
        ], [
            'role_name.required' => __('Name is required'),
            'role_name.unique' => __('This role already exists for you'),
            'role_slug.required' => __('Slug is required'),
            'role_slug.unique' => __('This Slug already exists for you'),
            'status.required' => __('Status is required'),
            'roles_permissions_id.required' => __('Please select at least one permission'),
        ]);

        // Update role
        $role->update([
            'role_name' => $validated['role_name'],
            'role_slug' => $validated['role_slug'],
            'status' => $validated['status'],
            'added_by' => $userId,
        ]);

        // 🔥 Sync permissions (BEST PRACTICE)
        $role->permissions()->sync($validated['roles_permissions_id']);

        return redirect()
            ->route($this->admin_base_url)
            ->with('success', 'Successfully Updated');
    }

    public function destroy($id)
    {
        $methodName = request()->route()->getActionMethod();
        // FT-IDOR-11 fix (2026-05-28) — see above. Destroy path also
        // uses the (pageName, methodName) overload because `destroy`
        // maps to a separate "roles-delete" permission slug.
        $flag = checkPermission($this->pageName, $methodName);
        if ($flag == 1) {
            $exist = $this->findOwnedRoleOrFail($id);
            $coachId = userAuth()->role === 'instructor' ? userAuth()->id : userAuth()->coach_id;

            // 2026-07-04 (RBAC Phase 5) — deleting a role must NOT leave its
            // former staff holding the role's permissions. The live gate reads
            // the MATERIALISED users_permissions snapshot, so without this the
            // staff would keep every module the (now-deleted) role granted.
            // Re-sync each affected staff to "no role", preserving only their
            // own explicit grant/revoke overrides. Runs in one transaction so a
            // partial failure never orphans a staff on a half-deleted role.
            $svc = app(CoachPermissionService::class);
            DB::transaction(function () use ($exist, $coachId, $svc) {
                $affected = DB::table('users_roles')
                    ->where('coach_staff_role_id', $exist->id)
                    ->pluck('coach_staff_id');

                foreach ($affected as $staffId) {
                    $staff = CoachStaff::find($staffId);
                    if (! $staff) {
                        continue;
                    }
                    $ov = $svc->overrideMap((int) $staffId);
                    $svc->syncStaff($staff, null, $ov['grant'] ?? [], $ov['revoke'] ?? [], (int) $coachId);
                }

                $exist->permissions()->detach();
                $exist->delete();
            });

            return redirect()->route($this->admin_base_url)->with('success', 'Successfully Deleted');
        } else {
            return view($this->admin_error_view);
        }
    }
}
