@extends('attendance::layouts.payroll')
@section('title', 'Payroll Dashboard')
@section('subtitle', 'Super Admin')

@php
    $cards = [
        ['Employees', $employeeCount, '#1f2d3d'],
        ['HR staff', $hrCount, '#0f9bb0'],
        ['Departments', $deptCount, '#5b6ee1'],
        ['Pending approvals', $pendingRuns, '#e08a1e'],
    ];
@endphp

@section('content')
<h4 class="mb-3">Payroll overview</h4>

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
    <div class="col-lg-6">
        <div class="card stat-card h-100"><div class="card-body">
            <h6 class="text-muted mb-3">Approvals</h6>
            @if($pendingRuns > 0)
                <p class="mb-2">{{ $pendingRuns }} payroll run(s) awaiting your approval.</p>
            @else
                <p class="text-muted mb-2">Nothing awaiting approval.</p>
            @endif
            <a href="{{ route('admin.payroll.index') }}" class="btn btn-sm btn-primary">Go to approvals</a>
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card stat-card h-100"><div class="card-body">
            <h6 class="text-muted mb-3">Last approved payroll</h6>
            @if($lastApproved)
                <p class="mb-1">{{ $lastApproved->periodLabel() }}</p>
                <p class="mb-2">Cost: <strong>₹{{ number_format($lastApproved->total_net,2) }}</strong> · {{ $lastApproved->employee_count }} employees</p>
                <a href="{{ route('admin.payroll.show',$lastApproved) }}" class="btn btn-sm btn-outline-secondary">Review</a>
            @else
                <p class="text-muted mb-0">No payroll approved yet.</p>
            @endif
        </div></div>
    </div>
</div>
@endsection
