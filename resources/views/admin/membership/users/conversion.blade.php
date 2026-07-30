@extends('admin.master_layout')
@section('title')<title>{{ __('Trial Conversion Report') }}</title>@endsection
@section('admin-content')
<div class="main-content">
    <section class="section">
        <div class="section-header">
            <div class="section-header-back">
                <a href="{{ route('admin.user-memberships.index') }}" class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
            </div>
            <h1><i class="fas fa-chart-line"></i> {{ __('Trial Conversion Report') }}</h1>
        </div>

        {{-- Date range form --}}
        <div class="card mb-3">
            <div class="card-body">
                {{-- M7 fix (2026-05-12) — date inputs now properly labeled. --}}
                <form method="GET" class="form-inline" style="gap:10px;">
                    <div class="form-group">
                        <label for="conversion-from" class="mr-2 small text-muted">{{ __('From') }}</label>
                        <input id="conversion-from" type="date" name="from" value="{{ $from->format('Y-m-d') }}" class="form-control form-control-sm">
                    </div>
                    <div class="form-group">
                        <label for="conversion-to" class="mr-2 small text-muted">{{ __('To') }}</label>
                        <input id="conversion-to" type="date" name="to" value="{{ $to->format('Y-m-d') }}" class="form-control form-control-sm">
                    </div>
                    <button class="btn btn-primary btn-sm">{{ __('Apply') }}</button>
                    <a href="{{ route('admin.user-memberships.conversion') }}" class="btn btn-link btn-sm">{{ __('Reset (last 90 days)') }}</a>
                </form>
                <div class="text-muted small mt-2">
                    {{ __('Showing trials that started between') }} <strong>{{ $from->format('M d, Y') }}</strong> {{ __('and') }} <strong>{{ $to->format('M d, Y') }}</strong>
                </div>
            </div>
        </div>

        {{-- Funnel cards --}}
        <div class="row">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card" style="padding:18px; border-left:4px solid #5751e1;">
                    <div style="font-size:11px; color:#9ca3af; text-transform:uppercase; font-weight:600;">{{ __('Trials started') }}</div>
                    <div style="font-size:30px; font-weight:800; color:#1c1a4a; margin-top:4px;">{{ $totalTrials }}</div>
                    <div style="font-size:12px; color:#6b7280;">{{ __('coaches in this cohort') }}</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card" style="padding:18px; border-left:4px solid #10b981;">
                    <div style="font-size:11px; color:#9ca3af; text-transform:uppercase; font-weight:600;">{{ __('Converted to paid') }}</div>
                    <div style="font-size:30px; font-weight:800; color:#10b981; margin-top:4px;">{{ $converted }}</div>
                    <div style="font-size:12px; color:#6b7280;">{{ __('of :n trials', ['n' => $totalTrials]) }}</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card" style="padding:18px; border-left:4px solid #f59e0b;">
                    <div style="font-size:11px; color:#9ca3af; text-transform:uppercase; font-weight:600;">{{ __('Conversion rate') }}</div>
                    <div style="font-size:30px; font-weight:800; color:#f59e0b; margin-top:4px;">{{ $conversionRate }}%</div>
                    <div style="font-size:12px; color:#6b7280;">
                        @if ($conversionRate < 5) {{ __('Low — review pricing/onboarding') }}
                        @elseif ($conversionRate < 15) {{ __('Below industry average') }}
                        @elseif ($conversionRate < 30) {{ __('On track for SaaS norms') }}
                        @else {{ __('Excellent') }} 🎉
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card" style="padding:18px; border-left:4px solid #3b82f6;">
                    <div style="font-size:11px; color:#9ca3af; text-transform:uppercase; font-weight:600;">{{ __('Avg days to paid') }}</div>
                    <div style="font-size:30px; font-weight:800; color:#3b82f6; margin-top:4px;">{{ $avgDaysToPaid }}</div>
                    <div style="font-size:12px; color:#6b7280;">{{ __('days from trial start to first paid plan') }}</div>
                </div>
            </div>
        </div>

        {{-- Cohort breakdown --}}
        <div class="row">
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-header"><h5 style="margin:0; font-size:14px; font-weight:700;">{{ __('Cohort breakdown') }}</h5></div>
                    <div class="card-body">
                        @php
                            $segs = [
                                ['label' => __('Converted to paid'),    'count' => $converted,  'tone' => '#10b981'],
                                ['label' => __('Still on trial'),       'count' => $stillTrial, 'tone' => '#3b82f6'],
                                ['label' => __('Churned (no upgrade)'), 'count' => $churned,    'tone' => '#ef4444'],
                            ];
                            $totalForBars = max($totalTrials, 1);
                        @endphp
                        @foreach ($segs as $s)
                            <div style="margin-bottom:12px;">
                                <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:4px;">
                                    <span><strong>{{ $s['label'] }}</strong></span>
                                    <span class="text-muted">{{ $s['count'] }} ({{ $totalTrials > 0 ? round(($s['count'] / $totalTrials) * 100, 1) : 0 }}%)</span>
                                </div>
                                <div style="height:8px; background:#f3f4f6; border-radius:4px; overflow:hidden;">
                                    <div style="height:100%; width:{{ ($s['count'] / $totalForBars) * 100 }}%; background:{{ $s['tone'] }};"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-header"><h5 style="margin:0; font-size:14px; font-weight:700;">{{ __('Most-converted-to plans') }}</h5></div>
                    <div class="card-body">
                        @if ($converted === 0)
                            <p class="text-muted small">{{ __('No conversions in this window yet.') }}</p>
                        @else
                            @foreach ($convertedToPlan as $planId => $count)
                                @php $planName = $plansById[$planId] ?? 'Unknown plan'; @endphp
                                <div style="display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #f3f4f6;">
                                    <span style="font-size:13px;">{{ $planName }}</span>
                                    <span class="badge badge-success">{{ $count }} {{ __('conversions') }}</span>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Recent cohort sample --}}
        <div class="card">
            <div class="card-header"><h5 style="margin:0; font-size:14px; font-weight:700;">{{ __('Cohort sample (most recent 25)') }}</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead><tr>
                            <th>{{ __('Coach') }}</th>
                            <th>{{ __('Trial started') }}</th>
                            <th>{{ __('Converted to') }}</th>
                            <th>{{ __('Days to paid') }}</th>
                            <th>{{ __('Status') }}</th>
                        </tr></thead>
                        <tbody>
                        @forelse ($sample as $row)
                            <tr>
                                <td><strong>{{ $row['user']?->name ?? '—' }}</strong><br><small class="text-muted">{{ $row['user']?->email }}</small></td>
                                <td>{{ $row['trial_started']?->format('M d, Y') }}</td>
                                <td>{{ $row['paid_plan'] ?? '—' }}</td>
                                <td>{{ $row['days_to_paid'] !== null ? $row['days_to_paid'] . ' ' . __('days') : '—' }}</td>
                                <td>
                                    @if ($row['paid_at'])
                                        <span class="badge badge-success">{{ __('Converted') }}</span>
                                    @elseif ($row['trial_status'] === 'active')
                                        <span class="badge badge-info">{{ __('Active trial') }}</span>
                                    @else
                                        <span class="badge badge-secondary">{{ __('Churned') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted p-4">{{ __('No trials in the selected window.') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
