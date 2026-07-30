@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
@include('frontend.instructor-dashboard.settings.partials._corporate')

<style>
    #teacherBatches .corp-kpi__tile { padding-left: 70px; min-height: 96px; }
    #teacherBatches .corp-kpi__tile .tb-kpi-icon {
        position: absolute; top: 18px; left: 18px;
        width: 38px; height: 38px; border-radius: 10px;
        display: inline-flex; align-items: center; justify-content: center;
        background: color-mix(in srgb, var(--accent, var(--corp-brand)) 12%, #ffffff);
        color: var(--accent, var(--corp-brand));
        border: 1px solid color-mix(in srgb, var(--accent, var(--corp-brand)) 18%, transparent);
        font-size: 15px;
    }
</style>

<style>
/* 2026-07-10 (New Changes for UI #4) — dark mode for this page's bespoke components. */
html[data-theme="dark"] #teacherBatches .corp-kpi__tile .tb-kpi-icon {
    background: color-mix(in srgb, var(--accent, var(--corp-brand)) 20%, #1e293b);
}
</style>

<div class="corp-page" id="teacherBatches">

    {{-- Header --}}
    <div class="corp-header">
        <div class="corp-header__title">
            <h4>{{ __('Teacher Batch Assignments') }}</h4>
            <p>{{ __('Give a teacher access to specific batches. Teachers only see and manage the batches you assign — no more, no less.') }}</p>
        </div>
        <div class="corp-header__actions">
            <a href="{{ route('instructor.teacher-batches.create') }}" class="btn-corp-primary">
                <i class="fas fa-user-plus"></i> {{ __('Assign Batches') }}
            </a>
        </div>
    </div>

    {{-- KPI strip --}}
    <div class="corp-kpi">
        <div class="corp-kpi__tile" style="--accent:var(--corp-brand);">
            <span class="tb-kpi-icon"><i class="fas fa-tasks"></i></span>
            <div class="corp-kpi__label">{{ __('Total Assignments') }}</div>
            <div class="corp-kpi__value">{{ number_format($kpi['total'] ?? 0) }}</div>
            <div class="corp-kpi__sub">{{ __('all time, including removed') }}</div>
        </div>
        <div class="corp-kpi__tile" style="--accent:#10b981;">
            <span class="tb-kpi-icon"><i class="fas fa-check-circle"></i></span>
            <div class="corp-kpi__label">{{ __('Active') }}</div>
            <div class="corp-kpi__value" style="color:#047857;">{{ number_format($kpi['active'] ?? 0) }}</div>
            <div class="corp-kpi__sub">{{ __('granting access right now') }}</div>
        </div>
        <div class="corp-kpi__tile" style="--accent:#ef4444;">
            <span class="tb-kpi-icon"><i class="fas fa-user-times"></i></span>
            <div class="corp-kpi__label">{{ __('Removed') }}</div>
            <div class="corp-kpi__value" style="color:#b91c1c;">{{ number_format($kpi['inactive'] ?? 0) }}</div>
            <div class="corp-kpi__sub">{{ __('blocked, kept for audit') }}</div>
        </div>
        <div class="corp-kpi__tile" style="--accent:#f59e0b;">
            <span class="tb-kpi-icon"><i class="fas fa-user-friends"></i></span>
            <div class="corp-kpi__label">{{ __('Teachers Assigned') }}</div>
            <div class="corp-kpi__value">{{ number_format($kpi['teachers'] ?? 0) }}</div>
            <div class="corp-kpi__sub">{{ __('distinct people with grants') }}</div>
        </div>
    </div>

    {{-- Filter bar --}}
    <form method="GET" action="{{ route('instructor.teacher-batches.index') }}" class="corp-filters">
        <select name="teacher_id">
            <option value="">{{ __('All teachers') }}</option>
            @foreach ($teachers as $t)
                <option value="{{ $t->id }}" @selected(request('teacher_id') == $t->id)>{{ $t->name }}</option>
            @endforeach
        </select>
        <select name="course_id">
            <option value="">{{ __('All courses') }}</option>
            @foreach ($courses as $c)
                <option value="{{ $c->id }}" @selected(request('course_id') == $c->id)>{{ $c->title }}</option>
            @endforeach
        </select>
        <select name="status">
            <option value="">{{ __('All statuses') }}</option>
            <option value="active"   @selected(request('status') == 'active')>{{ __('Active') }}</option>
            <option value="inactive" @selected(request('status') == 'inactive')>{{ __('Removed') }}</option>
        </select>
        <button type="submit" class="btn-corp-search"><i class="fas fa-search"></i> {{ __('Filter') }}</button>
        @if (request('teacher_id') || request('course_id') || request('status'))
            <a href="{{ route('instructor.teacher-batches.index') }}" class="btn-corp-clear">{{ __('Clear') }}</a>
        @endif
    </form>

    {{-- Table --}}
    <div class="corp-table-wrap">
        <table class="corp-table">
            <thead>
                <tr>
                    <th>{{ __('Teacher') }}</th>
                    <th>{{ __('Course') }}</th>
                    <th>{{ __('Batch') }}</th>
                    <th>{{ __('Permission') }}</th>
                    <th>{{ __('Assigned') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th style="width:120px; text-align:right;">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($assignments as $a)
                    <tr>
                        <td>
                            <div style="display:flex; align-items:center; gap:10px;">
                                <div class="corp-avatar">{{ strtoupper(substr($a->teacher?->name ?? '?', 0, 1)) }}</div>
                                <div>
                                    <div style="font-weight:600; color:var(--corp-text);">{{ $a->teacher?->name ?? '—' }}</div>
                                    <div style="font-size:11px; color:var(--corp-muted);">{{ $a->teacher?->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td style="color:var(--corp-muted);">{{ $a->course?->title ?? '—' }}</td>
                        <td>
                            <div style="font-weight:600;">{{ $a->batch?->title ?? '—' }}</div>
                        </td>
                        <td>
                            <span class="corp-pill corp-pill--brand corp-pill--plain">
                                {{ ucfirst($a->permission_type) }}
                            </span>
                        </td>
                        <td>
                            <div style="font-size:13px;">{{ optional($a->assigned_at ?: $a->created_at)->format('M j, Y') }}</div>
                            <div style="font-size:11px; color:var(--corp-muted);">
                                {{ optional($a->assigned_at ?: $a->created_at)->diffForHumans() }}
                            </div>
                        </td>
                        <td>
                            @if ($a->status === 'active')
                                <span class="corp-pill corp-pill--success">{{ __('Active') }}</span>
                            @else
                                <span class="corp-pill corp-pill--danger">{{ __('Removed') }}</span>
                            @endif
                        </td>
                        <td style="text-align:right;">
                            <div class="corp-actions" style="justify-content:flex-end;">
                                <a href="{{ route('instructor.teacher-batches.edit', $a->id) }}"
                                   class="corp-actions__btn" title="{{ __('Edit') }}">
                                    <i class="fas fa-edit"></i>
                                </a>
                                @if ($a->status === 'active')
                                    <form action="{{ route('instructor.teacher-batches.destroy', $a->id) }}"
                                          method="POST" style="display:inline;"
                                          onsubmit="return confirm('{{ __('Remove this assignment? The teacher will lose access immediately.') }}');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="corp-actions__btn corp-actions__btn--danger" title="{{ __('Remove') }}">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            <div class="corp-empty">
                                <div class="corp-empty__icon"><i class="fas fa-user-tag"></i></div>
                                <div class="corp-empty__title">{{ __('No assignments yet') }}</div>
                                <div class="corp-empty__hint">
                                    {{ __('Pick a teacher and a course, then choose which batches they should manage.') }}
                                </div>
                                <a href="{{ route('instructor.teacher-batches.create') }}"
                                   class="btn-corp-primary" style="margin-top:16px;">
                                    <i class="fas fa-user-plus"></i> {{ __('Assign your first batch') }}
                                </a>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($assignments->hasPages())
            <div class="corp-pagination">{{ $assignments->links() }}</div>
        @endif
    </div>
</div>
@endsection
