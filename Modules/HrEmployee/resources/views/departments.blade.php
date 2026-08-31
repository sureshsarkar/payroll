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
                    <thead><tr><th>Name</th><th>Code</th><th class="pv-c">Employees</th><th>Status</th><th class="pv-r">Actions</th></tr></thead>
                    <tbody>
                    @foreach($departments as $d)
                        <tr>
                            <td><strong>{{ $d->name }}</strong></td>
                            <td class="pv-muted">{{ $d->code ?? '—' }}</td>
                            <td class="pv-c">{{ $d->employees_count }}</td>
                            <td><span class="pv-badge {{ $d->is_active ? 'active' : 'draft' }}">{{ $d->is_active ? 'Active' : 'Inactive' }}</span></td>
                            <td class="pv-r" style="white-space:nowrap">
                                <span class="pv-btngrp">
                                    <button type="button" class="pv-btn sm"
                                        onclick="var r=document.getElementById('dept-edit-{{ $d->id }}');r.style.display=(r.style.display==='table-row'?'none':'table-row')">
                                        <i class="fas fa-pen"></i> Edit
                                    </button>
                                    <form method="POST" action="{{ route('hr.departments.destroy', $d) }}" style="display:inline"
                                        onsubmit="return confirm('Delete this department? This cannot be undone.')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="pv-btn d sm" {{ $d->employees_count > 0 ? 'disabled' : '' }}
                                            title="{{ $d->employees_count > 0 ? 'Reassign its employees before deleting' : 'Delete department' }}">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </span>
                            </td>
                        </tr>
                        <tr id="dept-edit-{{ $d->id }}" style="display:none">
                            <td colspan="5" style="background:#fafbff">
                                <form method="POST" action="{{ route('hr.departments.update', $d) }}" class="pv-inline">
                                    @csrf
                                    @method('PUT')
                                    <div class="pv-field" style="margin:0;min-width:180px">
                                        <label class="pv-label">Name *</label>
                                        <input name="name" class="pv-input" required value="{{ $d->name }}">
                                    </div>
                                    <div class="pv-field" style="margin:0;min-width:120px">
                                        <label class="pv-label">Code</label>
                                        <input name="code" class="pv-input" value="{{ $d->code }}">
                                    </div>
                                    <div class="pv-field" style="margin:0;min-width:130px">
                                        <label class="pv-label">Status</label>
                                        <select name="is_active" class="pv-select">
                                            <option value="1" {{ $d->is_active ? 'selected' : '' }}>Active</option>
                                            <option value="0" {{ $d->is_active ? '' : 'selected' }}>Inactive</option>
                                        </select>
                                    </div>
                                    <button class="pv-btn p"><i class="fas fa-save"></i> Save changes</button>
                                </form>
                            </td>
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
