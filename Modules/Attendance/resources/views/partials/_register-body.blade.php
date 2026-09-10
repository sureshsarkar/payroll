@php
    $mask = fn ($v) => filled($v) ? $v : '—';
@endphp
<div class="emp-block">
    <table class="meta"><tr>
        <td><span class="label">Employee code:</span> {{ $mask($profile?->employee_code) }}</td>
        <td><span class="label">Card No:</span> {{ $mask($profile?->id) }}</td>
        <td><span class="label">Name:</span> {{ $employee->name }}</td>
        <td><span class="label">Designation:</span> {{ $mask($profile?->designation) }}</td>
        <td><span class="label">Department:</span> {{ $mask($profile?->department?->name) }}</td>
        <td><span class="label">DOJ:</span> {{ optional($profile?->date_of_joining)->format('d/m/Y') ?? '—' }}</td>
        <td><span class="label">P.Days:</span> {{ number_format((float) $summary['payable_days'], 1) }}</td>
    </tr></table>

    <table class="register">
        <thead><tr>
            @foreach($days as $day)<th class="day">{{ $day['date']->day }}<br>{{ $day['date']->format('D') }}</th>@endforeach
            <th class="summary">Attendance Summary</th>
        </tr></thead>
        <tbody><tr>
            @foreach($days as $day)
                @php($record = $day['record'])
                @php($isHoliday = filled($day['holiday']))
                @php($isHalfLeave = $record && $record->isHalfDayLeave())
                @php($code = match(true) {
                    $isHoliday   => 'HD',
                    $isHalfLeave => 'L',
                    (bool) $record => $record->shortCode(),
                    default      => ($day['date']->dayOfWeek === (int) config('payroll.attendance.weekly_off_day', \Carbon\Carbon::SUNDAY) ? 'WO' : '-'),
                })
                <td class="{{ $isHoliday ? 'hd-cell' : '' }}" @if($isHoliday) title="{{ $day['holiday'] }}" @endif><div class="entry">
                    <div>{{ ! $isHoliday && $record?->check_in ? \Carbon\Carbon::parse($record->check_in)->format('H:i') : '' }}</div>
                    <div>{{ ! $isHoliday && $record?->check_out ? \Carbon\Carbon::parse($record->check_out)->format('H:i') : '' }}</div>
                    <div class="status">{{ $code }}</div>
                </div></td>
            @endforeach
            <td>
                <div class="summary-box">
                    Present: <b>{{ $summary['present'] }}</b> &nbsp; Absent: <b>{{ $summary['absent'] }}</b><br>
                    Leave: <b>{{ rtrim(rtrim(number_format((float) $summary['leave_days'], 1), '0'), '.') }}</b> &nbsp; Holiday (HD): <b>{{ $holidayCount }}</b><br>
                    Weekly Off: <b>{{ $weeklyOffs + $summary['holiday'] }}</b><br>
                    <strong>Payable Days: {{ number_format((float) $summary['payable_days'], 1) }}</strong>
                </div>
            </td>
        </tr></tbody>
    </table>
</div>
