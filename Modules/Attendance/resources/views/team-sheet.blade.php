@extends('attendance::layouts.payroll')
@section('title', 'Team Attendance Sheet')
@section('subtitle', 'HR · '.$hr->name)

@php
    use Carbon\Carbon;
    $first = Carbon::create($year, $month, 1);
    $prev = (clone $first)->subMonth();
    $next = (clone $first)->addMonth();
@endphp

@section('content')
<div class="d-flex align-items-center mb-3">
    <a class="btn btn-sm btn-outline-secondary" href="{{ route('hr.attendance.sheet', ['year'=>$prev->year,'month'=>$prev->month]) }}">&larr;</a>
    <h4 class="mb-0 mx-3">Attendance sheet · {{ $first->format('F Y') }}</h4>
    <a class="btn btn-sm btn-outline-secondary" href="{{ route('hr.attendance.sheet', ['year'=>$next->year,'month'=>$next->month]) }}">&rarr;</a>
    <a href="{{ route('hr.attendance.team') }}" class="btn btn-sm btn-outline-primary ms-auto">&larr; Mark attendance</a>
</div>

<div class="card stat-card"><div class="card-body">
    @if($rows->isEmpty())
        <p class="text-muted mb-0">No employees assigned to you yet.</p>
    @else
    <div class="table-responsive">
        <table class="table table-sm table-striped align-middle">
            <thead><tr>
                <th>Employee</th><th class="text-center">Present</th><th class="text-center">Absent</th>
                <th class="text-center">Half</th><th class="text-center">Leave</th><th class="text-center">WFH</th>
                <th class="text-center">Holiday</th><th class="text-center">LOP</th>
                <th class="text-center">Payable</th><th class="text-center">Working</th>
            </tr></thead>
            <tbody>
            @foreach($rows as $r)
                <tr>
                    <td>{{ $r['user']->name }} <span class="text-muted small">#{{ $r['user']->id }}</span></td>
                    <td class="text-center">{{ $r['summary']['present'] }}</td>
                    <td class="text-center">{{ $r['summary']['absent'] }}</td>
                    <td class="text-center">{{ $r['summary']['half_day'] }}</td>
                    <td class="text-center">{{ $r['summary']['leave'] }}</td>
                    <td class="text-center">{{ $r['summary']['wfh'] }}</td>
                    <td class="text-center">{{ $r['summary']['holiday'] }}</td>
                    <td class="text-center text-danger fw-bold">{{ $r['summary']['lop_days'] }}</td>
                    <td class="text-center fw-bold">{{ $r['summary']['payable_days'] }}</td>
                    <td class="text-center text-muted">{{ $r['summary']['working_days'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <p class="text-muted small mb-0">Excel / PDF export arrives in Phase 5 (shared SheetExport). This is the on-screen view.</p>
    @endif
</div></div>
@endsection
