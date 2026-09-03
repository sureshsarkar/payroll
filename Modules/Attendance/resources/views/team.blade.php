@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
<div class="pv">
    @include('payroll::partials.ui')

    <div class="pv-head">
        <div>
            <h1 class="t">Team Attendance</h1>
            <p class="s">Mark attendance for your team.</p>
        </div>
        <div class="pv-actions">
            <a href="{{ route('hr.attendance.sheet') }}" class="pv-btn"><i class="fas fa-table"></i> Monthly sheet</a>
        </div>
    </div>

    <form method="GET" class="pv-inline" style="margin-bottom:16px">
        <div class="pv-field" style="margin:0">
            <label class="pv-label">Date</label>
            <input type="date" name="date" value="{{ $date }}" class="pv-input" style="width:170px">
        </div>
        <button class="pv-btn">Load date</button>
    </form>

    <form method="POST" action="{{ route('hr.attendance.mark') }}">
        @csrf
        <input type="hidden" name="date" value="{{ $date }}">
        <div class="pv-card">
            <div class="h"><i class="fas fa-calendar-check pv-muted"></i> {{ \Carbon\Carbon::parse($date)->format('l, d M Y') }}</div>
            <div class="b">
                @if($team->isEmpty())
                    <div class="pv-empty"><div class="ic"><i class="fas fa-user-friends"></i></div>
                        No employees assigned to you yet. Add them under <strong>Employees</strong>.</div>
                @else
                <div class="pv-tw">
                <table class="pv-table" style="min-width:auto">
                    <thead><tr>
                        <th style="width:36px"><input type="checkbox" onclick="document.querySelectorAll('.pv-cb').forEach(c=>c.checked=this.checked)"></th>
                        <th>Employee</th><th>Marked status</th>
                    </tr></thead>
                    <tbody>
                    @foreach($team as $emp)
                        @php $rec = $marked->get($emp->id); @endphp
                        <tr>
                            <td><input class="pv-cb" type="checkbox" name="user_ids[]" value="{{ $emp->id }}"></td>
                            <td><strong>{{ $emp->name }}</strong> <span class="pv-mut2">#{{ $emp->id }}</span></td>
                            <td>@if($rec)<span class="pv-badge {{ $rec->badgeClass() }}" title="{{ $rec->label() }}">{{ $rec->shortCode() }}</span>@else<span class="pv-muted">—</span>@endif</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                </div>
                <div class="pv-inline" style="margin-top:16px">
                    <div class="pv-field" style="margin:0"><label class="pv-label">Status</label>
                        <select name="status" class="pv-select" style="width:auto">
                            @foreach($statuses as $code => $label)<option value="{{ $code }}">{{ $label }}</option>@endforeach
                        </select></div>
                    <div class="pv-field" style="margin:0"><label class="pv-label">Day type</label>
                        <select name="day_type" class="pv-select" style="width:auto">
                            <option value="">Ordinary day</option>
                            @foreach($dayTypes as $code => $label)<option value="{{ $code }}">{{ $label }}</option>@endforeach
                        </select></div>
                    <button class="pv-btn g"><i class="fas fa-check"></i> Mark selected</button>
                </div>
                <p class="pv-mut2" style="margin:8px 4px 0">Codes: <b>PP</b> present all day · <b>AP</b> first half off · <b>PA</b> second half off · <b>AA</b> absent all day. Day type marks the reason (WFH, holiday, leave) — paid types never cause loss of pay.</p>
                @endif
            </div>
        </div>
    </form>
</div>
@endsection
