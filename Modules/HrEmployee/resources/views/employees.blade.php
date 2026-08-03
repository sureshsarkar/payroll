@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
<div class="pv">
    @include('payroll::partials.ui')
    <div class="pv-head">
        <div><h1 class="t">Employees</h1><p class="s">View your employee directory and open profiles when changes are needed.</p></div>
        <div class="pv-actions"><a href="{{ route('hr.departments.index') }}" class="pv-btn"><i class="fas fa-sitemap"></i> Departments</a><a href="{{ route('hr.employees.create') }}" class="pv-btn g"><i class="fas fa-user-plus"></i> Add employee</a></div>
    </div>
    <div class="pv-card">
        <div class="h"><i class="fas fa-users pv-muted"></i> Your team ({{ $employees->count() }})</div>
        <div class="b tight">
            @if($employees->isEmpty())
                <div class="pv-empty"><div class="ic"><i class="fas fa-user-friends"></i></div>No employees yet. <a href="{{ route('hr.employees.create') }}">Add the first employee.</a></div>
            @else
                <div class="pv-tw"><table class="pv-table" style="min-width:760px"><thead><tr><th>Employee</th><th>Code</th><th>Department</th><th>Designation</th><th>Joining</th><th>Status</th><th></th></tr></thead><tbody>
                @foreach($employees as $emp)
                    @php($p = $profiles->get($emp->id))
                    <tr>
                        <td><strong>{{ $emp->name }}</strong><div class="pv-mut2">#{{ $emp->id }} · {{ $emp->email }}</div></td>
                        <td>{{ $p->employee_code ?? '—' }}</td><td>{{ $p?->department?->name ?? '—' }}</td><td>{{ $p->designation ?? '—' }}</td>
                        <td>{{ optional($p?->date_of_joining)->format('d-M-Y') ?? '—' }}</td>
                        <td><span class="pv-badge {{ $p?->status === 'active' ? 'ok' : '' }}">{{ ucfirst($p->status ?? 'onboarding') }}</span></td>
                        <td><a href="{{ route('hr.employees.edit', $emp) }}" class="pv-btn p sm"><i class="fas fa-pen"></i> View / edit</a></td>
                    </tr>
                @endforeach
                </tbody></table></div>
            @endif
        </div>
    </div>
</div>
@endsection
