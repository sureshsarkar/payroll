@extends('attendance::layouts.payroll')
@section('title', 'Departments')
@section('subtitle', 'HR')

@section('content')
<div class="d-flex align-items-center mb-3">
    <h4 class="mb-0">Departments</h4>
    <a href="{{ route('hr.employees.index') }}" class="btn btn-sm btn-outline-primary ms-auto">Employees</a>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card stat-card"><div class="card-body">
            <h5 class="mb-3">New department</h5>
            <form method="POST" action="{{ route('hr.departments.store') }}">
                @csrf
                <div class="mb-2">
                    <label class="form-label small">Name</label>
                    <input name="name" class="form-control form-control-sm" required placeholder="e.g. Engineering">
                </div>
                <div class="mb-2">
                    <label class="form-label small">Code</label>
                    <input name="code" class="form-control form-control-sm" placeholder="ENG">
                </div>
                <div class="mb-3">
                    <label class="form-label small">Department head (HR)</label>
                    <select name="head_user_id" class="form-select form-select-sm">
                        <option value="">—</option>
                        @foreach($hrs as $h)
                            <option value="{{ $h->id }}">{{ $h->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="btn btn-sm btn-success">Create department</button>
            </form>
        </div></div>
    </div>

    <div class="col-lg-7">
        <div class="card stat-card"><div class="card-body">
            <h5 class="mb-3">All departments</h5>
            @if($departments->isEmpty())
                <p class="text-muted mb-0">No departments yet. Create one on the left.</p>
            @else
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Name</th><th>Code</th><th class="text-center">Employees</th><th>Status</th></tr></thead>
                <tbody>
                @foreach($departments as $d)
                    <tr>
                        <td>{{ $d->name }}</td>
                        <td>{{ $d->code ?? '—' }}</td>
                        <td class="text-center">{{ $d->employees_count }}</td>
                        <td><span class="badge bg-{{ $d->is_active ? 'success' : 'secondary' }}">{{ $d->is_active ? 'Active' : 'Inactive' }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            @endif
        </div></div>
    </div>
</div>
@endsection
