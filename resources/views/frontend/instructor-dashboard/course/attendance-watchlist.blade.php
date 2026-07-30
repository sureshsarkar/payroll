@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
{{-- 2026-07-04 — corp header (banners / at-risk tables below unchanged). --}}
@include('frontend.instructor-dashboard.settings.partials._corporate')
<div class="dashboard__content-wrap">
    <div class="corp-header">
        <div class="corp-header__title">
            <h4><i class="fas fa-triangle-exclamation" style="color:var(--corp-brand);"></i> {{ __('Attendance watchlist') }}</h4>
            <p>{{ $course->title }} · {{ __('Threshold') }}: <strong>{{ $threshold }}%</strong>
               · {{ __('Live classes') }}: <strong>{{ $totals['total_classes'] }}</strong>
               · {{ __('Enrolled') }}: <strong>{{ $totals['enrolled'] }}</strong></p>
        </div>
        <div class="corp-header__actions">
            <a href="{{ route('instructor.courses.attendance-watchlist.export', ['id' => $course->id]) }}" class="btn-corp-secondary">
                <i class="fas fa-download"></i> {{ __('Download CSV') }}
            </a>
            <a href="{{ route('instructor.courses.index') }}" class="btn-corp-secondary">
                <i class="fas fa-arrow-left"></i> {{ __('Back') }}
            </a>
        </div>
    </div>

    {{-- At-risk summary banner — drives instructor attention. Three states:
         (a) threshold=0: feature disabled, surface as informational.
         (b) total_classes=0: feature inert, no live classes yet to attend.
         (c) at_risk>0: red banner. (d) at_risk=0: green banner. --}}
    @if ($threshold === 0)
        <div class="alert alert-secondary">
            ℹ {{ __('Attendance threshold is set to 0% — no students will be flagged.') }}
            <a href="{{ route('instructor.courses.edit', ['id' => $course->id, 'step' => 2]) }}">
                {{ __('Edit course settings') }}
            </a>
        </div>
    @elseif ($totals['total_classes'] === 0)
        <div class="alert alert-info">
            ℹ {{ __('No live classes scheduled yet — the watchlist will populate once you create live classes and students start attending.') }}
        </div>
    @elseif ($totals['at_risk'] > 0)
        <div class="alert alert-danger">
            🚨 <strong>{{ $totals['at_risk'] }}</strong>
            {{ trans_choice('{1} student is|[2,*] students are', $totals['at_risk']) }}
            {{ __('below the') }} <strong>{{ $threshold }}%</strong>
            {{ __('attendance threshold across this course\'s :count live classes.', ['count' => $totals['total_classes']]) }}
        </div>
    @else
        <div class="alert alert-success">
            ✓ {{ __('All enrolled students are meeting the') }} <strong>{{ $threshold }}%</strong>
            {{ __('attendance threshold.') }}
        </div>
    @endif

    {{-- Trend chart (#6 — 2026-05-12). One bar per live class.
         Height = % attended; color = green if ≥ threshold, red if not.
         Pure CSS, no JS lib needed — keeps the watchlist page light. --}}
    @if (!empty($trend))
        <div class="card mb-3" style="border:1px solid #e5e7eb;border-radius:12px;">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">📈 {{ __('Attendance trend by class') }}</h5>
                <span class="small text-muted">
                    {{ __('Threshold') }}: <strong>{{ $threshold }}%</strong>
                </span>
            </div>
            <div class="card-body">
                <div style="display:flex;align-items:flex-end;gap:6px;height:140px;border-bottom:1px solid #e5e7eb;padding-bottom:4px;">
                    @foreach ($trend as $t)
                        @php
                            // Bar height = percent (so 100% = full height 140px).
                            // Below-threshold = red, at/above = green.
                            $pct       = (float) $t['percent'];
                            $heightPx  = max(2, round($pct * 1.3));   // 100% → 130px, 0% → 2px (so empty classes show a sliver)
                            $color     = ($threshold > 0 && $pct < $threshold) ? '#ef4444' : '#10b981';
                            $bgFaded   = ($threshold > 0 && $pct < $threshold) ? '#fee2e2' : '#d1fae5';
                        @endphp
                        <div style="flex:1;min-width:32px;text-align:center;"
                             title="{{ $t['title'] }} — {{ $t['attended_count'] }}/{{ $t['enrolled'] }} ({{ $pct }}%)">
                            <div style="height:130px;display:flex;align-items:flex-end;justify-content:center;">
                                <div style="width:60%;background:{{ $color }};height:{{ $heightPx }}px;border-radius:3px 3px 0 0;"></div>
                            </div>
                            <div class="small text-muted" style="white-space:nowrap;font-size:10px;margin-top:4px;">
                                {{ $t['date'] }}
                            </div>
                            <div style="font-size:10px;font-weight:600;color:{{ $color }};">
                                {{ (int) $pct }}%
                            </div>
                        </div>
                    @endforeach
                </div>
                {{-- Threshold reference line — visual marker so the
                     instructor sees "where the bar should reach". --}}
                @if ($threshold > 0)
                    <p class="text-muted small mt-2 mb-0">
                        ↑ {{ __('Bars below') }} <strong style="color:#ef4444;">{{ $threshold }}%</strong>
                        {{ __('indicate a class where enrollment fell below the attendance threshold.') }}
                    </p>
                @endif
            </div>
        </div>
    @endif

    {{-- Heatmap (#11) — student × class grid. Each cell is colored by
         attendance state. Lets the instructor see attendance patterns
         in O(1) eye-scan instead of digging through per-lesson pages.
         Only rendered when there's both classes AND students to plot. --}}
    @if (!empty($heatmap['students']) && !empty($heatmap['classes']))
        <div class="card mb-3" style="border:1px solid #e5e7eb;border-radius:12px;">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">📊 {{ __('Attendance heatmap') }}</h5>
                <div class="small">
                    <span style="display:inline-block;width:12px;height:12px;background:#10b981;border-radius:2px;vertical-align:middle;"></span>
                    {{ __('Attended') }}
                    &nbsp;
                    <span style="display:inline-block;width:12px;height:12px;background:#f59e0b;border-radius:2px;vertical-align:middle;"></span>
                    {{ __('Briefly') }}
                    &nbsp;
                    <span style="display:inline-block;width:12px;height:12px;background:#fee2e2;border:1px solid #fecaca;border-radius:2px;vertical-align:middle;"></span>
                    {{ __('Missed') }}
                </div>
            </div>
            <div class="card-body p-0" style="overflow-x:auto;">
                <table class="table table-borderless mb-0 align-middle" style="font-size:11px;">
                    <thead class="table-light">
                        <tr>
                            <th style="position:sticky;left:0;background:#f8fafc;min-width:180px;z-index:1;">
                                {{ __('Student') }}
                            </th>
                            @foreach ($heatmap['classes'] as $col)
                                <th title="{{ $col->title }}" style="text-align:center;white-space:nowrap;font-weight:600;">
                                    {{ $col->date }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($heatmap['students'] as $sr)
                            <tr>
                                <td style="position:sticky;left:0;background:#fff;font-weight:600;color:#1c1a4a;">
                                    {{ $sr['name'] }}
                                </td>
                                @foreach ($sr['cells'] as $state)
                                    @php
                                        $color = match ($state) {
                                            'attended' => '#10b981',
                                            'partial'  => '#f59e0b',
                                            'missed'   => '#fee2e2',
                                            default    => '#f1f5f9',
                                        };
                                        $label = match ($state) {
                                            'attended' => __('Attended'),
                                            'partial'  => __('Joined briefly'),
                                            'missed'   => __('Missed'),
                                            default    => '',
                                        };
                                    @endphp
                                    <td style="text-align:center;padding:4px;">
                                        <span style="display:inline-block;width:18px;height:18px;background:{{ $color }};border-radius:3px;@if ($state === 'missed') border:1px solid #fecaca; @endif"
                                              title="{{ $label }}"></span>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">{{ __('Students') }}</h5>
            <span class="badge bg-secondary">{{ count($rows) }} {{ __('enrolled') }}</span>
        </div>
        <div class="card-body p-0">
            @if (empty($rows))
                <p class="text-muted text-center p-4">{{ __('No students enrolled yet.') }}</p>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('Student') }}</th>
                                <th class="text-center">{{ __('Attended') }}</th>
                                <th class="text-center">{{ __('Attendance %') }}</th>
                                <th>{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $r)
                                <tr class="{{ $r['at_risk'] ? 'table-danger' : '' }}">
                                    <td>
                                        <div class="fw-semibold">{{ $r['name'] ?: '—' }}</div>
                                        <div class="text-muted small">{{ $r['email'] }}</div>
                                    </td>
                                    <td class="text-center">
                                        {{ $r['attended_count'] }} / {{ $r['total_classes'] }}
                                    </td>
                                    <td class="text-center">
                                        <strong>{{ $r['percent'] }}%</strong>
                                    </td>
                                    <td>
                                        @if ($r['at_risk'])
                                            <span class="badge bg-danger">{{ __('At risk') }}</span>
                                        @else
                                            <span class="badge bg-success">{{ __('OK') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
