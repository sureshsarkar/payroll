<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RedirectType;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Traits\RedirectHelperTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminController extends Controller
{
    use RedirectHelperTrait;

    /**
     * Allowed sort keys → DB columns. M6 fix (2026-05-12). The map
     * indirection lets us expose a friendly key on the URL ("name")
     * while ordering by the real column ("name"). Building orderBy()
     * from request input directly is an injection vector — we allowlist.
     */
    private const SORTABLE = [
        'name'       => 'name',
        'email'      => 'email',
        'status'     => 'status',
        'created_at' => 'created_at',
    ];

    public function index(Request $request)
    {
        checkAdminHasPermissionAndThrowException('admin.view');

        // M6 fix (2026-05-12) — allowlist-guarded sort. URL ?sort=…&dir=…
        // round-trips through the view's <th> links via the shared
        // sort-header partial.
        $sortKey = (string) $request->query('sort', 'created_at');
        $sortCol = self::SORTABLE[$sortKey] ?? 'created_at';
        $sortDir = strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $admins = Admin::orderBy($sortCol, $sortDir)
            ->paginate(15)
            ->withQueryString();

        return view('admin.admin-list.admin')->with([
            'admins' => $admins,
            'sort'   => $sortKey,
            'dir'    => $sortDir,
        ]);

    }

    public function create()
    {
        checkAdminHasPermissionAndThrowException('admin.create');
        $roles = Role::all();

        return view('admin.admin-list.create_admin', compact('roles'));
    }

    public function store(Request $request)
    {
        checkAdminHasPermissionAndThrowException('admin.store');
        // FT-VAL-1 fix (2026-05-27) — added `email|max:190` so the
        // platform-operator account create form requires a real email
        // address (and isn't capped only by the DB column length).
        $rules = [
            'name'     => 'required|string|max:190',
            'email'    => 'required|email|max:190|unique:admins,email',
            'password' => 'required|min:8',
            'status'   => 'required',
            // FT-VAL-17 (extension, 2026-05-28) — verify each role
            // name exists. Same shape as the RolesController fix:
            // without this, Spatie::syncRoles throws RoleDoesNotExist
            // on typo'd / forged role names and the admin sees a 500.
            'role'     => 'nullable|array|max:50',
            'role.*'   => 'string|exists:roles,name',
        ];
        $customMessages = [
            'name.required' => __('Name is required'),
            'email.required' => __('Email is required'),
            'email.email'    => __('Please enter a valid email address'),
            'status.required' => __('Status is required'),
            'email.unique' => __('Email already exist'),
            'password.required' => __('Password is required'),
            'password.min' => __('Password Must be 8 characters'),
            'role.array' => __('You must select role'),
        ];
        $this->validate($request, $rules, $customMessages);

        $admin = new Admin();
        $admin->name = $request->name;
        $admin->email = $request->email;
        $admin->status = $request->status;
        $admin->password = Hash::make($request->password);
        $admin->save();
        if ($request->role) {
            $admin->syncRoles($request->role);
        }

        return $this->redirectWithMessage(RedirectType::CREATE->value, 'admin.admin.index');
    }

    public function edit($id)
    {
        checkAdminHasPermissionAndThrowException('admin.edit');
        $admin = Admin::findOrFail($id);
        $roles = Role::all();

        return view('admin.admin-list.edit_admin', compact('roles', 'admin'));
    }

    public function update(Request $request, $id)
    {
        checkAdminHasPermissionAndThrowException('admin.update');
        $admin = Admin::findOrFail($id);
        abort_if($admin->id == 1, 403);
        // FT-VAL-1 fix (2026-05-27) — see store() above.
        $rules = [
            'name'     => 'required|string|max:190',
            'email'    => 'required|email|max:190|unique:admins,email,'.$admin->id,
            'password' => 'nullable|min:8',
            'status'   => 'required',
            // FT-VAL-17 (extension, 2026-05-28) — verify each role
            // name exists. Same shape as the RolesController fix:
            // without this, Spatie::syncRoles throws RoleDoesNotExist
            // on typo'd / forged role names and the admin sees a 500.
            'role'     => 'nullable|array|max:50',
            'role.*'   => 'string|exists:roles,name',
        ];
        $customMessages = [
            'name.required' => __('Name is required'),
            'email.required' => __('Email is required'),
            'email.unique' => __('Email already exist'),
            'password.min' => __('Password Must be 8 characters'),
            'role.array' => __('You must select role'),
        ];
        $this->validate($request, $rules, $customMessages);

        $admin->name = $request->name;
        $admin->email = $request->email;
        $admin->status = $request->status;
        if ($request->filled('password')) {
            $admin->password = Hash::make($request->password);
        }

        $admin->save();
        if ($request->role) {
            $admin->syncRoles($request->role);
        }

        return $this->redirectWithMessage(RedirectType::UPDATE->value, 'admin.admin.index');
    }

    public function destroy($id)
    {
        checkAdminHasPermissionAndThrowException('admin.delete');
        $admin = Admin::findOrFail($id);
        abort_if($admin->id == 1, 403);
        $admin->delete();

        return $this->redirectWithMessage(RedirectType::DELETE->value, 'admin.admin.index');
    }

    public function changeStatus($id)
    {
        checkAdminHasPermissionAndThrowException('admin.update');
        $admin = Admin::findOrFail($id);
        abort_if($admin->id == 1, 403);
        $status = $admin->status == 'active' ? 'inactive' : 'active';
        $admin->status = $status;
        $admin->save();
        $notification = __('Updated Successfully');

        return response()->json([
            'success' => true,
            'message' => $notification,
        ]);
    }
}
