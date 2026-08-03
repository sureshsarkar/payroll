<!doctype html>
<html lang="en">
<head><meta charset="utf-8">
@php
    $money = function ($v) {
        $v = (float) $v;
        return $v == 0 ? '' : ($v == floor($v) ? number_format($v, 0) : number_format($v, 2));
    };
    $day = fn ($v) => ($v === null || $v === '') ? '' : number_format((float) $v, 1);
    $mask = fn ($v) => filled($v) ? $v : '';
    $rt = $row['rate']; $er = $row['earnings'];
    $period = strtoupper(\Carbon\Carbon::create($run->year, $run->month, 1)->format('F-Y'));
@endphp
<style>
@page { margin: 8mm; }
* { box-sizing: border-box; }
body { margin: 0; color: #000; font-family: DejaVu Sans, sans-serif; font-size: 9px; }
.sheet { border: 1.5px solid #000; }
table { border-collapse: collapse; width: 100%; }
td, th { vertical-align: top; }
.pad { padding: 3px 5px; }
.b { font-weight: bold; }
.c { text-align: center; }
.r { text-align: right; }
.big { font-size: 16px; font-weight: bold; letter-spacing: .5px; }
.rule { font-size: 9px; }
.addr { font-size: 8.5px; }
.formxi { font-size: 10px; font-weight: bold; }
/* header */
.hdr td { border-bottom: 1.5px solid #000; padding: 4px 6px; }
.photo { width: 74px; height: 88px; object-fit: cover; border: 1px solid #000; }
.photo-ph { width: 74px; height: 88px; border: 1px solid #999; }
/* left FORM XI panel */
.leftbox { width: 30%; border-right: 1.5px solid #000; }
.leftbox td { padding: 3px 6px; }
.leftbox .hd { border-bottom: 1px solid #000; text-align: center; font-weight: bold; }
.netbox { border-top: 1px solid #000; border-bottom: 1px solid #000; }
.net-amt { font-size: 15px; font-weight: bold; }
/* identity */
.ident td { border: 1px solid #000; padding: 2px 5px; font-size: 8.5px; }
.ident .k { width: 15%; color: #000; }
.ident .kk { width: 14%; }
/* salary */
.sal th, .sal td { border: 1px solid #000; padding: 2px 5px; }
.sal th { background: #f0f0f0; font-size: 8.5px; }
.att td { border: 1px solid #000; padding: 1px 5px; font-size: 8.5px; }
.att .v { text-align: right; font-weight: bold; }
.foot { text-align: center; font-size: 8px; padding: 3px; border-top: 1px solid #000; }
.words { font-weight: bold; font-size: 9px; }
</style></head>
<body>
<div class="sheet">

    {{-- ============ HEADER ============ --}}
    <table class="hdr"><tr>
        <td style="width:26%; border-right:1.5px solid #000; text-align:center; vertical-align:middle">
            <span class="rule">( Rule 26 (2) )</span>
        </td>
        <td style="width:54%; text-align:center">
            <div class="big">{{ $establishment['name'] ?? config('app.name') }}</div>
            <div class="addr">{{ $establishment['address'] ?? '' }}</div>
            <div style="margin-top:2px">PAYSLIP FOR THE MONTH OF <span class="b">{{ $period }}</span></div>
            <div class="formxi">FORM - XI</div>
        </td>
        <td style="width:20%; text-align:center; border-left:1.5px solid #000">
            @if($photo)
                <img class="photo" src="{{ $photo }}" alt="photo">
            @else
                <div class="photo-ph"></div>
            @endif
        </td>
    </tr></table>

    {{-- ============ BODY: left panel + right details ============ --}}
    <table><tr>

        {{-- ---- LEFT: FORM XI receipt panel ---- --}}
        <td class="leftbox">
            <table class="leftbox">
                <tr><td class="hd formxi">FORM XI</td></tr>
                <tr><td class="c">Received the wages Slip for the month of</td></tr>
                <tr><td class="c b">{{ $period }}</td></tr>
                <tr><td style="height:8px"></td></tr>
                <tr><td>Employee Name</td></tr>
                <tr><td class="b">{{ $employee->name ?? 'Employee #'.($profile->user_id ?? '') }}</td></tr>
                <tr><td>Code &nbsp; {{ $mask($profile?->employee_code) }}</td></tr>
                <tr><td class="netbox">Net Payable with OT Rs</td></tr>
                <tr><td class="c net-amt">{{ number_format((float) $item->net_pay, 2) }}</td></tr>
                <tr><td style="height:20px"></td></tr>
                <tr><td>Signature or LTI of the Employee</td></tr>
                <tr><td style="height:18px"></td></tr>
            </table>
        </td>

        {{-- ---- RIGHT: identity, bank/statutory, attendance, salary ---- --}}
        <td>
            {{-- identity + bank/statutory --}}
            <table class="ident">
                <tr>
                    <td class="k">Code</td><td class="b">{{ $mask($profile?->employee_code) }}</td>
                    <td class="kk">BANK NAME</td><td class="b">{{ $mask($profile?->bank_name) }}</td>
                </tr>
                <tr>
                    <td class="k">Name</td><td class="b">{{ $employee->name ?? '' }}</td>
                    <td class="kk">A/c No</td><td>{{ $mask($profile?->bank_account_number) }}</td>
                </tr>
                <tr>
                    <td class="k">Father's / Husb. Name</td><td>{{ $mask($profile?->father_or_spouse_name) }}</td>
                    <td class="kk">PF No</td><td>{{ $mask($profile?->pf_number) }}</td>
                </tr>
                <tr>
                    <td class="k">Desig</td><td>{{ $mask($profile?->designation) }}</td>
                    <td class="kk">UAN</td><td>{{ $mask($profile?->uan_number) }}</td>
                </tr>
                <tr>
                    <td class="k">Dept.</td><td>{{ $mask($profile?->department?->name) }}</td>
                    <td class="kk">ESI No</td><td>{{ $mask($profile?->esi_number) }}</td>
                </tr>
                <tr>
                    <td class="k">Date of Joining</td><td>{{ $profile?->date_of_joining?->format('d/M/Y') }}</td>
                    <td class="kk">PAN</td><td>{{ $mask($profile?->pan_number) }}</td>
                </tr>
                <tr>
                    <td class="k"></td><td></td>
                    <td class="kk">AADHAR NO</td><td>{{ $mask($profile?->aadhaar_number) }}</td>
                </tr>
            </table>

            {{-- attendance + salary rate/earnings/deductions --}}
            <table class="sal"><tr>
                {{-- attendance --}}
                <td style="width:32%; padding:0">
                    <table class="att">
                        <tr><td>OT Hrs</td><td class="v"></td></tr>
                        <tr><td>Present</td><td class="v">{{ $day($summary['present'] ?? null) }}</td></tr>
                        <tr><td>WO</td><td class="v">{{ $day($summary['weekly_off'] ?? null) }}</td></tr>
                        <tr><td>HD</td><td class="v">{{ $day($summary['holiday'] ?? null) }}</td></tr>
                        <tr><td>CL</td><td class="v">{{ $day($summary['leave'] ?? null) }}</td></tr>
                        <tr><td>EL</td><td class="v"></td></tr>
                        <tr><td>SL</td><td class="v"></td></tr>
                        <tr><td>SP</td><td class="v"></td></tr>
                        <tr><td class="b">Payable Days</td><td class="v">{{ $day($summary['payable_days'] ?? null) }}</td></tr>
                    </table>
                </td>
                {{-- salary rate / earnings / deductions --}}
                <td style="padding:0">
                    <table class="sal">
                        <tr>
                            <th style="width:34%">Salary Rate</th>
                            <th class="r" style="width:22%">Rate</th>
                            <th class="r" style="width:22%">Earnings</th>
                            <th class="r" style="width:22%">Deductions</th>
                        </tr>
                        <tr><td>Basic</td><td class="r">{{ $money($rt['basic']) }}</td><td class="r">{{ $money($er['basic']) }}</td><td class="r"></td></tr>
                        <tr><td>VDA</td><td class="r">{{ $money($rt['vda']) }}</td><td class="r">{{ $money($er['vda']) }}</td><td class="r"></td></tr>
                        <tr><td>HRA</td><td class="r">{{ $money($rt['hra']) }}</td><td class="r">{{ $money($er['hra']) }}</td><td class="r"></td></tr>
                        <tr><td>Conv. Allow</td><td class="r">{{ $money($rt['conv']) }}</td><td class="r">{{ $money($er['conv']) }}</td><td class="r"></td></tr>
                        <tr><td>Others</td><td class="r">{{ $money($rt['others']) }}</td><td class="r">{{ $money($er['others']) }}</td><td class="r"></td></tr>
                        @foreach($deductions as $d)
                            <tr><td>{{ $d['name'] }}</td><td class="r"></td><td class="r"></td><td class="r">{{ $money($d['amount']) }}</td></tr>
                        @endforeach
                        <tr>
                            <td class="b">Total</td>
                            <td class="r b">{{ $money($item->total_earnings) }}</td>
                            <td class="r b">{{ $money($item->total_earnings) }}</td>
                            <td class="r b">{{ number_format((float) $item->total_deductions, 0) }}</td>
                        </tr>
                    </table>
                </td>
            </tr></table>

            {{-- amount in words --}}
            <table class="sal"><tr>
                <td class="words" style="width:78%">{{ strtoupper($words) }}</td>
                <td class="r b">{{ number_format((float) $item->net_pay, 2) }}</td>
            </tr></table>
        </td>
    </tr></table>

    <div class="foot">This is a Computer generated Pay Slip, hence Signature is not required.</div>
</div>
</body></html>
