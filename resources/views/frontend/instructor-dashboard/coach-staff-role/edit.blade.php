@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
@include('frontend.instructor-dashboard.settings.partials._corporate')

@php
    $existingIds = collect($arr ?? [])->map(fn ($v) => (int) $v)->all();
    if (empty($existingIds) && isset($role)) {
        $existingIds = $role->permissions->pluck('id')->map(fn ($v) => (int) $v)->all();
    }
    // Resolve "staff using this role" count for the side panel context.
    try {
        $coachIdForCount = userAuth()->role === 'instructor' ? userAuth()->id : (userAuth()->coach_id ?? userAuth()->id);
        $staffOnRole = \DB::table('users')
            ->where('added_by', $coachIdForCount)
            ->where('role_id', $role->id)
            ->count();
    } catch (\Throwable $e) {
        $staffOnRole = 0;
    }
@endphp

<div class="corp-page" id="role-edit-page">
    {{-- Header --}}
    <div class="corp-header">
        <div class="corp-header__title">
            <h4>{{ __('Edit Role') }} — <span style="color:var(--corp-brand);">{{ $role->role_name }}</span></h4>
            <p>{{ __('Changes apply to every staff member assigned to this role on save.') }}</p>
        </div>
        <div class="corp-header__actions">
            {{-- View / Edit mode toggle — defaults to View for safety on
                 production roles. Toggle to Edit to mutate. --}}
            <div class="corp-modeswitch" id="role-mode-switch" role="tablist">
                <button type="button" class="corp-modeswitch__btn is-active" data-mode="view"><i class="fas fa-eye"></i> {{ __('View') }}</button>
                <button type="button" class="corp-modeswitch__btn" data-mode="edit"><i class="fas fa-edit"></i> {{ __('Edit') }}</button>
            </div>
            <a href="{{ route('instructor.coach-staff-role.index') }}" class="btn-corp-secondary">
                <i class="fas fa-arrow-left"></i> {{ __('Back') }}
            </a>
        </div>
    </div>

    <form action="{{ route('instructor.coach-staff-role.update', $role->id) }}" method="POST" id="role-edit-form" class="corp-readonly">
        @csrf
        @method('PUT')

        {{-- 2026-07-04 — side context panel removed; form is full-width. --}}
        <div>
            <div>
                {{-- Role details ──────────────────────────────── --}}
                <div class="corp-form-card">
                    <div class="corp-form-card__head">
                        <h6 class="corp-form-card__title">
                            <i class="fas fa-shield-alt" style="color:var(--corp-brand);"></i>
                            {{ __('Role Details') }}
                        </h6>
                    </div>
                    <div class="corp-form-card__body">
                        <div class="row g-3">
                            <div class="col-md-5">
                                <div class="corp-field">
                                    <label class="corp-field__label">{{ __('Role Name') }}<span class="req">*</span></label>
                                    <input type="text" name="role_name" class="corp-input role_name @error('role_name') is-invalid @enderror"
                                           value="{{ old('role_name', $role->role_name ?? '') }}" required>
                                    @error('role_name')<div class="corp-field__hint" style="color:#b91c1c;">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="col-md-5">
                                <div class="corp-field">
                                    <label class="corp-field__label">{{ __('Role Slug') }}<span class="req">*</span></label>
                                    <input type="text" name="role_slug" class="corp-input role_slug @error('role_slug') is-invalid @enderror"
                                           value="{{ old('role_slug', $role->role_slug ?? '') }}" required>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="corp-field">
                                    <label class="corp-field__label">{{ __('Status') }}<span class="req">*</span></label>
                                    <select id="status" name="status" class="corp-select">
                                        <option @selected($role->status == 1) value="1">{{ __('Active') }}</option>
                                        <option @selected($role->status == 0) value="0">{{ __('Inactive') }}</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Permissions picker (with built-in diff preview) ── --}}
                <div class="corp-form-card">
                    <div class="corp-form-card__head">
                        <h6 class="corp-form-card__title">
                            <i class="fas fa-key" style="color:var(--corp-brand);"></i>
                            {{ __('Permissions') }}
                        </h6>
                        <p class="corp-form-card__sub">
                            {{ __('The diff bar below shows what would change if you save now.') }}
                            <strong style="color:var(--corp-brand);">{{ $staffOnRole }}</strong>
                            {{ trans_choice('staff member|staff members', $staffOnRole) }}
                            {{ __('will be affected.') }}
                        </p>
                    </div>
                    @include('frontend.instructor-dashboard.settings.partials._permission-picker', [
                        'pickerPermissions' => \App\Models\CoachStaffPermission::orderBy('name')->get(),
                        'pickerField'       => 'roles_permissions_id[]',
                        'pickerChecked'     => old('roles_permissions_id', $existingIds),
                        'pickerId'          => 'role-edit-picker',
                    ])
                </div>

                {{-- Sticky action bar ─────────────────────────── --}}
                <div class="corp-sticky-bar">
                    <div class="corp-sticky-bar__summary">
                        <i class="fas fa-info-circle" style="color:var(--corp-brand);"></i>
                        <span>
                            <strong id="sticky-role-name">{{ $role->role_name }}</strong>
                            <span class="text-muted">·</span>
                            <span id="sticky-role-perms">{{ count($existingIds) }} {{ __('permissions') }}</span>
                            <span class="text-muted">·</span>
                            <span class="text-muted">{{ __('Affects') }}</span>
                            <strong>{{ $staffOnRole }}</strong>
                            <span class="text-muted">{{ trans_choice('staff|staff', $staffOnRole) }}</span>
                        </span>
                    </div>
                    <div class="corp-sticky-bar__actions">
                        <a href="{{ route('instructor.coach-staff-role.index') }}" class="btn-corp-secondary">{{ __('Cancel') }}</a>
                        <button type="submit" class="btn-corp-primary">
                            <i class="fas fa-check"></i> {{ __('Save Changes') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
(function () {
    /* ── Auto-slug from name ───────────────────────────────── */
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
            stickyName.textContent = this.value.trim() || '{{ __('Untitled role') }}';
        });
    }

    /* ── View/Edit mode toggle ─────────────────────────────── */
    var form  = document.getElementById('role-edit-form');
    var modeBtns = document.querySelectorAll('#role-mode-switch .corp-modeswitch__btn');
    modeBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            modeBtns.forEach(function (b) { b.classList.remove('is-active'); });
            btn.classList.add('is-active');
            if (btn.dataset.mode === 'view') {
                form.classList.add('corp-readonly');
            } else {
                form.classList.remove('corp-readonly');
            }
        });
    });

    /* ── Live sticky-bar perm count ────────────────────────── */
    var stickyPerms = document.getElementById('sticky-role-perms');
    document.getElementById('role-edit-picker')?.addEventListener('pp:change', function (e) {
        var checked = (e.detail && e.detail.checked) || 0;
        stickyPerms.textContent = checked + ' {{ __('permissions') }}';
    });

    /* ── Warn on leave with unsaved changes (only in Edit mode) --- */
    var dirty = false;
    document.addEventListener('change', function (e) {
        if (form.classList.contains('corp-readonly')) return;
        if (e.target && e.target.closest('#role-edit-form')) dirty = true;
    });
    document.addEventListener('input', function (e) {
        if (form.classList.contains('corp-readonly')) return;
        if (e.target && e.target.closest('#role-edit-form')) dirty = true;
    });
    form.addEventListener('submit', function () { dirty = false; });
    window.addEventListener('beforeunload', function (e) {
        if (!dirty) return;
        e.preventDefault();
        e.returnValue = '';
    });
})();
</script>
@endsection
