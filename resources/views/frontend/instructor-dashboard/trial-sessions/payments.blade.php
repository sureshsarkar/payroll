@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
@include('frontend.instructor-dashboard.settings.partials._corporate')

<style>
    .ts-head h4 { font-size:20px; font-weight:750; color:#1c1a4a; margin:0; display:flex; align-items:center; gap:9px; }
    .ts-head p { color:#64748b; font-size:13.5px; margin:6px 0 0; }
    .ts-tabs { display:flex; gap:4px; border-bottom:1px solid #e6e8f0; margin:18px 0 22px; }
    .ts-tab { padding:10px 16px; font-size:14px; font-weight:600; color:#64748b; text-decoration:none;
              border-bottom:2px solid transparent; margin-bottom:-1px; }
    .ts-tab:hover { color:#1c1a4a; }
    .ts-tab.is-active { color:#10b981; border-bottom-color:#10b981; }
</style>

<style>
/* 2026-07-10 (New Changes for UI #4) — dark mode for this page's bespoke components. */
html[data-theme="dark"] .ts-head h4 { color:#e2e8f0; }
html[data-theme="dark"] .ts-head p { color:#94a3b8; }
html[data-theme="dark"] .ts-tab { color:#94a3b8; }
html[data-theme="dark"] .ts-tab:hover { color:#e2e8f0; }
</style>

<div class="corp-page">
    <div class="ts-head">
        <h4><i class="fas fa-receipt"></i> {{ __('Trial Session Payments') }}</h4>
        <p>{{ __('Payments collected for trial-session bookings.') }}</p>
    </div>

    <nav class="ts-tabs">
        <a href="{{ route('instructor.trial-sessions.index') }}" class="ts-tab">{{ __('Settings') }}</a>
        <a href="{{ route('instructor.trial-sessions.enquiries.index') }}" class="ts-tab">{{ __('Enquiries') }}</a>
        <a href="{{ route('instructor.trial-sessions.payments.index') }}" class="ts-tab is-active">{{ __('Payments') }}</a>
    </nav>

    <form method="GET" style="margin-bottom:14px;display:flex;gap:8px;flex-wrap:wrap;max-width:360px;">
        <select name="status" onchange="this.form.submit()" style="border:1px solid #e2e8f0;border-radius:10px;padding:9px 12px;font-size:14px;">
            <option value="">{{ __('All statuses') }}</option>
            @foreach(['pending'=>'Pending','paid'=>'Paid','failed'=>'Failed','refunded'=>'Refunded'] as $k=>$v)
                <option value="{{ $k }}" {{ request('status')===$k ? 'selected' : '' }}>{{ __($v) }}</option>
            @endforeach
        </select>
    </form>

    @if ($payments->count())
        <div class="corp-form-card">
            <div class="corp-form-card__body" style="overflow-x:auto;padding:0;">
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead>
                        <tr style="text-align:left;color:#64748b;border-bottom:1px solid #eef0f5;">
                            <th style="padding:10px 12px;font-weight:600;">{{ __('Customer') }}</th>
                            <th style="padding:10px 12px;font-weight:600;">{{ __('Amount') }}</th>
                            <th style="padding:10px 12px;font-weight:600;">{{ __('Gateway') }}</th>
                            <th style="padding:10px 12px;font-weight:600;">{{ __('Transaction') }}</th>
                            <th style="padding:10px 12px;font-weight:600;">{{ __('Status') }}</th>
                            <th style="padding:10px 12px;font-weight:600;">{{ __('Paid at') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($payments as $p)
                            <tr style="border-bottom:1px solid #f1f5f9;">
                                <td style="padding:10px 12px;">
                                    <div style="font-weight:600;color:#1c1a4a;">{{ $p->enquiry?->name ?? '—' }}</div>
                                    <div style="color:#64748b;">{{ $p->enquiry?->email }}</div>
                                </td>
                                <td style="padding:10px 12px;font-weight:600;">{{ $p->currency }} {{ number_format((float)$p->amount,2) }}</td>
                                <td style="padding:10px 12px;text-transform:capitalize;">{{ $p->gateway }}</td>
                                <td style="padding:10px 12px;color:#64748b;font-size:12px;">{{ $p->transaction_id ?: '—' }}</td>
                                <td style="padding:10px 12px;">
                                    @php
                                        $pm = ['paid'=>['#dcfce7','#166534'],'pending'=>['#fef9c3','#854d0e'],'failed'=>['#fee2e2','#991b1b'],'refunded'=>['#e2e8f0','#475569']][$p->status] ?? ['#f1f5f9','#475569'];
                                    @endphp
                                    <span style="background:{{ $pm[0] }};color:{{ $pm[1] }};border-radius:20px;padding:2px 10px;font-size:11.5px;text-transform:capitalize;">{{ $p->status }}</span>
                                </td>
                                <td style="padding:10px 12px;color:#64748b;white-space:nowrap;">{{ $p->paid_at?->format('d M Y, H:i') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div style="margin-top:14px;">{{ $payments->links() }}</div>
    @else
        <div class="corp-form-card"><div class="corp-form-card__body" style="padding:28px;text-align:center;color:#94a3b8;">{{ __('No payments yet.') }}</div></div>
    @endif
</div>
@endsection
