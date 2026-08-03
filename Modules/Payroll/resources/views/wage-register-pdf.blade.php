<!doctype html>
<html lang="en">
<head><meta charset="utf-8">
<style>
@page { margin: 6mm 5mm 7mm; }
* { box-sizing: border-box; }
body { margin: 0; color: #111; font-family: DejaVu Sans, sans-serif; font-size: 6px; }
.company { text-align: center; font-size: 14px; font-weight: bold; text-transform: uppercase; }
.title { text-align: center; font-size: 10px; font-weight: bold; margin-top: 2px; text-transform: uppercase; }
.subtitle { text-align: center; font-size: 7px; margin: 2px 0 6px; }
.details { width: 100%; margin: 0 0 5px; border-collapse: collapse; font-size: 7px; }
.details td { padding: 1px 3px; }
table.register { border-collapse: collapse; width: 100%; table-layout: fixed; }
.register th, .register td { border: 1px solid #333; padding: 2px 1px; vertical-align: top; text-align: center; overflow-wrap: break-word; }
.register th { background: #f1f1f1; font-weight: bold; line-height: 8px; }
.register .left { text-align: left; }
.register .number { text-align: right; }
.register .employee { line-height: 9px; }
.register .small { font-size: 5px; color: #333; }
.register tfoot td { font-weight: bold; background: #f4f4f4; }
.foot { margin-top: 5px; font-size: 6px; }
</style></head>
<body>
<div class="company">{{ config('app.name', 'Company') }}</div>
<div class="title">Register of Payment of Wages / Salary</div>
<div class="subtitle">For the Month of {{ $run->periodLabel() }}</div>
<table class="details"><tr><td><strong>Register period:</strong> {{ $run->periodLabel() }}</td><td><strong>Employees:</strong> {{ $items->count() }}</td><td style="text-align:right"><strong>Generated:</strong> {{ now()->format('d-M-Y') }}</td></tr></table>
<table class="register"><thead>
<tr><th rowspan="2">S.No</th><th rowspan="2">Code</th><th rowspan="2">Employee name<br>Designation / Dept.</th><th rowspan="2">Attendance<br>Pay / LOP</th><th rowspan="2">Rate of salary</th><th colspan="{{ max(1, $earningHeads->count()) }}">Earnings / Arrear</th><th rowspan="2">Gross salary</th><th colspan="{{ max(1, $deductionHeads->count()) }}">Deductions</th><th rowspan="2">Net pay</th><th rowspan="2">Signature</th></tr>
<tr>@forelse($earningHeads as $head)<th>{{ $head }}</th>@empty<th>Earnings</th>@endforelse @forelse($deductionHeads as $head)<th>{{ $head }}</th>@empty<th>Deductions</th>@endforelse</tr>
</thead><tbody>
@foreach($items as $index => $item)
@php($employee = $item->employee) @php($profile = $profiles->get($item->user_id)) @php($earnings = collect($item->earnings ?? [])->keyBy('name')) @php($deductions = collect($item->deductions ?? [])->keyBy('name'))
<tr><td>{{ $index + 1 }}</td><td>{{ $profile?->employee_code ?? $item->user_id }}</td><td class="left employee"><strong>{{ $employee?->name ?? 'Employee #'.$item->user_id }}</strong><br><span class="small">{{ $profile?->designation ?? '-' }} / {{ $profile?->department?->name ?? '-' }}</span></td><td>Pay: {{ number_format((float) $item->payable_days, 1) }}<br>LOP: {{ number_format((float) $item->lop_days, 1) }}</td><td class="number">{{ number_format((float) $item->gross, 2) }}</td>@forelse($earningHeads as $head)<td class="number">{{ number_format((float) ($earnings->get($head)['amount'] ?? 0), 2) }}</td>@empty<td class="number">0.00</td>@endforelse<td class="number">{{ number_format((float) $item->total_earnings, 2) }}</td>@forelse($deductionHeads as $head)<td class="number">{{ number_format((float) ($deductions->get($head)['amount'] ?? 0), 2) }}</td>@empty<td class="number">0.00</td>@endforelse<td class="number"><strong>{{ number_format((float) $item->net_pay, 2) }}</strong></td><td></td></tr>
@endforeach
</tbody><tfoot><tr><td colspan="5" class="left">Total</td>
@foreach($earningHeads as $head)<td class="number">{{ number_format((float) $items->sum(fn($item) => collect($item->earnings ?? [])->firstWhere('name', $head)['amount'] ?? 0), 2) }}</td>
@endforeach
@if($earningHeads->isEmpty())<td>0.00</td>
@endif
<td class="number">{{ number_format((float) $items->sum('total_earnings'), 2) }}</td>
@foreach($deductionHeads as $head)<td class="number">{{ number_format((float) $items->sum(fn($item) => collect($item->deductions ?? [])->firstWhere('name', $head)['amount'] ?? 0), 2) }}</td>
@endforeach
@if($deductionHeads->isEmpty())<td>0.00</td>
@endif
<td class="number">{{ number_format((float) $items->sum('net_pay'), 2) }}</td><td></td></tr></tfoot></table>
<div class="foot">This is a system-generated wage/salary register. Amounts are in INR.</div>
</body></html>
