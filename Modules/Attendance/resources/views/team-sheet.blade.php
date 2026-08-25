@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
@php
    use Carbon\Carbon;
    $first = Carbon::create($year, $month, 1)->startOfMonth();
    $prev = (clone $first)->subMonth();
    $next = (clone $first)->addMonth();
    $leading = $first->dayOfWeekIso - 1;
    $daysInMonth = $first->daysInMonth;
    $statusClass = fn ($status) => strtolower(str_replace(' ', '', $status ?? ''));
    $checkIn = $selectedRecord?->check_in ? substr((string) $selectedRecord->check_in, 0, 5) : '';
    $checkOut = $selectedRecord?->check_out ? substr((string) $selectedRecord->check_out, 0, 5) : '';
@endphp

<div class="pv att-workspace">
    @include('payroll::partials.ui')

    <style>
        .att-workspace .att-layout{display:grid;grid-template-columns:270px minmax(0,1fr);gap:18px;align-items:start}
        .att-workspace .att-roster{position:sticky;top:14px;max-height:calc(100vh - 120px);overflow:auto}
        .att-workspace .att-person{display:flex;align-items:center;gap:10px;padding:10px 12px;border-bottom:1px solid var(--pv-line2);color:var(--pv-ink)}
        .att-workspace .att-person:hover{background:#fafbff}.att-workspace .att-person.active{background:#eef2ff;color:#3730a3;box-shadow:inset 3px 0 0 var(--pv-brand)}
        .att-workspace .att-avatar{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;background:#e9edff;color:#4f46e5;font-weight:750;flex:0 0 auto;overflow:hidden}
        .att-workspace .att-avatar img{width:100%;height:100%;object-fit:cover}.att-workspace .att-person .name{font-weight:650;font-size:13px;line-height:1.25}.att-workspace .att-person .meta{font-size:11px;color:var(--pv-mut);margin-top:2px}
        .att-workspace .att-summary{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px;margin:16px 0}.att-workspace .att-summary .item{padding:13px;border:1px solid var(--pv-line);border-radius:10px;background:#fff}.att-workspace .att-summary .num{font-size:22px;font-weight:750;line-height:1}.att-workspace .att-summary .label{font-size:11px;color:var(--pv-sub);margin-top:4px}
        .att-workspace .att-calendar{display:grid;grid-template-columns:repeat(7,minmax(76px,1fr));gap:7px}.att-workspace .att-calendar .dow{text-align:center;color:var(--pv-mut);font-size:11px;font-weight:750;text-transform:uppercase;padding:4px}.att-workspace .att-day{min-height:98px;border:1px solid var(--pv-line);border-radius:11px;padding:9px;background:#fff;color:var(--pv-ink);display:flex;flex-direction:column;gap:7px;transition:.15s}.att-workspace .att-day:hover{border-color:#a5b4fc;box-shadow:0 3px 10px rgba(79,70,229,.08)}.att-workspace .att-day.selected{border:2px solid var(--pv-brand);padding:8px;background:#f8f9ff}.att-workspace .att-day.weekend{background:#fbfcfe}.att-workspace .att-day.blank{visibility:hidden}.att-workspace .att-date{font-size:12px;font-weight:750}.att-workspace .att-time{font-size:10px;color:var(--pv-sub);margin-top:auto}
        .att-workspace .att-editor{display:grid;grid-template-columns:1.35fr 1fr 1fr .9fr .9fr;gap:10px;align-items:end}.att-workspace .att-editor .pv-field{margin:0}
        .att-workspace .att-quick{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:14px;padding-top:14px;border-top:1px dashed var(--pv-line)}.att-workspace .att-quick .hint{font-size:12px;color:var(--pv-sub);font-weight:600;margin-right:2px}
        .att-workspace .att-month-fill{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-top:12px;padding:12px;border-radius:10px;background:#f8fafc;border:1px solid var(--pv-line)}.att-workspace .att-month-fill .copy{font-size:12px;color:var(--pv-sub);flex:1 1 260px}.att-workspace .att-month-fill strong{color:var(--pv-ink)}
        @media(max-width:1050px){.att-workspace .att-layout{grid-template-columns:1fr}.att-workspace .att-roster{position:static;max-height:none}.att-workspace .att-roster-list{display:flex;overflow:auto}.att-workspace .att-person{min-width:185px;border-right:1px solid var(--pv-line2);border-bottom:0}.att-workspace .att-summary{grid-template-columns:repeat(3,1fr)}.att-workspace .att-editor{grid-template-columns:repeat(2,1fr)}}
        @media(max-width:620px){.att-workspace .att-summary{grid-template-columns:repeat(2,1fr)}.att-workspace .att-editor{grid-template-columns:1fr}.att-workspace .att-calendar{grid-template-columns:repeat(7,minmax(58px,1fr));overflow:auto}.att-workspace .att-day{min-height:82px;padding:6px}.att-workspace .att-time{display:none}}
    </style>

    <div class="pv-head">
        <div>
            <h1 class="t">Attendance Workspace</h1>
            <p class="s">Review, update, and download each employee’s day-by-day attendance.</p>
        </div>
        <div class="pv-actions">
            <a class="pv-btn sm" aria-label="Previous month" href="{{ route('hr.attendance.sheet', ['year'=>$prev->year, 'month'=>$prev->month, 'employee_id'=>$selected?->id]) }}"><i class="fas fa-chevron-left"></i></a>
            <span class="pv-btn sm" style="cursor:default">{{ $first->format('F Y') }}</span>
            <a class="pv-btn sm" aria-label="Next month" href="{{ route('hr.attendance.sheet', ['year'=>$next->year, 'month'=>$next->month, 'employee_id'=>$selected?->id]) }}"><i class="fas fa-chevron-right"></i></a>
            <a href="{{ route('hr.attendance.sheet.export', ['format'=>'xlsx','year'=>$year,'month'=>$month]) }}" class="pv-btn sm">Team Excel</a>
            @if($selected)
                <span class="pv-btngrp">
                    <a href="{{ route('hr.attendance.sheet.employee.export', ['format'=>'xlsx','year'=>$year,'month'=>$month,'employee_id'=>$selected->id]) }}" class="pv-btn sm g"><i class="fas fa-file-excel"></i> Employee Excel</a>
                    <a href="{{ route('hr.attendance.sheet.employee.export', ['format'=>'pdf','year'=>$year,'month'=>$month,'employee_id'=>$selected->id]) }}" class="pv-btn sm d">PDF</a>
                    <a href="{{ route('hr.attendance.sheet.employee.export', ['format'=>'csv','year'=>$year,'month'=>$month,'employee_id'=>$selected->id]) }}" class="pv-btn sm">CSV</a>
                </span>
            @endif
            <a href="{{ route('hr.attendance.team') }}" class="pv-btn sm p"><i class="fas fa-users"></i> Bulk mark</a>
        </div>
    </div>

    @if($team->isEmpty())
        <div class="pv-card"><div class="pv-empty"><div class="ic"><i class="fas fa-user-friends"></i></div>No employees are assigned to you yet. Add them under <strong>Employees</strong> first.</div></div>
    @else
    <div class="att-layout">
        <aside class="pv-card att-roster">
            <div class="h"><i class="fas fa-users pv-muted"></i> Employees <span class="pv-mut2">{{ $team->count() }}</span></div>
            <div class="att-roster-list">
            @foreach($team as $employee)
                @php $profile = $profiles->get($employee->id); @endphp
                <a class="att-person {{ $selected?->id === $employee->id ? 'active' : '' }}" href="{{ route('hr.attendance.sheet', ['year'=>$year,'month'=>$month,'employee_id'=>$employee->id]) }}">
                    <span class="att-avatar">@if($profile?->photo_path)<img src="{{ asset($profile->photo_path) }}" alt="">@else{{ strtoupper(substr($employee->name, 0, 1)) }}@endif</span>
                    <span><span class="name">{{ $employee->name }}</span><span class="meta">{{ $profile?->employee_code ?? 'Employee #'.$employee->id }}</span></span>
                </a>
            @endforeach
            </div>
        </aside>

        <main>
            <div class="pv-card">
                <div class="h">
                    <span class="att-avatar" style="width:40px;height:40px">@if($selectedProfile?->photo_path)<img src="{{ asset($selectedProfile->photo_path) }}" alt="">@else{{ strtoupper(substr($selected->name, 0, 1)) }}@endif</span>
                    <span>{{ $selected->name }} <span class="pv-mut2">{{ $selectedProfile?->employee_code ?? 'Employee #'.$selected->id }} · {{ $selectedProfile?->designation ?? 'No designation' }}</span></span>
                </div>
                <div class="b">
                    <div class="att-summary">
                        <div class="item"><div class="num" style="color:var(--pv-green)">{{ $summary['present'] }}</div><div class="label">Present</div></div>
                        <div class="item"><div class="num" style="color:var(--pv-red)">{{ $summary['absent'] }}</div><div class="label">Absent</div></div>
                        <div class="item"><div class="num" style="color:var(--pv-amber)">{{ $summary['half_day'] }}</div><div class="label">Half days</div></div>
                        <div class="item"><div class="num" style="color:var(--pv-violet)">{{ $summary['leave'] + $summary['wfh'] }}</div><div class="label">Leave / WFH</div></div>
                        <div class="item"><div class="num">{{ $summary['payable_days'] }}</div><div class="label">Payable days <span class="pv-mut2">of {{ $summary['working_days'] }}</span></div></div>
                    </div>

                    <form method="POST" action="{{ route('hr.attendance.sheet.day.store') }}" class="att-editor">
                        @csrf
                        <input type="hidden" name="employee_id" value="{{ $selected->id }}"><input type="hidden" name="year" value="{{ $year }}"><input type="hidden" name="month" value="{{ $month }}">
                        <div class="pv-field"><label class="pv-label">Selected date</label><input type="date" name="date" value="{{ $selectedDate->format('Y-m-d') }}" class="pv-input"></div>
                        <div class="pv-field"><label class="pv-label">Attendance status</label><select name="status" class="pv-select">@foreach(\Modules\Attendance\app\Models\Attendance::STATUSES as $status)<option value="{{ $status }}" {{ ($selectedRecord?->status ?? 'Present') === $status ? 'selected' : '' }}>{{ $status }}</option>@endforeach</select></div>
                        <div class="pv-field"><label class="pv-label">Remarks</label><input name="remarks" value="{{ $selectedRecord?->remarks ?? '' }}" class="pv-input" placeholder="Optional note"></div>
                        <div class="pv-field"><label class="pv-label">Check in</label><input type="time" name="check_in" value="{{ $checkIn }}" class="pv-input"></div>
                        <div class="pv-field"><label class="pv-label">Check out</label><input type="time" name="check_out" value="{{ $checkOut }}" class="pv-input"></div>
                        <button class="pv-btn p" type="submit"><i class="fas fa-save"></i> Update day</button>
                        <div class="att-quick" style="grid-column:1 / -1">
                            <span class="hint"><i class="fas fa-bolt" style="color:var(--pv-amber)"></i> One-click present:</span>
                            <button class="pv-btn g sm" type="submit" name="quick_preset" value="0930_1830">09:30 in · 18:30 out</button>
                            <button class="pv-btn g sm" type="submit" name="quick_preset" value="0940_1900">09:40 in · 19:00 out</button>
                        </div>
                    </form>
                    <form method="POST" action="{{ route('hr.attendance.sheet.month.random-fill') }}" class="att-month-fill" onsubmit="return confirm('Fill every unmarked day (including Saturdays) in {{ $first->format('F Y') }} for {{ $selected->name }}? Sundays are left blank. Existing attendance will not be changed.')">
                        @csrf
                        <input type="hidden" name="employee_id" value="{{ $selected->id }}"><input type="hidden" name="year" value="{{ $year }}"><input type="hidden" name="month" value="{{ $month }}">
                        <span class="copy"><strong>Fill the whole month</strong><br>Each unmarked day (Saturdays included) becomes Present with a random check-in from 09:30–09:40 and check-out from 18:30–19:00. Sundays are left blank — mark one manually above if needed. Existing entries are kept.</span>
                        <button class="pv-btn g" type="submit"><i class="fas fa-magic"></i> Fill month with random times</button>
                    </form>
                </div>
            </div>

            <div class="pv-card">
                <div class="h"><i class="fas fa-calendar-alt pv-muted"></i> {{ $first->format('F Y') }} calendar <span class="pv-mut2">Select a day to edit it</span></div>
                <div class="b"><div class="pv-tw"><div class="att-calendar">
                    @foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $day)<div class="dow">{{ $day }}</div>@endforeach
                    @for($i = 0; $i < $leading; $i++)<div class="att-day blank"></div>@endfor
                    @for($day = 1; $day <= $daysInMonth; $day++)
                        @php $date = Carbon::create($year, $month, $day); $record = $map->get($day); @endphp
                        <a class="att-day {{ $date->isWeekend() ? 'weekend' : '' }} {{ $selectedDate->isSameDay($date) ? 'selected' : '' }}" href="{{ route('hr.attendance.sheet', ['year'=>$year,'month'=>$month,'employee_id'=>$selected->id,'date'=>$date->format('Y-m-d')]) }}">
                            <span class="att-date">{{ $day }} <span class="pv-mut2">{{ $date->format('D') }}</span></span>
                            @if($record)<span class="pv-badge {{ $statusClass($record->status) }}">{{ $record->status }}</span>@else<span class="pv-mut2">Not marked</span>@endif
                            @if($record?->check_in || $record?->check_out)<span class="att-time">{{ $record->check_in ? substr((string) $record->check_in, 0, 5) : '—' }} – {{ $record->check_out ? substr((string) $record->check_out, 0, 5) : '—' }}</span>@endif
                        </a>
                    @endfor
                </div></div></div>
            </div>
        </main>
    </div>

    <div class="pv-card">
        <div class="h"><i class="fas fa-chart-bar pv-muted"></i> Team monthly overview <span class="pv-mut2">Use this to compare all employees</span></div>
        <div class="b tight"><div class="pv-tw"><table class="pv-table" style="min-width:760px">
            <thead><tr><th>Employee</th><th class="pv-c">Present</th><th class="pv-c">Absent</th><th class="pv-c">Half</th><th class="pv-c">Leave</th><th class="pv-c">WFH</th><th class="pv-c">LOP</th><th class="pv-c">Payable</th><th></th></tr></thead>
            <tbody>@foreach($rows as $row)<tr><td><strong>{{ $row['user']->name }}</strong></td><td class="pv-c">{{ $row['summary']['present'] }}</td><td class="pv-c">{{ $row['summary']['absent'] }}</td><td class="pv-c">{{ $row['summary']['half_day'] }}</td><td class="pv-c">{{ $row['summary']['leave'] }}</td><td class="pv-c">{{ $row['summary']['wfh'] }}</td><td class="pv-c" style="color:var(--pv-red);font-weight:700">{{ $row['summary']['lop_days'] }}</td><td class="pv-c" style="font-weight:700">{{ $row['summary']['payable_days'] }}</td><td><a class="pv-btn sm" href="{{ route('hr.attendance.sheet', ['year'=>$year,'month'=>$month,'employee_id'=>$row['user']->id]) }}">Open</a></td></tr>@endforeach</tbody>
        </table></div></div>
    </div>
    @endif
</div>
@endsection
