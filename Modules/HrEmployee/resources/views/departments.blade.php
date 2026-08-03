@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
<div class="pv">
    @include('payroll::partials.ui')

    <div class="pv-head">
        <div>
            <h1 class="t">Departments</h1>
            <p class="s">Organise your team into departments.</p>
        </div>
        <div class="pv-actions">
            <a href="{{ route('hr.employees.index') }}" class="pv-btn"><i class="fas fa-users"></i> Employees</a>
        </div>
    </div>

    <div class="pv-cols c73">
        <div class="pv-card">
            <div class="h"><i class="fas fa-plus-circle" style="color:var(--pv-brand)"></i> New department</div>
            <div class="b">
                <form method="POST" action="{{ route('hr.departments.store') }}">
                    @csrf
                    <div class="pv-field"><label class="pv-label">Name *</label>
                        <input name="name" class="pv-input" required placeholder="e.g. Engineering"></div>
                    <div class="pv-field"><label class="pv-label">Code</label>
                        <input name="code" class="pv-input" placeholder="ENG"></div>
                    <div class="pv-field"><label class="pv-label">Department head (HR)</label>
                        <select name="head_user_id" class="pv-select">
                            <option value="">—</option>
                            @foreach($hrs as $h)<option value="{{ $h->id }}">{{ $h->name }}</option>@endforeach
                        </select></div>
                    <button class="pv-btn g"><i class="fas fa-plus"></i> Create department</button>
                </form>
            </div>
        </div>

        <div class="pv-card">
            <div class="h"><i class="fas fa-sitemap pv-muted"></i> All departments</div>
            <div class="b tight">
                @if($departments->isEmpty())
                    <div class="pv-empty"><div class="ic"><i class="fas fa-sitemap"></i></div>No departments yet.</div>
                @else
                <div class="pv-tw">
                <table class="pv-table" style="min-width:auto">
                    <thead><tr><th>Name</th><th>Code</th><th class="pv-c">Employees</th><th>Status</th></tr></thead>
                    <tbody>
                    @foreach($departments as $d)
                        <tr>
                            <td><strong>{{ $d->name }}</strong></td>
                            <td class="pv-muted">{{ $d->code ?? '—' }}</td>
                            <td class="pv-c">{{ $d->employees_count }}</td>
                            <td><span class="pv-badge {{ $d->is_active ? 'active' : 'draft' }}">{{ $d->is_active ? 'Active' : 'Inactive' }}</span></td>
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
