@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
@php
    $cards = [
        ['Team size', $teamCount, 'var(--pv-brand)', 'fa-users'],
        ['Present today', $presentToday, 'var(--pv-green)', 'fa-user-check'],
        ['On leave today', $onLeaveToday, 'var(--pv-violet)', 'fa-plane-departure'],
        ['Pending approvals', $pendingLeaves, 'var(--pv-amber)', 'fa-hourglass-half'],
        ['Attendance %', $attendancePct.'%', 'var(--pv-sky)', 'fa-chart-line'],
    ];
    $map = ['draft'=>'draft','hr_submitted'=>'hr_submitted','admin_approved'=>'admin_approved','paid'=>'paid'];
@endphp

<div class="pv">
    @include('payroll::partials.ui')

    <div class="pv-head">
        <div>
            <h1 class="t">HR Dashboard</h1>
            <p class="s">Welcome back, {{ $hr->name }} — here's your team at a glance.</p>
        </div>
    </div>

    <div class="pv-stats">
        @foreach($cards as [$label,$val,$color,$icon])
            <div class="pv-stat" style="--c:{{ $color }}">
                <div class="n">{{ $val }}</div>
                <div class="l"><i class="fas {{ $icon }}" style="margin-right:5px;opacity:.7"></i>{{ $label }}</div>
            </div>
        @endforeach
    </div>

    <div class="pv-cols c73">
        <div class="pv-card">
            <div class="h"><i class="fas fa-receipt pv-muted"></i> This month's payroll · {{ now()->format('F Y') }}</div>
            <div class="b">
                @if($monthRun)
                    <p style="margin:0 0 8px">Status: <span class="pv-badge {{ $monthRun->status }}">{{ str_replace('_',' ',$monthRun->status) }}</span></p>
                    <p class="pv-muted" style="margin:0 0 14px">
                        {{ $monthRun->employee_count }} employees · Total net
                        <strong style="color:var(--pv-ink)">₹{{ number_format($monthRun->total_net,2) }}</strong>
                    </p>
                    <a href="{{ route('hr.payroll.show',$monthRun) }}" class="pv-btn p">Open run</a>
                @else
                    <p class="pv-muted" style="margin:0 0 14px">No payroll run for this month yet.</p>
                    <a href="{{ route('hr.payroll.index') }}" class="pv-btn g"><i class="fas fa-plus"></i> Prepare payroll</a>
                @endif
            </div>
        </div>

        <div class="pv-card">
            <div class="h"><i class="fas fa-bolt pv-muted"></i> Quick actions</div>
            <div class="b">
                <div class="pv-grid-actions">
                    <a class="pv-linkrow" href="{{ route('hr.attendance.team') }}"><i class="fas fa-calendar-check" style="color:var(--pv-sky)"></i> Mark team attendance</a>
                    <a class="pv-linkrow" href="{{ route('hr.leave.index') }}"><i class="fas fa-clipboard-check" style="color:var(--pv-amber)"></i> Review leave requests
                        @if($pendingLeaves)<span class="pv-badge pending" style="margin-left:auto">{{ $pendingLeaves }}</span>@endif</a>
                    <a class="pv-linkrow" href="{{ route('hr.employees.index') }}"><i class="fas fa-user-plus" style="color:var(--pv-brand)"></i> Onboard employees</a>
                    <a class="pv-linkrow" href="{{ route('hr.salary.index') }}"><i class="fas fa-money-bill-wave" style="color:var(--pv-green)"></i> Salary structures</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
