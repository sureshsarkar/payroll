<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 7mm 6mm 8mm; }
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; color: #111; font-size: 7px; margin: 0; }
    .company { text-align: center; font-size: 13px; font-weight: bold; text-transform: uppercase; }
    .title { text-align: center; font-size: 9px; font-weight: bold; margin: 3px 0 6px; }
    .meta, .register { width: 100%; border-collapse: collapse; }
    .meta { margin-bottom: 5px; font-size: 7px; }
    .meta td { padding: 2px 3px; border: 1px solid #444; }
    .label { font-weight: bold; color: #333; }
    .register { table-layout: fixed; }
    .register th, .register td { border: 1px solid #444; text-align: center; padding: 1px; vertical-align: top; }
    .register th { background: #efefef; font-size: 6px; font-weight: bold; height: 24px; }
    .register .day { width: 2.52%; }
    .register .total { width: 3.2%; }
    .entry { min-height: 38px; line-height: 10px; font-size: 6px; }
    .entry .time { color: #222; }
    .entry .status { font-weight: bold; font-size: 6px; }
    .foot { margin-top: 5px; font-size: 6px; color: #444; }
</style>
</head>
<body>
    <div class="company">{{ config('app.name', 'Company') }}</div>
    <div class="title">Attendance Register for the Month {{ $first->format('F, Y') }}</div>
    <table class="meta">
        <tr>
            <td><span class="label">Employee code:</span> {{ $profile?->employee_code ?? '-' }}</td>
            <td><span class="label">Name:</span> {{ $employee->name }}</td>
            <td><span class="label">Designation:</span> {{ $profile?->designation ?? '-' }}</td>
            <td><span class="label">Department:</span> {{ $profile?->department?->name ?? '-' }}</td>
            <td><span class="label">DOJ:</span> {{ optional($profile?->date_of_joining)->format('d/m/Y') ?? '-' }}</td>
            <td><span class="label">Type:</span> {{ $profile?->employment_type ? ucwords(str_replace('_', ' ', $profile->employment_type)) : '-' }}</td>
        </tr>
    </table>
    <table class="register">
        <thead><tr>
            @foreach($days as $day)<th class="day">{{ $day['date']->day }}<br>{{ $day['date']->format('D') }}</th>@endforeach
            <th class="total">P</th><th class="total">A</th><th class="total">L</th><th class="total">WFH</th><th class="total">Pay</th>
        </tr></thead>
        <tbody><tr>
            @foreach($days as $day)
                @php($record = $day['record'])
                <td><div class="entry">
                    <div class="time">{{ $record?->check_in ? \Carbon\Carbon::parse($record->check_in)->format('H:i') : '' }}</div>
                    <div class="time">{{ $record?->check_out ? \Carbon\Carbon::parse($record->check_out)->format('H:i') : '' }}</div>
                    <div class="status">{{ $record ? match($record->status) { 'present' => 'P', 'absent' => 'A', 'half_day' => 'HD', 'leave' => 'L', 'wfh' => 'WFH', 'holiday' => 'H', default => '-' } : ($day['date']->isWeekend() ? 'WO' : '-') }}</div>
                </div></td>
            @endforeach
            <td>{{ $summary['present'] }}</td><td>{{ $summary['absent'] }}</td><td>{{ $summary['leave'] }}</td><td>{{ $summary['wfh'] }}</td><td>{{ $summary['payable_days'] }}</td>
        </tr></tbody>
    </table>
    <div class="foot">Remarks: In time - Out time - Status. Codes: P Present, A Absent, HD Half day, L Leave, WFH Work from home, WO Weekly off.</div>
</body>
</html>
