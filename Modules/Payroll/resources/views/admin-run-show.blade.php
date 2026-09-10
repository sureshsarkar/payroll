@extends('attendance::layouts.payroll')
@section('title', 'Review · '.$run->periodLabel())
@section('subtitle', 'Super Admin')

@section('content')
<div class="d-flex align-items-center mb-3">
    <a href="{{ route('admin.payroll.index') }}" class="btn btn-sm btn-outline-secondary">&larr; Approvals</a>
    <h4 class="mb-0 mx-3">{{ $run->periodLabel() }}</h4>
    @php $map=['draft'=>'secondary','hr_submitted'=>'warning','admin_approved'=>'success','paid'=>'info']; @endphp
    <span class="badge bg-{{ $map[$run->status] ?? 'secondary' }}">{{ $run->statusLabel() }}</span>
    @if($items->isNotEmpty())
        <a href="{{ route('admin.payroll.payslips', $run) }}" class="btn btn-sm btn-outline-danger ms-auto">All payslips</a>
        <a href="{{ route('admin.payroll.slips', $run) }}" class="btn btn-sm btn-outline-danger ms-2">All salary slips</a>
    @endif
    <div class="btn-group btn-group-sm {{ $items->isEmpty() ? 'ms-auto' : 'ms-2' }}">
        <a href="{{ route('admin.payroll.export', ['run'=>$run,'format'=>'xlsx']) }}" class="btn btn-outline-success">Excel</a>
        <a href="{{ route('admin.payroll.export', ['run'=>$run,'format'=>'pdf']) }}" class="btn btn-outline-danger">PDF</a>
        <a href="{{ route('admin.payroll.export', ['run'=>$run,'format'=>'csv']) }}" class="btn btn-outline-secondary">CSV</a>
    </div>
    @if($items->isNotEmpty())
        <div class="btn-group btn-group-sm ms-2" title="EPF ECR salary sheet — every employee, one row each">
            <a href="{{ route('admin.payroll.ecr', ['run'=>$run,'format'=>'xlsx']) }}" class="btn btn-outline-success">ECR Excel</a>
            <a href="{{ route('admin.payroll.ecr', ['run'=>$run,'format'=>'csv']) }}" class="btn btn-outline-secondary">ECR CSV</a>
        </div>
    @endif
    @if($run->status === 'hr_submitted')
        <form method="POST" action="{{ route('admin.payroll.approve',$run) }}" class="ms-2"
              onsubmit="return confirm('Approve and generate payslips?');">
            @csrf<button class="btn btn-sm btn-success">Approve &amp; generate payslips</button>
        </form>
    @elseif($run->status === 'admin_approved')
        <span class="ms-2 text-success small">Finalized by HR · payslips generated.</span>
        <form method="POST" action="{{ route('admin.payroll.recalculate',$run) }}" class="ms-2"
              onsubmit="return confirm('Recompute every payslip in this run from current attendance/leave data and regenerate the PDFs?');">
            @csrf<button class="btn btn-sm btn-outline-warning">Recalculate</button>
        </form>
    @endif
</div>

@if($staleIds->isNotEmpty())
    <div class="alert alert-warning d-flex align-items-center gap-2">
        <i class="fas fa-triangle-exclamation"></i>
        <div>{{ $staleIds->count() }} employee(s) below have attendance or leave changes recorded
            <strong>after</strong> this run was last computed — their net pay may be out of date.
            @if($run->status === 'admin_approved')
                Use Recalculate above to pick up the change.
            @endif
        </div>
    </div>
@endif

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
                <td>{{ $it->employee->name ?? 'Employee #'.$it->user_id }}
                    @if($staleIds->contains($it->id))
                        <span class="badge bg-warning text-dark" title="Attendance/leave changed after this was computed">stale</span>
                    @endif
                </td>
                <td class="text-center">{{ $it->payable_days }}</td>
                <td class="text-center text-danger">{{ $it->lop_days }}</td>
                <td class="text-end">{{ number_format($it->total_earnings,2) }}</td>
                <td class="text-end">{{ number_format($it->total_deductions,2) }}</td>
                <td class="text-end fw-bold">{{ number_format($it->net_pay,2) }}</td>
                <td class="text-center text-nowrap">
                    <a href="{{ route('admin.payroll.payslip', ['run'=>$run, 'employee'=>$it->user_id]) }}" class="btn btn-sm btn-outline-primary py-0">Pay Slip</a>
                    <a href="{{ route('admin.payroll.slip', ['run'=>$run, 'employee'=>$it->user_id]) }}" class="btn btn-sm btn-outline-danger py-0">Salary Sheet</a>
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
