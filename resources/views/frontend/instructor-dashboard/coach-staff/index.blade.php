@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
@include('frontend.instructor-dashboard.settings.partials._corporate')

<style>
    #coachStaff .corp-kpi__tile { padding-left: 70px; min-height: 96px; }
    #coachStaff .corp-kpi__tile .cs-kpi-icon {
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
    html[data-theme="dark"] #coachStaff .corp-kpi__tile .cs-kpi-icon {
        background: color-mix(in srgb, var(--accent, var(--corp-brand)) 12%, #1e293b);
    }
</style>

<div class="corp-page" id="coachStaff">

    {{-- Header ─────────────────────────────────────────────────── --}}
    <div class="corp-header">
        <div class="corp-header__title">
            <h4>{{ __('Staff') }}</h4>
            <p>{{ __('Manage the people who help you run your coaching business — assign roles + permissions per teammate.') }}</p>
        </div>
        <div class="corp-header__actions">
            <a href="{{ route('instructor.coach-staff.create') }}" class="btn-corp-primary">
                <i class="fas fa-user-plus"></i> {{ __('Add Staff') }}
            </a>
        </div>
    </div>

    {{-- KPI strip ──────────────────────────────────────────────── --}}
    <div class="corp-kpi">
        <div class="corp-kpi__tile" style="--accent:var(--corp-brand);">
            <span class="cs-kpi-icon"><i class="fas fa-users"></i></span>
            <div class="corp-kpi__label">{{ __('Total Staff') }}</div>
            <div class="corp-kpi__value">{{ number_format($kpi['total'] ?? 0) }}</div>
            <div class="corp-kpi__sub">{{ __('all teammates added by you') }}</div>
        </div>
        <div class="corp-kpi__tile" style="--accent:#10b981;">
            <span class="cs-kpi-icon"><i class="fas fa-user-check"></i></span>
            <div class="corp-kpi__label">{{ __('Active') }}</div>
            <div class="corp-kpi__value" style="color:#047857;">{{ number_format($kpi['active'] ?? 0) }}</div>
            <div class="corp-kpi__sub">{{ __('can sign in right now') }}</div>
        </div>
        <div class="corp-kpi__tile" style="--accent:#ef4444;">
            <span class="cs-kpi-icon"><i class="fas fa-user-slash"></i></span>
            <div class="corp-kpi__label">{{ __('Banned / Inactive') }}</div>
            <div class="corp-kpi__value" style="color:#b91c1c;">{{ number_format($kpi['banned'] ?? 0) }}</div>
            <div class="corp-kpi__sub">{{ __('no access') }}</div>
        </div>
        <div class="corp-kpi__tile" style="--accent:#f59e0b;">
            <span class="cs-kpi-icon"><i class="fas fa-user-plus"></i></span>
            <div class="corp-kpi__label">{{ __('Added (30 days)') }}</div>
            <div class="corp-kpi__value">{{ number_format($kpi['added_30d'] ?? 0) }}</div>
            <div class="corp-kpi__sub">{{ __('recent activity') }}</div>
        </div>
    </div>

    {{-- Filter bar ─────────────────────────────────────────────── --}}
    <form method="GET" action="{{ route('instructor.coach-staff.index') }}" class="corp-filters">
        <input type="search" name="q" value="{{ request('q') }}"
               placeholder="{{ __('Search by name, email, phone…') }}">
        <select name="role_id">
            <option value="">{{ __('All roles') }}</option>
            @foreach (($roles ?? collect()) as $r)
                <option value="{{ $r->id }}" @selected(request('role_id') == $r->id)>{{ $r->role_name }}</option>
            @endforeach
        </select>
        <select name="status">
            <option value="">{{ __('All statuses') }}</option>
            <option value="active" @selected(request('status') == 'active')>{{ __('Active') }}</option>
            <option value="banned" @selected(request('status') == 'banned')>{{ __('Banned') }}</option>
        </select>
        <button type="submit" class="btn-corp-search"><i class="fas fa-search"></i> {{ __('Search') }}</button>
        @if (request('q') || request('role_id') || request('status'))
            <a href="{{ route('instructor.coach-staff.index') }}" class="btn-corp-clear">{{ __('Clear') }}</a>
        @endif
    </form>

    {{-- Table ──────────────────────────────────────────────────── --}}
    <div class="corp-table-wrap">
        <table class="corp-table">
            <thead>
                <tr>
                    <th>{{ __('Staff Member') }}</th>
                    <th>{{ __('Email') }}</th>
                    <th>{{ __('Role') }}</th>
                    <th>{{ __('Joined') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th style="width:120px; text-align:right;">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($coachStaff as $s)
                    <tr>
                        <td>
                            <div style="display:flex; align-items:center; gap:10px;">
                                <div class="corp-avatar">
                                    {{ strtoupper(substr($s->name, 0, 1)) }}
                                </div>
                                <div>
                                    <div style="font-weight:600; color:var(--corp-text);">{{ $s->name }}</div>
                                    @if ($s->phone)
                                        <div style="font-size:11px; color:var(--corp-muted);">
                                            <i class="fas fa-phone" style="font-size:9px;"></i> {{ $s->phone }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td style="color:var(--corp-muted);">{{ $s->email }}</td>
                        <td>
                            @php
                                $roleName = $s->roleName?->role_name ?? $s->role;
                            @endphp
                            <span class="corp-pill corp-pill--brand corp-pill--plain">
                                {{ ucfirst($roleName) }}
                            </span>
                        </td>
                        <td>
                            <div style="font-size:13px;">{{ optional($s->created_at)->format('M j, Y') }}</div>
                            <div style="font-size:11px; color:var(--corp-muted);">
                                {{ optional($s->created_at)->diffForHumans() }}
                            </div>
                        </td>
                        <td>
                            @if ($s->status == 'active')
                                <span class="corp-pill corp-pill--success">{{ __('Active') }}</span>
                            @else
                                <span class="corp-pill corp-pill--danger">{{ __('Banned') }}</span>
                            @endif
                        </td>
                        <td style="text-align:right;">
                            <div class="corp-actions" style="justify-content:flex-end;">
                                <a href="{{ route('instructor.coach-staff.edit', $s->id) }}"
                                   class="corp-actions__btn" title="{{ __('Edit') }}">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form action="{{ route('instructor.coach-staff.destroy', $s->id) }}"
                                      method="POST" style="display:inline;"
                                      onsubmit="return confirm('{{ __('Delete this staff member? They will lose access immediately.') }}');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="corp-actions__btn corp-actions__btn--danger"
                                            title="{{ __('Delete') }}">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            <div class="corp-empty">
                                <div class="corp-empty__icon"><i class="fas fa-users"></i></div>
                                <div class="corp-empty__title">{{ __('No staff yet') }}</div>
                                <div class="corp-empty__hint">
                                    {{ __('Add your first teammate and assign them a role to delegate work.') }}
                                </div>
                                <a href="{{ route('instructor.coach-staff.create') }}"
                                   class="btn-corp-primary" style="margin-top:16px;">
                                    <i class="fas fa-user-plus"></i> {{ __('Add your first staff member') }}
                                </a>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($coachStaff->hasPages())
            <div class="corp-pagination">{{ $coachStaff->links() }}</div>
        @endif
    </div>
</div>
@endsection
