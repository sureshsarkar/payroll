@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
@include('frontend.instructor-dashboard.settings.partials._corporate')

@php
    // Roll-ups across the paginated/loaded request list — gives the
    // coach a quick read of "what's queued vs settled" without
    // counting badges in the table.
    $kpiPending  = $withdrawRequests->where('status', 'pending')->count();
    $kpiApproved = $withdrawRequests->where('status', 'approved')->count();
    $kpiRejected = $withdrawRequests->where('status', 'rejected')->count();
@endphp

<div class="corp-page" id="payout">

    {{-- Header ────────────────────────────────────────────────── --}}
    <div class="corp-header">
        <div class="corp-header__title">
            <h4>{{ __('Earnings & Payouts') }}</h4>
            <p>{{ __('Your wallet balance, lifetime payouts, and every withdrawal request you have raised. Request a payout when you are ready to cash out.') }}</p>
        </div>
        <div class="corp-header__actions">
            <a href="{{ route('instructor.payout.create') }}" class="btn-corp-primary">
                <i class="fas fa-wallet"></i> {{ __('Request Payout') }}
            </a>
        </div>
    </div>

    {{-- KPI icon chip styles (scoped) --}}
    <style>
        #payout .corp-kpi__tile { padding-left: 70px; min-height: 96px; }
        #payout .corp-kpi__tile .po-kpi-icon {
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
        html[data-theme="dark"] #payout .corp-kpi__tile .po-kpi-icon {
            background: color-mix(in srgb, var(--accent, var(--corp-brand)) 22%, #1e293b);
        }
    </style>

    {{-- Earnings KPI strip ─────────────────────────────────────── --}}
    <div class="corp-kpi">
        <div class="corp-kpi__tile" style="--accent:#10b981;">
            <span class="po-kpi-icon"><i class="fas fa-wallet"></i></span>
            <div class="corp-kpi__label">{{ __('Current Balance') }}</div>
            <div class="corp-kpi__value" style="color:#047857;">{{ currency(userAuth()->wallet_balance) }}</div>
            <div class="corp-kpi__sub">{{ __('available in your wallet') }}</div>
        </div>
        <div class="corp-kpi__tile" style="--accent:#f59e0b;">
            <span class="po-kpi-icon"><i class="fas fa-shopping-cart"></i></span>
            <div class="corp-kpi__label">{{ __('Courses Sold') }}</div>
            <div class="corp-kpi__value">{{ number_format($totalCourseSold) }}</div>
            <div class="corp-kpi__sub">{{ __('paid orders all-time') }}</div>
        </div>
        <div class="corp-kpi__tile" style="--accent:#0ea5e9;">
            <span class="po-kpi-icon"><i class="fas fa-hand-holding-usd"></i></span>
            <div class="corp-kpi__label">{{ __('Total Payout') }}</div>
            <div class="corp-kpi__value">{{ currency($totalWithdraw) }}</div>
            <div class="corp-kpi__sub">{{ __('approved withdrawals') }}</div>
        </div>
        <div class="corp-kpi__tile" style="--accent:#ef4444;">
            <span class="po-kpi-icon"><i class="fas fa-clock"></i></span>
            <div class="corp-kpi__label">{{ __('Pending Requests') }}</div>
            <div class="corp-kpi__value" style="color:{{ $kpiPending > 0 ? '#92400e' : 'var(--corp-text)' }};">
                {{ $kpiPending }}
            </div>
            <div class="corp-kpi__sub">
                {{ $kpiPending > 0 ? __('awaiting admin review') : __('all clear') }}
            </div>
        </div>
    </div>

    {{-- Payout history ─────────────────────────────────────────── --}}
    <div class="corp-form-card" style="margin-top:14px;">
        <div class="corp-form-card__head">
            <h6 class="corp-form-card__title">
                <i class="fas fa-history" style="color:var(--corp-brand);"></i>
                {{ __('Payout History') }}
                <span style="margin-left:auto; font-weight:500; color:var(--corp-muted); text-transform:none; letter-spacing:0; font-size:11px;">
                    {{ $withdrawRequests->count() }} {{ trans_choice('request|requests', $withdrawRequests->count()) }}
                </span>
            </h6>
            <p class="corp-form-card__sub">
                {{ __('Pending requests can be cancelled before admin review. Approved or rejected ones stay for audit.') }}
            </p>
        </div>

        <div class="corp-table-wrap" style="border:none; border-radius:0;">
            <table class="corp-table">
                <thead>
                    <tr>
                        <th style="width:60px;">{{ __('#') }}</th>
                        <th>{{ __('Withdraw Amount') }}</th>
                        <th>{{ __('Method') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Date') }}</th>
                        <th style="width:80px; text-align:right;">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($withdrawRequests as $key => $w)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>
                                <strong style="color:var(--corp-text);">{{ currency($w->withdraw_amount) }}</strong>
                            </td>
                            <td style="color:var(--corp-muted);">{{ $w->method }}</td>
                            <td>
                                @switch($w->status)
                                    @case('approved')
                                        <span class="corp-pill corp-pill--success">{{ __('Approved') }}</span>
                                        @break
                                    @case('rejected')
                                        <span class="corp-pill corp-pill--danger">{{ __('Rejected') }}</span>
                                        @break
                                    @case('pending')
                                        <span class="corp-pill corp-pill--warning">{{ __('Pending') }}</span>
                                        @break
                                    @default
                                        <span class="corp-pill corp-pill--muted">{{ ucfirst($w->status) }}</span>
                                @endswitch
                            </td>
                            <td style="color:var(--corp-muted); font-size:12.5px;">{{ formatDate($w->created_at) }}</td>
                            <td style="text-align:right;">
                                @if ($w->status === 'pending')
                                    <a href="{{ route('instructor.payout.destroy', $w->id) }}"
                                       class="corp-actions__btn corp-actions__btn--danger delete-item"
                                       title="{{ __('Cancel request') }}">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6">
                            <div class="corp-empty">
                                <div class="corp-empty__icon"><i class="fas fa-wallet"></i></div>
                                <div class="corp-empty__title">{{ __('No payout requests yet') }}</div>
                                <div class="corp-empty__hint">
                                    {{ __('Your withdrawal requests will appear here once you submit one. Add a payout method in Settings first.') }}
                                </div>
                                <a href="{{ route('instructor.payout.create') }}" class="btn-corp-primary" style="margin-top:14px;">
                                    <i class="fas fa-wallet"></i> {{ __('Request Your First Payout') }}
                                </a>
                            </div>
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
