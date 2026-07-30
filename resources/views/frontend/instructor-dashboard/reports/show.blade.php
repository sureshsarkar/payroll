@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
@include('frontend.instructor-dashboard.settings.partials._corporate')

@php
    $tabs = [
        'revenue'    => ['label' => __('Revenue'),    'icon' => 'bi-graph-up-arrow'],
        'payments'   => ['label' => __('Payments'),   'icon' => 'bi-credit-card'],
        'invoices'   => ['label' => __('Invoices'),   'icon' => 'bi-receipt'],
        'attendance' => ['label' => __('Attendance'), 'icon' => 'bi-calendar-check'],
    ];
    $qbase = request()->except(['page']);
@endphp

<div class="corp-page" id="reportsPage">
    <div class="corp-header">
        <div class="corp-header__title">
            <h4><i class="fas fa-chart-pie"></i> {{ $report['title'] }}</h4>
            <p>{{ __('Filter, sort and export. Figures are scoped to your own courses.') }}</p>
        </div>
        <div class="corp-header__actions">
            <a href="{{ route('instructor.reports.index') }}" class="btn-corp-secondary" style="height:38px;display:inline-flex;align-items:center;gap:7px;padding:0 14px;">
                <i class="fas fa-th-large"></i> {{ __('All reports') }}
            </a>
        </div>
    </div>

    {{-- Report switcher --}}
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
        @foreach ($tabs as $type => $t)
            <a href="{{ route('instructor.reports.show', $type) }}"
               style="display:inline-flex;align-items:center;gap:7px;padding:7px 14px;border-radius:999px;font-size:12.5px;font-weight:600;text-decoration:none;
                      {{ $report['type'] === $type ? 'background:#10b981;color:#fff;' : 'background:#fff;color:#475569;border:1px solid #e6e8f0;' }}">
                <i class="bi {{ $t['icon'] }}"></i> {{ $t['label'] }}
            </a>
        @endforeach
    </div>

    {{-- Summary cards --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px;margin-bottom:18px;">
        @foreach ($report['summary'] as $s)
            <div style="background:#fff;border:1px solid #eef0f3;border-radius:12px;padding:14px 16px;position:relative;overflow:hidden;">
                <div style="position:absolute;left:0;top:0;bottom:0;width:3px;background:{{ $s['accent'] }};"></div>
                <div style="color:#64748b;font-size:12px;font-weight:600;">{{ $s['label'] }}</div>
                <div style="font-size:20px;font-weight:800;color:#0f172a;margin-top:4px;">{{ $s['value'] }}</div>
            </div>
        @endforeach
    </div>

    {{-- Filter bar --}}
    <form method="GET" action="{{ route('instructor.reports.show', $report['type']) }}" id="reportFilter"
          style="background:#fff;border:1px solid #eef0f3;border-radius:12px;padding:14px 16px;margin-bottom:16px;display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
        <div>
            <label style="display:block;font-size:11px;color:#64748b;font-weight:600;margin-bottom:4px;">{{ __('From') }}</label>
            <input type="date" name="from" value="{{ optional($report['from'])->format('Y-m-d') }}" style="height:38px;border:1px solid #e6e8f0;border-radius:8px;padding:0 10px;">
        </div>
        <div>
            <label style="display:block;font-size:11px;color:#64748b;font-weight:600;margin-bottom:4px;">{{ __('To') }}</label>
            <input type="date" name="to" value="{{ optional($report['to'])->format('Y-m-d') }}" style="height:38px;border:1px solid #e6e8f0;border-radius:8px;padding:0 10px;">
        </div>
        <div>
            <label style="display:block;font-size:11px;color:#64748b;font-weight:600;margin-bottom:4px;">{{ __('Course') }}</label>
            <select name="course_id" style="height:38px;border:1px solid #e6e8f0;border-radius:8px;padding:0 10px;min-width:160px;">
                <option value="">{{ __('All courses') }}</option>
                @foreach ($courses as $id => $title)
                    <option value="{{ $id }}" @selected((string) $report['course'] === (string) $id)>{{ $title }}</option>
                @endforeach
            </select>
        </div>
        <div style="flex:1;min-width:160px;">
            <label style="display:block;font-size:11px;color:#64748b;font-weight:600;margin-bottom:4px;">{{ __('Search') }}</label>
            <input type="text" name="q" value="{{ $report['search'] }}" placeholder="{{ __('Search in results…') }}" style="width:100%;height:38px;border:1px solid #e6e8f0;border-radius:8px;padding:0 10px;">
        </div>
        @if ($report['sort'])
            <input type="hidden" name="sort" value="{{ $report['sort'] }}">
            <input type="hidden" name="dir" value="{{ $report['dir'] }}">
        @endif
        <div style="display:flex;gap:8px;">
            <button type="submit" class="btn-corp-primary" style="height:38px;padding:0 16px;"><i class="fas fa-filter"></i> {{ __('Apply') }}</button>
            <a href="{{ route('instructor.reports.show', $report['type']) }}" class="btn-corp-secondary" style="height:38px;display:inline-flex;align-items:center;padding:0 14px;">{{ __('Reset') }}</a>
        </div>
    </form>

    {{-- Export + count row --}}
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:10px;">
        <div style="color:#64748b;font-size:12.5px;">{{ trans_choice('{0}No records|{1}:count record|[2,*]:count records', $rows->total(), ['count' => number_format($rows->total())]) }}</div>
        <div style="display:flex;gap:8px;">
            @foreach (['csv' => 'CSV', 'xlsx' => 'Excel', 'pdf' => 'PDF'] as $fmt => $lbl)
                <a href="{{ route('instructor.reports.export', $report['type']) }}?{{ http_build_query(array_merge($qbase, ['format' => $fmt])) }}"
                   class="btn-corp-secondary" style="height:34px;display:inline-flex;align-items:center;gap:6px;padding:0 12px;font-size:12.5px;">
                    <i class="fas fa-download"></i> {{ $lbl }}
                </a>
            @endforeach
        </div>
    </div>

    {{-- Table --}}
    <div class="corp-form-card">
        <div class="corp-form-card__body" style="overflow-x:auto;position:relative;">
            <div id="reportLoading" style="display:none;position:absolute;inset:0;background:rgba(255,255,255,.6);z-index:5;align-items:center;justify-content:center;">
                <span style="color:#10b981;font-weight:600;"><i class="fas fa-spinner fa-spin"></i> {{ __('Loading…') }}</span>
            </div>
            @if ($rows->total() > 0)
                <table style="width:100%;border-collapse:collapse;font-size:13.5px;">
                    <thead>
                        <tr style="text-align:left;color:#64748b;border-bottom:1px solid #eef0f5;">
                            @foreach ($report['columns'] as $col)
                                @php
                                    $align = $col['align'] ?? 'left';
                                    $isSorted = ($report['sort'] === $col['key']);
                                    $nextDir = ($isSorted && $report['dir'] === 'asc') ? 'desc' : 'asc';
                                    $sortUrl = request()->fullUrlWithQuery(['sort' => $col['key'], 'dir' => $nextDir, 'page' => 1]);
                                @endphp
                                <th style="padding:12px 14px;font-weight:600;text-align:{{ $align }};">
                                    @if (!empty($col['sortable']))
                                        <a href="{{ $sortUrl }}" style="color:inherit;text-decoration:none;white-space:nowrap;">
                                            {{ $col['label'] }}
                                            <i class="fas fa-sort{{ $isSorted ? ($report['dir'] === 'asc' ? '-up' : '-down') : '' }}" style="font-size:10px;opacity:{{ $isSorted ? 1 : .4 }};"></i>
                                        </a>
                                    @else
                                        {{ $col['label'] }}
                                    @endif
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr style="border-bottom:1px solid #f3f4f6;">
                                @foreach ($report['columns'] as $col)
                                    <td style="padding:12px 14px;text-align:{{ $col['align'] ?? 'left' }};">{{ $row[$col['key']] ?? '' }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div style="text-align:center;padding:48px 20px;color:#94a3b8;">
                    <i class="fas fa-folder-open" style="font-size:34px;color:#cbd5e1;"></i>
                    <div style="margin-top:12px;font-size:14px;font-weight:600;color:#64748b;">{{ __('No records for this filter') }}</div>
                    <div style="font-size:12.5px;margin-top:4px;">{{ __('Try a wider date range or a different course.') }}</div>
                </div>
            @endif
        </div>
    </div>

    @if ($rows->hasPages())
        <div style="margin-top:16px;">{{ $rows->onEachSide(1)->links() }}</div>
    @endif
</div>

<style>
    html[data-theme="dark"] #reportsPage div[style*="background:#fff"],
    html[data-theme="dark"] #reportsPage form,
    html[data-theme="dark"] #reportsPage .corp-form-card { background:#1e293b !important; border-color:#2a3a55 !important; }
    html[data-theme="dark"] #reportsPage td, html[data-theme="dark"] #reportsPage h4,
    html[data-theme="dark"] #reportsPage div[style*="color:#0f172a"] { color:#e2e8f0 !important; }
    html[data-theme="dark"] #reportsPage input, html[data-theme="dark"] #reportsPage select { background:#0f1e33 !important; color:#e2e8f0 !important; border-color:#2a3a55 !important; }
    html[data-theme="dark"] #reportLoading { background:rgba(15,23,42,.6) !important; }
</style>
<script>
    (function () {
        var form = document.getElementById('reportFilter');
        var loading = document.getElementById('reportLoading');
        if (form && loading) form.addEventListener('submit', function () { loading.style.display = 'flex'; });
    })();
</script>
@endsection
