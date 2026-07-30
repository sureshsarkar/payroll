@extends('attendance::layouts.payroll')
@section('title', 'Employees')
@section('subtitle', 'HR · '.$hr->name)

@section('content')
<div class="d-flex align-items-center mb-3">
    <h4 class="mb-0">Employees</h4>
    <a href="{{ route('hr.departments.index') }}" class="btn btn-sm btn-outline-primary ms-auto">Departments</a>
</div>

<div class="card stat-card"><div class="card-body">
    @if($employees->isEmpty())
        <p class="text-muted mb-0">No employees linked to you yet.</p>
    @else
    <div class="table-responsive">
    <table class="table align-middle">
        <thead><tr>
            <th>Employee</th><th>Code</th><th>Department</th><th>Designation</th>
            <th>Type</th><th>DOJ</th><th>Status</th><th></th>
        </tr></thead>
        <tbody>
        @foreach($employees as $emp)
            @php $p = $profiles->get($emp->id); @endphp
            <form method="POST" action="{{ route('hr.employees.profile') }}">
                @csrf
                <input type="hidden" name="user_id" value="{{ $emp->id }}">
                <tr>
                    <td>{{ $emp->name }} <span class="text-muted small">#{{ $emp->id }}</span></td>
                    <td><input name="employee_code" value="{{ $p->employee_code ?? '' }}" class="form-control form-control-sm" style="width:90px" placeholder="EMP001"></td>
                    <td>
                        <select name="department_id" class="form-select form-select-sm" style="width:130px">
                            <option value="">—</option>
                            @foreach($departments as $d)
                                <option value="{{ $d->id }}" {{ ($p->department_id ?? null)==$d->id?'selected':'' }}>{{ $d->name }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td><input name="designation" value="{{ $p->designation ?? '' }}" class="form-control form-control-sm" style="width:130px" placeholder="e.g. Analyst"></td>
                    <td>
                        <select name="employment_type" class="form-select form-select-sm" style="width:110px">
                            @foreach(['full_time'=>'Full-time','part_time'=>'Part-time','contract'=>'Contract','intern'=>'Intern'] as $v=>$lbl)
                                <option value="{{ $v }}" {{ ($p->employment_type ?? '')==$v?'selected':'' }}>{{ $lbl }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td><input type="date" name="date_of_joining" value="{{ optional($p?->date_of_joining)->format('Y-m-d') }}" class="form-control form-control-sm" style="width:140px"></td>
                    <td>
                        <select name="status" class="form-select form-select-sm" style="width:120px">
                            @foreach($statuses as $s)
                                <option value="{{ $s }}" {{ ($p->status ?? 'active')==$s?'selected':'' }}>{{ ucfirst($s) }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td><button class="btn btn-sm btn-success">Save</button></td>
                </tr>
            </form>
        @endforeach
        </tbody>
    </table>
    </div>
    <p class="text-muted small mb-0">Saving an employee assigns them to your team (reporting HR = you), so their attendance, leave and payroll scope to you.</p>
    @endif
</div></div>
@endsection
