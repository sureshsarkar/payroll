@extends('attendance::layouts.payroll')
@section('title', 'Leave Approvals')
@section('subtitle', 'HR')

@section('content')
<h4 class="mb-3">Pending leave requests</h4>
<div class="card stat-card mb-4"><div class="card-body">
    @if($pending->isEmpty())
        <p class="text-muted mb-0">No pending requests. 🎉</p>
    @else
    <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
        <thead><tr><th>Employee</th><th>Type</th><th>Dates</th><th class="text-center">Days</th><th>Reason</th><th class="text-end">Action</th></tr></thead>
        <tbody>
        @foreach($pending as $lv)
            <tr>
                <td>{{ $lv->employee->name ?? '#'.$lv->user_id }}</td>
                <td>{{ $lv->type->name ?? '—' }} <span class="badge bg-light text-dark">{{ $lv->type?->is_paid ? 'Paid' : 'Unpaid' }}</span></td>
                <td class="small">{{ $lv->start_date->format('d M') }} – {{ $lv->end_date->format('d M Y') }}</td>
                <td class="text-center">{{ $lv->days }}</td>
                <td class="small text-muted">{{ $lv->reason }}</td>
                <td class="text-end" style="white-space:nowrap">
                    <form method="POST" action="{{ route('hr.leave.approve',$lv) }}" class="d-inline">
                        @csrf<button class="btn btn-sm btn-success py-0">Approve</button>
                    </form>
                    <form method="POST" action="{{ route('hr.leave.reject',$lv) }}" class="d-inline">
                        @csrf<button class="btn btn-sm btn-outline-danger py-0">Reject</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
    @endif
</div></div>

<h5 class="mb-2">Recently reviewed</h5>
<div class="card stat-card"><div class="card-body">
    @if($recent->isEmpty())
        <p class="text-muted mb-0">Nothing reviewed yet.</p>
    @else
    <table class="table table-sm align-middle mb-0">
        <thead><tr><th>Employee</th><th>Type</th><th>Dates</th><th class="text-center">Days</th><th>Status</th></tr></thead>
        <tbody>
        @foreach($recent as $lv)
            @php $c=['approved'=>'success','rejected'=>'danger']; @endphp
            <tr>
                <td>{{ $lv->employee->name ?? '#'.$lv->user_id }}</td>
                <td>{{ $lv->type->name ?? '—' }}</td>
                <td class="small">{{ $lv->start_date->format('d M') }} – {{ $lv->end_date->format('d M Y') }}</td>
                <td class="text-center">{{ $lv->days }}</td>
                <td><span class="badge bg-{{ $c[$lv->status] ?? 'secondary' }}">{{ $lv->status }}</span></td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div></div>
@endsection
