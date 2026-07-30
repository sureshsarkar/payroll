@extends('attendance::layouts.payroll')
@section('title', 'My Dashboard')
@section('subtitle', $user->name)

@php
    $cards = [
        ['Present (this month)', $summary['present'], '#1e9e5a'],
        ['LOP days', $summary['lop_days'], '#d0403a'],
        ['Payable days', $summary['payable_days'], '#1f2d3d'],
        ['Leave balance', $leaveLeft, '#5b6ee1'],
    ];
@endphp

@section('content')
<h4 class="mb-3">Hi {{ $user->name }} 👋</h4>

<div class="row g-3 mb-4">
    @foreach($cards as [$label,$val,$color])
        <div class="col-6 col-lg-3">
            <div class="card stat-card h-100"><div class="card-body text-center">
                <div class="h3 mb-0" style="color:{{ $color }}">{{ $val }}</div>
                <div class="small text-muted">{{ $label }}</div>
            </div></div>
        </div>
    @endforeach
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card stat-card h-100"><div class="card-body">
            <h6 class="text-muted mb-2">Today · {{ now()->format('D, d M') }}</h6>
            @if($today && $today->check_in)
                <p class="mb-1">In: <strong>{{ \Carbon\Carbon::parse($today->check_in)->format('h:i A') }}</strong>
                    @if($today->check_out) · Out: <strong>{{ \Carbon\Carbon::parse($today->check_out)->format('h:i A') }}</strong>@endif</p>
            @else
                <p class="text-muted mb-2">Not checked in yet.</p>
            @endif
            <a href="{{ route('employee.attendance.my') }}" class="btn btn-sm btn-success mt-1">Attendance &amp; check-in</a>
        </div></div>
    </div>
    <div class="col-lg-4">
        <div class="card stat-card h-100"><div class="card-body">
            <h6 class="text-muted mb-2">Latest payslip</h6>
            @if($latest)
                <p class="mb-1">{{ $latest->run->periodLabel() }}</p>
                <p class="mb-2 h5">₹{{ number_format($latest->net_pay,2) }}</p>
                <a href="{{ route('employee.payslips.download',$latest) }}" class="btn btn-sm btn-outline-primary">Download PDF</a>
            @else
                <p class="text-muted mb-0">No payslip yet.</p>
            @endif
        </div></div>
    </div>
    <div class="col-lg-4">
        <div class="card stat-card h-100"><div class="card-body">
            <h6 class="text-muted mb-2">Leave</h6>
            <p class="mb-2">You have <strong>{{ $leaveLeft }}</strong> paid leave day(s) left.</p>
            <a href="{{ route('employee.leave.index') }}" class="btn btn-sm btn-outline-secondary">Apply / view leave</a>
        </div></div>
    </div>
</div>
@endsection
