@php
    // White-label: every visual comes from the COACH's brand, never the platform.
    $brandName = $brand->name ?? config('app.name');
    $primary   = (is_object($brand) && !empty($brand->primaryColor)) ? $brand->primaryColor : '#10b981';
    $logo      = null;
    try { $logo = method_exists($brand, 'logoUrl') ? $brand->logoUrl() : ($brand->logo ?? null); } catch (\Throwable $e) {}
    // Guard: a receipt must never crash on currency resolution (e.g. no default
    // currency configured). Fall back to the session icon, else ₹.
    try { getSessionCurrency(); } catch (\Throwable $e) {}
    $curIcon = session('currency_icon') ?: '₹';
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Receipt') }} {{ $op->receipt_no }} — {{ $brandName }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin:0; font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; background:#f1f5f9; color:#0f172a; }
        .sheet { max-width:640px; margin:24px auto; background:#fff; border-radius:14px; overflow:hidden; box-shadow:0 8px 30px rgba(15,23,42,.10); }
        .rh { padding:22px 26px; color:#fff; background:{{ $primary }}; display:flex; align-items:center; gap:14px; }
        .rh img { height:38px; max-width:150px; object-fit:contain; }
        .rh .bn { font-size:19px; font-weight:700; }
        .rh .rt { margin-left:auto; text-align:right; font-size:12px; opacity:.92; }
        .rb { padding:24px 26px; }
        .tag { display:inline-block; font-size:11px; font-weight:600; letter-spacing:.04em; text-transform:uppercase; padding:4px 12px; border-radius:999px; background:rgba(16,185,129,.12); color:#0f6e56; }
        .amt { font-size:34px; font-weight:800; margin:14px 0 4px; }
        .muted { color:#64748b; font-size:13px; }
        table.det { width:100%; border-collapse:collapse; margin-top:18px; font-size:13.5px; }
        table.det td { padding:9px 0; border-top:1px solid #eef2f7; }
        table.det td:first-child { color:#64748b; width:42%; }
        table.det td:last-child { text-align:right; font-weight:500; }
        .rf { padding:16px 26px 24px; border-top:1px solid #eef2f7; color:#94a3b8; font-size:11.5px; }
        .noprint { text-align:center; margin:16px; }
        .noprint button { border:1px solid {{ $primary }}; color:{{ $primary }}; background:#fff; border-radius:8px; padding:9px 18px; font-size:13px; cursor:pointer; }
        @media print { body { background:#fff; } .sheet { box-shadow:none; margin:0; } .noprint { display:none; } }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="rh">
            @if ($logo)<img src="{{ $logo }}" alt="{{ $brandName }}">@else<span class="bn">{{ $brandName }}</span>@endif
            <div class="rt">
                <div>{{ __('Payment receipt') }}</div>
                <div style="font-weight:700; font-size:14px;">{{ $op->receipt_no }}</div>
            </div>
        </div>
        <div class="rb">
            <span class="tag">
                {{ $op->cancelled_at ? __('Cancelled') : ($op->isAwaitingApproval() ? __('Awaiting approval') : __('Received')) }}
            </span>
            <div class="amt">{{ $curIcon }}{{ number_format((float) $op->amount, 2) }}</div>
            <div class="muted">{{ __('Paid via') }} {{ $op->methodLabel() }} · {{ optional($op->paid_at)->format('d M Y') }}</div>
            @if ($op->tax_amount)
                <div class="muted" style="margin-top:3px;">
                    {{ __('Incl.') }} {{ $op->tax_label ?: __('tax') }}@if ($op->tax_rate) ({{ rtrim(rtrim(number_format((float) $op->tax_rate, 2), '0'), '.') }}%)@endif:
                    {{ $curIcon }}{{ number_format((float) $op->tax_amount, 2) }}
                </div>
            @endif

            <table class="det">
                <tr><td>{{ __('Student') }}</td><td>{{ $op->student->name ?: '—' }}</td></tr>
                <tr><td>{{ __('For') }}</td><td>{{ $op->course->title ?: __('Fee / other') }}</td></tr>
                <tr><td>{{ __('Payment method') }}</td><td>{{ $op->methodLabel() }}</td></tr>
                @if ($op->tax_amount)
                    <tr><td>{{ __('Base amount') }}</td><td>{{ $curIcon }}{{ number_format((float) $op->base_amount, 2) }}</td></tr>
                    <tr><td>{{ $op->tax_label ?: __('Tax') }}@if ($op->tax_rate) ({{ rtrim(rtrim(number_format((float) $op->tax_rate, 2), '0'), '.') }}%)@endif</td><td>{{ $curIcon }}{{ number_format((float) $op->tax_amount, 2) }}</td></tr>
                @endif
                @if ($op->reference_no)<tr><td>{{ __('Reference no.') }}</td><td>{{ $op->reference_no }}</td></tr>@endif
                <tr><td>{{ __('Payment date') }}</td><td>{{ optional($op->paid_at)->format('d M Y') }}</td></tr>
                <tr><td>{{ __('Receipt no.') }}</td><td>{{ $op->receipt_no }}</td></tr>
                @if ($op->notes)<tr><td>{{ __('Notes') }}</td><td>{{ $op->notes }}</td></tr>@endif
            </table>
        </div>
        <div class="rf">
            {{ __('Recorded by') }} {{ $op->recorder->name ?: '—' }}
            @if ($op->approved_at) · {{ __('Approved by') }} {{ $op->approver->name ?: '—' }} @endif
            · {{ __('Issued by') }} {{ $brandName }}. {{ __('This is a computer-generated receipt for an offline payment.') }}
        </div>
    </div>
    <div class="noprint"><button onclick="window.print()">{{ __('Print / Save PDF') }}</button></div>
</body>
</html>
