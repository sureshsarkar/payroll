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
@page { margin: 3mm 3mm 4mm; }
* { box-sizing: border-box; }
body { margin: 0; color: #000; font-family: DejaVu Sans, sans-serif; font-size: 7.5px; }
.sheet { border: 1px solid #000; padding: 3px 4px; }
.hdr { width: 100%; border-collapse: collapse; }
.hdr td { vertical-align: top; padding: 0; }
.title { text-align: center; font-size: 13px; font-weight: bold; text-transform: uppercase; letter-spacing: .3px; }
.subtitle { text-align: center; font-size: 8.5px; }
.form-meta { text-align: right; font-size: 8.5px; line-height: 11px; }
.form-meta .strong { font-weight: bold; }
.estab { font-size: 9.5px; line-height: 13px; margin-top: 3px; }
.estab .lbl { display: inline-block; min-width: 90px; }
.reg-for { font-size: 9.5px; font-weight: bold; margin: 3px 0 2px; }
.stat-no { font-size: 8.5px; line-height: 12px; text-align: right; }

table.reg { border-collapse: collapse; width: 100%; table-layout: fixed; margin-top: 2px; }
.reg th, .reg td { border: 1px solid #000; padding: 0.5px 1px; vertical-align: top; text-align: center; overflow-wrap: break-word; }
.reg th { font-weight: bold; font-size: 7px; line-height: 8.5px; background: #fff; }
.reg td { font-size: 7px; }
.reg .l { text-align: left; }
.reg .r { text-align: right; }
.reg .ident { line-height: 13px; }
.reg .ident .nm { font-weight: bold; font-size: 11px; }
.reg .ident .sub { font-size: 10px; }
.mini { width: 100%; border-collapse: collapse; }
.mini td { border: none; padding: 0 0.5px; font-size: 10px; line-height: 13px; }
.mini td.k { text-align: left; }
.mini td.v { text-align: right; font-weight: bold; }
/* Not bolding every totals cell on purpose: bold DejaVu Sans is measurably
   wider than regular, and that was enough to wrap "40,000"/"16,000" onto two
   lines in this row while the identical, non-bold value fit on one line in
   the data row above — a font-weight side effect, not a column-width one.
   Keep bold only on the label and the two figures that matter most. */
.reg tr.totals td.l, .reg tr.totals td.emph { font-weight: bold; }
.foot { font-size: 7px; margin-top: 3px; text-align: right; color: #333; }
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
        <thead>
            {{-- Column widths are set directly on each header cell rather than
                 via <colgroup> — dompdf does not reliably apply colgroup widths
                 on a table-layout:fixed table whose header mixes rowspan and
                 colspan across two rows (confirmed by testing: colgroup changes,
                 including extreme ones, produced zero visible effect here).
                 Identity/attendance/rate hold real text and need room;
                 earning/deduction sub-columns mostly show short numbers (often
                 "0") and are sized just wide enough for their typical value;
                 totals/net-pay carry the longest numbers and get more back. --}}
            <tr>
                <th rowspan="2" style="width:1.5%">S.<br>No</th>
                <th rowspan="2" style="width:5%">Code<br>Card No</th>
                <th rowspan="2" style="width:15%">Employee Name<br>Father/Husb. Name<br>Desig./Dept</th>
                <th rowspan="2" style="width:9%">Attendance</th>
                <th rowspan="2" style="width:9%">Rate of Salary</th>
                <th colspan="7">Earnings / Arrear</th>
                <th rowspan="2" style="width:3.6%">Gross<br>Salary</th>
                <th colspan="8">Deductions</th>
                <th rowspan="2" style="width:5%">Net Pay<br>(in Rs.)</th>
                <th rowspan="2" style="width:4%">Stamp &amp;<br>Signature</th>
            </tr>
            <tr>
                <th style="width:3.4%">Basic</th><th style="width:1.8%">VDA</th><th style="width:3.4%">HRA</th><th style="width:2%">Conv.<br>Allow.</th><th style="width:2%">Others</th><th style="width:1.8%">OT</th><th style="width:2%">Arrear</th>
                <th style="width:2.6%">PF<br>Wages</th><th style="width:2.2%">PF</th><th style="width:1.4%">ESI</th><th style="width:1.4%">TDS</th><th style="width:1.6%">Loan/<br>Adv</th><th style="width:1.8%">Others</th><th style="width:1.6%">LWF</th><th style="width:4.8%">Total<br>Ded.</th>
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
                <td class="r">{{ $money($dd['lwf']) }}</td>
                <td class="r">{{ $money($r['total_ded']) }}</td>
                <td class="r"><strong>{{ $money($r['net_pay']) }}</strong></td>
                <td>{{ $p?->bank_name ? 'Bank' : '' }}</td>
            </tr>
        @endforeach
            {{-- Totals row lives in <tbody> (not <tfoot>) and un-colspan'd to
                 exactly mirror the data row's cell structure. The mid-digit
                 wrapping this row used to show ("40,00|0") turned out to be a
                 font-weight issue, not a layout one — see the `.reg tr.totals`
                 rule below — but keeping the row structurally identical to the
                 data rows above removes one more variable if width issues ever
                 reappear. --}}
            <tr class="totals">
                <td></td>
                <td></td>
                <td class="l">Total</td>
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
                <td class="r">{{ $money($totals['lwf']) }}</td>
                <td class="r">{{ $money($totals['total_ded']) }}</td>
                <td class="r emph">{{ $money($totals['net_pay']) }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>
    <div class="foot">System-generated wage/salary register &middot; Amounts in INR &middot; Generated {{ now()->format('d-M-Y H:i') }}</div>
</div>
</body></html>
