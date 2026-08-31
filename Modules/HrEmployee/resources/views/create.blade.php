@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
<div class="pv">
    @include('payroll::partials.ui')
    <div class="pv-head"><div><h1 class="t">Add employee</h1><p class="s">Create a new employee account and basic HR profile.</p></div><div class="pv-actions"><a href="{{ route('hr.employees.index') }}" class="pv-btn"><i class="fas fa-arrow-left"></i> Employees</a></div></div>
    <div class="pv-card"><div class="h"><i class="fas fa-user-plus" style="color:var(--pv-brand)"></i> Employee information</div><div class="b"><form method="POST" action="{{ route('hr.employees.store') }}">@csrf
        <div class="pv-cols c3">
            <div class="pv-field"><label class="pv-label">Full name *</label><input name="name" value="{{ old('name') }}" class="pv-input" required placeholder="e.g. Anita Rao"></div>
            <div class="pv-field"><label class="pv-label">Email *</label><input type="email" name="email" value="{{ old('email') }}" class="pv-input" required placeholder="anita@company.com"></div>
            <div class="pv-field"><label class="pv-label">Login password</label><input type="text" name="password" value="{{ old('password') }}" class="pv-input" autocomplete="off" minlength="8" placeholder="Min 8 chars — blank = auto-generate"></div>
            <div class="pv-field"><label class="pv-label">Employee code</label><input name="employee_code" value="{{ old('employee_code') }}" class="pv-input" placeholder="EMP001"></div>
            <div class="pv-field"><label class="pv-label">Designation</label><input name="designation" value="{{ old('designation') }}" class="pv-input" placeholder="e.g. Analyst"></div>
            <div class="pv-field"><label class="pv-label">Department</label><select name="department_id" class="pv-select"><option value="">—</option>@foreach($departments as $d)<option value="{{ $d->id }}" @selected(old('department_id') == $d->id)>{{ $d->name }}</option>@endforeach</select></div>
            <div class="pv-field"><label class="pv-label">Date of joining</label><input type="date" name="date_of_joining" value="{{ old('date_of_joining') }}" class="pv-input"></div>
        </div><div style="margin-top:18px"><button class="pv-btn g"><i class="fas fa-plus"></i> Create employee</button><span class="pv-mut2" style="margin-left:10px">Set a login password to share, or leave it blank — a temporary one is generated and shown once.</span></div>
    </form></div></div>
</div>
@endsection
