@extends('frontend.student-dashboard.layouts.master')

@section('dashboard-contents')
<div class="pv">
    @include('payroll::partials.ui')

    <div class="pv-head">
        <div>
            <h1 class="t">My Payslips</h1>
            <p class="s">Download your monthly payslips.</p>
        </div>
    </div>

    <div class="pv-card">
        <div class="b tight">
            @if($items->isEmpty())
                <div class="pv-empty"><div class="ic"><i class="fas fa-file-invoice-dollar"></i></div>
                    No payslips yet. They appear once your monthly payroll is approved.</div>
            @else
            <div class="pv-tw">
            <table class="pv-table" style="min-width:560px">
                <thead><tr><th>Period</th><th class="pv-r">Gross</th><th class="pv-r">Deductions</th><th class="pv-r">Net Pay</th><th class="pv-r"></th></tr></thead>
                <tbody>
                @foreach($items as $it)
                    <tr>
                        <td><strong>{{ $it->run->periodLabel() }}</strong></td>
                        <td class="pv-r">₹{{ number_format($it->total_earnings,2) }}</td>
                        <td class="pv-r">₹{{ number_format($it->total_deductions,2) }}</td>
                        <td class="pv-r" style="font-weight:700">₹{{ number_format($it->net_pay,2) }}</td>
                        <td class="pv-r">
                            <a href="{{ route('employee.payslips.formxi',$it) }}" class="pv-btn sm"><i class="fas fa-file-invoice"></i> Form XI</a>
                            <a href="{{ route('employee.payslips.download',$it) }}" class="pv-btn p sm"><i class="fas fa-download"></i> PDF</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
