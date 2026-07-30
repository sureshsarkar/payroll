<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RedirectType;
use App\Http\Controllers\Controller;
use App\Http\Requests\RoleFormRequest;
use App\Models\Admin;
use App\Traits\RedirectHelperTrait;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesController extends Controller
{
    use RedirectHelperTrait;

    /**
     * Allowed sort keys → DB columns. M6 fix (2026-05-12).
     * 'permissions' sorts by a withCount() derived column so we
     * never hand-build the ORDER BY from request input.
     */
    private const SORTABLE = [
        'name'        => 'name',
        'created_at'  => 'created_at',
        'permissions' => 'permissions_count',
    ];

    public function index(Request $request)
    {
        checkAdminHasPermissionAndThrowException('role.view');

        // M6 fix (2026-05-12) — allowlist-guarded sort with a join-free
        // withCount() to avoid N+1 when sorting by permission count.
        $sortKey = (string) $request->query('sort', 'created_at');
        $sortCol = self::SORTABLE[$sortKey] ?? 'created_at';
        $sortDir = strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $roles = Role::withCount('permissions')
            ->orderBy($sortCol, $sortDir)
            ->paginate(15)
            ->withQueryString();

        return view('admin.roles.index', [
            'roles' => $roles,
            'sort'  => $sortKey,
            'dir'   => $sortDir,
        ]);
    }

    public function create()
    {
        checkAdminHasPermissionAndThrowException('role.create');
        $permissions = Permission::all();
        $permission_groups = Admin::getPermissionGroup();

        return view('admin.roles.create', compact('permissions', 'permission_groups'));
    }

    public function store(RoleFormRequest $request)
    {
        checkAdminHasPermissionAndThrowException('role.store');
        $role = Role::create(['name' => $request->name]);
        if (! empty($request->permissions)) {
            $role->syncPermissions($request->permissions);
        }

        // Enterprise H-A — audit role creation (security-sensitive).
        \App\Services\ActivityLogger::log(
            \App\Models\ActivityLog::ROLE_CHANGED, 'role', $role,
            null, ['name' => $role->name, 'permissions' => $request->permissions ?? []],
            'Created role "' . $role->name . '"'
        );

        return $this->redirectWithMessage(RedirectType::CREATE->value, 'admin.role.index');
    }

    public function edit(Role $role)
    {
        checkAdminHasPermissionAndThrowException('role.edit');
        $permissions = Permission::all();
        $permission_groups = Admin::getPermissionGroup();

        return view('admin.roles.edit', compact('permissions', 'permission_groups', 'role'));
    }

    public function update(RoleFormRequest $request, Role $role)
    {
        checkAdminHasPermissionAndThrowException('role.update');
        $oldRole = ['name' => $role->name, 'permissions' => $role->permissions->pluck('name')->all()];
        if (! empty($request->permissions)) {
            $role->name = $request->name;
            $role->save();
            $role->syncPermissions($request->permissions);

            // Enterprise H-A — audit role/permission changes (security-sensitive).
            \App\Services\ActivityLogger::log(
                \App\Models\ActivityLog::ROLE_CHANGED, 'role', $role,
                $oldRole, ['name' => $role->name, 'permissions' => $request->permissions],
                'Updated role "' . $role->name . '"'
            );
        }

        return $this->redirectWithMessage(RedirectType::UPDATE->value, 'admin.role.index');
    }

    public function destroy(Role $role)
    {
        checkAdminHasPermissionAndThrowException('role.delete');
        abort_if($role->id == 1, 403);
        if (! is_null($role)) {
            // Enterprise H-A — audit role deletion (log before the row is gone).
            \App\Services\ActivityLogger::log(
                \App\Models\ActivityLog::ROLE_CHANGED, 'role', $role,
                ['name' => $role->name, 'permissions' => $role->permissions->pluck('name')->all()], null,
                'Deleted role "' . $role->name . '"'
            );
            $role->delete();
        }

        return $this->redirectWithMessage(RedirectType::DELETE->value, 'admin.role.index');
    }

    public function assignRoleView()
    {
        checkAdminHasPermissionAndThrowException('role.assign');
        $admins = Admin::whereStatus('active')->get();
        $roles = Role::all();

        return view('admin.roles.assign-role', compact('admins', 'roles'));
    }

    public function getAdminRoles($id)
    {
        $admin = Admin::findOrFail($id);
        $options = '<option value="" disabled>'.e(__('Select Role')).'</option>';
        if ($admin) {
            $roles = Role::all();
            foreach ($roles as $role) {
                $name = e($role->name);
                $sel  = $admin->hasRole($role->name) ? 'selected' : '';
                $options .= '<option value="'.$name.'" '.$sel.'>'.$name.'</option>';
            }

            return response()->json([
                'success' => true,
                'data' => $options,
            ]);
        }

        return response()->json([
            'success' => false,
            'data' => $options,
        ]);
    }

    public function assignRoleUpdate(Request $request)
    {
        checkAdminHasPermissionAndThrowException('role.assign');

        $messages = [
            'user_id.required' => __('You must select an admin'),
            'user_id.exists' => __('Admin not found'),
            'role.required' => __('You must select role'),
            'role.array' => __('You must select role'),
            'role.*.required' => __('You must select role'),
            'role.*.string' => __('You must select role'),
        ];

        // FT-VAL-17 (2026-05-28) — verify each role name exists.
        // Pre-fix `role.*` was just `required|string`; if the name
        // didn't match an existing roles row, Spatie::syncRoles
        // threw `RoleDoesNotExist` and the admin got a generic
        // "something went wrong" 500. Validate up front so we
        // return a friendly 422 instead.
        $request->validate([
            'user_id' => 'required|exists:admins,id',
            'role'    => 'required|array|max:50',
            'role.*'  => 'required|string|exists:roles,name',
        ], $messages);

        Admin::findOrFail($request->user_id)?->syncRoles($request->role);

        return $this->redirectWithMessage(RedirectType::UPDATE->value, 'admin.role.index');
    }
}
