@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
@include('frontend.instructor-dashboard.settings.partials._corporate')
@php $s = $summary; $plan = $s['plan']; @endphp

<div class="corp-page">
    <div class="corp-header">
        <div class="corp-header__title">
            <h4><i class="fas fa-id-card"></i> {{ __('Plan & Billing') }}</h4>
            <p>{{ __('Your current plan and billing details. These are managed by the platform — contact support to change your plan.') }}</p>
        </div>
    </div>

    @if(!$plan)
        <div class="corp-form-card"><div class="corp-form-card__body" style="text-align:center;padding:40px 20px;color:#94a3b8;">
            <i class="fas fa-id-card" style="font-size:32px;margin-bottom:10px;"></i>
            <p style="margin:0;">{{ __('No active plan assigned yet. Please contact the platform.') }}</p>
        </div></div>
    @else
        {{-- Plan header card --}}
        <div class="corp-form-card" style="margin-bottom:16px;">
            <div class="corp-form-card__body" style="display:flex;flex-wrap:wrap;align-items:center;gap:16px;justify-content:space-between;">
                <div>
                    <div style="font-size:12px;text-transform:uppercase;letter-spacing:.05em;color:#94a3b8;">{{ __('Current plan') }}</div>
                    <div style="font-size:24px;font-weight:800;color:#1e293b;">{{ $plan->name }}
                        <span style="font-size:11px;text-transform:uppercase;background:#ecfdf5;color:#4f46e5;padding:2px 9px;border-radius:999px;vertical-align:middle;">{{ $plan->tier }}</span>
                    </div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:28px;font-weight:800;color:#4f46e5;">{{ currency($s['subscription']) }}<span style="font-size:14px;color:#64748b;font-weight:400;"> /{{ $plan->duration_days >= 28 && $plan->duration_days <= 31 ? __('mo') : $plan->duration_days.' '.__('days') }}</span></div>
                    @if($s['expires_at'])<div style="font-size:12px;color:#64748b;">{{ __('Renews / expires') }}: {{ $s['expires_at']->format('d M Y') }}</div>@endif
                </div>
            </div>
        </div>

        {{-- Key figures grid --}}
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px;margin-bottom:16px;">
            @php
                $tile = function ($label, $value, $sub = null) {
                    return '<div class="corp-form-card"><div class="corp-form-card__body" style="padding:16px 18px;">'
                        . '<div style="font-size:12px;color:#94a3b8;text-transform:uppercase;letter-spacing:.04em;">'.$label.'</div>'
                        . '<div style="font-size:22px;font-weight:700;color:#1e293b;margin-top:4px;">'.$value.'</div>'
                        . ($sub ? '<div style="font-size:11.5px;color:#64748b;margin-top:2px;">'.$sub.'</div>' : '')
                        . '</div></div>';
                };
            @endphp
            {!! $tile(__('Setup fee'), $s['setup_custom'] ? __('Custom') : currency($s['setup_fee'] ?? 0), __('One-time')) !!}
            {!! $tile(__('Platform commission'), rtrim(rtrim(number_format($s['commission_rate'],2),'0'),'.').'%', __('Deducted per sale')) !!}
            {!! $tile(__('Student capacity'), $s['capacity_unlimited'] ? __('Unlimited') : number_format($s['capacity']), $s['capacity_unlimited'] ? __(':n students', ['n'=>number_format($s['capacity_used'])]) : __(':used of :cap used', ['used'=>number_format($s['capacity_used']),'cap'=>number_format($s['capacity'])])) !!}
            {!! $tile(__('Settlement'), $s['direct_settlement'] ? __('Direct to bank') : __('Payout request'), $s['direct_settlement'] ? __('No payout request needed') : __('Request from Payout page')) !!}
        </div>

        {{-- Revenue split --}}
        <div class="corp-form-card">
            <div class="corp-form-card__body">
                <h6 class="mb-3" style="text-transform:uppercase;letter-spacing:.04em;color:#64748b;">{{ __('Lifetime earnings (paid orders)') }}</h6>
                {{-- AUD-028 — earnings shown per original currency (never combined across
                     currencies). formatMoney is session-rate independent (currency() would
                     multiply these historical totals by the viewer's live session rate). --}}
                @php $bc = !empty($s['by_currency']) ? $s['by_currency'] : [['currency'=>$s['primary_currency'] ?? null,'gross'=>$s['gross'],'platform_commission'=>$s['platform_commission'],'coach_revenue'=>$s['coach_revenue'],'is_exception'=>false]]; @endphp
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;">
                    <div><div style="font-size:12px;color:#94a3b8;">{{ __('Gross sales') }}</div><div style="font-size:20px;font-weight:700;">@foreach($bc as $g)<div>{{ formatMoneyCur($g['gross'], $g['currency']) }}</div>@endforeach</div></div>
                    <div><div style="font-size:12px;color:#94a3b8;">{{ __('Platform commission') }}</div><div style="font-size:20px;font-weight:700;color:#b91c1c;">@foreach($bc as $g)<div>− {{ formatMoneyCur($g['platform_commission'], $g['currency']) }}</div>@endforeach</div></div>
                    <div><div style="font-size:12px;color:#94a3b8;">{{ __('Your revenue') }}</div><div style="font-size:20px;font-weight:700;color:#16a34a;">@foreach($bc as $g)<div>{{ formatMoneyCur($g['coach_revenue'], $g['currency']) }}</div>@endforeach</div></div>
                    <div><div style="font-size:12px;color:#94a3b8;">{{ __('Wallet balance') }}</div><div style="font-size:20px;font-weight:700;color:#4f46e5;">{{ formatMoneyCur($s['wallet_balance'], $s['primary_currency'] ?? null) }}</div></div>
                </div>
                @if(!empty($plan->features))
                    <hr>
                    <h6 class="mb-2" style="text-transform:uppercase;letter-spacing:.04em;color:#64748b;">{{ __('Plan includes') }}</h6>
                    <ul style="columns:2;margin:0;padding-left:18px;color:#334155;font-size:13.5px;">
                        @foreach($plan->features as $f)<li style="margin-bottom:5px;">{{ $f }}</li>@endforeach
                    </ul>
                @endif
            </div>
        </div>
    @endif
</div>
@endsection
