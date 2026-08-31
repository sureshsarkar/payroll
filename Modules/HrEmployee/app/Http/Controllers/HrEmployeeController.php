<?php

namespace Modules\HrEmployee\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Modules\HrEmployee\app\Models\Department;
use Modules\HrEmployee\app\Models\EmployeeProfile;

/**
 * HR-side employee onboarding (role=instructor). HR sees the employees linked
 * to them and maintains each one's HR profile — department, reporting HR,
 * designation, employee code, DOJ and status. Setting reporting_hr_id is what
 * makes team-scoped attendance/leave/payroll resolve on real data instead of
 * the legacy coach_id fallback.
 */
class HrEmployeeController extends Controller
{
    /** List the HR's employees with their profiles + onboarding form. */
    public function index(Request $request): View
    {
        $hr        = $request->user();
        $employees = $this->linkedEmployees($hr);
        $profiles  = EmployeeProfile::whereIn('user_id', $employees->pluck('id'))
            ->get()->keyBy('user_id');

        return view('hremployee::employees', [
            'hr'          => $hr,
            'employees'   => $employees,
            'profiles'    => $profiles,
            'departments' => Department::where('is_active', true)->orderBy('name')->get(),
            'statuses'    => [EmployeeProfile::ACTIVE, EmployeeProfile::ONBOARDING, EmployeeProfile::EXITED, EmployeeProfile::SUSPENDED],
        ]);
    }

    public function create(): View
    {
        return view('hremployee::create', [
            'departments' => Department::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function edit(Request $request, User $employee): View
    {
        if (! $this->linkedEmployees($request->user())->pluck('id')->contains($employee->id)) {
            abort(403);
        }

        return view('hremployee::edit', [
            'employee' => $employee,
            'profile' => EmployeeProfile::firstOrNew(['user_id' => $employee->id]),
            'departments' => Department::where('is_active', true)->orderBy('name')->get(),
            'statuses' => [EmployeeProfile::ACTIVE, EmployeeProfile::ONBOARDING, EmployeeProfile::EXITED, EmployeeProfile::SUSPENDED],
        ]);
    }

    /** Create/update one employee's HR profile. */
    public function storeProfile(Request $request): RedirectResponse
    {
        $data = $request->validate(array_merge([
            'user_id'         => ['required', 'integer'],
            'employee_code'   => ['nullable', 'string', 'max:40'],
            'department_id'   => ['nullable', 'integer', 'exists:departments,id'],
            'designation'     => ['nullable', 'string', 'max:255'],
            'employment_type' => ['nullable', 'string', 'max:30'],
            'date_of_joining' => ['nullable', 'date'],
            'status'          => ['required', 'string', 'max:20'],
        ], $this->employeeDetailRules()));

        $hr = $request->user();
        if (! $this->linkedEmployees($hr)->pluck('id')->contains((int) $data['user_id'])) {
            return back()->with('error', 'That employee is not linked to you.');
        }

        $profileData = [
            'employee_code'   => $data['employee_code'] ?? null,
            'department_id'   => $data['department_id'] ?? null,
            'reporting_hr_id' => $hr->id,               // claims the employee onto this HR's team
            'designation'     => $data['designation'] ?? null,
            'employment_type' => $data['employment_type'] ?? null,
            'date_of_joining' => $data['date_of_joining'] ?? null,
            'status'          => $data['status'],
        ];

        if ($request->hasFile('photo')) {
            File::ensureDirectoryExists(public_path('uploads/employee-photos'));
            $filename = $request->file('photo')->hashName();
            $request->file('photo')->move(public_path('uploads/employee-photos'), $filename);
            $profileData['photo_path'] = 'uploads/employee-photos/'.$filename;
        }

        foreach (array_keys($this->employeeDetailRules()) as $field) {
            if ($field !== 'photo') {
                $profileData[$field] = $data[$field] ?? null;
            }
        }

        EmployeeProfile::updateOrCreate(
            ['user_id' => $data['user_id']],
            $profileData,
        );

        return back()->with('success', 'Employee profile saved.');
    }

    /** Create a brand-new employee account (role=student) + HR profile. */
    public function storeEmployee(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'            => ['required', 'string', 'max:191'],
            'email'           => ['required', 'email', 'max:191', 'unique:users,email'],
            'password'        => ['nullable', 'string', 'min:8', 'max:100'],
            'designation'     => ['nullable', 'string', 'max:255'],
            'department_id'   => ['nullable', 'integer', 'exists:departments,id'],
            'employment_type' => ['nullable', 'string', 'max:30'],
            'date_of_joining' => ['nullable', 'date'],
            'employee_code'   => ['nullable', 'string', 'max:40'],
        ]);

        $hr = $request->user();

        // HR may set the login password directly; otherwise generate a
        // one-time temporary password and surface it once so it can be shared.
        $autoPassword = empty($data['password']);
        $plainPass    = $autoPassword ? Str::password(10) : $data['password'];

        $user = User::create([
            'role'     => 'student',            // "Employee" in payroll terms
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($plainPass),
            'status'   => 1,
            'coach_id' => $hr->id,
            'added_by' => $hr->id,
        ]);

        EmployeeProfile::create([
            'user_id'         => $user->id,
            'employee_code'   => $data['employee_code'] ?? null,
            'department_id'   => $data['department_id'] ?? null,
            'reporting_hr_id' => $hr->id,
            'designation'     => $data['designation'] ?? null,
            'employment_type' => $data['employment_type'] ?? 'full_time',
            'date_of_joining' => $data['date_of_joining'] ?? now(),
            'status'          => EmployeeProfile::ACTIVE,
        ]);

        $message = $autoPassword
            ? "Employee '{$data['name']}' created. Temporary password: {$plainPass} (share it with them to log in)."
            : "Employee '{$data['name']}' created. They can sign in with their email and the password you set.";

        return redirect()->route('hr.employees.index')->with('success', $message);
    }

    public function updateEmployee(Request $request, User $employee): RedirectResponse
    {
        $hr = $request->user();
        if (! $this->linkedEmployees($hr)->pluck('id')->contains($employee->id)) {
            abort(403);
        }

        $data = $request->validate(array_merge([
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email,'.$employee->id],
            'password' => ['nullable', 'string', 'min:8', 'max:100'],
            'employee_code' => ['nullable', 'string', 'max:40'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'designation' => ['nullable', 'string', 'max:255'],
            'employment_type' => ['nullable', 'string', 'max:30'],
            'date_of_joining' => ['nullable', 'date'],
            'date_of_exit' => ['nullable', 'date'],
            'status' => ['required', 'string', 'max:20'],
        ], $this->employeeDetailRules()));

        $accountData = [
            'name' => $data['name'],
            'email' => $data['email'],
        ];
        if (! empty($data['password'])) {
            $accountData['password'] = Hash::make($data['password']);
        }
        $employee->update($accountData);

        $profileData = [
            'employee_code' => $data['employee_code'] ?? null,
            'department_id' => $data['department_id'] ?? null,
            'reporting_hr_id' => $hr->id,
            'designation' => $data['designation'] ?? null,
            'employment_type' => $data['employment_type'] ?? null,
            'date_of_joining' => $data['date_of_joining'] ?? null,
            'date_of_exit' => $data['date_of_exit'] ?? null,
            'status' => $data['status'],
        ];
        if ($request->hasFile('photo')) {
            File::ensureDirectoryExists(public_path('uploads/employee-photos'));
            $filename = $request->file('photo')->hashName();
            $request->file('photo')->move(public_path('uploads/employee-photos'), $filename);
            $profileData['photo_path'] = 'uploads/employee-photos/'.$filename;
        }
        foreach (array_keys($this->employeeDetailRules()) as $field) {
            if ($field !== 'photo') $profileData[$field] = $data[$field] ?? null;
        }
        EmployeeProfile::updateOrCreate(['user_id' => $employee->id], $profileData);

        $saved = ! empty($data['password'])
            ? 'Employee profile saved. The new login password is now active.'
            : 'Employee profile saved.';

        return redirect()->route('hr.employees.edit', $employee)->with('success', $saved);
    }

    /** List + create departments for the active company. */
    public function departments(Request $request): View
    {
        return view('hremployee::departments', [
            'departments' => Department::withCount('employees')->orderBy('name')->get(),
        ]);
    }

    public function storeDepartment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:40'],
        ]);

        Department::create($data + ['is_active' => true]);

        return back()->with('success', 'Department created.');
    }

    /** Rename / re-code / activate-deactivate a department in the active company. */
    public function updateDepartment(Request $request, Department $department): RedirectResponse
    {
        $data = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'code'      => ['nullable', 'string', 'max:40'],
            'is_active' => ['required', 'boolean'],
        ]);

        $department->update([
            'name'      => $data['name'],
            'code'      => $data['code'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', 'Department updated.');
    }

    /** Delete a department — only allowed while no employee is assigned to it. */
    public function destroyDepartment(Department $department): RedirectResponse
    {
        if ($department->employees()->exists()) {
            return back()->with('error', "Can't delete \"{$department->name}\" — employees are still assigned to it. Move them to another department first.");
        }

        $department->delete();

        return back()->with('success', 'Department deleted.');
    }

    /* ------------------------------------------------------------------ */

    /**
     * Employees this HR can manage in the active company.
     *
     * Delegates to EmployeeProfile::teamUserIds(), which drops the legacy
     * cross-company coach_id fallback once a company is bound. Using the old
     * unscoped `User::where('coach_id', $hr->id)` fallback here let an
     * employee from a DIFFERENT company than the active one pass this guard
     * (an HR who owns multiple companies coaches all their employees under
     * the same coach_id) — the edit page would then open, but the
     * company-scoped `updateOrCreate(['user_id'=>...])` on save couldn't find
     * that employee's profile (it belongs to another company) and tried to
     * INSERT a second row for the same user_id, hitting
     * employee_profiles_user_id_unique.
     */
    private function linkedEmployees(User $hr): Collection
    {
        return User::whereIn('id', EmployeeProfile::teamUserIds($hr))
            ->orderBy('name')->get();
    }

    /** Additional personal, banking and statutory details kept on the HR profile. */
    private function employeeDetailRules(): array
    {
        return [
            'photo' => ['nullable', 'image', 'max:2048'],
            'father_or_spouse_name' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date'],
            'phone' => ['nullable', 'string', 'max:30'],
            'personal_email' => ['nullable', 'email', 'max:191'],
            'current_address' => ['nullable', 'string', 'max:2000'],
            'permanent_address' => ['nullable', 'string', 'max:2000'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'string', 'max:100'],
            'bank_ifsc_code' => ['nullable', 'string', 'max:30'],
            'pf_number' => ['nullable', 'string', 'max:100'],
            'uan_number' => ['nullable', 'string', 'max:100'],
            'esi_number' => ['nullable', 'string', 'max:100'],
            'pan_number' => ['nullable', 'string', 'max:30'],
            'aadhaar_number' => ['nullable', 'string', 'max:30'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
        ];
    }
}
