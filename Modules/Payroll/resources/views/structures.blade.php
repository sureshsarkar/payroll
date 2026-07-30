@extends('attendance::layouts.payroll')
@section('title', 'Salary Structures')
@section('subtitle', 'HR')

@section('content')
<div class="d-flex align-items-center mb-3">
    <h4 class="mb-0">Salary structures</h4>
    <a href="{{ route('hr.payroll.index') }}" class="btn btn-sm btn-outline-primary ms-auto">Payroll runs &rarr;</a>
</div>

<div class="card stat-card"><div class="card-body">
    @if($team->isEmpty())
        <p class="text-muted mb-0">No employees assigned to you yet.</p>
    @else
    <div class="table-responsive">
    <table class="table align-middle">
        <thead><tr>
            <th>Employee</th><th class="text-end">Current Gross (₹)</th>
            <th style="width:38%">Set / update structure</th>
        </tr></thead>
        <tbody>
        @foreach($team as $emp)
            @php $s = $current->get($emp->id); @endphp
            <tr>
                <td>{{ $emp->name }} <span class="text-muted small">#{{ $emp->id }}</span></td>
                <td class="text-end">{{ $s ? number_format($s->gross_monthly,2) : '—' }}</td>
                <td>
                    <form method="POST" action="{{ route('hr.salary.store') }}" class="row g-1 align-items-center">
                        @csrf
                        <input type="hidden" name="user_id" value="{{ $emp->id }}">
                        <div class="col"><input name="basic" class="form-control form-control-sm" placeholder="Basic" value="{{ $s?->basic() }}"></div>
                        <div class="col"><input name="hra_percent" class="form-control form-control-sm" placeholder="HRA %" value="40"></div>
                        <div class="col"><input name="special" class="form-control form-control-sm" placeholder="Special" value="0"></div>
                        <div class="col-auto"><button class="btn btn-sm btn-success">Save</button></div>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
    <p class="text-muted small mb-0">Statutory deductions (PF/ESIC/PT) are applied automatically — no need to enter them here.</p>
    @endif
</div></div>
@endsection
