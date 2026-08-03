@extends('attendance::layouts.payroll')
@section('title', 'Review · '.$run->periodLabel())
@section('subtitle', 'Super Admin')

@section('content')
<div class="d-flex align-items-center mb-3">
    <a href="{{ route('admin.payroll.index') }}" class="btn btn-sm btn-outline-secondary">&larr; Approvals</a>
    <h4 class="mb-0 mx-3">{{ $run->periodLabel() }}</h4>
    @php $map=['draft'=>'secondary','hr_submitted'=>'warning','admin_approved'=>'success','paid'=>'info']; @endphp
    <span class="badge bg-{{ $map[$run->status] ?? 'secondary' }}">{{ str_replace('_',' ',$run->status) }}</span>
    <div class="ms-auto btn-group btn-group-sm">
        <a href="{{ route('admin.payroll.export', ['run'=>$run,'format'=>'xlsx']) }}" class="btn btn-outline-success">Excel</a>
        <a href="{{ route('admin.payroll.export', ['run'=>$run,'format'=>'pdf']) }}" class="btn btn-outline-danger">PDF</a>
        <a href="{{ route('admin.payroll.export', ['run'=>$run,'format'=>'csv']) }}" class="btn btn-outline-secondary">CSV</a>
    </div>
    @if($run->status === 'hr_submitted')
        <form method="POST" action="{{ route('admin.payroll.approve',$run) }}" class="ms-2"
              onsubmit="return confirm('Approve and generate payslips?');">
            @csrf<button class="btn btn-sm btn-success">Approve &amp; generate payslips</button>
        </form>
    @elseif($run->status === 'admin_approved')
        <span class="ms-2 text-success small">Approved · payslips generated.</span>
    @endif
</div>

<div class="card stat-card"><div class="card-body">
    <div class="table-responsive">
    <table class="table table-sm table-striped align-middle mb-0">
        <thead><tr>
            <th>Employee</th><th class="text-center">Payable</th><th class="text-center">LOP</th>
            <th class="text-end">Gross</th><th class="text-end">Deductions</th><th class="text-end">Net Pay (₹)</th>
            <th class="text-center">Slip</th>
        </tr></thead>
        <tbody>
        @foreach($items as $it)
            <tr>
                <td>{{ $it->employee->name ?? 'Employee #'.$it->user_id }}</td>
                <td class="text-center">{{ $it->payable_days }}</td>
                <td class="text-center text-danger">{{ $it->lop_days }}</td>
                <td class="text-end">{{ number_format($it->total_earnings,2) }}</td>
                <td class="text-end">{{ number_format($it->total_deductions,2) }}</td>
                <td class="text-end fw-bold">{{ number_format($it->net_pay,2) }}</td>
                <td class="text-center text-nowrap">
                    <a href="{{ route('admin.payroll.payslip', ['run'=>$run, 'employee'=>$it->user_id]) }}" class="btn btn-sm btn-outline-primary py-0">Payslip</a>
                    <a href="{{ route('admin.payroll.slip', ['run'=>$run, 'employee'=>$it->user_id]) }}" class="btn btn-sm btn-outline-danger py-0">Form IV</a>
                </td>
            </tr>
        @endforeach
        </tbody>
        <tfoot><tr class="fw-bold"><td colspan="6" class="text-end">Total Net</td>
            <td class="text-end">{{ number_format($run->total_net,2) }}</td></tr></tfoot>
    </table>
    </div>
</div></div>
@endsection
