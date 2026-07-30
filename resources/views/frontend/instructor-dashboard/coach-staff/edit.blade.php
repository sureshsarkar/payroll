@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
@include('frontend.instructor-dashboard.settings.partials._corporate')

@php
    // Pre-checked permissions for THIS staff member (used to mark
    // checkboxes inside the grouped picker below).
    $checkedIds = collect($userPermissions ?? [])->pluck('id')->map(fn ($v) => (int) $v)->all();
@endphp

<style>
/* Inherits everything from _corporate; this file adds form-specific bits. */
.corp-form-card {
    background: var(--corp-card);
    border: 1px solid var(--corp-line);
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 14px;
}
.corp-form-card__head {
    padding: 14px 18px;
    border-bottom: 1px solid var(--corp-line-soft);
    background: #fafbfc;
}
.corp-form-card__title {
    font-size: 13px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: var(--corp-text);
    margin: 0;
}
.corp-form-card__sub {
    font-size: 11px;
    color: var(--corp-muted);
    margin: 2px 0 0;
}
.corp-form-card__body { padding: 18px; }

.corp-field__label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: var(--corp-text);
    margin-bottom: 6px;
}
.corp-field__label .req { color: #ef4444; margin-left: 2px; }
.corp-field__hint { font-size: 11px; color: var(--corp-muted); margin-top: 4px; }
.corp-input, .corp-select {
    width: 100%;
    height: 40px;
    border: 1px solid var(--corp-line);
    border-radius: 8px;
    padding: 8px 12px;
    font-size: 13px;
    color: var(--corp-text);
    background: #fff;
}
.corp-input:focus, .corp-select:focus {
    outline: none;
    border-color: var(--corp-brand);
    box-shadow: 0 0 0 3px rgba(16, 185, 129, .12);
}

/* Permission picker — same look as create page. */
.corp-perm-toolbar {
    display: flex; gap: 10px; align-items: center; flex-wrap: wrap;
    padding: 12px 18px;
    border-bottom: 1px solid var(--corp-line-soft);
    background: #fcfcfd;
}
.corp-perm-toolbar input[type="search"] {
    flex: 1; min-width: 200px; height: 36px;
    border: 1px solid var(--corp-line); border-radius: 8px;
    padding: 6px 12px; font-size: 13px;
}
.corp-perm-toolbar .corp-perm-count {
    font-size: 11px; color: var(--corp-muted); font-weight: 600;
    padding: 4px 10px; background: #fff;
    border: 1px solid var(--corp-line); border-radius: 6px;
}
.corp-perm-toolbar .corp-perm-btn {
    background: transparent; border: 1px solid var(--corp-line);
    color: var(--corp-text); padding: 6px 12px;
    font-size: 12px; border-radius: 6px; cursor: pointer;
}
.corp-perm-toolbar .corp-perm-btn:hover { background: #f3f4f6; }

.corp-perm-modules { padding: 6px 14px 14px; max-height: 480px; overflow-y: auto; }
.corp-perm-module { border: 1px solid var(--corp-line-soft); border-radius: 8px; margin-top: 8px; background: #fff; }
.corp-perm-module__head {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 12px; cursor: pointer; user-select: none;
    border-bottom: 1px solid transparent;
}
.corp-perm-module[open] .corp-perm-module__head {
    border-bottom-color: var(--corp-line-soft); background: #fafbfc;
}
.corp-perm-module__chev {
    width: 18px; height: 18px;
    display: inline-flex; align-items: center; justify-content: center;
    transition: transform .15s;
    color: var(--corp-muted); font-size: 11px;
}
.corp-perm-module[open] .corp-perm-module__chev { transform: rotate(90deg); }
.corp-perm-module__name {
    font-size: 12px; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.4px;
    color: var(--corp-text);
}
.corp-perm-module__meta {
    margin-left: auto;
    display: flex; align-items: center; gap: 8px;
    font-size: 11px; color: var(--corp-muted);
}
.corp-perm-module__select-all {
    font-size: 11px; color: var(--corp-brand); cursor: pointer; user-select: none;
    background: transparent; border: 1px solid transparent;
    padding: 2px 8px; border-radius: 4px;
}
.corp-perm-module__select-all:hover { background: var(--corp-brand-bg); }

.corp-perm-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 6px 10px; padding: 10px 12px 12px;
}
.corp-perm-item {
    display: flex; align-items: flex-start; gap: 8px;
    padding: 7px 10px; border-radius: 6px; cursor: pointer;
    border: 1px solid transparent;
}
.corp-perm-item:hover { background: var(--corp-brand-bg); border-color: #a7f3d0; }
.corp-perm-item input[type="checkbox"] {
    accent-color: var(--corp-brand);
    width: 14px; height: 14px; margin-top: 2px; cursor: pointer; flex-shrink: 0;
}
.corp-perm-item__name {
    font-size: 12px; color: var(--corp-text); font-weight: 500; line-height: 1.35;
}
.corp-perm-item__slug {
    font-size: 10px; color: var(--corp-muted);
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    margin-top: 1px;
}

.corp-form-actions {
    display: flex; justify-content: flex-end; gap: 10px;
    padding: 14px 0 0; border-top: 1px solid var(--corp-line); margin-top: 16px;
}
.btn-corp-secondary {
    background: #fff; color: var(--corp-text);
    border: 1px solid var(--corp-line);
    padding: 9px 18px; border-radius: 8px;
    font-size: 13px; font-weight: 500;
    text-decoration: none; display: inline-flex; align-items: center; gap: 6px;
}
.btn-corp-secondary:hover { background: #f9fafb; }
</style>

<style>
/* 2026-07-10 (New Changes for UI #4) — dark mode for this page's bespoke components. */
html[data-theme="dark"] .corp-form-card__head { background: #17233a; }
html[data-theme="dark"] .corp-input,
html[data-theme="dark"] .corp-select { background: #1e293b; }
html[data-theme="dark"] .corp-perm-toolbar { background: #17233a; }
html[data-theme="dark"] .corp-perm-toolbar .corp-perm-count { background: #1e293b; }
html[data-theme="dark"] .corp-perm-toolbar .corp-perm-btn:hover { background: #22304a; }
html[data-theme="dark"] .corp-perm-module { background: #1e293b; }
html[data-theme="dark"] .corp-perm-module[open] .corp-perm-module__head { background: #17233a; }
html[data-theme="dark"] .btn-corp-secondary { background: #1e293b; }
html[data-theme="dark"] .btn-corp-secondary:hover { background: #17233a; }
</style>

<div class="corp-page">
    {{-- Header --}}
    <div class="corp-header">
        <div class="corp-header__title">
            <h4>{{ __('Edit Staff Member') }}</h4>
            <p>
                {{ __('Update the profile, change the assigned role, or adjust the permission set.') }}
            </p>
        </div>
        <div class="corp-header__actions">
            <a href="{{ route('instructor.coach-staff.index') }}" class="btn-corp-secondary">
                <i class="fas fa-arrow-left"></i> {{ __('Back to Staff') }}
            </a>
        </div>
    </div>

    <form action="{{ route('instructor.coach-staff.update', $user->id) }}" method="POST">
        @csrf
        @method('PUT')

        {{-- SECTION 1 — Personal --}}
        <div class="corp-form-card">
            <div class="corp-form-card__head">
                <h6 class="corp-form-card__title"><i class="fas fa-user me-1" style="color:var(--corp-brand);"></i> {{ __('Personal Information') }}</h6>
            </div>
            <div class="corp-form-card__body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="corp-field">
                            <label class="corp-field__label">{{ __('Full Name') }}<span class="req">*</span></label>
                            <input type="text" name="name" class="corp-input" value="{{ old('name', $user->name) }}" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="corp-field">
                            <label class="corp-field__label">{{ __('Email Address') }}<span class="req">*</span></label>
                            <input type="email" name="email" class="corp-input" value="{{ old('email', $user->email) }}" required>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- SECTION 2 — Account --}}
        <div class="corp-form-card">
            <div class="corp-form-card__head">
                <h6 class="corp-form-card__title"><i class="fas fa-lock me-1" style="color:var(--corp-brand);"></i> {{ __('Account Access') }}</h6>
                <p class="corp-form-card__sub">{{ __('Leave password blank to keep the current one.') }}</p>
            </div>
            <div class="corp-form-card__body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="corp-field">
                            <label class="corp-field__label">{{ __('New Password') }}</label>
                            <input type="password" name="password" class="corp-input" placeholder="{{ __('Leave blank to keep current') }}">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="corp-field">
                            <label class="corp-field__label">{{ __('Status') }}<span class="req">*</span></label>
                            <select name="status" class="corp-select">
                                <option value="active" @selected($user->status == 'active')>{{ __('Active — can sign in') }}</option>
                                <option value="inactive" @selected($user->status == 'inactive')>{{ __('Inactive — no access') }}</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- SECTION 3 — Role + Permissions --}}
        <div class="corp-form-card">
            <div class="corp-form-card__head">
                <h6 class="corp-form-card__title"><i class="fas fa-shield-alt me-1" style="color:var(--corp-brand);"></i> {{ __('Role & Permissions') }}</h6>
                <p class="corp-form-card__sub">{{ __('Switching role loads the default permissions for that role; overrides per teammate are kept.') }}</p>
            </div>
            <div class="corp-form-card__body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="corp-field">
                            <label class="corp-field__label">{{ __('Role') }}</label>
                            <select class="corp-select role" name="role_id" id="role">
                                <option value="">{{ __('— Select a role —') }}</option>
                                @foreach ($roles as $role)
                                    <option data-role-id="{{ $role->id }}" value="{{ $role->id }}"
                                            {{ $user->role_id == $role->id ? 'selected' : '' }}>
                                        {{ $role->role_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Grouped permission picker — pre-populated from $rolePermissions
                 with $userPermissions pre-checked. --}}
            <div id="permissions_box" style="border-top:1px solid var(--corp-line-soft);">
                {{-- 2026-05-20 — Resource x Action matrix picker.
                     Pre-checked from $checkedIds (the staff member's
                     current grants). AJAX swap on role change. --}}
                <div id="staff-edit-picker-host">
                    @include('frontend.instructor-dashboard.settings.partials._permission-picker', [
                        'pickerPermissions'  => $rolePermissions ?? collect(),
                        'pickerField'        => 'permissions[]',
                        'pickerChecked'      => $checkedIds,   // the staff's CURRENT effective set
                        'pickerRoleDefaults' => collect($rolePermissions ?? [])->pluck('id')->map(fn ($v) => (int) $v)->all(),
                        'pickerId'           => 'staff-perm-picker-edit',
                    ])
                </div>
            </div>
        </div>

        <div class="corp-form-actions">
            <a href="{{ route('instructor.coach-staff.index') }}" class="btn-corp-secondary">{{ __('Cancel') }}</a>
            <button type="submit" class="btn-corp-primary">
                <i class="fas fa-check"></i> {{ __('Save Changes') }}
            </button>
        </div>
    </form>
</div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script>
(function () {
    // 2026-05-20 — matrix picker. The picker partial owns its own
    // toolbar / search / counts / event handlers. We only handle role
    // changes by fetching a fresh server-rendered picker and replacing
    // the host's innerHTML (and re-executing the inline scripts so the
    // new picker's behaviour wires up).
    function executeScriptsIn(root) {
        root.querySelectorAll('script').forEach(function (oldScript) {
            var s = document.createElement('script');
            for (var i = 0; i < oldScript.attributes.length; i++) {
                var a = oldScript.attributes[i];
                s.setAttribute(a.name, a.value);
            }
            s.textContent = oldScript.textContent;
            oldScript.parentNode.replaceChild(s, oldScript);
        });
    }

    $('#role').on('change', function () {
        var roleId = $(this).find(':selected').data('role-id');
        if (!roleId) return;
        var host = document.getElementById('staff-edit-picker-host');
        host.innerHTML = '<div style="padding:48px 24px;text-align:center;color:#6b7280;">' +
            '<i class="fas fa-spinner fa-spin" style="font-size:24px;color:#d1d5db;"></i>' +
            '<div style="margin-top:8px;">{{ __('Loading matrix…') }}</div></div>';
        $.ajax({
            url: "{{ route('instructor.coach-staff.edit', $user->id) }}",
            method: 'GET', dataType: 'json',
            data: { role_id: roleId },
            success: function (resp) {
                if (!resp || !resp.html) {
                    host.innerHTML = '<div style="padding:48px 24px;text-align:center;color:#6b7280;">' +
                        '{{ __('This role has no permissions yet.') }}</div>';
                    return;
                }
                host.innerHTML = resp.html;
                executeScriptsIn(host);
            },
            error: function () {
                host.innerHTML = '<div style="padding:48px 24px;text-align:center;color:#b91c1c;">' +
                    '{{ __('Failed to load permissions. Please try again.') }}</div>';
            }
        });
    });
})();
</script>
@endsection
