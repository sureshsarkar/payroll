@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
@include('frontend.instructor-dashboard.settings.partials._corporate')

@php
    // KPI roll-up over the (paginated) subscription history rows.
    // We use ->getCollection() so KPIs reflect ONLY the current page;
    // for true totals we'd need a separate count query — but the
    // page is paginated 15 at a time and the strip is meant as a
    // "what am I looking at" cue, not an all-time roll-up.
    $kpiTotal    = $subscription_history->total();
    $items       = $subscription_history->getCollection();
    $kpiActive   = $items->where('status', 'active')->count();
    $kpiInactive = $items->where('status', '!=', 'active')->count();
    $kpiSpend    = (float) $items->sum(fn ($r) => (float) ($r->price ?? 0));
@endphp

<div class="corp-page" id="subHistory">

    {{-- Header ────────────────────────────────────────────────── --}}
    <div class="corp-header">
        <div class="corp-header__title">
            <h4>{{ __('Subscription History') }}</h4>
            <p>{{ __('Every plan you have ever subscribed to — active, expired, or cancelled. Click a row to see the receipt details.') }}</p>
        </div>
        <div class="corp-header__actions">
            <a href="{{ route('membership.index') }}" class="btn-corp-primary">
                <i class="fas fa-shield-alt"></i> {{ __('Membership Plans') }}
            </a>
        </div>
    </div>

    {{-- KPI icon chip styles (scoped) --}}
    <style>
        #subHistory .corp-kpi__tile { padding-left: 70px; min-height: 96px; }
        #subHistory .corp-kpi__tile .sh-kpi-icon {
            position: absolute; top: 18px; left: 18px;
            width: 38px; height: 38px; border-radius: 10px;
            display: inline-flex; align-items: center; justify-content: center;
            background: color-mix(in srgb, var(--accent, var(--corp-brand)) 12%, #ffffff);
            color: var(--accent, var(--corp-brand));
            border: 1px solid color-mix(in srgb, var(--accent, var(--corp-brand)) 18%, transparent);
            font-size: 15px;
        }
    </style>

    <style>
        /* 2026-07-10 (New Changes for UI #4) — dark mode for this page's bespoke components. */
        html[data-theme="dark"] #subHistory .corp-kpi__tile .sh-kpi-icon {
            background: color-mix(in srgb, var(--accent, var(--corp-brand)) 12%, #1e293b);
        }
    </style>

    {{-- KPI strip ──────────────────────────────────────────────── --}}
    <div class="corp-kpi">
        <div class="corp-kpi__tile" style="--accent:var(--corp-brand);">
            <span class="sh-kpi-icon"><i class="fas fa-history"></i></span>
            <div class="corp-kpi__label">{{ __('Total Records') }}</div>
            <div class="corp-kpi__value">{{ number_format($kpiTotal) }}</div>
            <div class="corp-kpi__sub">{{ __('plans you have purchased') }}</div>
        </div>
        <div class="corp-kpi__tile" style="--accent:#10b981;">
            <span class="sh-kpi-icon"><i class="fas fa-check-circle"></i></span>
            <div class="corp-kpi__label">{{ __('Active') }}</div>
            <div class="corp-kpi__value" style="color:#047857;">{{ number_format($kpiActive) }}</div>
            <div class="corp-kpi__sub">{{ __('on this page') }}</div>
        </div>
        <div class="corp-kpi__tile" style="--accent:#ef4444;">
            <span class="sh-kpi-icon"><i class="fas fa-times-circle"></i></span>
            <div class="corp-kpi__label">{{ __('Inactive') }}</div>
            <div class="corp-kpi__value" style="color:#b91c1c;">{{ number_format($kpiInactive) }}</div>
            <div class="corp-kpi__sub">{{ __('on this page') }}</div>
        </div>
        <div class="corp-kpi__tile" style="--accent:#f59e0b;">
            <span class="sh-kpi-icon"><i class="fas fa-coins"></i></span>
            <div class="corp-kpi__label">{{ __('Spend (this page)') }}</div>
            <div class="corp-kpi__value">₹{{ number_format($kpiSpend, 0) }}</div>
            <div class="corp-kpi__sub">{{ __('sum of visible rows') }}</div>
        </div>
    </div>

    {{-- Table ─────────────────────────────────────────────────── --}}
    <div class="corp-table-wrap">
        <table class="corp-table">
            <thead>
                <tr>
                    <th style="width:60px;">{{ __('#') }}</th>
                    <th>{{ __('Plan') }}</th>
                    <th>{{ __('Price') }}</th>
                    <th>{{ __('Duration') }}</th>
                    <th>{{ __('Start') }}</th>
                    <th>{{ __('End') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th style="width:80px; text-align:right;">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($subscription_history as $index => $c)
                    <tr>
                        <td>{{ ($subscription_history->currentPage() - 1) * $subscription_history->perPage() + $loop->iteration }}</td>
                        <td>
                            <div style="font-weight:600;">{{ $c->subscription->name ?? '—' }}</div>
                        </td>
                        <td>₹{{ number_format((float) ($c->price ?? 0), 0) }}</td>
                        <td>{{ ($c->subscription->duration_days ?? 0) }} {{ __('days') }}</td>
                        <td style="font-size:12.5px; color:var(--corp-muted);">{{ $c->start_date }}</td>
                        <td style="font-size:12.5px; color:var(--corp-muted);">{{ $c->end_date }}</td>
                        <td>
                            @if ($c->status === 'active')
                                <span class="corp-pill corp-pill--success">{{ __('Active') }}</span>
                            @else
                                <span class="corp-pill corp-pill--danger">{{ __('Inactive') }}</span>
                            @endif
                        </td>
                        <td style="text-align:right;">
                            <a href="{{ route('instructor.subscription-histories.show', $c->id) }}"
                               class="corp-actions__btn" title="{{ __('View receipt') }}">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8">
                        <div class="corp-empty">
                            <div class="corp-empty__icon"><i class="fas fa-receipt"></i></div>
                            <div class="corp-empty__title">{{ __('No subscriptions yet') }}</div>
                            <div class="corp-empty__hint">
                                {{ __('When you subscribe to a membership plan, the history appears here.') }}
                            </div>
                            <a href="{{ route('membership.index') }}" class="btn-corp-primary" style="margin-top:14px;">
                                <i class="fas fa-shield-alt"></i> {{ __('Browse Plans') }}
                            </a>
                        </div>
                    </td></tr>
                @endforelse
            </tbody>
        </table>

        @if ($subscription_history->hasPages())
            <div class="corp-pagination">{{ $subscription_history->links() }}</div>
        @endif
    </div>
</div>
@endsection
