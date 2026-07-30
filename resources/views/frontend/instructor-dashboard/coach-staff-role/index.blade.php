@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
@include('frontend.instructor-dashboard.settings.partials._corporate')

<style>
    #staffRoles .corp-kpi__tile { padding-left: 70px; min-height: 96px; }
    #staffRoles .corp-kpi__tile .sr-kpi-icon {
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
    html[data-theme="dark"] #staffRoles .corp-kpi__tile .sr-kpi-icon {
        background: color-mix(in srgb, var(--accent, var(--corp-brand)) 12%, #1e293b);
    }
</style>

<div class="corp-page" id="staffRoles">

    {{-- Header ─────────────────────────────────────────────────── --}}
    <div class="corp-header">
        <div class="corp-header__title">
            <h4>{{ __('Roles') }}</h4>
            <p>{{ __('Bundles of permissions you can assign to staff. Edit a role once — every staff member with that role inherits the change.') }}</p>
        </div>
        <div class="corp-header__actions">
            <a href="{{ route('instructor.coach-staff-role.create') }}" class="btn-corp-primary">
                <i class="fas fa-plus"></i> {{ __('Add Role') }}
            </a>
        </div>
    </div>

    {{-- KPI strip ──────────────────────────────────────────────── --}}
    <div class="corp-kpi">
        <div class="corp-kpi__tile" style="--accent:var(--corp-brand);">
            <span class="sr-kpi-icon"><i class="fas fa-user-tag"></i></span>
            <div class="corp-kpi__label">{{ __('Total Roles') }}</div>
            <div class="corp-kpi__value">{{ number_format($kpi['total'] ?? 0) }}</div>
            <div class="corp-kpi__sub">{{ __('defined by you') }}</div>
        </div>
        <div class="corp-kpi__tile" style="--accent:#10b981;">
            <span class="sr-kpi-icon"><i class="fas fa-check-circle"></i></span>
            <div class="corp-kpi__label">{{ __('Active') }}</div>
            <div class="corp-kpi__value" style="color:#047857;">{{ number_format($kpi['active'] ?? 0) }}</div>
            <div class="corp-kpi__sub">{{ __('available for assignment') }}</div>
        </div>
        <div class="corp-kpi__tile" style="--accent:#f59e0b;">
            <span class="sr-kpi-icon"><i class="fas fa-hourglass-half"></i></span>
            <div class="corp-kpi__label">{{ __('Pending') }}</div>
            <div class="corp-kpi__value">{{ number_format($kpi['pending'] ?? 0) }}</div>
            <div class="corp-kpi__sub">{{ __('drafts — not active yet') }}</div>
        </div>
        <div class="corp-kpi__tile" style="--accent:#06b6d4;">
            <span class="sr-kpi-icon"><i class="fas fa-users-cog"></i></span>
            <div class="corp-kpi__label">{{ __('Roles in Use') }}</div>
            <div class="corp-kpi__value">{{ number_format($kpi['staffed'] ?? 0) }}</div>
            <div class="corp-kpi__sub">{{ __('have at least 1 staff member') }}</div>
        </div>
    </div>

    {{-- Filter bar ─────────────────────────────────────────────── --}}
    <form method="GET" action="{{ route('instructor.coach-staff-role.index') }}" class="corp-filters">
        <input type="search" name="q" value="{{ request('q') }}"
               placeholder="{{ __('Search by role name…') }}">
        <select name="status">
            <option value="">{{ __('All statuses') }}</option>
            <option value="1" @selected(request('status') === '1')>{{ __('Active') }}</option>
            <option value="0" @selected(request('status') === '0')>{{ __('Pending') }}</option>
        </select>
        <button type="submit" class="btn-corp-search"><i class="fas fa-search"></i> {{ __('Search') }}</button>
        @if (request('q') || request()->has('status'))
            <a href="{{ route('instructor.coach-staff-role.index') }}" class="btn-corp-clear">{{ __('Clear') }}</a>
        @endif
    </form>

    {{-- Table ──────────────────────────────────────────────────── --}}
    <div class="corp-table-wrap">
        <table class="corp-table">
            <thead>
                <tr>
                    <th>{{ __('Role') }}</th>
                    <th style="text-align:center; width:130px;">{{ __('Permissions') }}</th>
                    <th style="text-align:center; width:110px;">{{ __('Staff') }}</th>
                    <th>{{ __('Created') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th style="width:120px; text-align:right;">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($coachStaffRole as $s)
                    <tr>
                        <td>
                            <div style="font-weight:600; color:var(--corp-text);">
                                <i class="fas fa-shield-alt" style="color:var(--corp-brand); margin-right:6px;"></i>
                                {{ $s->role_name }}
                            </div>
                        </td>
                        <td style="text-align:center;">
                            <span class="corp-pill corp-pill--brand corp-pill--plain">
                                <i class="fas fa-key" style="font-size:9px;"></i> {{ $s->permissions_count ?? 0 }}
                            </span>
                        </td>
                        <td style="text-align:center;">
                            @if ($s->staff_count > 0)
                                <span class="corp-pill corp-pill--success">
                                    {{ $s->staff_count }} {{ trans_choice('person|people', $s->staff_count) }}
                                </span>
                            @else
                                <span style="font-size:11px; color:var(--corp-subtle);">{{ __('—') }}</span>
                            @endif
                        </td>
                        <td>
                            <div style="font-size:13px;">{{ optional($s->created_at)->format('M j, Y') }}</div>
                            <div style="font-size:11px; color:var(--corp-muted);">
                                {{ optional($s->created_at)->diffForHumans() }}
                            </div>
                        </td>
                        <td>
                            @if ($s->status == 1)
                                <span class="corp-pill corp-pill--success">{{ __('Active') }}</span>
                            @else
                                <span class="corp-pill corp-pill--warning">{{ __('Pending') }}</span>
                            @endif
                        </td>
                        <td style="text-align:right;">
                            <div class="corp-actions" style="justify-content:flex-end;">
                                <a href="{{ route('instructor.coach-staff-role.edit', $s->id) }}"
                                   class="corp-actions__btn" title="{{ __('Edit') }}">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form action="{{ route('instructor.coach-staff-role.destroy', $s->id) }}"
                                      method="POST" style="display:inline;"
                                      onsubmit="return confirm('{{ __('Delete this role? Staff assigned to it will need a new role.') }}');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="corp-actions__btn corp-actions__btn--danger"
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
                                <div class="corp-empty__icon"><i class="fas fa-shield-alt"></i></div>
                                <div class="corp-empty__title">{{ __('No roles defined') }}</div>
                                <div class="corp-empty__hint">
                                    {{ __('Create a role first — for example "Assistant Coach" — then assign staff to it.') }}
                                </div>
                                <a href="{{ route('instructor.coach-staff-role.create') }}"
                                   class="btn-corp-primary" style="margin-top:16px;">
                                    <i class="fas fa-plus"></i> {{ __('Create first role') }}
                                </a>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($coachStaffRole->hasPages())
            <div class="corp-pagination">{{ $coachStaffRole->links() }}</div>
        @endif
    </div>
</div>
@endsection
