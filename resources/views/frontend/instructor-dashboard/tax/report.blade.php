@extends('frontend.instructor-dashboard.layouts.master')

@php $cur = \Illuminate\Support\Facades\Session::get('currency_icon', '₹'); @endphp

@section('dashboard-contents')
<div class="tax-ui">
    @include('frontend.instructor-dashboard.tax.partials.ui-styles')

    {{-- Page header --}}
    <div class="tx-head">
        <div>
            <h2>{{ __('Tax Report') }}</h2>
            <p>{{ __('Tax you have collected — for filing, audit & records.') }}</p>
        </div>
        <div class="d-flex align-items-center" style="gap:10px;">
            <a href="{{ route('instructor.tax.index') }}" class="tx-btn tx-btn--ghost tx-btn--sm">
                <i class="fas fa-cog"></i> {{ __('Tax Settings') }}
            </a>
            <a href="{{ route('instructor.tax.report', array_merge(request()->only(['from','to']), ['export' => 'csv'])) }}" class="tx-btn tx-btn--primary tx-btn--sm">
                <i class="fas fa-download"></i> {{ __('Export CSV') }}
            </a>
        </div>
    </div>

    {{-- Date filter --}}
    <form method="GET" action="{{ route('instructor.tax.report') }}" class="tx-filter">
        <div class="tx-field" style="gap:5px;">
            <label>{{ __('From') }}</label>
            <input type="date" name="from" value="{{ $from->format('Y-m-d') }}" class="tx-input" style="min-width:160px;">
        </div>
        <div class="tx-field" style="gap:5px;">
            <label>{{ __('To') }}</label>
            <input type="date" name="to" value="{{ $to->format('Y-m-d') }}" class="tx-input" style="min-width:160px;">
        </div>
        <button type="submit" class="tx-btn tx-btn--primary tx-btn--sm"><i class="fas fa-filter"></i> {{ __('Apply') }}</button>
    </form>

    {{-- KPI cards --}}
    <div class="tx-kpis">
        @php
            $kpis = [
                ['label' => __('Tax collected'),  'value' => $cur . number_format((float) $totals->tax, 2),     'icon' => 'fa-percent',     'bg' => '#ecfdf5', 'fg' => '#10b981'],
                ['label' => __('Taxable amount'), 'value' => $cur . number_format((float) $totals->taxable, 2), 'icon' => 'fa-coins',       'bg' => '#ecfdf5', 'fg' => '#10b981'],
                ['label' => __('Taxed orders'),   'value' => number_format((int) $totals->cnt),                 'icon' => 'fa-receipt',     'bg' => '#fff7ed', 'fg' => '#f59e0b'],
            ];
        @endphp
        @foreach ($kpis as $k)
            <div class="tx-kpi">
                <span class="tx-kpi__ic" style="background:{{ $k['bg'] }};color:{{ $k['fg'] }};"><i class="fas {{ $k['icon'] }}"></i></span>
                <div>
                    <div class="tx-kpi__val">{{ $k['value'] }}</div>
                    <div class="tx-kpi__lbl">{{ $k['label'] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Breakdown by rate --}}
    <div class="tx-card">
        <div class="tx-card__head">
            <span class="tx-card__ic"><i class="fas fa-layer-group"></i></span>
            <div>
                <h3 class="tx-card__title">{{ __('By tax rate') }}</h3>
                <p class="tx-card__sub">{{ __('How the collected tax breaks down across your rates.') }}</p>
            </div>
        </div>
        <div class="tx-card__body">
            <div class="tx-table-wrap">
                <table class="tx-table">
                    <thead>
                        <tr>
                            <th>{{ __('Rate') }}</th><th>{{ __('Mode') }}</th>
                            <th class="tx-num">{{ __('Orders') }}</th><th class="tx-num">{{ __('Taxable') }}</th><th class="tx-num">{{ __('Tax') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($byRate as $r)
                            <tr>
                                <td><strong>{{ $r->tax_label ?: __('Tax') }}</strong> @if($r->tax_rate_applied)<span style="color:#94a3b8;">({{ rtrim(rtrim(number_format($r->tax_rate_applied,3),'0'),'.') }}%)</span>@endif</td>
                                <td><span class="tx-pill tx-pill--muted" style="text-transform:capitalize;">{{ $r->tax_mode }}</span></td>
                                <td class="tx-num">{{ number_format((int) $r->cnt) }}</td>
                                <td class="tx-num">{{ $cur }}{{ number_format((float) $r->taxable, 2) }}</td>
                                <td class="tx-num"><strong>{{ $cur }}{{ number_format((float) $r->tax, 2) }}</strong></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="tx-empty">{{ __('No tax collected in this period.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Per-order detail --}}
    <div class="tx-card">
        <div class="tx-card__head">
            <span class="tx-card__ic"><i class="fas fa-file-invoice"></i></span>
            <div>
                <h3 class="tx-card__title">{{ __('Taxed orders') }}</h3>
                <p class="tx-card__sub">{{ __('Every paid order on which you collected tax.') }}</p>
            </div>
        </div>
        <div class="tx-card__body">
            <div class="tx-table-wrap">
                <table class="tx-table" style="min-width:720px;">
                    <thead>
                        <tr>
                            <th>{{ __('Invoice') }}</th><th>{{ __('Date') }}</th><th>{{ __('Student') }}</th>
                            <th>{{ __('Rate') }}</th><th class="tx-num">{{ __('Taxable') }}</th>
                            <th class="tx-num">{{ __('Tax') }}</th><th class="tx-num">{{ __('Total') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($orders as $o)
                            <tr>
                                <td><strong>#{{ $o->invoice_id }}</strong></td>
                                <td>{{ optional($o->created_at)->format('d M Y') }}</td>
                                <td>{{ optional($o->user)->name ?? '—' }}</td>
                                <td>{{ $o->tax_label }} @if($o->tax_rate_applied)<span style="color:#94a3b8;">({{ rtrim(rtrim(number_format($o->tax_rate_applied,3),'0'),'.') }}%)</span>@endif</td>
                                <td class="tx-num">{{ $cur }}{{ number_format((float) $o->taxable_amount, 2) }}</td>
                                <td class="tx-num">{{ $cur }}{{ number_format((float) $o->tax_amount, 2) }}</td>
                                <td class="tx-num"><strong>{{ $cur }}{{ number_format((float) $o->taxable_amount + (float) $o->tax_amount, 2) }}</strong></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="tx-empty">{{ __('No taxed orders in this period.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($orders->hasPages())
                <div class="mt-3">{{ $orders->withQueryString()->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
