@extends('frontend.student-dashboard.layouts.master')

@section('dashboard-contents')
@php
    use Carbon\Carbon;
    $first = Carbon::create($year, $month, 1);
    $daysInMonth = $first->daysInMonth;
    $leadBlanks = (int) $first->dayOfWeek;
    $prev = (clone $first)->subMonth();
    $next = (clone $first)->addMonth();
@endphp

<div class="pv">
    @include('payroll::partials.ui')

    <div class="pv-head">
        <div>
            <h1 class="t">My Attendance</h1>
            <p class="s">Check in / out and track your month.</p>
        </div>
    </div>

    <div class="pv-cols c73" style="margin-bottom:18px">
        <div class="pv-card" style="margin:0">
            <div class="b">
                <div class="pv-mut2" style="margin-bottom:6px">Today · {{ now()->format('l, d M Y') }}</div>
                @if($today && $today->check_in)
                    <div style="margin-bottom:8px">In <strong>{{ \Carbon\Carbon::parse($today->check_in)->format('h:i A') }}</strong>
                        @if($today->check_out) · Out <strong>{{ \Carbon\Carbon::parse($today->check_out)->format('h:i A') }}</strong>@endif</div>
                @endif
                <div class="pv-inline" style="align-items:center">
                    <form method="POST" action="{{ route('employee.attendance.checkin') }}">@csrf
                        <button class="pv-btn g" {{ $today && $today->check_in ? 'disabled' : '' }}><i class="fas fa-sign-in-alt"></i> Check in</button></form>
                    <form method="POST" action="{{ route('employee.attendance.checkout') }}">@csrf
                        <button class="pv-btn d" {{ !($today && $today->check_in) ? 'disabled' : '' }}><i class="fas fa-sign-out-alt"></i> Check out</button></form>
                </div>
            </div>
        </div>
        <div class="pv-stats" style="margin:0">
            @foreach([['Present',$summary['present'],'var(--pv-green)'],['Absent',$summary['absent'],'var(--pv-red)'],['LOP',$summary['lop_days'],'var(--pv-amber)'],['Payable',$summary['payable_days'],'var(--pv-brand)']] as [$l,$v,$c])
                <div class="pv-stat" style="--c:{{ $c }}"><div class="n">{{ $v }}</div><div class="l">{{ $l }}</div></div>
            @endforeach
        </div>
    </div>

    <div class="pv-card">
        <div class="h">
            <a class="pv-btn sm" href="{{ route('employee.attendance.my', ['year'=>$prev->year,'month'=>$prev->month]) }}">‹</a>
            <span style="margin:0 6px">{{ $first->format('F Y') }}</span>
            <a class="pv-btn sm" href="{{ route('employee.attendance.my', ['year'=>$next->year,'month'=>$next->month]) }}">›</a>
            <span class="pv-btngrp" style="margin-left:auto">
                <a href="{{ route('employee.attendance.export', ['format'=>'xlsx','year'=>$year,'month'=>$month]) }}" class="pv-btn sm g">Excel</a>
                <a href="{{ route('employee.attendance.export', ['format'=>'pdf','year'=>$year,'month'=>$month]) }}" class="pv-btn sm d">PDF</a>
            </span>
        </div>
        <div class="b">
            <div class="pv-cal" style="margin-bottom:7px">
                @foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d)<div class="dow">{{ $d }}</div>@endforeach
            </div>
            <div class="pv-cal">
                @for($b=0; $b<$leadBlanks; $b++)<div class="cell mut"></div>@endfor
                @for($day=1; $day<=$daysInMonth; $day++)
                    @php $rec = $map->get($day); @endphp
                    <div class="cell">
                        <div class="d">{{ $day }}</div>
                        @if($rec)
                            <span class="pv-badge {{ strtolower($rec->status) }}" style="margin-top:4px;font-size:10px">{{ $rec->status }}</span>
                            @if($rec->check_in)<div class="pv-mut2" style="margin-top:3px">{{ \Carbon\Carbon::parse($rec->check_in)->format('H:i') }}</div>@endif
                        @endif
                    </div>
                @endfor
            </div>
        </div>
    </div>
</div>
@endsection
