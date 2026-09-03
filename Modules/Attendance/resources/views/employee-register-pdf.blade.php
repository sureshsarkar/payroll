<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
@php
    $mask = fn ($v) => filled($v) ? $v : '—';
@endphp
<style>
    @page { margin: 6mm 5mm 7mm; }
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; color: #111; font-size: 10px; margin: 0; }

    .hdr { width: 100%; border-collapse: collapse; margin-bottom: 2px; }
    .hdr td { vertical-align: top; padding: 0; }
    .company { text-align: center; font-size: 15px; font-weight: bold; text-transform: uppercase; }
    .co-addr { text-align: center; font-size: 9px; color: #333; margin-top: 1px; }
    .page-no { text-align: right; font-size: 8px; color: #444; }
    .remarks { font-size: 8px; color: #333; }
    .title { text-align: center; font-size: 11px; font-weight: bold; margin: 4px 0 5px; }

    .meta { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
    .meta td { padding: 2px 5px; border: 1px solid #444; font-size: 10px; }
    .meta .label { font-weight: bold; color: #333; }

    .register { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .register th, .register td { border: 1px solid #444; text-align: center; padding: 1px; vertical-align: top; }
    .register th { background: #efefef; font-size: 10px; font-weight: normal; line-height: 11.5px; }
    .register .day { width: 2.65%; }
    .register .summary { width: 8%; }
    .entry { min-height: 34px; line-height: 10px; font-size: 8.5px; }
    .entry .status { font-weight: bold; }

    .summary-box { text-align: left; font-size: 9px; line-height: 11px; padding: 1px 2px; }

    .foot { margin-top: 5px; font-size: 8px; color: #444; }
</style>
</head>
<body>
    <table class="hdr"><tr>
        <td style="width:20%"></td>
        <td style="width:60%">
            <div class="company">{{ $company['name'] }}</div>
            @if(filled($company['address']))
                <div class="co-addr">{{ $company['address'] }}</div>
            @endif
        </td>
        <td style="width:20%">
            <div class="page-no">Page 1 of 1</div>
        </td>
    </tr></table>

    <div class="remarks">Remarks :- 1. In &nbsp; 2. Out &nbsp; 3. Status</div>
    <div class="title">Attendance Register for the Month {{ $first->format('F, Y') }}</div>

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
                <td><div class="entry">
                    <div>{{ $record?->check_in ? \Carbon\Carbon::parse($record->check_in)->format('H:i') : '' }}</div>
                    <div>{{ $record?->check_out ? \Carbon\Carbon::parse($record->check_out)->format('H:i') : '' }}</div>
                    <div class="status">{{ $record ? $record->shortCode() : ($day['date']->dayOfWeek === (int) config('payroll.attendance.weekly_off_day', \Carbon\Carbon::SUNDAY) ? 'WO' : '-') }}</div>
                </div></td>
            @endforeach
            <td>
                <div class="summary-box">
                    Present: <b>{{ $summary['present'] }}</b><br>
                    Absent: <b>{{ $summary['absent'] }}</b><br>
                    Half Day: <b>{{ $summary['half_day'] }}</b>
                        <span style="font-size:8px">({{ $summary['first_half_absent'] }} AP / {{ $summary['second_half_absent'] }} PA)</span><br>
                    Leave: <b>{{ $summary['leave'] }}</b><br>
                    Holiday: <b>{{ $summary['holiday'] }}</b><br>
                    WFH: <b>{{ $summary['wfh'] }}</b><br>
                    Weekly Off: <b>{{ $weeklyOffs }}</b><br>
                    <strong>Payable Days: {{ number_format((float) $summary['payable_days'], 1) }}</strong>
                </div>
            </td>
        </tr></tbody>
    </table>
    <div class="foot">Codes: PP Present all day, AP 1st half off, PA 2nd half off, AA Absent all day, H Holiday, WFH Work from home, WO Weekly off.</div>
</body>
</html>
