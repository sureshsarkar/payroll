{{-- One Delhi Form IV register sheet.

     Layout is built for pagination: the company letterhead is a position:fixed
     block that dompdf re-paints in the reserved top margin of every page, and
     the statutory column headings sit in <thead> so dompdf repeats those too.
     The body is one <tr> per payroll item in $rows followed by a closing totals
     row. Rendered with a single item for one employee's "salary slip" or with
     every item of a run for the full register / "all salary slips" batch — the
     row list simply flows onto as many pages as it needs, the header and the
     S.No sequence carrying over unbroken. "Page X of Y" is stamped into the top
     strip by the controller (the page count is only known after layout).

     Expects: $run, $rows, $totals, $establishment --}}
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
{{-- position:fixed + negative top -> dompdf lifts this into the page's top
     margin and repaints it on every page. It must be a direct child of <body>
     (not nested in .sheet) or dompdf only paints it on the first page. Keeping
     it out of the table also leaves the table's fixed column layout untouched. --}}
<div class="running-header">
    <table class="hdr"><tr>
        <td style="width:20%">&nbsp;</td>
        <td style="width:60%">
            <div class="title">Register of Payment of Wages / Salary</div>
            <div class="subtitle">(With Employees State Insurance Column)</div>
        </td>
        <td style="width:20%">
            <div class="form-meta">
                <div class="strong">Form IV</div>
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
</div>

<div class="sheet">
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
                 exactly mirror the data row's cell structure. --}}
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
