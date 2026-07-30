<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: 'DejaVu Sans', sans-serif; }
        body { color: #1f2937; font-size: 11px; }
        h1 { font-size: 16px; margin: 0 0 2px; color: #0f172a; }
        .muted { color: #6b7280; font-size: 10px; }
        .brand { font-size: 12px; font-weight: bold; color: #10b981; }
        .summary { margin: 12px 0; }
        .summary span { display: inline-block; margin-right: 16px; font-size: 11px; }
        .summary b { color: #0f172a; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { background: #f1f5f9; text-align: left; padding: 7px 8px; font-size: 10px; border-bottom: 1px solid #e2e8f0; }
        td { padding: 6px 8px; font-size: 10px; border-bottom: 1px solid #eef0f5; }
        .r { text-align: right; }
        .foot { margin-top: 14px; color: #9ca3af; font-size: 9px; }
    </style>
</head>
<body>
    @php $brandName = $brand->name ?? config('app.name', 'Reports'); @endphp
    <div class="brand">{{ $brandName }}</div>
    <h1>{{ $report['title'] }}</h1>
    <div class="muted">
        {{ optional($report['from'])->format('d M Y') }} – {{ optional($report['to'])->format('d M Y') }}
        · {{ __('Generated') }} {{ now()->format('d M Y H:i') }}
    </div>

    <div class="summary">
        @foreach ($report['summary'] as $s)
            <span>{{ $s['label'] }}: <b>{{ $s['value'] }}</b></span>
        @endforeach
    </div>

    <table>
        <thead>
            <tr>
                @foreach ($report['columns'] as $col)
                    <th class="{{ ($col['align'] ?? 'left') === 'right' ? 'r' : '' }}">{{ $col['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($report['rows'] as $row)
                <tr>
                    @foreach ($report['columns'] as $col)
                        <td class="{{ ($col['align'] ?? 'left') === 'right' ? 'r' : '' }}">{{ $row[$col['key']] ?? '' }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($report['columns']) }}" style="text-align:center;color:#9ca3af;padding:18px;">{{ __('No records') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="foot">{{ __('Tenant-scoped report — figures reflect only your own courses.') }}</div>
</body>
</html>
