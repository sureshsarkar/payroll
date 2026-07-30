<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
    * { font-family: DejaVu Sans, sans-serif; }
    body { font-size: 12px; color: #222; margin: 0; }
    .wrap { padding: 24px 28px; }
    .head { border-bottom: 2px solid #1f2d3d; padding-bottom: 10px; margin-bottom: 14px; }
    .company { font-size: 18px; font-weight: bold; color: #1f2d3d; }
    .doc-title { float: right; text-align: right; font-size: 12px; color: #555; }
    .meta td { padding: 2px 6px; }
    .meta .k { color: #777; }
    h4 { margin: 16px 0 6px; color: #1f2d3d; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
    table.grid { width: 100%; border-collapse: collapse; }
    table.grid th, table.grid td { border: 1px solid #d9dee6; padding: 6px 8px; }
    table.grid th { background: #f1f4f8; text-align: left; font-size: 11px; }
    .amt { text-align: right; }
    .totrow td { font-weight: bold; background: #f7f9fc; }
    .net { margin-top: 16px; background: #1f2d3d; color: #fff; padding: 10px 14px; border-radius: 4px; }
    .net .big { font-size: 18px; font-weight: bold; }
    .cols { width: 100%; }
    .cols td { vertical-align: top; width: 50%; }
    .foot { margin-top: 22px; color: #888; font-size: 10px; text-align: center; }
</style>
</head>
<body>
<div class="wrap">
    <div class="head">
        <span class="company">{{ config('app.name') }}</span>
        <div class="doc-title">
            <strong>PAYSLIP</strong><br>
            {{ $run->periodLabel() }}
        </div>
        <div style="clear:both"></div>
    </div>

    <table class="meta">
        <tr><td class="k">Employee</td><td><strong>{{ $employee->name ?? 'Employee #'.$item->user_id }}</strong></td>
            <td class="k">Employee ID</td><td>#{{ $item->user_id }}</td></tr>
        <tr><td class="k">Payable days</td><td>{{ $item->payable_days }}</td>
            <td class="k">LOP days</td><td>{{ $item->lop_days }}</td></tr>
    </table>

    <table class="cols">
        <tr>
            <td style="padding-right:10px">
                <h4>Earnings</h4>
                <table class="grid">
                    <tr><th>Component</th><th class="amt">Amount ({{ $currency }})</th></tr>
                    @foreach($item->earnings ?? [] as $e)
                        <tr><td>{{ $e['name'] }}</td><td class="amt">{{ number_format($e['amount'], 2) }}</td></tr>
                    @endforeach
                    <tr class="totrow"><td>Total Earnings</td><td class="amt">{{ number_format($item->total_earnings, 2) }}</td></tr>
                </table>
            </td>
            <td style="padding-left:10px">
                <h4>Deductions</h4>
                <table class="grid">
                    <tr><th>Component</th><th class="amt">Amount ({{ $currency }})</th></tr>
                    @foreach($item->deductions ?? [] as $d)
                        <tr><td>{{ $d['name'] }}</td><td class="amt">{{ number_format($d['amount'], 2) }}</td></tr>
                    @endforeach
                    <tr class="totrow"><td>Total Deductions</td><td class="amt">{{ number_format($item->total_deductions, 2) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="net">
        Net Pay &nbsp; <span class="big">{{ $currency }} {{ number_format($item->net_pay, 2) }}</span>
    </div>

    <div class="foot">
        This is a system-generated payslip and does not require a signature.
        Generated on {{ now()->format('d M Y') }}.
    </div>
</div>
</body>
</html>
