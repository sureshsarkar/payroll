@extends('attendance::layouts.payroll')
@section('title', 'HR Dashboard')
@section('subtitle', 'HR · '.$hr->name)

@php
    $cards = [
        ['Team size', $teamCount, '#1f2d3d', 'bi-people'],
        ['Present today', $presentToday, '#1e9e5a', 'bi-check2-circle'],
        ['On leave today', $onLeaveToday, '#5b6ee1', 'bi-airplane'],
        ['Pending leave approvals', $pendingLeaves, '#e08a1e', 'bi-hourglass-split'],
        ['Team attendance %', $attendancePct.'%', '#0f9bb0', 'bi-graph-up'],
    ];
@endphp

@section('content')
<h4 class="mb-3">Welcome, {{ $hr->name }}</h4>

<div class="row g-3 mb-4">
    @foreach($cards as [$label,$val,$color,$icon])
        <div class="col-6 col-lg">
            <div class="card stat-card h-100"><div class="card-body text-center">
                <div class="h3 mb-0" style="color:{{ $color }}">{{ $val }}</div>
                <div class="small text-muted">{{ $label }}</div>
            </div></div>
        </div>
    @endforeach
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card stat-card"><div class="card-body">
            <h6 class="text-muted mb-3">This month's payroll · {{ now()->format('F Y') }}</h6>
            @if($monthRun)
                @php $map=['draft'=>'secondary','hr_submitted'=>'warning','admin_approved'=>'success','paid'=>'info']; @endphp
                <p class="mb-1">Status: <span class="badge bg-{{ $map[$monthRun->status] ?? 'secondary' }}">{{ str_replace('_',' ',$monthRun->status) }}</span></p>
                <p class="mb-1">Employees: <strong>{{ $monthRun->employee_count }}</strong> · Total net: <strong>₹{{ number_format($monthRun->total_net,2) }}</strong></p>
                <a href="{{ route('hr.payroll.show',$monthRun) }}" class="btn btn-sm btn-outline-primary mt-2">Open run</a>
            @else
                <p class="text-muted">No payroll run for this month yet.</p>
                <a href="{{ route('hr.payroll.index') }}" class="btn btn-sm btn-success">Prepare payroll</a>
            @endif
        </div></div>
    </div>
    <div class="col-lg-5">
        <div class="card stat-card"><div class="card-body">
            <h6 class="text-muted mb-3">Quick actions</h6>
            <div class="d-grid gap-2">
                <a href="{{ route('hr.attendance.team') }}" class="btn btn-sm btn-outline-secondary text-start">🗓️ Mark team attendance</a>
                <a href="{{ route('hr.leave.index') }}" class="btn btn-sm btn-outline-secondary text-start">✅ Review leave requests @if($pendingLeaves)<span class="badge bg-warning ms-1">{{ $pendingLeaves }}</span>@endif</a>
                <a href="{{ route('hr.employees.index') }}" class="btn btn-sm btn-outline-secondary text-start">👥 Onboard employees</a>
                <a href="{{ route('hr.salary.index') }}" class="btn btn-sm btn-outline-secondary text-start">💰 Salary structures</a>
            </div>
        </div></div>
    </div>
</div>
@endsection
