@extends('attendance::layouts.payroll')
@section('title', 'Payroll Runs')
@section('subtitle', 'HR')

@section('content')
<div class="d-flex align-items-center mb-3">
    <h4 class="mb-0">Payroll runs</h4>
    <a href="{{ route('hr.salary.index') }}" class="btn btn-sm btn-outline-primary ms-auto">Salary structures</a>
</div>

<div class="card stat-card mb-4"><div class="card-body">
    <form method="POST" action="{{ route('hr.payroll.prepare') }}" class="row g-2 align-items-end">
        @csrf
        <div class="col-auto">
            <label class="form-label small mb-0">Month</label>
            <select name="month" class="form-select form-select-sm">
                @foreach(range(1,12) as $m)
                    <option value="{{ $m }}" {{ $m==$now->month?'selected':'' }}>{{ \Carbon\Carbon::create(null,$m,1)->format('F') }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-auto">
            <label class="form-label small mb-0">Year</label>
            <input type="number" name="year" value="{{ $now->year }}" class="form-control form-control-sm" style="width:110px">
        </div>
        <div class="col-auto">
            <button class="btn btn-sm btn-success">Prepare payroll</button>
        </div>
        <div class="col-auto text-muted small">Pulls attendance → computes LOP, statutory deductions & net pay.</div>
    </form>
</div></div>

<div class="card stat-card"><div class="card-body">
    @if($runs->isEmpty())
        <p class="text-muted mb-0">No payroll runs yet. Prepare one above.</p>
    @else
    <table class="table table-sm align-middle mb-0">
        <thead><tr><th>Period</th><th>Status</th><th class="text-center">Employees</th><th class="text-end">Total Net (₹)</th><th></th></tr></thead>
        <tbody>
        @foreach($runs as $run)
            <tr>
                <td>{{ $run->periodLabel() }}</td>
                <td>
                    @php $map=['draft'=>'secondary','hr_submitted'=>'warning','admin_approved'=>'success','paid'=>'info']; @endphp
                    <span class="badge bg-{{ $map[$run->status] ?? 'secondary' }}">{{ str_replace('_',' ',$run->status) }}</span>
                </td>
                <td class="text-center">{{ $run->employee_count }}</td>
                <td class="text-end">{{ number_format($run->total_net,2) }}</td>
                <td class="text-end"><a href="{{ route('hr.payroll.show',$run) }}" class="btn btn-sm btn-outline-secondary">Open</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div></div>
@endsection
