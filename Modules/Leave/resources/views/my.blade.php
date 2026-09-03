@extends('frontend.student-dashboard.layouts.master')

@section('dashboard-contents')
<div class="pv">
    @include('payroll::partials.ui')

    <div class="pv-head">
        <div>
            <h1 class="t">My Leave</h1>
            <p class="s">Apply for leave and track your balances · {{ now()->year }}</p>
        </div>
    </div>

    <div class="pv-stats">
        @forelse($balances as $bal)
            <div class="pv-stat" style="--c:var(--pv-brand)">
                <div class="n">{{ $bal->available() }}</div>
                <div class="l">{{ $bal->type->name ?? 'Leave' }}</div>
                <div class="sub">used {{ $bal->used }} / {{ $bal->allotted }}</div>
            </div>
        @empty
            <div class="pv-muted">No paid leave types configured.</div>
        @endforelse
    </div>

    <div class="pv-cols c73">
        <div class="pv-card">
            <div class="h"><i class="fas fa-plane-departure" style="color:var(--pv-brand)"></i> Apply for leave</div>
            <div class="b">
                <form method="POST" action="{{ route('employee.leave.store') }}">
                    @csrf
                    <div class="pv-field"><label class="pv-label">Leave type</label>
                        <select name="leave_type_id" class="pv-select" required>
                            @foreach($types as $t)<option value="{{ $t->id }}">{{ $t->name }} ({{ $t->is_paid ? 'Paid' : 'Unpaid' }})</option>@endforeach
                        </select></div>
                    <div class="pv-cols c2">
                        <div class="pv-field"><label class="pv-label">From</label><input type="date" name="start_date" class="pv-input" required></div>
                        <div class="pv-field"><label class="pv-label">To</label><input type="date" name="end_date" class="pv-input" required></div>
                    </div>
                    <div class="pv-cols c2">
                        <label class="pv-check pv-field" style="align-self:end"><input type="checkbox" name="half_day" value="1"> Half day (same-day only)</label>
                        <div class="pv-field"><label class="pv-label">Which half</label>
                            <select name="half_session" class="pv-select">
                                <option value="second">Second half off — present in the morning (PA)</option>
                                <option value="first">First half off — present in the afternoon (AP)</option>
                            </select></div>
                    </div>
                    <div class="pv-field"><label class="pv-label">Reason</label><textarea name="reason" rows="2" class="pv-textarea"></textarea></div>
                    <button class="pv-btn g"><i class="fas fa-paper-plane"></i> Submit request</button>
                </form>
            </div>
        </div>

        <div class="pv-card">
            <div class="h"><i class="fas fa-list pv-muted"></i> My requests</div>
            <div class="b tight">
                @if($leaves->isEmpty())
                    <div class="pv-empty">No leave requests yet.</div>
                @else
                <div class="pv-tw">
                <table class="pv-table" style="min-width:auto">
                    <thead><tr><th>Type</th><th>Dates</th><th class="pv-c">Days</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @foreach($leaves as $lv)
                        <tr>
                            <td>{{ $lv->type->name ?? '—' }}</td>
                            <td class="pv-mut2">{{ $lv->start_date->format('d M') }} – {{ $lv->end_date->format('d M') }}</td>
                            <td class="pv-c">{{ $lv->days }}</td>
                            <td><span class="pv-badge {{ $lv->status }}">{{ $lv->status }}</span></td>
                            <td class="pv-r">@if($lv->isPending())<form method="POST" action="{{ route('employee.leave.cancel',$lv) }}">@csrf<button class="pv-btn d sm">Cancel</button></form>@endif</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
