<!doctype html>
<html lang="en">
<head><meta charset="utf-8">
@php
    // Whole rupees when the amount is integral (matches the statutory register
    // look); otherwise two decimals so paise are never silently dropped.
    $money = function ($v) {
        $v = (float) $v;
        return $v == floor($v) ? number_format($v, 0) : number_format($v, 2);
    };
    $days = fn ($v) => rtrim(rtrim(number_format((float) $v, 1), '0'), '.') ?: '0';
    $mask = fn ($v) => filled($v) ? $v : '';
@endphp
<style>
@page { margin: 5mm 4mm 6mm; }
* { box-sizing: border-box; }
body { margin: 0; color: #000; font-family: DejaVu Sans, sans-serif; font-size: 6px; }
.sheet { border: 1px solid #000; padding: 4px 6px; }
.hdr { width: 100%; border-collapse: collapse; }
.hdr td { vertical-align: top; padding: 0; }
.title { text-align: center; font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: .3px; }
.subtitle { text-align: center; font-size: 7px; }
.form-meta { text-align: right; font-size: 7px; line-height: 10px; }
.form-meta .strong { font-weight: bold; }
.estab { font-size: 8px; line-height: 12px; margin-top: 4px; }
.estab .lbl { display: inline-block; min-width: 90px; }
.reg-for { font-size: 8px; font-weight: bold; margin: 4px 0 3px; }
.stat-no { font-size: 7px; line-height: 11px; text-align: right; }

table.reg { border-collapse: collapse; width: 100%; table-layout: fixed; margin-top: 2px; }
.reg th, .reg td { border: 1px solid #000; padding: 1px; vertical-align: top; text-align: center; overflow-wrap: break-word; }
.reg th { font-weight: bold; font-size: 5.5px; line-height: 7px; background: #fff; }
.reg td { font-size: 5.5px; }
.reg .l { text-align: left; }
.reg .r { text-align: right; }
.reg .ident { line-height: 8px; }
.reg .ident .nm { font-weight: bold; font-size: 6px; }
.reg .ident .sub { font-size: 5px; }
.mini { width: 100%; border-collapse: collapse; }
.mini td { border: none; padding: 0 1px; font-size: 5.5px; line-height: 8px; }
.mini td.k { text-align: left; }
.mini td.v { text-align: right; font-weight: bold; }
.reg tfoot td { font-weight: bold; }
.foot { font-size: 6px; margin-top: 4px; text-align: right; color: #333; }
</style></head>
<body>
<div class="sheet">
    <table class="hdr"><tr>
        <td style="width:20%">&nbsp;</td>
        <td style="width:60%">
            <div class="title">Register of Payment of Wages / Salary</div>
            <div class="subtitle">(With Employees State Insurance Column)</div>
        </td>
        <td style="width:20%">
            <div class="form-meta">
                <div>Page 1 of 1</div>
                <div class="strong">FORM IV</div>
                <div>Revised Under Delhi Province</div>
                <div>Payment of Wages Rules, 1971</div>
            </div>
        </td>
    </tr></table>

    <table class="hdr"><tr>
        <td style="width:70%">
            <div class="estab">
                <span class="lbl">Name of the Factory :</span>
                <strong>{{ $establishment['name'] ?? config('app.name') }}</strong>
            </div>
            <div class="estab">
                <span class="lbl">Address :</span>{{ $establishment['address'] ?? '' }}
            </div>
        </td>
        <td style="width:30%">
            <div class="stat-no">PF No&nbsp; : {{ $establishment['pf_no'] ?? '' }}</div>
            <div class="stat-no">ESI No : {{ $establishment['esi_no'] ?? '' }}</div>
        </td>
    </tr></table>

    <div class="reg-for">Register of Payment of Wages/Salary for the Month of {{ strtoupper($run->periodLabel()) }}</div>

    <table class="reg">
        <colgroup>
            <col style="width:2.4%"><col style="width:4.5%"><col style="width:12.5%">
            <col style="width:7.5%"><col style="width:8%">
            <col style="width:3.4%"><col style="width:2.8%"><col style="width:3.2%"><col style="width:3.2%"><col style="width:3.2%"><col style="width:2.6%"><col style="width:3%">
            <col style="width:3.6%">
            <col style="width:3.4%"><col style="width:2.6%"><col style="width:2.6%"><col style="width:2.6%"><col style="width:3%"><col style="width:2.8%"><col style="width:3.2%">
            <col style="width:3.4%"><col style="width:3.4%"><col style="width:2.2%"><col style="width:4.6%">
        </colgroup>
        <thead>
            <tr>
                <th rowspan="2">S.<br>No</th>
                <th rowspan="2">Code<br>Card No</th>
                <th rowspan="2">Employee Name<br>Father/Husb. Name<br>Desig./Dept</th>
                <th rowspan="2">Attendance</th>
                <th rowspan="2">Rate of Salary</th>
                <th colspan="7">Earnings / Arrear</th>
                <th rowspan="2">Gross<br>Salary</th>
                <th colspan="7">Deductions</th>
                <th rowspan="2">Net Pay<br>(in Rs.)<br>2</th>
                <th rowspan="2">Net Pay<br>(in Rs.)<br>1</th>
                <th rowspan="2">D.<br>Pay</th>
                <th rowspan="2">Stamp &amp;<br>Signature</th>
            </tr>
            <tr>
                <th>Basic</th><th>VDA</th><th>HRA</th><th>Conv.<br>Allow.</th><th>Others</th><th>OT</th><th>Arrear</th>
                <th>PF<br>Wages</th><th>PF</th><th>ESI</th><th>TDS</th><th>Loan/<br>Adv</th><th>Others</th><th>Total<br>Ded.</th>
            </tr>
        </thead>
        <tbody>
        @foreach($rows as $i => $r)
            @php($p = $r['profile']) @php($e = $r['employee']) @php($a = $r['attendance']) @php($rt = $r['rate']) @php($er = $r['earnings']) @php($dd = $r['deductions'])
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $p?->employee_code ?? '' }}<br><span class="sub">{{ $mask($p?->id) }}</span></td>
                <td class="l ident">
                    <span class="nm">{{ $e?->name ?? 'Employee #'.($p?->user_id ?? '') }}</span><br>
                    <span class="sub">{{ $mask($p?->father_or_spouse_name) }}</span><br>
                    <span class="sub">{{ $mask($p?->designation) }}{{ $p?->department?->name ? ' / '.$p->department->name : '' }}</span><br>
                    <span class="sub">UAN No : {{ $mask($p?->uan_number) }}</span><br>
                    <span class="sub">ESI No : {{ $mask($p?->esi_number) }}</span><br>
                    <span class="sub">A/c No : {{ $mask($p?->bank_account_number) }}</span><br>
                    <span class="sub">DOJ : {{ $p?->date_of_joining?->format('d/m/Y') }}</span>
                </td>
                <td>
                    <table class="mini">
                        <tr><td class="k">W Days</td><td class="v">{{ $days($a['w_days']) }}</td></tr>
                        <tr><td class="k">WO</td><td class="v">{{ $days($a['wo']) }}</td></tr>
                        <tr><td class="k">HD</td><td class="v">{{ $days($a['hd']) }}</td></tr>
                        <tr><td class="k">EL</td><td class="v">{{ $days($a['el']) }}</td></tr>
                        <tr><td class="k">CL</td><td class="v">{{ $days($a['cl']) }}</td></tr>
                        <tr><td class="k">SL</td><td class="v">{{ $days($a['sl']) }}</td></tr>
                        <tr><td class="k">SP</td><td class="v">{{ $days($a['sp']) }}</td></tr>
                        <tr><td class="k">Pay Days</td><td class="v">{{ $days($a['pay_days']) }}</td></tr>
                    </table>
                </td>
                <td>
                    <table class="mini">
                        <tr><td class="k">Basic</td><td class="v">{{ $money($rt['basic']) }}</td></tr>
                        <tr><td class="k">VDA</td><td class="v">{{ $money($rt['vda']) }}</td></tr>
                        <tr><td class="k">HRA</td><td class="v">{{ $money($rt['hra']) }}</td></tr>
                        <tr><td class="k">Conv. All</td><td class="v">{{ $money($rt['conv']) }}</td></tr>
                        <tr><td class="k">Others</td><td class="v">{{ $money($rt['others']) }}</td></tr>
                        <tr><td class="k">OT Hrs</td><td class="v">{{ $money($rt['ot']) }}</td></tr>
                        <tr><td class="k">Total</td><td class="v">{{ $money($r['gross']) }}</td></tr>
                    </table>
                </td>
                <td class="r">{{ $money($er['basic']) }}</td>
                <td class="r">{{ $money($er['vda']) }}</td>
                <td class="r">{{ $money($er['hra']) }}</td>
                <td class="r">{{ $money($er['conv']) }}</td>
                <td class="r">{{ $money($er['others']) }}</td>
                <td class="r">{{ $money($er['ot']) }}</td>
                <td class="r">{{ $money($er['arrear']) }}</td>
                <td class="r"><strong>{{ $money($r['gross']) }}</strong></td>
                <td class="r">{{ $money($dd['pf_wages']) }}</td>
                <td class="r">{{ $money($dd['pf']) }}</td>
                <td class="r">{{ $money($dd['esi']) }}</td>
                <td class="r">{{ $money($dd['tds']) }}</td>
                <td class="r">{{ $money($dd['loan_adv']) }}</td>
                <td class="r">{{ $money($dd['others']) }}</td>
                <td class="r">{{ $money($r['total_ded']) }}</td>
                <td class="r"><strong>{{ $money($r['net_pay']) }}</strong></td>
                <td class="r"><strong>{{ $money($r['net_pay']) }}</strong></td>
                <td>0</td>
                <td>{{ $p?->bank_name ? 'Bank' : '' }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" class="l">Total</td>
                <td>
                    <table class="mini">
                        <tr><td class="k">P.Days</td><td class="v">{{ $days($totals['pay_days']) }}</td></tr>
                        <tr><td class="k">W.Days</td><td class="v">{{ $days($totals['work_days']) }}</td></tr>
                    </table>
                </td>
                <td class="r">{{ $money($totals['gross']) }}</td>
                <td class="r">{{ $money($totals['basic']) }}</td>
                <td class="r">{{ $money($totals['vda']) }}</td>
                <td class="r">{{ $money($totals['hra']) }}</td>
                <td class="r">{{ $money($totals['conv']) }}</td>
                <td class="r">{{ $money($totals['others']) }}</td>
                <td class="r">{{ $money($totals['ot']) }}</td>
                <td class="r">{{ $money($totals['arrear']) }}</td>
                <td class="r">{{ $money($totals['gross']) }}</td>
                <td class="r">{{ $money($totals['pf_wages']) }}</td>
                <td class="r">{{ $money($totals['pf']) }}</td>
                <td class="r">{{ $money($totals['esi']) }}</td>
                <td class="r">{{ $money($totals['tds']) }}</td>
                <td class="r">{{ $money($totals['loan_adv']) }}</td>
                <td class="r">{{ $money($totals['ded_others']) }}</td>
                <td class="r">{{ $money($totals['total_ded']) }}</td>
                <td class="r">{{ $money($totals['net_pay']) }}</td>
                <td class="r">{{ $money($totals['net_pay']) }}</td>
                <td>0</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
    <div class="foot">System-generated wage/salary register &middot; Amounts in INR &middot; Generated {{ now()->format('d-M-Y H:i') }}</div>
</div>
</body></html>
