@extends('attendance::layouts.payroll')
@section('title', 'Team Attendance')
@section('subtitle', 'HR · '.$hr->name)

@section('content')
<div class="d-flex align-items-center mb-3">
    <h4 class="mb-0">Mark team attendance</h4>
    <a href="{{ route('hr.attendance.sheet') }}" class="btn btn-sm btn-outline-primary ms-auto">Monthly sheet &rarr;</a>
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-auto">
        <input type="date" name="date" value="{{ $date }}" class="form-control form-control-sm">
    </div>
    <div class="col-auto"><button class="btn btn-sm btn-secondary">Load date</button></div>
</form>

<form method="POST" action="{{ route('hr.attendance.mark') }}">
    @csrf
    <input type="hidden" name="date" value="{{ $date }}">
    <div class="card stat-card">
        <div class="card-body">
            @if($team->isEmpty())
                <p class="text-muted mb-0">No employees are assigned to you yet. Assign employees via their profile (reporting HR) to see them here.</p>
            @else
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead><tr>
                        <th style="width:2rem"><input type="checkbox" onclick="document.querySelectorAll('.emp-cb').forEach(c=>c.checked=this.checked)"></th>
                        <th>Employee</th>
                        <th>Marked ({{ \Carbon\Carbon::parse($date)->format('d M') }})</th>
                    </tr></thead>
                    <tbody>
                    @foreach($team as $emp)
                        @php $rec = $marked->get($emp->id); @endphp
                        <tr>
                            <td><input class="emp-cb form-check-input" type="checkbox" name="user_ids[]" value="{{ $emp->id }}"></td>
                            <td>{{ $emp->name }} <span class="text-muted small">#{{ $emp->id }}</span></td>
                            <td>
                                @if($rec)<span class="badge badge-{{ $rec->status }}">{{ $rec->status }}</span>
                                @else<span class="text-muted small">—</span>@endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <select name="status" class="form-select form-select-sm" style="width:auto">
                    @foreach($statuses as $s)<option value="{{ $s }}">{{ $s }}</option>@endforeach
                </select>
                <button class="btn btn-sm btn-success">Mark selected</button>
            </div>
            @endif
        </div>
    </div>
</form>
@endsection
