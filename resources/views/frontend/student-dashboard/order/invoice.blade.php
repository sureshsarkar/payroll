<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Invoice') }}</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        .invoice_area {
            font-family: sans-serif;
        }

        /* Dompdf doesn't support `display: flex` — the original CSS used
           flexbox on <table>, which broke PDF rendering with "Parent
           table not found for table cell". The browser version relied on
           the flex fallback; the PDF needs the native table layout.
           Browsers fall back to `display: table` for <table>, so this
           change is invisible there too. */
        .invoice_area table {
            width: 100%;
            border-collapse: collapse;
        }

        .invoice_area table tr td {
            text-transform: capitalize;
            font-size: 16px;
            vertical-align: top;
        }

        .invoice_header {
            background: #f3f2f2;
            padding: 30px;
        }

        .invoice_header table tr td {
            width: 49%;
        }

        .invoice_header table tr td.left a {
            display: block;
            width: 160px;
        }

        .invoice_header table tr td.right h2 {
            text-transform: uppercase;
            font-size: 28px;
            font-weight: 600;
            line-height: initial;
            margin-bottom: 10px;
            text-align: right;
            color: #05092B;
        }

        .invoice_header table tr td.right h5 {
            font-size: 16px;
            text-align: right;
            color: #05092B;
        }

        .invoice_billing_info {
            padding: 30px;
        }

        .invoice_billing_info table tr td h5 {
            font-size: 20px;
            font-weight: 700;
            text-transform: capitalize;
            margin-bottom: 10px;
            color: #05092B;
        }

        .invoice_billing_info table tr td p {
            color: #545353;
            font-weight: 400;
            font-size: 16px;
            margin-top: 10px;
        }

        .invoice_billing_info .right p {
            text-align: right;
        }

        .invoice_billing_info .right p span {
            display: inline-block;
            width: 70px;
            text-align: right;
        }

        .invoice_billing_info .right p:last-child,
        .invoice_billing_info .right p:last-child span {
            font-weight: 600;
        }

        .invoice_billing_order {
            padding: 30px;
        }

        .invoice_billing_order table {
            border: 1px solid rgb(238, 238, 238);
        }

        .invoice_billing_order table thead {
            width: 100%;
        }

        .invoice_billing_order table thead tr {
            background: #f3f2f2;
        }

        .invoice_billing_order table thead tr th {
            background: #f3f2f2;
        }

        .invoice_billing_order table tr th,
        .invoice_billing_order table tr td {
            padding: 10px 20px;
            border-right: 1px solid rgb(238, 238, 238);
            border-bottom: 1px solid rgb(238, 238, 238);
            width: 25%;
            color: #05092B;
            text-align: left;
        }

        .sl-no {
            width: 10% !important;
        }

        .item {
            width: 40% !important;
        }

        .by {
            width: 35% !important;
        }

        .price {
            width: 15% !important;
        }

        .invoice_billing_info .right p span {
            display: inline-block;
            width: 150px !important;
            text-align: right;
        }
    </style>
</head>

<body>

    <div class="invoice_area">

        <div class="invoice_header">
            <div class="table-responsive">
                <table class="table">
                    <tbody>
                        <tr>
                            <td class="left">
                                @php $invBrand = orderInvoiceBrand($order); @endphp
                                <a href="javascript:;">
                                    @if($invBrand->logo)
                                        <img src="{{ $invBrand->logo }}" alt="{{ $invBrand->name }}">
                                    @else
                                        <h3 style="margin:0;color:#29a65c;font-weight:700;">{{ $invBrand->name }}</h3>
                                    @endif
                                </a>
                                @if ($order->isBundleOrder())
                                    <br>
                                    <h5>{{ __('Bundle Name') }}: {{ $order?->order_details?->title }}</h5>
                                @endif
                                @if ($order->isGiftOrder())
                                    <br>
                                    <address>
                                        <strong>{{ __('Gift') }}:</strong><br>
                                        <span>{{ __('Recipient Name') }}:</span>
                                        {{ $order?->order_details?->recipient_name }}<br>
                                        <span>{{ __('Recipient Email') }}:</span>
                                        {{ $order?->order_details?->recipient_email }}<br>
                                        {{ __('Status') }}: 
                                        @if (empty($order?->order_details?->verification_token))
                                            <span>{{ __('Claimed') }}</span>
                                        @else
                                            <span>{{ __('Pending') }}</span>
                                        @endif
                                    </address>
                                @endif
                            </td>
                            <td class="right">
                                <h2>{{ __('invoice') }}</h2>
                                <h5>{{ __('Order Id: ') }}{{ $order->invoice_id }}</h5>
                                <br>
                                <h5>{{ __('Date: ') }}{{ formatDate($order->created_at) }}</h5>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="invoice_billing_info">
            <div class="table-responsive">
                <table class="table">
                    <tbody>
                        <tr>
                            <td>
                                <h5>{{ __('Billed To') }}</h5>
                                <p>{{ $order->user->name }}</p>
                                <p>{{ $order->user->phone }}</p>
                                <p>{{ $order->user->email }}</p>
                                <p>{{ $order->user->address }}</p>

                            </td>
                            <td>
                                <h5>{{ __('Billed From') }}</h5>
                                <p>{{ $invBrand->name }}</p>
                                @if($invBrand->email)<p>{{ $invBrand->email }}</p>@endif
                                @if($invBrand->phone)<p>{{ $invBrand->phone }}</p>@endif
                                @if($invBrand->address)<p>{{ $invBrand->address }}</p>@endif
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="invoice_billing_order">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th class="sl-no">{{ __('No') }}.</th>
                            <th class="item">{{ __('Item') }}</th>
                            <th class="by">{{ __('By') }}</th>
                            <th class="price">{{ __('Price') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($order->orderItems as $item)
                            <tr>
                                <td class="sl-no">{{ $loop->iteration }}</td>
                                <td class="item">{{ $item->course->title }}</td>
                                <td class="by">
                                    {{ $item->course->instructor->name }}
                                    <br>
                                    <small>{{ $item->course->instructor->email }}</small>
                                </td>
                                <td class="price">
                                    @if ($order->isBundleOrder())
                                        --
                                    @else
                                        {{ $item->price * $order->conversion_rate }}
                                        {{ $order->payable_currency }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="invoice_billing_info">
            <div class="table-responsive">
                <table class="table">
                    <tbody>
                        <tr>
                            <td class="left">
                                <h5>{{ __('Payment Details') }}</h5>
                                <p><b>{{ __('Payment Method') }} :</b> {{ $order->payment_method }}</p>
                                <p><b>{{ __('Payment Status') }} :</b> {{ $order->payment_status }}</p>
                            </td>
                            <td class="right">
                                @if ($order->isBundleOrder())
                                    @php
                                        $subTotal = $order->payable_amount;
                                        $subTotalWithCharge = $order->payable_amount * $order->conversion_rate;
                                        $gatewayCharge = 0;
                                        if ($order->gateway_charge > 0) {
                                            $gatewayCharge = ($order->gateway_charge / $subTotalWithCharge) * 100;
                                        }
                                        $total = number_format($subTotalWithCharge + $order->gateway_charge, 2);
                                    @endphp
                                    <p>{{ __('Sub Total') }}
                                        :<span>{{ number_format($subTotal * $order->conversion_rate, 2) }}
                                        </span> {{ $order->payable_currency }}</p>
                                    <p>{{ __('Gateway Charge') }}
                                        ({{ $gatewayCharge }}%):<span> {{ number_format($order->gateway_charge, 2) }}
                                            {{ $order->payable_currency }}</span> </p>
                                    <p>{{ __('Total') }} :<span>{{ $total }}
                                        </span> {{ $order->payable_currency }}</p>
                                @else
                                    @php
                                        $discount = $order->coupon_discount_amount > 0 ? $order->coupon_discount_amount : 0;
                                        // 2026-06-13 — tax-aware totals. When tax was charged we use the
                                        // stored snapshot (taxable + tax) which is mode-agnostic: exclusive
                                        // (base+tax) and inclusive ((base-tax)+tax) both reconcile to the
                                        // amount paid. No-tax orders keep the original line-sum behaviour.
                                        $hasTax = (float) $order->tax_amount > 0;
                                        if ($hasTax) {
                                            $subTotal = (float) $order->taxable_amount;
                                            $taxAmount = (float) $order->tax_amount;
                                        } else {
                                            $subTotal = 0;
                                            foreach ($order->orderItems as $item) { $subTotal += $item->price; }
                                            $taxAmount = 0;
                                        }
                                        $subTotalWithConversion = $subTotal * $order->conversion_rate;
                                        $taxWithConversion = $taxAmount * $order->conversion_rate;
                                        $gatewayCharge = 0;
                                        if ($order->gateway_charge > 0) {
                                            $gatewayCharge = ($order->gateway_charge / max(0.01, ($subTotalWithConversion + $taxWithConversion - $discount))) * 100;
                                        }
                                        $total = number_format(
                                            $subTotalWithConversion - $discount + $taxWithConversion + $order->gateway_charge,
                                            2,
                                        );
                                    @endphp

                                    <p>{{ $hasTax ? __('Taxable Amount') : __('Sub Total') }}
                                        :<span>{{ number_format($subTotalWithConversion, 2) }}
                                        </span> {{ $order->payable_currency }}</p>
                                    <p>{{ __('Discount') }}
                                        :<span>{{ number_format($discount, 2) }}
                                            {{ $order->payable_currency }}</span></p>
                                    @if ($hasTax)
                                        {{-- 2026-06-13 — show each tax component (e.g. CGST + SGST)
                                             separately for GST compliance; fall back to a single line. --}}
                                        @if (!empty($order->tax_components) && is_array($order->tax_components))
                                            @foreach ($order->tax_components as $tc)
                                                <p>{{ $tc['name'] ?? __('Tax') }}
                                                    @if(isset($tc['rate'])) ({{ rtrim(rtrim(number_format((float)$tc['rate'],3),'0'),'.') }}%) @endif
                                                    :<span>{{ number_format((float)($tc['amount'] ?? 0) * $order->conversion_rate, 2) }}
                                                    {{ $order->payable_currency }}</span></p>
                                            @endforeach
                                        @else
                                            <p>{{ $order->tax_label ?: __('Tax') }}
                                                @if($order->tax_rate_applied) ({{ rtrim(rtrim(number_format($order->tax_rate_applied,3),'0'),'.') }}%) @endif
                                                :<span>{{ number_format($taxWithConversion, 2) }}
                                                {{ $order->payable_currency }}</span></p>
                                        @endif
                                    @endif
                                    <p>{{ __('Gateway Charge') }}
                                        ({{ number_format($gatewayCharge, 2) }}%)<span>{{ number_format($order->gateway_charge, 2) }}
                                            {{ $order->payable_currency }}</span> </p>
                                    <p>{{ __('Total') }} :<span>{{ $total }}
                                        </span> {{ $order->payable_currency }}</p>
                                    @if ($order->tax_registration)
                                        <p style="margin-top:6px;font-size:12px;color:#555;">{{ $order->tax_registration }}</p>
                                    @endif
                                    @if ($order->tax_mode === 'inclusive')
                                        <p style="font-size:11px;color:#777;">{{ __('Price is inclusive of tax.') }}</p>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>


    {{-- Auto-print is irrelevant when rendered via Dompdf for the PDF
         download flow. Browser print preview is handled by the user
         agent's own controls anyway. --}}
</body>

</html>
