@extends('frontend.student-dashboard.layouts.master')

@section('dashboard-contents')
    {{-- Order / Invoice detail. 2026-07-04: rebuilt as a clean invoice card.
         Display-only — every $order binding, the bundle/gift conditionals, the
         totals math, and the print link are preserved 1:1. --}}
    @php
        $paid = strtolower((string) $order->payment_status);
        $statusColor = in_array($paid, ['paid','success','completed','complete']) ? ['#065f46','#ecfdf5','#a7f3d0']
                     : (in_array($paid, ['pending','processing']) ? ['#92400e','#fffbeb','#fde68a']
                     : ['#991b1b','#fef2f2','#fecaca']);
    @endphp

    <div class="inv-page">
        <div class="inv-topbar">
            <div>
                <h4>{{ __('Order History') }}</h4>
                <p>{{ __('Order') }} #{{ $order->invoice_id }} · {{ formatDate($order->created_at) }}</p>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="{{ route('student.orders.index') }}" class="inv-btn"><i class="fas fa-arrow-left"></i> {{ __('Go Back') }}</a>
                <a target="_blank" href="{{ route('student.order.print-invoice', $order->id) }}" class="inv-print"><i class="fas fa-print"></i> {{ __('Print') }}</a>
            </div>
        </div>

        <div class="inv-card">
            <div class="inv-head">
                <div class="inv-head__brand">
                    <div class="inv-logo"><i class="fas fa-file-invoice"></i></div>
                    <div>
                        <div class="inv-head__num">#{{ $order->invoice_id }}</div>
                        <div class="inv-head__date">{{ formatDate($order->created_at) }}</div>
                    </div>
                </div>
                <span class="inv-status" style="color:{{ $statusColor[0] }};background:{{ $statusColor[1] }};border-color:{{ $statusColor[2] }};">
                    {{ ucfirst($order->payment_status) }}
                </span>
            </div>

            <div class="inv-meta">
                <div class="inv-meta__col">
                    <span class="inv-meta__label">{{ __('Billed To') }}</span>
                    <span class="inv-meta__name">{{ $order->user->name }}</span>
                    <span class="inv-meta__line">{{ $order->user->email }}</span>
                    @if ($order->user->phone)<span class="inv-meta__line">{{ $order->user->phone }}</span>@endif
                    @if ($order->user->address)<span class="inv-meta__line">{{ $order->user->address }}</span>@endif
                </div>
                <div class="inv-meta__col">
                    <span class="inv-meta__label">{{ __('Payment') }}</span>
                    <span class="inv-meta__name">{{ $order->payment_method }}</span>
                    <span class="inv-meta__line">{{ __('Status') }}: {{ ucfirst($order->payment_status) }}</span>
                    @if ($order->isBundleOrder())
                        <span class="inv-meta__line">{{ __('Bundle') }}: {{ $order?->order_details?->title }}</span>
                    @endif
                </div>
                @if ($order->isGiftOrder())
                    <div class="inv-meta__col">
                        <span class="inv-meta__label">{{ __('Gift') }}
                            @if (empty($order?->order_details?->verification_token))
                                <span class="inv-mini" style="color:#065f46;background:#ecfdf5;">{{ __('Claimed') }}</span>
                            @else
                                <span class="inv-mini" style="color:#92400e;background:#fffbeb;">{{ __('Pending') }}</span>
                            @endif
                        </span>
                        <span class="inv-meta__line">{{ $order?->order_details?->recipient_name }}</span>
                        <span class="inv-meta__line">{{ $order?->order_details?->recipient_email }}</span>
                    </div>
                @endif
            </div>

            <div class="inv-body">
                <div class="inv-section-title">{{ __('Order Summary') }}</div>
                <div style="overflow-x:auto;">
                    <table class="inv-table">
                        <thead>
                            <tr>
                                <th style="width:40px;">#</th>
                                <th>{{ __('Item') }}</th>
                                <th>{{ __('by') }}</th>
                                <th style="text-align:right;">{{ __('Price') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($order->orderItems as $item)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td style="font-weight:600;color:#0f172a;">{{ $item->course->title }}</td>
                                    <td>
                                        <span style="display:block;">{{ $item->course->instructor->name }}</span>
                                        <small style="color:#94a3b8;">{{ $item->course->instructor->email }}</small>
                                    </td>
                                    <td style="text-align:right;font-variant-numeric:tabular-nums;">
                                        @if ($order->isBundleOrder())
                                            —
                                        @else
                                            {{ $item->price * $order->conversion_rate }} {{ $order->payable_currency }}
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="inv-totals">
                    @if ($order->isBundleOrder())
                        @php
                            $subTotal = $order->payable_amount;
                            $subTotalWithCharge = $subTotal * $order->conversion_rate;
                            $gatewayCharge = 0;
                            if ($order->gateway_charge > 0) {
                                $gatewayCharge = ($order->gateway_charge / $subTotalWithCharge) * 100;
                            }
                            $total = number_format($subTotalWithCharge + $order->gateway_charge, 2);
                        @endphp
                        <div class="inv-row"><span>{{ __('Subtotal') }}</span><span>{{ number_format($subTotal * $order->conversion_rate, 2) }} {{ $order->payable_currency }}</span></div>
                        <div class="inv-row"><span>{{ __('Gateway Charge') }} ({{ number_format($gatewayCharge) }}%)</span><span>{{ number_format($order->gateway_charge, 2) }} {{ $order->payable_currency }}</span></div>
                        <div class="inv-row inv-row--total"><span>{{ __('Total') }}</span><span>{{ $total }} {{ $order->payable_currency }}</span></div>
                    @else
                        @php
                            $subTotal = 0; $discount = 0; $gatewayCharge = 0;
                            foreach ($order->orderItems as $item) { $subTotal += $item->price; }
                            $subTotalWithConversion = $subTotal * $order->conversion_rate;
                            if ($order->coupon_discount_amount > 0) { $discount = $order->coupon_discount_amount; }
                            if ($order->gateway_charge > 0) {
                                $gatewayCharge = ($order->gateway_charge / ($subTotalWithConversion - $discount)) * 100;
                            }
                            $total = number_format($subTotalWithConversion - $discount + $order->gateway_charge, 2);
                        @endphp
                        <div class="inv-row"><span>{{ __('Subtotal') }}</span><span>{{ number_format($subTotal * $order->conversion_rate, 2) }} {{ $order->payable_currency }}</span></div>
                        <div class="inv-row"><span>{{ __('Discount') }}</span><span>{{ number_format($discount, 2) }} {{ $order->payable_currency }}</span></div>
                        <div class="inv-row"><span>{{ __('Gateway Charge') }} ({{ number_format($gatewayCharge) }}%)</span><span>{{ number_format($order->gateway_charge, 2) }} {{ $order->payable_currency }}</span></div>
                        <div class="inv-row inv-row--total"><span>{{ __('Total') }}</span><span>{{ $total }} {{ $order->payable_currency }}</span></div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <style>
        .inv-page { font-family:'DM Sans',-apple-system,'Segoe UI',sans-serif; max-width:860px; }
        .inv-topbar { display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:18px; }
        .inv-topbar h4 { font-size:22px;font-weight:800;color:#0f172a;margin:0;letter-spacing:-.02em; }
        .inv-topbar p { font-size:13px;color:#64748b;margin:3px 0 0;font-variant-numeric:tabular-nums; }
        .inv-btn { display:inline-flex;align-items:center;gap:8px;background:#fff;color:#475569;border:1.5px solid #e8ecf2;
            border-radius:100px;padding:9px 20px;font-size:13.5px;font-weight:600;text-decoration:none;transition:all .16s; }
        .inv-btn:hover { background:#f8fafc;color:#0f172a; }
        .inv-print { display:inline-flex;align-items:center;gap:8px;background:#fff;color:#059669;border:1.5px solid #a7f3d0;
            border-radius:100px;padding:9px 20px;font-size:13.5px;font-weight:650;text-decoration:none;transition:all .16s; }
        .inv-print:hover { background:#ecfdf5;color:#059669;transform:translateY(-1px); }
        .inv-card { background:#fff;border:1px solid #e8ecf2;border-radius:18px;overflow:hidden;
            box-shadow:inset 0 1px 0 rgba(255,255,255,.7),0 1px 2px rgba(15,23,42,.04),0 20px 50px -28px rgba(15,23,42,.22); }
        .inv-head { display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;
            padding:22px 26px;background:linear-gradient(135deg,#10b981,#059669);color:#fff; }
        .inv-head__brand { display:flex;align-items:center;gap:13px; }
        .inv-logo { width:44px;height:44px;border-radius:12px;background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.3);
            display:grid;place-items:center;font-size:18px; }
        .inv-head__num { font-size:18px;font-weight:800;letter-spacing:-.01em; }
        .inv-head__date { font-size:12px;opacity:.85; }
        .inv-status { font-size:12px;font-weight:700;padding:5px 14px;border-radius:100px;border:1px solid;text-transform:capitalize; }
        .inv-meta { display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:20px;padding:22px 26px;border-bottom:1px solid #f1f5f9; }
        .inv-meta__col { display:flex;flex-direction:column;gap:3px; }
        .inv-meta__label { font-size:10.5px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#94a3b8;margin-bottom:4px;display:flex;align-items:center;gap:8px; }
        .inv-meta__name { font-size:14px;font-weight:700;color:#0f172a; }
        .inv-meta__line { font-size:12.5px;color:#64748b; }
        .inv-mini { font-size:10px;font-weight:700;padding:2px 8px;border-radius:100px; }
        .inv-body { padding:22px 26px 26px; }
        .inv-section-title { font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#94a3b8;margin-bottom:12px; }
        .inv-table { width:100%;border-collapse:collapse;font-size:13.5px;min-width:480px; }
        .inv-table th { text-align:left;font-size:10.5px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#94a3b8;
            padding:10px 12px;border-bottom:1px solid #e8ecf2; }
        .inv-table td { padding:13px 12px;border-bottom:1px solid #f1f5f9;color:#475569;vertical-align:top; }
        .inv-totals { margin-top:18px;margin-left:auto;max-width:340px;display:flex;flex-direction:column;gap:8px; }
        .inv-row { display:flex;justify-content:space-between;gap:14px;font-size:13.5px;color:#64748b;font-variant-numeric:tabular-nums; }
        .inv-row span:last-child { font-weight:600;color:#334155; }
        .inv-row--total { margin-top:6px;padding-top:12px;border-top:1px solid #e8ecf2;font-size:16px; }
        .inv-row--total span { color:#059669 !important;font-weight:800; }
        @media (max-width:560px){ .inv-head,.inv-meta,.inv-body{padding-left:18px;padding-right:18px;} }
    </style>

    <style>
        /* 2026-07-10 (New Changes for UI #4) — dark mode for this page's bespoke components. */
        html[data-theme="dark"] .inv-topbar h4 { color: #e2e8f0; }
        html[data-theme="dark"] .inv-topbar p { color: #94a3b8; }
        html[data-theme="dark"] .inv-btn { background: #1e293b; color: #94a3b8; border-color: #2a3a55; }
        html[data-theme="dark"] .inv-btn:hover { background: #17233a; color: #e2e8f0; }
        html[data-theme="dark"] .inv-print { background: #1e293b; }
        html[data-theme="dark"] .inv-card { background: #1e293b; border-color: #2a3a55; box-shadow: none; }
        html[data-theme="dark"] .inv-meta { border-bottom-color: #2a3a55; }
        html[data-theme="dark"] .inv-meta__name { color: #e2e8f0; }
        html[data-theme="dark"] .inv-meta__line { color: #94a3b8; }
        html[data-theme="dark"] .inv-table th { border-bottom-color: #2a3a55; }
        html[data-theme="dark"] .inv-table td { border-bottom-color: #2a3a55; color: #94a3b8; }
        html[data-theme="dark"] .inv-row { color: #94a3b8; }
        html[data-theme="dark"] .inv-row--total { border-top-color: #2a3a55; }
    </style>
@endsection
