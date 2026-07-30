@extends('attendance::layouts.payroll')
@section('title', 'My Payslips')
@section('subtitle', auth()->user()->name)

@section('content')
<h4 class="mb-3">My payslips</h4>

<div class="card stat-card"><div class="card-body">
    @if($items->isEmpty())
        <p class="text-muted mb-0">No payslips yet. They appear here once payroll for a month is approved.</p>
    @else
    <table class="table table-sm align-middle mb-0">
        <thead><tr>
            <th>Period</th><th class="text-end">Gross</th><th class="text-end">Deductions</th>
            <th class="text-end">Net Pay ({{ $currency }})</th><th></th>
        </tr></thead>
        <tbody>
        @foreach($items as $it)
            <tr>
                <td>{{ $it->run->periodLabel() }}</td>
                <td class="text-end">{{ number_format($it->total_earnings,2) }}</td>
                <td class="text-end">{{ number_format($it->total_deductions,2) }}</td>
                <td class="text-end fw-bold">{{ number_format($it->net_pay,2) }}</td>
                <td class="text-end">
                    <a href="{{ route('employee.payslips.download',$it) }}" class="btn btn-sm btn-outline-primary">Download PDF</a>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div></div>
@endsection
