@extends('attendance::layouts.payroll')
@section('title', 'My Attendance')
@section('subtitle', $user->name)

@php
    use Carbon\Carbon;
    $first = Carbon::create($year, $month, 1);
    $daysInMonth = $first->daysInMonth;
    $leadBlanks = (int) $first->dayOfWeek; // 0=Sun
    $monthName = $first->format('F Y');
    $prev = (clone $first)->subMonth();
    $next = (clone $first)->addMonth();
@endphp

@section('content')
<div class="row g-3 align-items-stretch mb-4">
    <div class="col-md-4">
        <div class="card stat-card h-100">
            <div class="card-body">
                <h6 class="text-muted">Today · {{ now()->format('D, d M Y') }}</h6>
                @if($today && $today->check_in)
                    <p class="mb-1">Checked in at <strong>{{ \Carbon\Carbon::parse($today->check_in)->format('h:i A') }}</strong></p>
                @endif
                @if($today && $today->check_out)
                    <p class="mb-2">Checked out at <strong>{{ \Carbon\Carbon::parse($today->check_out)->format('h:i A') }}</strong></p>
                @endif
                <div class="d-flex gap-2">
                    <form method="POST" action="{{ route('employee.attendance.checkin') }}">
                        @csrf
                        <button class="btn btn-success btn-sm" {{ $today && $today->check_in ? 'disabled' : '' }}>Check in</button>
                    </form>
                    <form method="POST" action="{{ route('employee.attendance.checkout') }}">
                        @csrf
                        <button class="btn btn-outline-danger btn-sm" {{ !($today && $today->check_in) ? 'disabled' : '' }}>Check out</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="row g-2 h-100">
            @foreach([['Present',$summary['present'],'#1e9e5a'],['Absent',$summary['absent'],'#d0403a'],['LOP days',$summary['lop_days'],'#e08a1e'],['Payable days',$summary['payable_days'],'#1f2d3d']] as [$label,$val,$c])
            <div class="col-6 col-lg-3">
                <div class="card stat-card h-100"><div class="card-body text-center">
                    <div class="h3 mb-0" style="color:{{ $c }}">{{ $val }}</div>
                    <div class="small text-muted">{{ $label }}</div>
                </div></div>
            </div>
            @endforeach
        </div>
    </div>
</div>

<div class="card stat-card">
    <div class="card-body">
        <div class="d-flex align-items-center mb-3">
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('employee.attendance.my', ['year'=>$prev->year,'month'=>$prev->month]) }}">&larr;</a>
            <h5 class="mb-0 mx-3">{{ $monthName }}</h5>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('employee.attendance.my', ['year'=>$next->year,'month'=>$next->month]) }}">&rarr;</a>
        </div>
        <div class="row g-1 text-center fw-bold text-muted small mb-1">
            @foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d)<div class="col">{{ $d }}</div>@endforeach
        </div>
        <div class="row g-1">
            @for($b=0; $b<$leadBlanks; $b++)<div class="col"><div class="cal-cell cal-muted"></div></div>@endfor
            @for($day=1; $day<=$daysInMonth; $day++)
                @php $rec = $map->get($day); @endphp
                <div class="col">
                    <div class="cal-cell">
                        <div class="fw-bold">{{ $day }}</div>
                        @if($rec)
                            <span class="badge badge-{{ $rec->status }}">{{ $rec->status }}</span>
                            @if($rec->check_in)<div class="text-muted mt-1">{{ \Carbon\Carbon::parse($rec->check_in)->format('H:i') }}</div>@endif
                        @endif
                    </div>
                </div>
                @if(($day + $leadBlanks) % 7 == 0)</div><div class="row g-1">@endif
            @endfor
        </div>
    </div>
</div>
@endsection
