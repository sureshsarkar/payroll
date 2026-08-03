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
            'designation'     => ['nullable', 'string', 'max:255'],
            'department_id'   => ['nullable', 'integer', 'exists:departments,id'],
            'employment_type' => ['nullable', 'string', 'max:30'],
            'date_of_joining' => ['nullable', 'date'],
            'employee_code'   => ['nullable', 'string', 'max:40'],
        ]);

        $hr       = $request->user();
        $tempPass = Str::password(10);

        $user = User::create([
            'role'     => 'student',            // "Employee" in payroll terms
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($tempPass),
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

        return redirect()->route('hr.employees.index')
            ->with('success', "Employee '{$data['name']}' created. Temporary password: {$tempPass} (share it with them to log in).");
    }

    public function updateEmployee(Request $request, User $employee): RedirectResponse
    {
        $hr = $request->user();
        if (! $this->linkedEmployees($hr)->pluck('id')->contains($employee->id)) {
            abort(403);
        }

        $data = $request->validate(array_merge([
            'employee_code' => ['nullable', 'string', 'max:40'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'designation' => ['nullable', 'string', 'max:255'],
            'employment_type' => ['nullable', 'string', 'max:30'],
            'date_of_joining' => ['nullable', 'date'],
            'status' => ['required', 'string', 'max:20'],
        ], $this->employeeDetailRules()));

        $profileData = [
            'employee_code' => $data['employee_code'] ?? null,
            'department_id' => $data['department_id'] ?? null,
            'reporting_hr_id' => $hr->id,
            'designation' => $data['designation'] ?? null,
            'employment_type' => $data['employment_type'] ?? null,
            'date_of_joining' => $data['date_of_joining'] ?? null,
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

        return redirect()->route('hr.employees.edit', $employee)->with('success', 'Employee profile saved.');
    }

    /** List + create departments (company-wide). */
    public function departments(Request $request): View
    {
        return view('hremployee::departments', [
            'departments' => Department::withCount('employees')->orderBy('name')->get(),
            'hrs'         => User::where('role', 'instructor')->orderBy('name')->get(),
        ]);
    }

    public function storeDepartment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'code'         => ['nullable', 'string', 'max:40'],
            'head_user_id' => ['nullable', 'integer'],
        ]);

        Department::create($data + ['is_active' => true]);

        return back()->with('success', 'Department created.');
    }

    /* ------------------------------------------------------------------ */

    /**
     * Employees this HR can manage: anyone already reporting to them, plus
     * their legacy coach_id-linked students (so onboarding has a starting pool).
     */
    private function linkedEmployees(User $hr): Collection
    {
        $byProfile = EmployeeProfile::where('reporting_hr_id', $hr->id)->pluck('user_id');
        $byCoach   = User::where('role', 'student')->where('coach_id', $hr->id)->pluck('id');

        return User::whereIn('id', $byProfile->merge($byCoach)->unique())
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
