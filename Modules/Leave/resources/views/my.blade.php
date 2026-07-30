@extends('attendance::layouts.payroll')
@section('title', 'My Leave')
@section('subtitle', auth()->user()->name)

@section('content')
<h4 class="mb-3">Leave balances · {{ now()->year }}</h4>
<div class="row g-3 mb-4">
    @forelse($balances as $bal)
        <div class="col-6 col-lg-3">
            <div class="card stat-card h-100"><div class="card-body text-center">
                <div class="h3 mb-0" style="color:#1f2d3d">{{ $bal->available() }}</div>
                <div class="small text-muted">{{ $bal->type->name ?? 'Leave' }}</div>
                <div class="small text-muted">used {{ $bal->used }} / {{ $bal->allotted }}</div>
            </div></div>
        </div>
    @empty
        <div class="col"><p class="text-muted">No paid leave types configured.</p></div>
    @endforelse
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card stat-card"><div class="card-body">
            <h5 class="mb-3">Apply for leave</h5>
            <form method="POST" action="{{ route('employee.leave.store') }}">
                @csrf
                <div class="mb-2">
                    <label class="form-label small">Leave type</label>
                    <select name="leave_type_id" class="form-select form-select-sm" required>
                        @foreach($types as $t)
                            <option value="{{ $t->id }}">{{ $t->name }} ({{ $t->is_paid ? 'Paid' : 'Unpaid' }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col"><label class="form-label small">From</label>
                        <input type="date" name="start_date" class="form-control form-control-sm" required></div>
                    <div class="col"><label class="form-label small">To</label>
                        <input type="date" name="end_date" class="form-control form-control-sm" required></div>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="half_day" value="1" id="hd">
                    <label class="form-check-label small" for="hd">Half day (same-day only)</label>
                </div>
                <div class="mb-3">
                    <label class="form-label small">Reason</label>
                    <textarea name="reason" rows="2" class="form-control form-control-sm"></textarea>
                </div>
                <button class="btn btn-sm btn-success">Submit request</button>
            </form>
        </div></div>
    </div>

    <div class="col-lg-7">
        <div class="card stat-card"><div class="card-body">
            <h5 class="mb-3">My requests</h5>
            @if($leaves->isEmpty())
                <p class="text-muted mb-0">No leave requests yet.</p>
            @else
            <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Type</th><th>Dates</th><th class="text-center">Days</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @foreach($leaves as $lv)
                    @php $c=['pending'=>'warning','approved'=>'success','rejected'=>'danger','cancelled'=>'secondary']; @endphp
                    <tr>
                        <td>{{ $lv->type->name ?? '—' }}</td>
                        <td class="small">{{ $lv->start_date->format('d M') }} – {{ $lv->end_date->format('d M Y') }}</td>
                        <td class="text-center">{{ $lv->days }}</td>
                        <td><span class="badge bg-{{ $c[$lv->status] ?? 'secondary' }}">{{ $lv->status }}</span></td>
                        <td class="text-end">
                            @if($lv->isPending())
                            <form method="POST" action="{{ route('employee.leave.cancel',$lv) }}">
                                @csrf<button class="btn btn-sm btn-outline-danger py-0">Cancel</button>
                            </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            </div>
            @endif
        </div></div>
    </div>
</div>
@endsection
