@extends('attendance::layouts.payroll')
@section('title', 'Payroll Approvals')
@section('subtitle', 'Super Admin')

@section('content')
<h4 class="mb-3">Payroll approvals</h4>
<div class="card stat-card"><div class="card-body">
    @if($runs->isEmpty())
        <p class="text-muted mb-0">No payroll runs yet. HR prepares them from the HR panel.</p>
    @else
    <table class="table table-sm align-middle mb-0">
        <thead><tr>
            <th>Period</th><th>Status</th><th class="text-center">Employees</th>
            <th class="text-end">Total Net (₹)</th><th class="text-end">Action</th>
        </tr></thead>
        <tbody>
        @foreach($runs as $run)
            @php $map=['draft'=>'secondary','hr_submitted'=>'warning','admin_approved'=>'success','paid'=>'info']; @endphp
            <tr>
                <td>{{ $run->periodLabel() }}</td>
                <td><span class="badge bg-{{ $map[$run->status] ?? 'secondary' }}">{{ str_replace('_',' ',$run->status) }}</span></td>
                <td class="text-center">{{ $run->employee_count }}</td>
                <td class="text-end">{{ number_format($run->total_net,2) }}</td>
                <td class="text-end" style="white-space:nowrap">
                    <a href="{{ route('admin.payroll.show',$run) }}" class="btn btn-sm btn-outline-secondary">Review</a>
                    @if($run->status === 'hr_submitted')
                        <form method="POST" action="{{ route('admin.payroll.approve',$run) }}" class="d-inline"
                              onsubmit="return confirm('Approve payroll for {{ $run->periodLabel() }} and generate payslips?');">
                            @csrf<button class="btn btn-sm btn-success">Approve</button>
                        </form>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div></div>
@endsection
