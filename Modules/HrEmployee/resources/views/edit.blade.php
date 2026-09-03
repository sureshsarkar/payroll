@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
<div class="pv">
    @include('payroll::partials.ui')
    <div class="pv-head"><div><h1 class="t">Edit employee</h1><p class="s">{{ $employee->name }} <span class="pv-mut2">#{{ $employee->id }} · {{ $employee->email }}</span></p></div><div class="pv-actions"><a href="{{ route('hr.employees.index') }}" class="pv-btn"><i class="fas fa-arrow-left"></i> Employees</a></div></div>
    <form method="POST" action="{{ route('hr.employees.update', $employee) }}" enctype="multipart/form-data">@csrf @method('PUT')
        <div class="pv-card"><div class="h"><i class="fas fa-user-circle" style="color:var(--pv-brand)"></i> Account</div><div class="b"><div class="pv-cols c3">
            <div class="pv-field"><label class="pv-label">Full name *</label><input name="name" value="{{ old('name', $employee->name) }}" class="pv-input" required></div>
            <div class="pv-field"><label class="pv-label">Login email *</label><input type="email" name="email" value="{{ old('email', $employee->email) }}" class="pv-input" required></div>
            <div class="pv-field"><label class="pv-label">Reset login password</label><input type="text" name="password" value="{{ old('password') }}" class="pv-input" autocomplete="off" minlength="8" placeholder="Min 8 chars — blank = unchanged"><span class="pv-mut2">The employee can also change this from their own profile.</span></div>
        </div></div></div>
        <div class="pv-card"><div class="h"><i class="fas fa-id-card" style="color:var(--pv-brand)"></i> Employment details</div><div class="b"><div class="pv-cols c3">
            <div class="pv-field"><label class="pv-label">Employee code</label><input name="employee_code" value="{{ old('employee_code', $profile->employee_code) }}" class="pv-input"></div>
            <div class="pv-field"><label class="pv-label">Department</label><select name="department_id" class="pv-select"><option value="">—</option>@foreach($departments as $d)<option value="{{ $d->id }}" @selected(old('department_id', $profile->department_id) == $d->id)>{{ $d->name }}</option>@endforeach</select></div>
            <div class="pv-field"><label class="pv-label">Designation</label><input name="designation" value="{{ old('designation', $profile->designation) }}" class="pv-input"></div>
            <div class="pv-field"><label class="pv-label">Employment type</label><select name="employment_type" class="pv-select">@foreach(['full_time'=>'Full-time','part_time'=>'Part-time','contract'=>'Contract','intern'=>'Intern'] as $v=>$label)<option value="{{ $v }}" @selected(old('employment_type', $profile->employment_type ?? 'full_time') === $v)>{{ $label }}</option>@endforeach</select></div>
            <div class="pv-field"><label class="pv-label">Date of joining</label><input type="date" name="date_of_joining" value="{{ old('date_of_joining', optional($profile->date_of_joining)->format('Y-m-d')) }}" class="pv-input"></div>
            <div class="pv-field"><label class="pv-label">Date of exit</label><input type="date" name="date_of_exit" value="{{ old('date_of_exit', optional($profile->date_of_exit)->format('Y-m-d')) }}" class="pv-input"></div>
            <div class="pv-field"><label class="pv-label">Status *</label><select name="status" class="pv-select" required>@foreach($statuses as $status)<option value="{{ $status }}" @selected(old('status', $profile->status ?? 'active') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
        </div></div></div>
        <div class="pv-card"><div class="h"><i class="fas fa-user" style="color:var(--pv-brand)"></i> Personal details</div><div class="b"><div class="pv-cols c3">
            <div class="pv-field">
                <label class="pv-label">Profile photo</label>
                @if($profile->photo_path && is_file(public_path($profile->photo_path)))
                    <div style="margin-bottom:6px"><img src="{{ asset($profile->photo_path) }}" alt="" style="width:56px;height:56px;border-radius:50%;object-fit:cover"></div>
                @endif
                <input type="file" name="photo" class="pv-input" accept="image/*">
            </div>
            <div class="pv-field"><label class="pv-label">Father's or spouse's name</label><input name="father_or_spouse_name" value="{{ old('father_or_spouse_name', $profile->father_or_spouse_name) }}" class="pv-input"></div>
            <div class="pv-field"><label class="pv-label">Date of birth</label><input type="date" name="date_of_birth" value="{{ old('date_of_birth', optional($profile->date_of_birth)->format('Y-m-d')) }}" class="pv-input"></div>
            <div class="pv-field"><label class="pv-label">Phone number</label><input name="phone" value="{{ old('phone', $profile->phone) }}" class="pv-input"></div>
            <div class="pv-field"><label class="pv-label">Personal email</label><input type="email" name="personal_email" value="{{ old('personal_email', $profile->personal_email) }}" class="pv-input"></div>
            <div class="pv-field"><label class="pv-label">Emergency contact name</label><input name="emergency_contact_name" value="{{ old('emergency_contact_name', $profile->emergency_contact_name) }}" class="pv-input"></div>
            <div class="pv-field"><label class="pv-label">Emergency contact phone</label><input name="emergency_contact_phone" value="{{ old('emergency_contact_phone', $profile->emergency_contact_phone) }}" class="pv-input"></div>
            <div class="pv-field"><label class="pv-label">Bank name</label><input name="bank_name" value="{{ old('bank_name', $profile->bank_name) }}" class="pv-input"></div>
            <div class="pv-field"><label class="pv-label">Bank account number</label><input name="bank_account_number" value="{{ old('bank_account_number', $profile->bank_account_number) }}" class="pv-input"></div>
            <div class="pv-field"><label class="pv-label">Bank IFSC code</label><input name="bank_ifsc_code" value="{{ old('bank_ifsc_code', $profile->bank_ifsc_code) }}" class="pv-input"></div>
            <div class="pv-field"><label class="pv-label">PF number</label><input name="pf_number" value="{{ old('pf_number', $profile->pf_number) }}" class="pv-input"></div>
            <div class="pv-field"><label class="pv-label">UAN number</label><input name="uan_number" value="{{ old('uan_number', $profile->uan_number) }}" class="pv-input"></div>
            <div class="pv-field"><label class="pv-label">ESI number</label><input name="esi_number" value="{{ old('esi_number', $profile->esi_number) }}" class="pv-input"></div>
            <div class="pv-field"><label class="pv-label">PAN number</label><input name="pan_number" value="{{ old('pan_number', $profile->pan_number) }}" class="pv-input"></div>
            <div class="pv-field"><label class="pv-label">Aadhaar number</label><input name="aadhaar_number" value="{{ old('aadhaar_number', $profile->aadhaar_number) }}" class="pv-input"></div>
            <div class="pv-field"><label class="pv-label">Current address</label><textarea name="current_address" class="pv-input" rows="3">{{ old('current_address', $profile->current_address) }}</textarea></div>
            <div class="pv-field"><label class="pv-label">Permanent address</label><textarea name="permanent_address" class="pv-input" rows="3">{{ old('permanent_address', $profile->permanent_address) }}</textarea></div>
        </div></div></div>
        <div class="pv-actions" style="justify-content:flex-end"><a href="{{ route('hr.employees.index') }}" class="pv-btn">Cancel</a><button class="pv-btn p"><i class="fas fa-save"></i> Save employee</button></div>
    </form>

    <div class="pv-card" style="border-color:#f6caca">
        <div class="h" style="color:var(--pv-red)"><i class="fas fa-triangle-exclamation"></i> Danger zone</div>
        <div class="b" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
            <div class="pv-mut2" style="max-width:520px">Deleting removes {{ $employee->name }} from attendance, leave, payroll and salary screens. It is a soft delete — the record stays in the database and can be restored from the Employees list.</div>
            <form method="POST" action="{{ route('hr.employees.destroy', $employee) }}"
                  onsubmit="return confirm('Delete {{ $employee->name }}? This is reversible from “Deleted employees”.')">
                @csrf @method('DELETE')
                <button class="pv-btn d"><i class="fas fa-trash"></i> Delete employee</button>
            </form>
        </div>
    </div>
</div>
@endsection
