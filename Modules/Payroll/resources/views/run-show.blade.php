@extends('attendance::layouts.payroll')
@section('title', 'Payroll · '.$run->periodLabel())
@section('subtitle', 'HR')

@section('content')
<div class="d-flex align-items-center mb-3">
    <a href="{{ route('hr.payroll.index') }}" class="btn btn-sm btn-outline-secondary">&larr; Runs</a>
    <h4 class="mb-0 mx-3">{{ $run->periodLabel() }}</h4>
    @php $map=['draft'=>'secondary','hr_submitted'=>'warning','admin_approved'=>'success','paid'=>'info']; @endphp
    <span class="badge bg-{{ $map[$run->status] ?? 'secondary' }}">{{ str_replace('_',' ',$run->status) }}</span>

    @if($run->status === 'draft')
        <form method="POST" action="{{ route('hr.payroll.submit',$run) }}" class="ms-auto">
            @csrf
            <button class="btn btn-sm btn-primary" {{ $items->isEmpty()?'disabled':'' }}>Submit for approval</button>
        </form>
    @elseif($run->status === 'hr_submitted')
        <span class="ms-auto text-muted small">Awaiting Super Admin approval.</span>
    @else
        <span class="ms-auto text-success small">Approved · payslips generated.</span>
    @endif
</div>

<div class="card stat-card"><div class="card-body">
    @if($items->isEmpty())
        <p class="text-muted mb-0">No items. Go back and prepare the run.</p>
    @else
    <div class="table-responsive">
        <table class="table table-sm table-striped align-middle mb-2">
            <thead><tr>
                <th>Employee</th><th class="text-center">Payable</th><th class="text-center">LOP</th>
                <th class="text-end">Gross</th><th class="text-end">Deductions</th><th class="text-end">Net Pay (₹)</th>
            </tr></thead>
            <tbody>
            @foreach($items as $it)
                <tr>
                    <td>{{ $it->employee->name ?? 'Employee #'.$it->user_id }} <span class="text-muted small">#{{ $it->user_id }}</span></td>
                    <td class="text-center">{{ $it->payable_days }}</td>
                    <td class="text-center text-danger">{{ $it->lop_days }}</td>
                    <td class="text-end">{{ number_format($it->total_earnings,2) }}</td>
                    <td class="text-end">{{ number_format($it->total_deductions,2) }}</td>
                    <td class="text-end fw-bold">{{ number_format($it->net_pay,2) }}</td>
                </tr>
            @endforeach
            </tbody>
            <tfoot><tr class="fw-bold">
                <td colspan="5" class="text-end">Total Net</td>
                <td class="text-end">{{ number_format($run->total_net,2) }}</td>
            </tr></tfoot>
        </table>
    </div>
    <p class="text-muted small mb-0">Statutory deductions (PF/ESIC/PT/TDS) and LOP are auto-computed by the engine per the company config.</p>
    @endif
</div></div>
@endsection
