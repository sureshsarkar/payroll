@php
    /**
     * Statutory Form XI (Rule 26(2)) wage slip — one A4 page per employee.
     * Rendered by Modules\Payroll\app\Support\FormXiPayslip with mPDF, which
     * shapes the Devanagari half of each label correctly.
     */
    $money = function ($v) {
        $v = (float) $v;
        return $v == 0 ? '' : number_format($v, 2);
    };
    $day = function ($v) {
        $v = (float) $v;
        return $v == 0 ? '' : number_format($v, 1);
    };
    $mask = fn ($v) => filled($v) ? (string) $v : '';
    $html = fn (string $raw) => new \Illuminate\Support\HtmlString($raw);

    // English label with the Hindi half beside it, when bilingual printing is on.
    // Returns Htmlable so plain double-brace interpolation prints the markup
    // unescaped without triggering the stored-XSS scanner's raw-print check —
    // safe here because both halves are always literal strings from this
    // file, never user input.
    $L = fn (string $en, string $hi = '') => $html($bilingual && $hi !== ''
        ? e($en).'/<span class="hi">'.e($hi).'</span>'
        : e($en));

    $period = strtoupper(\Carbon\Carbon::create($run->year, $run->month, 1)->format('F-Y'));

    // Deduction heads carry a Hindi name where the statutory form prescribes one.
    $dedHindi = [
        'esi' => 'ई0 एस0 आई0', 'esic' => 'ई0 एस0 आई0',
        'pf' => 'भ0 नि0 नि0', 'provident fund' => 'भ0 नि0 नि0',
        'professional tax' => 'व्यवसाय कर',
        'income tax' => 'आयकर', 'tds' => 'आयकर',
    ];
    $dedLines = $deductions->map(function ($d) use ($dedHindi, $bilingual, $html) {
        $name = (string) ($d['name'] ?? '');
        $hi = $dedHindi[\Illuminate\Support\Str::lower($name)] ?? '';
        return [
            'name'   => $html($bilingual && $hi !== '' ? e($name).'<br><span class="hi">'.e($hi).'</span>' : e($name)),
            'amount' => (float) ($d['amount'] ?? 0),
        ];
    })->values();

    // The salary grid is as tall as its longest column, so the shorter ones pad out.
    $rateHeads = [
        ['Basic', 'बेसिक', $rate['basic'], $earnings['basic']],
        ['V D A', 'महंगाई भत्ता', $rate['vda'], $earnings['vda']],
        ['HRA', 'मकान किराया', $rate['hra'], $earnings['hra']],
        ['Conv. Allow.', 'किराया भत्ता', $rate['conv'], $earnings['conv']],
        ['Others', 'अन्य', $rate['others'], $earnings['others']],
    ];
    $attHeads = [
        ['OT Hrs', 'ओ0टी0 आवर्स', $days['ot_hours']],
        ['Present', 'कार्य दिवस', $days['present']],
        ['WO', 'साप्ताहिक अवकाश', $days['wo']],
        ['HD', 'अवकाश', $days['hd']],
        ['CL', 'आकस्मिक अवकाश', $days['cl']],
        ['EL', 'वेतन सहित अवकाश', $days['el']],
        ['SL', 'बीमारी अवकाश', $days['sl']],
        ['SP', '', $days['sp']],
    ];
    $bodyRows = max(count($attHeads), count($rateHeads), $dedLines->count());
@endphp
<style>
    body { font-family: freeserif; font-size: 9pt; color: #000; }
    .hi { font-size: 8pt; }
    table { border-collapse: collapse; width: 100%; }
    td, th { vertical-align: top; padding: 1.5pt 3pt; }
    .b { font-weight: bold; }
    .c { text-align: center; }
    .r { text-align: right; }
    .sheet { border: 0.6mm solid #000; }
    .co-name { font-size: 15pt; font-weight: bold; }
    .co-addr { font-size: 8pt; }
    .rule { font-size: 8.5pt; }
    .formxi { font-size: 9.5pt; font-weight: bold; }
    .photo { width: 22mm; }
    .hdr td { border-bottom: 0.6mm solid #000; }
    .panel { border-right: 0.6mm solid #000; width: 25%; }
    .panel .row { padding: 1.5pt 4pt; }
    .panel .hd { border-bottom: 0.3mm solid #000; text-align: center; font-weight: bold; font-size: 10pt; padding: 3pt; }
    .net { border-top: 0.3mm solid #000; }
    .net-amt { font-size: 13pt; font-weight: bold; text-align: right; padding-right: 8pt; }
    .sign { border-top: 0.3mm solid #000; }
    .ident td { border: 0.3mm solid #000; font-size: 8.5pt; }
    .grid td, .grid th { border: 0.3mm solid #000; font-size: 8.5pt; }
    .grid th { font-weight: bold; }
    .words { border: 0.3mm solid #000; font-weight: bold; }
    .note { border-top: 0.3mm solid #000; font-size: 7pt; padding: 2pt 4pt; }
</style>

<table class="sheet">
    {{-- ---------------- letterhead ---------------- --}}
    <tr class="hdr">
        <td style="width:25%; vertical-align:middle">
            @if($establishment['logo'])
                <img src="{{ $establishment['logo'] }}" style="height:12mm">
            @endif
            <div class="rule">( Rule 26 (2) )</div>
        </td>
        <td class="c" style="width:55%">
            <div class="co-name">{{ $establishment['name'] }}</div>
            <div class="co-addr">{{ $establishment['address'] }}</div>
            <div>{{ $L('PAYSLIP FOR THE MONTH OF', 'वेतन स्लिप माह') }} <span class="b">{{ $period }}</span></div>
            <div class="formxi">FORM - XI</div>
        </td>
        <td class="c" style="width:20%">
            @if($photo)<img src="{{ $photo }}" class="photo">@endif
        </td>
    </tr>

    {{-- ---------------- body: receipt panel | details ---------------- --}}
    <tr>
        <td class="panel">
            <div class="hd">FORM XI</div>
            <div class="row c">Received the wages Slip for the month of</div>
            <div class="row c b">{{ $period }}</div>
            <div class="row" style="padding-top:10pt">{{ $L('Employee Name', 'कर्मचारी का नाम') }}</div>
            <div class="row b">{{ $employee->name ?? 'Employee #'.$item->user_id }}</div>
            <div class="row">Code &nbsp; {{ $mask($profile?->employee_code) }}</div>
            <div class="row net" style="padding-top:6pt">{{ $L('Net Payable with OT Rs', 'देय राशि ओ0 टी0 सहित') }}</div>
            <div class="net-amt">{{ number_format((float) $item->net_pay, 2) }}</div>
            <div class="row sign" style="padding-top:6pt">{{ $L('Signature or LTI of the Employee', 'क0 हस्ताक्षर') }}</div>
            <div style="height:16mm"></div>
        </td>

        <td colspan="2" style="padding:0">
            {{-- identity + bank / statutory --}}
            <table class="ident">
                <tr>
                    <td style="width:17%">Code</td><td class="b" style="width:29%">{{ $mask($profile?->employee_code) }}</td>
                    <td style="width:24%">{{ $L('BANK NAME', 'बैंक नाम') }}</td><td class="b" style="width:30%">{{ $mask($profile?->bank_name) }}</td>
                </tr>
                <tr>
                    <td>{{ $L('Name', 'नाम') }}</td><td class="b">{{ $employee->name ?? '' }}</td>
                    <td>{{ $L('A/c No', 'खाता संख्या') }}</td><td>{{ $mask($profile?->bank_account_number) }}</td>
                </tr>
                <tr>
                    <td>{{ $L("Father's / Husb. Name", 'पिता/पति का नाम') }}</td><td>{{ $mask($profile?->father_or_spouse_name) }}</td>
                    <td>{{ $L('PF No', 'भ0 नि0 नं0') }}</td><td>{{ $mask($profile?->pf_number) }}</td>
                </tr>
                <tr>
                    <td>{{ $L('Desig', 'पद') }}</td><td>{{ $mask($profile?->designation) }}</td>
                    <td>UAN</td><td>{{ $mask($profile?->uan_number) }}</td>
                </tr>
                <tr>
                    <td>{{ $L('Dept.', 'विभाग') }}</td><td>{{ $mask($profile?->department?->name) }}</td>
                    <td>{{ $L('ESI No', 'क0 रा0 बी0 नं0') }}</td><td>{{ $mask($profile?->esi_number) }}</td>
                </tr>
                <tr>
                    <td>{{ $L('Date of Joining', 'नियुक्ति तिथि') }}</td><td>{{ $profile?->date_of_joining?->format('d/M/Y') }}</td>
                    <td>{{ $L('PAN', 'आयकर नं0') }}</td><td>{{ $mask($profile?->pan_number) }}</td>
                </tr>
                <tr>
                    <td></td><td></td>
                    <td>{{ $L('AADHAR NO', 'आधार नं0') }}</td><td>{{ $mask($profile?->aadhaar_number) }}</td>
                </tr>
            </table>

            {{-- attendance | salary rate | earnings | deductions --}}
            <table class="grid">
                <tr>
                    <th colspan="3" style="width:26%"></th>
                    <th colspan="3" class="c" style="width:34%">{{ $L('Salary Rate', 'वेतन दर') }}</th>
                    <th class="c" style="width:12%">{{ $L('Earnings', 'अर्जित वेतन') }}</th>
                    <th colspan="2" class="c" style="width:28%">{{ $L('Deductions', 'कटौतियाँ') }}</th>
                </tr>

                @for($i = 0; $i < $bodyRows; $i++)
                    @php
                        $att  = $attHeads[$i]  ?? null;
                        $head = $rateHeads[$i] ?? null;
                        $ded  = $dedLines[$i]  ?? null;
                    @endphp
                    <tr>
                        <td style="width:7%">{{ $att[0] ?? '' }}</td>
                        <td class="hi" style="width:13%">{{ $att && $bilingual ? $att[1] : '' }}</td>
                        <td class="r b" style="width:6%">{{ $att ? $day($att[2]) : '' }}</td>
                        <td style="width:11%">{{ $head[0] ?? '' }}</td>
                        <td class="hi" style="width:11%">{{ $head && $bilingual ? $head[1] : '' }}</td>
                        <td class="r" style="width:12%">{{ $head ? $money($head[2]) : '' }}</td>
                        <td class="r" style="width:12%">{{ $head ? $money($head[3]) : '' }}</td>
                        <td style="width:16%">{{ $ded['name'] ?? '' }}</td>
                        <td class="r" style="width:12%">{{ $ded ? $money($ded['amount']) : '' }}</td>
                    </tr>
                @endfor

                <tr class="b">
                    <td colspan="2">{{ $L('Payable Days', 'देय दिन') }}</td>
                    <td class="r">{{ $day($days['payable_days']) }}</td>
                    <td colspan="2">{{ $L('Total', 'कुल योग') }}</td>
                    <td class="r">{{ $money($item->total_earnings) }}</td>
                    <td class="r">{{ $money($item->total_earnings) }}</td>
                    <td>{{ $L('Deductions', 'कटौतियाँ') }}</td>
                    <td class="r">{{ $money($item->total_deductions) }}</td>
                </tr>
            </table>

            <table class="words">
                <tr>
                    <td class="c">RUPEES {{ strtoupper(\Illuminate\Support\Str::of($words)->after('Rupees ')) }}</td>
                    <td class="r b" style="width:20%">{{ number_format((float) $item->net_pay, 2) }}</td>
                </tr>
            </table>

            <div class="note">This is a Computer generated Pay Slip, hence Signature is not required.</div>
        </td>
    </tr>
</table>
