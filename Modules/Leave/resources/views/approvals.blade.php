@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
<div class="pv">
    @include('payroll::partials.ui')

    <div class="pv-head">
        <div>
            <h1 class="t">Leave Approvals</h1>
            <p class="s">Review and act on your team's leave requests.</p>
        </div>
    </div>

    <div class="pv-card">
        <div class="h"><i class="fas fa-hourglass-half" style="color:var(--pv-amber)"></i> Pending requests
            @if($pending->count())<span class="pv-badge pending" style="margin-left:6px">{{ $pending->count() }}</span>@endif</div>
        <div class="b tight">
            @if($pending->isEmpty())
                <div class="pv-empty"><div class="ic"><i class="fas fa-check-circle"></i></div>No pending requests. All caught up!</div>
            @else
            <div class="pv-tw">
            <table class="pv-table" style="min-width:760px">
                <thead><tr><th>Employee</th><th>Type</th><th>Dates</th><th class="pv-c">Days</th><th>Reason</th><th class="pv-r">Action</th></tr></thead>
                <tbody>
                @foreach($pending as $lv)
                    <tr>
                        <td><strong>{{ $lv->employee->name ?? '#'.$lv->user_id }}</strong></td>
                        <td>{{ $lv->type->name ?? '—' }} <span class="pv-badge {{ $lv->type?->is_paid ? 'leave' : 'draft' }}">{{ $lv->type?->is_paid ? 'Paid' : 'Unpaid' }}</span></td>
                        <td class="pv-muted">{{ $lv->start_date->format('d M') }} – {{ $lv->end_date->format('d M Y') }}</td>
                        <td class="pv-c">{{ $lv->days }}</td>
                        <td class="pv-mut2">{{ $lv->reason }}</td>
                        <td class="pv-r" style="white-space:nowrap">
                            <form method="POST" action="{{ route('hr.leave.approve',$lv) }}" style="display:inline">@csrf<button class="pv-btn g sm">Approve</button></form>
                            <form method="POST" action="{{ route('hr.leave.reject',$lv) }}" style="display:inline">@csrf<button class="pv-btn d sm">Reject</button></form>
                            <form method="POST" action="{{ route('hr.leave.destroy',$lv) }}" style="display:inline" onsubmit="return confirm('Delete this leave request? It is hidden everywhere but kept in the database.')">@csrf @method('DELETE')<button class="pv-btn sm" title="Delete"><i class="fas fa-trash"></i></button></form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            </div>
            @endif
        </div>
    </div>

    <div class="pv-card">
        <div class="h"><i class="fas fa-history pv-muted"></i> Recently reviewed</div>
        <div class="b tight">
            @if($recent->isEmpty())
                <div class="pv-empty">Nothing reviewed yet.</div>
            @else
            <div class="pv-tw">
            <table class="pv-table" style="min-width:auto">
                <thead><tr><th>Employee</th><th>Type</th><th>Dates</th><th class="pv-c">Days</th><th>Status</th><th class="pv-r"></th></tr></thead>
                <tbody>
                @foreach($recent as $lv)
                    <tr>
                        <td>{{ $lv->employee->name ?? '#'.$lv->user_id }}</td>
                        <td>{{ $lv->type->name ?? '—' }}</td>
                        <td class="pv-muted">{{ $lv->start_date->format('d M') }} – {{ $lv->end_date->format('d M Y') }}</td>
                        <td class="pv-c">{{ $lv->days }}</td>
                        <td><span class="pv-badge {{ $lv->status }}">{{ $lv->status }}</span></td>
                        <td class="pv-r"><form method="POST" action="{{ route('hr.leave.destroy',$lv) }}" style="display:inline" onsubmit="return confirm('Delete this leave record? It is hidden everywhere but kept in the database.')">@csrf @method('DELETE')<button class="pv-btn sm" title="Delete"><i class="fas fa-trash"></i></button></form></td>
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
