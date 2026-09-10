@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
<div class="pv">
    @include('payroll::partials.ui')

    <div class="pv-head">
        <div>
            <h1 class="t">Company Holidays</h1>
            <p class="s">Holidays entered here are pulled into every employee's Attendance Register PDF automatically and shown as <b>HD</b>.</p>
        </div>
        <div class="pv-actions">
            <a href="{{ route('hr.attendance.team') }}" class="pv-btn p"><i class="fas fa-calendar-check"></i> Team attendance</a>
        </div>
    </div>

    <div class="pv-card">
        <div class="b tight">
            <form method="GET" action="{{ route('hr.attendance.holidays.index') }}" class="pv-inline" style="gap:8px;margin-bottom:14px">
                <label class="pv-mut2">Year</label>
                <select name="year" class="pv-input" style="width:110px" onchange="this.form.submit()">
                    @for($y = now()->year + 1; $y >= now()->year - 5; $y--)
                        <option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>
                    @endfor
                </select>
                <noscript><button class="pv-btn g sm">Go</button></noscript>
            </form>

            <form method="POST" action="{{ route('hr.attendance.holidays.store') }}" class="pv-inline" style="gap:8px;flex-wrap:wrap;margin-bottom:16px">
                @csrf
                <input type="date" name="holiday_date" class="pv-input" style="width:170px"
                       min="{{ $year }}-01-01" max="{{ $year }}-12-31" value="{{ old('holiday_date') }}" required>
                <input name="name" class="pv-input" style="width:260px" placeholder="Holiday name (e.g. Diwali)" value="{{ old('name') }}" required>
                <button class="pv-btn g sm"><i class="fas fa-plus"></i> Add holiday</button>
            </form>

            @if($holidays->isEmpty())
                <div class="pv-empty"><div class="ic"><i class="fas fa-umbrella-beach"></i></div>No holidays set for {{ $year }} yet.</div>
            @else
            <div class="pv-tw">
            <table class="pv-table" style="min-width:520px">
                <thead><tr><th style="width:150px">Date</th><th style="width:90px">Day</th><th>Holiday</th><th class="pv-r" style="width:90px">Action</th></tr></thead>
                <tbody>
                @foreach($holidays as $h)
                    <tr>
                        <td><strong>{{ $h->holiday_date->format('d M Y') }}</strong></td>
                        <td>{{ $h->holiday_date->format('D') }}</td>
                        <td>{{ $h->name }}</td>
                        <td class="pv-r">
                            <form method="POST" action="{{ route('hr.attendance.holidays.destroy', $h) }}"
                                  onsubmit="return confirm('Remove {{ addslashes($h->name) }}?')">
                                @csrf @method('DELETE')
                                <button class="pv-btn d sm"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            </div>
            <p class="pv-mut2" style="margin:12px 4px 0">{{ $holidays->count() }} holiday(s) in {{ $year }}. Changes apply to Attendance Register PDFs generated from now on.</p>
            @endif
        </div>
    </div>
</div>
@endsection
