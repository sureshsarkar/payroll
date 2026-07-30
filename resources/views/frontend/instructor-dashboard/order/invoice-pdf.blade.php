{{--
    2026-06-02 — DomPDF-compatible invoice (coach PDF download).
    The legacy order/invoice.blade.php styles <table> as display:flex for a
    responsive HTML layout, which DomPDF cannot render ("Parent table not found
    for table cell"). This template uses plain table layout + a Unicode font so
    it produces a clean, professional PDF (and prints fine in a browser too).
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: "DejaVu Sans", sans-serif; }
        body { color: #1f2937; font-size: 13px; margin: 0; padding: 28px 32px; }
        .brand { font-size: 22px; font-weight: bold; color: #29a65c; }
        .muted { color: #6b7280; }
        h2.inv { margin: 0; font-size: 20px; letter-spacing: 3px; color: #111827; }
        table { border-collapse: collapse; width: 100%; }
        table.items td, table.items th { border: 1px solid #e5e7eb; padding: 9px 12px; text-align: left; font-size: 12.5px; }
        table.items th { background: #f3f4f6; color: #374151; }
        .right { text-align: right; }
        .toprule { border-bottom: 3px solid #29a65c; padding-bottom: 14px; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 4px; font-size: 11px; font-weight: bold; }
    </style>
</head>
<body>

    {{-- Header --}}
    <table class="toprule">
        <tr>
            <td style="vertical-align: top;">
                @php $invBrand = orderInvoiceBrand($order); @endphp
                <div class="brand">{{ $invBrand->name }}</div>
                @if (!empty($invBrand->email)) <div class="muted">{{ $invBrand->email }}</div> @endif
                @if (!empty($invBrand->phone)) <div class="muted">{{ $invBrand->phone }}</div> @endif
            </td>
            <td style="vertical-align: top; text-align: right;">
                <h2 class="inv">{{ __('INVOICE') }}</h2>
                <div style="margin-top:6px;"># {{ $order->invoice_id ?? $order->id }}</div>
                <div class="muted">{{ function_exists('formatDate') ? formatDate($order->created_at) : optional($order->created_at)->format('d M Y') }}</div>
            </td>
        </tr>
    </table>

    {{-- Bill to / payment --}}
    <table style="margin-top: 18px;">
        <tr>
            <td style="vertical-align: top; width: 55%;">
                <div style="font-weight: bold; margin-bottom: 4px;">{{ __('Billed To') }}</div>
                <div>{{ $order->user->name ?? __('—') }}</div>
                @if (!empty($order->user?->email)) <div class="muted">{{ $order->user->email }}</div> @endif
                @if (!empty($order->user?->phone)) <div class="muted">{{ $order->user->phone }}</div> @endif
            </td>
            <td style="vertical-align: top; width: 45%; text-align: right;">
                <div style="font-weight: bold; margin-bottom: 4px;">{{ __('Payment') }}</div>
                @php
                    $ps = $order->payment_status ?? 'pending';
                    $bg = $ps === 'paid' ? '#ecfdf5' : ($ps === 'refunded' ? '#fef2f2' : '#fffbeb');
                    $fg = $ps === 'paid' ? '#047857' : ($ps === 'refunded' ? '#991b1b' : '#92400e');
                @endphp
                <span class="badge" style="background: {{ $bg }}; color: {{ $fg }};">{{ ucfirst($ps) }}</span>
                @if (!empty($order->payment_method)) <div class="muted" style="margin-top:5px;">{{ $order->payment_method }}</div> @endif
            </td>
        </tr>
    </table>

    {{-- Items --}}
    <table class="items" style="margin-top: 18px;">
        <thead>
            <tr>
                <th style="width: 8%;">{{ __('No') }}.</th>
                <th>{{ __('Item') }}</th>
                <th style="width: 28%;">{{ __('By') }}</th>
                <th class="right" style="width: 20%;">{{ __('Price') }}</th>
            </tr>
        </thead>
        <tbody>
            @php $grand = 0; @endphp
            @foreach ($order->orderItems as $i => $item)
                @php $line = (float) ($item->price ?? 0) * (int) ($item->qty ?: 1); $grand += $line; @endphp
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $item->course?->title ?? __('—') }}@if (($item->qty ?? 1) > 1) <span class="muted"> × {{ $item->qty }}</span>@endif</td>
                    <td>{{ $item->course?->instructor?->name ?? __('—') }}</td>
                    <td class="right">{{ function_exists('currency') ? currency($line) : number_format($line, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- 2026-07-07 ("Coach Order amount Issue") — full money breakdown so the
         coupon discount, the amount the student actually paid, and the coach's
         earnings after commission are all shown (commission is on the discounted
         amount, not the original price). --}}
    @php
        $invOrig = $order->grossItemsTotal();
        $invDisc = $order->couponDiscountAmount();
        $invPaid = $order->finalPaidAmount();
        $invEarn = $order->coachEarnings();
        $cur = fn ($v) => function_exists('currency') ? currency($v) : number_format($v, 2);
    @endphp
    <table style="margin-top: 12px;">
        <tr>
            <td class="right" style="width: 70%;">{{ __('Original Course Price') }}</td>
            <td class="right">{{ $cur($invOrig) }}</td>
        </tr>
        @if ($invDisc > 0)
            <tr>
                <td class="right">{{ __('Coupon Discount') }} @if ($order->coupon_code)<span class="muted">({{ $order->coupon_code }})</span>@endif</td>
                <td class="right" style="color:#b45309;">− {{ $cur($invDisc) }}</td>
            </tr>
        @endif
        <tr>
            <td class="right" style="font-weight: bold;">{{ __('Final Amount Paid by Student') }}</td>
            <td class="right" style="font-weight: bold; font-size: 15px;">{{ $cur($invPaid) }}</td>
        </tr>
        <tr>
            <td class="right" style="font-weight: bold; color:#047857;">{{ __('Final Coach Earnings') }} <span class="muted" style="font-weight:normal;">({{ __('after commission') }})</span></td>
            <td class="right" style="font-weight: bold; font-size: 15px; color: #047857;">{{ $cur($invEarn) }}</td>
        </tr>
    </table>

    <p class="muted" style="margin-top: 28px; font-size: 12px;">
        {{ __('Thank you for your purchase.') }} — {{ $invBrand->name }}
    </p>

</body>
</html>
