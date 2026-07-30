@php
    // DomPDF-safe, coach-branded receipt (plain tables, inline styles, no flex /
    // gradients / color-mix / web fonts / remote images). White-label: the coach's
    // own name + brand colour + currency. Currency shown as the CODE (e.g. INR) so
    // it always renders in DejaVu Sans (the ₹/glyph set may be missing).
    $brandName = $brand->name ?? config('app.name');
    $primary   = (is_object($brand) && !empty($brand->primaryColor)) ? $brand->primaryColor : '#10b981';
    $cur       = $curCode ?: 'INR';
    $money     = fn ($v) => $cur . ' ' . number_format((float) $v, 2);
@endphp
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: 'DejaVu Sans', sans-serif; }
        body { margin: 0; color: #1c1a4a; font-size: 12px; }
        .hdr { background: {{ $primary }}; color: #ffffff; padding: 18px 24px; }
        .hdr .bn { font-size: 18px; font-weight: bold; }
        .hdr .rl { font-size: 11px; }
        .hdr .rn { font-size: 14px; font-weight: bold; }
        .body { padding: 22px 24px; }
        .tag { display: inline-block; font-size: 10px; font-weight: bold; text-transform: uppercase;
               padding: 3px 10px; background: #e1f5ee; color: #0f6e56; border-radius: 10px; }
        .amt { font-size: 26px; font-weight: bold; margin: 12px 0 2px; }
        .muted { color: #64748b; font-size: 12px; }
        table.det { width: 100%; border-collapse: collapse; margin-top: 16px; font-size: 12px; }
        table.det td { padding: 7px 0; border-top: 1px solid #eef2f7; }
        table.det td.k { color: #64748b; width: 46%; }
        table.det td.v { text-align: right; font-weight: bold; }
        .ft { padding: 14px 24px; border-top: 1px solid #eef2f7; color: #94a3b8; font-size: 10px; }
    </style>
</head>
<body>
    <div class="hdr">
        <table width="100%"><tr>
            <td><span class="bn">{{ $brandName }}</span></td>
            <td align="right"><div class="rl">{{ __('Payment receipt') }}</div><div class="rn">{{ $op->receipt_no }}</div></td>
        </tr></table>
    </div>
    <div class="body">
        <span class="tag">{{ $op->cancelled_at ? __('Cancelled') : __('Received') }}</span>
        <div class="amt">{{ $money($op->amount) }}</div>
        <div class="muted">{{ __('Paid via') }} {{ $op->methodLabel() }} &middot; {{ optional($op->paid_at)->format('d M Y') }}</div>

        <table class="det">
            <tr><td class="k">{{ __('Student') }}</td><td class="v">{{ $op->student->name ?: '-' }}</td></tr>
            <tr><td class="k">{{ __('For') }}</td><td class="v">{{ $op->course->title ?: __('Fee / other') }}</td></tr>
            <tr><td class="k">{{ __('Payment method') }}</td><td class="v">{{ $op->methodLabel() }}</td></tr>
            @if ($op->tax_amount)
                <tr><td class="k">{{ __('Base amount') }}</td><td class="v">{{ $money($op->base_amount) }}</td></tr>
                <tr><td class="k">{{ $op->tax_label ?: __('Tax') }}@if ($op->tax_rate) ({{ rtrim(rtrim(number_format((float) $op->tax_rate, 2), '0'), '.') }}%)@endif</td><td class="v">{{ $money($op->tax_amount) }}</td></tr>
            @endif
            @if ($op->reference_no)<tr><td class="k">{{ __('Reference no.') }}</td><td class="v">{{ $op->reference_no }}</td></tr>@endif
            <tr><td class="k">{{ __('Payment date') }}</td><td class="v">{{ optional($op->paid_at)->format('d M Y') }}</td></tr>
            <tr><td class="k">{{ __('Receipt no.') }}</td><td class="v">{{ $op->receipt_no }}</td></tr>
            @if ($op->notes)<tr><td class="k">{{ __('Notes') }}</td><td class="v">{{ $op->notes }}</td></tr>@endif
        </table>
    </div>
    <div class="ft">
        {{ __('Recorded by') }} {{ $op->recorder->name ?: '-' }}
        @if ($op->approved_at) &middot; {{ __('Approved by') }} {{ $op->approver->name ?: '-' }} @endif
        &middot; {{ __('Issued by') }} {{ $brandName }}. {{ __('Computer-generated receipt for an offline payment.') }}
    </div>
</body>
</html>
