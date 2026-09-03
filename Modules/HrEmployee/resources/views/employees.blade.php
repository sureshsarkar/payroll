@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
<style>
    .emp-toolbar{display:flex;gap:8px;align-items:center;flex-wrap:wrap;padding:10px 10px 6px}
    .emp-search{position:relative;flex:1 1 260px;min-width:220px}
    .emp-search > i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--pv-mut);font-size:12px;pointer-events:none}
    .emp-search .pv-input{padding-left:32px}
    .emp-sort{color:inherit;text-decoration:none;display:inline-flex;align-items:center;gap:4px;white-space:nowrap}
    .emp-sort:hover{color:var(--pv-brand)}
    .emp-sort.active{color:var(--pv-brand)}
    .emp-sort .caret{font-size:9px}
    .emp-pager{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;padding:12px 10px 6px}
    .emp-pager .pv-btn.is-disabled{opacity:.45;pointer-events:none}
</style>
<div class="pv">
    @include('payroll::partials.ui')
    <div class="pv-head">
        <div><h1 class="t">Employees</h1><p class="s">View your employee directory and open profiles when changes are needed.</p></div>
        <div class="pv-actions"><a href="{{ route('hr.departments.index') }}" class="pv-btn"><i class="fas fa-sitemap"></i> Departments</a><a href="{{ route('hr.employees.create') }}" class="pv-btn g"><i class="fas fa-user-plus"></i> Add employee</a></div>
    </div>

    @php
        $sortLabels = [
            'created' => $dir === 'asc' ? 'oldest first' : 'newest first',
            'name' => 'name', 'email' => 'email', 'code' => 'code',
            'department' => 'department', 'designation' => 'designation',
            'joining' => 'joining date', 'status' => 'status',
        ];
        $sortText = $sortLabels[$sort] ?? 'newest first';
        if ($sort !== 'created') {
            $sortText .= $dir === 'asc' ? ' (A–Z)' : ' (Z–A)';
        }

        // Sortable column header: clicking toggles asc/desc, keeps the search term.
        $baseQ = $search !== '' ? ['q' => $search] : [];
        $th = function (string $key, string $label) use ($sort, $dir, $baseQ) {
            $active  = $sort === $key;
            $nextDir = $active && $dir === 'asc' ? 'desc' : 'asc';
            $url     = route('hr.employees.index', $baseQ + ['sort' => $key, 'dir' => $nextDir]);
            $caret   = $active ? '<span class="caret">'.($dir === 'asc' ? '▲' : '▼').'</span>' : '';
            return '<a class="emp-sort'.($active ? ' active' : '').'" href="'.e($url).'">'.e($label).$caret.'</a>';
        };
    @endphp

    <div class="pv-card">
        <div class="h">
            <i class="fas fa-users pv-muted"></i>
            @if($search !== '')
                {{ $employees->total() }} {{ Str::plural('result', $employees->total()) }} for “{{ $search }}”
            @else
                Your team ({{ $employees->total() }})
            @endif
            <span class="pv-mut2">· sorted by {{ $sortText }}</span>
        </div>
        <div class="b tight">
            <form method="GET" action="{{ route('hr.employees.index') }}" class="emp-toolbar" role="search">
                <label class="emp-search">
                    <i class="fas fa-magnifying-glass"></i>
                    <input type="search" name="q" value="{{ $search }}" class="pv-input"
                           placeholder="Search by code, name, email, designation or department"
                           aria-label="Search employees">
                </label>
                @if($sort !== 'created')
                    <input type="hidden" name="sort" value="{{ $sort }}">
                    <input type="hidden" name="dir" value="{{ $dir }}">
                @endif
                <button type="submit" class="pv-btn p"><i class="fas fa-magnifying-glass"></i> Search</button>
                @if($search !== '')
                    <a href="{{ route('hr.employees.index') }}" class="pv-btn">Clear</a>
                @endif
            </form>

            @if($employees->isEmpty())
                @if($search !== '')
                    <div class="pv-empty"><div class="ic"><i class="fas fa-magnifying-glass"></i></div>
                        No employees match “{{ $search }}”. <a href="{{ route('hr.employees.index') }}">Clear search</a></div>
                @else
                    <div class="pv-empty"><div class="ic"><i class="fas fa-user-friends"></i></div>
                        No employees yet. <a href="{{ route('hr.employees.create') }}">Add the first employee.</a></div>
                @endif
            @else
                <div class="pv-tw"><table class="pv-table" style="min-width:760px"><thead><tr>
                    <th>{!! $th('name', 'Employee') !!}</th>
                    <th>{!! $th('code', 'Code') !!}</th>
                    <th>{!! $th('department', 'Department') !!}</th>
                    <th>{!! $th('designation', 'Designation') !!}</th>
                    <th>{!! $th('joining', 'Joining') !!}</th>
                    <th>{!! $th('status', 'Status') !!}</th>
                    <th></th>
                </tr></thead><tbody>
                @foreach($employees as $emp)
                    @php($p = $profiles->get($emp->id))
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:10px">
                                @if($p?->photo_path && is_file(public_path($p->photo_path)))
                                    <img src="{{ asset($p->photo_path) }}" alt="" style="width:34px;height:34px;border-radius:50%;object-fit:cover;flex:none">
                                @else
                                    <span style="width:34px;height:34px;border-radius:50%;background:var(--pv-brand,#6366f1);color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex:none">{{ strtoupper(substr($emp->name ?? '?', 0, 1)) }}</span>
                                @endif
                                <div><strong>{{ $emp->name }}</strong><div class="pv-mut2">#{{ $emp->id }} · {{ $emp->email }}</div></div>
                            </div>
                        </td>
                        <td>{{ $p->employee_code ?? '—' }}</td><td>{{ $p?->department?->name ?? '—' }}</td><td>{{ $p->designation ?? '—' }}</td>
                        <td>{{ optional($p?->date_of_joining)->format('d-M-Y') ?? '—' }}</td>
                        <td><span class="pv-badge {{ $p?->status === 'active' ? 'ok' : '' }}">{{ ucfirst($p->status ?? 'onboarding') }}</span></td>
                        <td style="white-space:nowrap">
                            <a href="{{ route('hr.employees.edit', $emp) }}" class="pv-btn p sm"><i class="fas fa-pen"></i> View / edit</a>
                            <form method="POST" action="{{ route('hr.employees.destroy', $emp) }}" style="display:inline"
                                  onsubmit="return confirm('Remove {{ $emp->name }}? They drop off attendance, leave, payroll and salary screens. You can restore them afterwards.')">
                                @csrf @method('DELETE')
                                <button class="pv-btn d sm"><i class="fas fa-trash"></i> Delete</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody></table></div>

                @if($employees->hasPages())
                    <div class="emp-pager">
                        <span class="pv-mut2">Showing {{ $employees->firstItem() }}–{{ $employees->lastItem() }} of {{ $employees->total() }}</span>
                        <span class="pv-btngrp">
                            <a class="pv-btn sm {{ $employees->onFirstPage() ? 'is-disabled' : '' }}"
                               href="{{ $employees->previousPageUrl() ?: '#' }}" @if($employees->onFirstPage()) aria-disabled="true" @endif>
                                <i class="fas fa-chevron-left"></i> Prev
                            </a>
                            <span class="pv-btn sm">Page {{ $employees->currentPage() }} of {{ $employees->lastPage() }}</span>
                            <a class="pv-btn sm {{ $employees->hasMorePages() ? '' : 'is-disabled' }}"
                               href="{{ $employees->nextPageUrl() ?: '#' }}" @unless($employees->hasMorePages()) aria-disabled="true" @endunless>
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        </span>
                    </div>
                @endif
            @endif
        </div>
    </div>

    @if(isset($deleted) && $deleted->isNotEmpty())
    <div class="pv-card">
        <div class="h"><i class="fas fa-trash-can pv-muted"></i> Deleted employees ({{ $deleted->count() }})
            <span class="pv-mut2">Soft-deleted — hidden from all reports, still in the database</span></div>
        <div class="b tight">
            <div class="pv-tw"><table class="pv-table" style="min-width:520px"><thead><tr><th>Employee</th><th>Deleted</th><th></th></tr></thead><tbody>
            @foreach($deleted as $d)
                <tr>
                    <td><strong>{{ $d->user->name ?? 'Employee #'.$d->user_id }}</strong><div class="pv-mut2">{{ $d->user->email ?? '' }}</div></td>
                    <td class="pv-mut2">{{ optional($d->deleted_at)->diffForHumans() }}</td>
                    <td>
                        <form method="POST" action="{{ route('hr.employees.restore', $d->user_id) }}" style="display:inline">
                            @csrf
                            <button class="pv-btn g sm"><i class="fas fa-rotate-left"></i> Restore</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody></table></div>
        </div>
    </div>
    @endif
</div>
@endsection
