@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
@include('frontend.instructor-dashboard.settings.partials._corporate')

@php
    // Pre-built presets — match by slug pattern. Filter against actual
    // available permissions so we never grant a slug that doesn't exist.
    $allPerms = \App\Models\CoachStaffPermission::orderBy('name')->get();
    $presets = [
        [
            'key'   => 'readonly',
            'name'  => __('Read-only Auditor'),
            'desc'  => __('Every view/show permission, no create/edit/delete.'),
            'icon'  => 'fa-eye',
            'match' => fn ($p) => preg_match('/(-show|\.show|-view|\.view|-list|\.list)$/', (string) ($p->slug ?? $p->name)) === 1,
        ],
        [
            'key'   => 'course_manager',
            'name'  => __('Course Manager'),
            'desc'  => __('Full control over courses, batches, lessons.'),
            'icon'  => 'fa-graduation-cap',
            'match' => fn ($p) => preg_match('/^(course|courses|course-batches?)/', (string) ($p->slug ?? '')) === 1,
        ],
        [
            'key'   => 'student_manager',
            'name'  => __('Student Manager'),
            'desc'  => __('Add, edit, list students and view sales.'),
            'icon'  => 'fa-users',
            'match' => fn ($p) => preg_match('/^(coach-students?|coach-sells?)/', (string) ($p->slug ?? '')) === 1,
        ],
        [
            'key'   => 'blank',
            'name'  => __('Custom (Blank)'),
            'desc'  => __('Start with nothing — tick what you need.'),
            'icon'  => 'fa-arrow-pointer',
            'match' => fn ($p) => false,
        ],
    ];
    // Pre-compute permission IDs per preset so the JS can apply instantly.
    $presetMap = [];
    foreach ($presets as $preset) {
        $presetMap[$preset['key']] = $allPerms->filter($preset['match'])->pluck('id')->values()->all();
    }
@endphp

<div class="corp-page">
    {{-- Header --}}
    <div class="corp-header">
        <div class="corp-header__title">
            <h4>{{ __('Add Role') }}</h4>
            <p>{{ __('A role is a reusable bundle of permissions. Assign the role to a teammate — they inherit everything the role can do.') }}</p>
        </div>
        <div class="corp-header__actions">
            <a href="{{ route('instructor.coach-staff-role.index') }}" class="btn-corp-secondary">
                <i class="fas fa-arrow-left"></i> {{ __('Back to Roles') }}
            </a>
        </div>
    </div>

    <form action="{{ route('instructor.coach-staff-role.store') }}" method="POST" id="role-create-form">
        @csrf

        {{-- 2026-07-04 — side Tips/How-roles-work panel removed; form is full-width. --}}
        <div>
            <div>
                {{-- Role details ──────────────────────────────── --}}
                <div class="corp-form-card">
                    <div class="corp-form-card__head">
                        <h6 class="corp-form-card__title">
                            <i class="fas fa-shield-alt" style="color:var(--corp-brand);"></i>
                            {{ __('Role Details') }}
                        </h6>
                        <p class="corp-form-card__sub">{{ __('Name it after a job function — e.g. "Senior Coach", "Accounts Assistant".') }}</p>
                    </div>
                    <div class="corp-form-card__body">
                        <div class="row g-3">
                            <div class="col-md-5">
                                <div class="corp-field">
                                    <label class="corp-field__label">{{ __('Role Name') }}<span class="req">*</span></label>
                                    <input type="text" id="role-name" class="corp-input role_name @error('role_name') is-invalid @enderror"
                                           name="role_name" value="{{ old('role_name') }}" required
                                           placeholder="e.g. Senior Coach">
                                    @error('role_name')<div class="corp-field__hint" style="color:#b91c1c;">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="col-md-5">
                                <div class="corp-field">
                                    <label class="corp-field__label">{{ __('Role Slug') }}<span class="req">*</span></label>
                                    <input type="text" name="role_slug" class="corp-input role_slug @error('role_slug') is-invalid @enderror"
                                           value="{{ old('role_slug', $role->role_slug ?? '') }}" required
                                           placeholder="auto-generated">
                                    <div class="corp-field__hint">{{ __('Lowercase, dash-separated. Auto-fills from name.') }}</div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="corp-field">
                                    <label class="corp-field__label">{{ __('Status') }}<span class="req">*</span></label>
                                    <select id="status" name="status" class="corp-select">
                                        <option value="1">{{ __('Active') }}</option>
                                        <option value="0">{{ __('Inactive') }}</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Quick-start presets ───────────────────────── --}}
                <div class="corp-form-card">
                    <div class="corp-form-card__head">
                        <h6 class="corp-form-card__title">
                            <i class="fas fa-bolt" style="color:var(--corp-brand);"></i>
                            {{ __('Quick Start (Optional)') }}
                        </h6>
                        <p class="corp-form-card__sub">{{ __('Pick a template to pre-fill common permission sets. You can still customise after.') }}</p>
                    </div>
                    <div class="corp-form-card__body">
                        <div class="corp-presets">
                            @foreach ($presets as $preset)
                                <button type="button" class="corp-preset"
                                        data-preset-key="{{ $preset['key'] }}"
                                        data-preset-ids='@json($presetMap[$preset['key']])'>
                                    <div class="corp-preset__icon"><i class="fas {{ $preset['icon'] }}"></i></div>
                                    <div class="corp-preset__name">{{ $preset['name'] }}</div>
                                    <div class="corp-preset__desc">{{ $preset['desc'] }}</div>
                                    <div class="corp-preset__count">
                                        @if (count($presetMap[$preset['key']]) > 0)
                                            <i class="fas fa-check-circle"></i> {{ count($presetMap[$preset['key']]) }} {{ __('permissions') }}
                                        @else
                                            <i class="fas fa-minus-circle"></i> {{ __('clear all') }}
                                        @endif
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Permissions picker ────────────────────────── --}}
                <div class="corp-form-card">
                    <div class="corp-form-card__head">
                        <h6 class="corp-form-card__title">
                            <i class="fas fa-key" style="color:var(--corp-brand);"></i>
                            {{ __('Permissions') }}
                        </h6>
                        <p class="corp-form-card__sub">{{ __('Tick what this role can do. Search, group-toggle, or use a preset above.') }}</p>
                    </div>
                    @include('frontend.instructor-dashboard.settings.partials._permission-picker', [
                        'pickerPermissions' => $allPerms,
                        'pickerField'       => 'roles_permissions_id[]',
                        'pickerChecked'     => old('roles_permissions_id', $arr ?? []),
                        'pickerId'          => 'role-create-picker',
                    ])
                </div>

                {{-- Sticky action bar ─────────────────────────── --}}
                <div class="corp-sticky-bar">
                    <div class="corp-sticky-bar__summary">
                        <i class="fas fa-info-circle" style="color:var(--corp-brand);"></i>
                        <span>
                            <strong id="sticky-role-name">{{ __('New role') }}</strong>
                            <span class="text-muted">·</span>
                            <span id="sticky-role-perms">0 {{ __('permissions') }}</span>
                            <span class="text-muted">·</span>
                            <span id="sticky-role-modules">0 {{ __('modules') }}</span>
                        </span>
                    </div>
                    <div class="corp-sticky-bar__actions">
                        <a href="{{ route('instructor.coach-staff-role.index') }}" class="btn-corp-secondary">{{ __('Cancel') }}</a>
                        <button type="submit" class="btn-corp-primary">
                            <i class="fas fa-check"></i> {{ __('Create Role') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
(function () {
    /* ── Auto-slug from role name ──────────────────────────── */
    var name = document.querySelector('.role_name');
    var slug = document.querySelector('.role_slug');
    var stickyName = document.getElementById('sticky-role-name');
    if (name && slug) {
        name.addEventListener('input', function () {
            slug.value = this.value.toLowerCase().trim()
                .replace(/[^a-z0-9\s-]/g, '')
                .replace(/\s+/g, '-')
                .replace(/-+/g, '-')
                .replace(/^-|-$/g, '');
            stickyName.textContent = this.value.trim() || '{{ __('New role') }}';
        });
    }

    /* ── Apply preset → check matching permission ids ──────── */
    document.querySelectorAll('.corp-preset').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var ids;
            try { ids = JSON.parse(btn.dataset.presetIds || '[]'); } catch (e) { ids = []; }
            var picker = document.getElementById('role-create-picker');
            if (!picker) return;
            var idSet = new Set(ids.map(Number));
            picker.querySelectorAll('input[type=checkbox]').forEach(function (cb) {
                cb.checked = idSet.has(Number(cb.value));
            });
            // Trigger picker's recount listener.
            picker.querySelector('.pp-modules').dispatchEvent(new Event('change', { bubbles: true }));
        });
    });

    /* ── Live sticky-bar summary driven by picker events ──── */
    var stickyPerms   = document.getElementById('sticky-role-perms');
    var stickyMods    = document.getElementById('sticky-role-modules');
    document.getElementById('role-create-picker')?.addEventListener('pp:change', function (e) {
        var d = e.detail || {};
        stickyPerms.textContent = (d.checked || 0) + ' {{ __('permissions') }}';
        stickyMods.textContent  = (d.modulesCovered || 0) + ' {{ __('modules') }}';
    });
})();
</script>
@endsection
