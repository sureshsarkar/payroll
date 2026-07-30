@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
@include('frontend.instructor-dashboard.settings.partials._corporate')

<div class="corp-page" id="tba-edit-page">

    {{-- Header ────────────────────────────────────────────────── --}}
    <div class="corp-header">
        <div class="corp-header__title">
            <h4>
                {{ __('Edit Assignment') }} —
                <span style="color:var(--corp-brand);">{{ $assignment->teacher?->name ?? '—' }}</span>
            </h4>
            <p>
                {{ __('Toggle the status to revoke or restore. To change which batches the teacher manages, remove this assignment and create new ones.') }}
            </p>
        </div>
        <div class="corp-header__actions">
            <a href="{{ route('instructor.teacher-batches.index') }}" class="btn-corp-secondary">
                <i class="fas fa-arrow-left"></i> {{ __('Back to list') }}
            </a>
        </div>
    </div>

    @if ($errors->any())
        <div class="corp-form-card" style="border-color:#fecaca; background:#fef2f2;">
            <div class="corp-form-card__body" style="padding:14px 18px;">
                <div style="display:flex; gap:10px; align-items:flex-start; color:#b91c1c; font-size:13px;">
                    <i class="fas fa-exclamation-circle" style="font-size:16px; margin-top:2px;"></i>
                    <div>
                        <strong>{{ __('Please correct the following:') }}</strong>
                        <ul style="margin:6px 0 0; padding-left:18px;">
                            @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('instructor.teacher-batches.update', $assignment->id) }}" id="tbaEditForm">
        @csrf
        @method('PUT')

        <div class="corp-2col">

            {{-- ───────── Main column ───────── --}}
            <div>

                {{-- Card 1 · Identity (read-only) ─────────────── --}}
                <div class="corp-form-card">
                    <div class="corp-form-card__head">
                        <h6 class="corp-form-card__title">
                            <i class="fas fa-id-card" style="color:var(--corp-brand);"></i>
                            {{ __('Assignment Identity') }}
                        </h6>
                        <p class="corp-form-card__sub">{{ __('These fields are fixed for the life of the assignment.') }}</p>
                    </div>
                    <div class="corp-form-card__body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div style="display:flex; align-items:center; gap:12px;">
                                    <div class="corp-avatar">
                                        {{ strtoupper(substr($assignment->teacher?->name ?? '?', 0, 1)) }}
                                    </div>
                                    <div>
                                        <div style="font-size:11px; color:var(--corp-muted); text-transform:uppercase; letter-spacing:.4px;">{{ __('Teacher') }}</div>
                                        <div style="font-weight:600; color:var(--corp-text);">{{ $assignment->teacher?->name ?? '—' }}</div>
                                        <div style="font-size:12px; color:var(--corp-muted);">{{ $assignment->teacher?->email }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div>
                                    <div style="font-size:11px; color:var(--corp-muted); text-transform:uppercase; letter-spacing:.4px;">{{ __('Course') }}</div>
                                    <div style="font-weight:600; color:var(--corp-text); margin-top:2px;">{{ $assignment->course?->title ?? '—' }}</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div>
                                    <div style="font-size:11px; color:var(--corp-muted); text-transform:uppercase; letter-spacing:.4px;">{{ __('Batch') }}</div>
                                    <div style="font-weight:600; color:var(--corp-text); margin-top:2px;">{{ $assignment->batch?->title ?? '—' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Card 2 · Editable settings ──────────────── --}}
                <div class="corp-form-card">
                    <div class="corp-form-card__head">
                        <h6 class="corp-form-card__title">
                            <i class="fas fa-sliders" style="color:var(--corp-brand);"></i>
                            {{ __('Access Settings') }}
                        </h6>
                        <p class="corp-form-card__sub">
                            {{ __('Switching status to Removed blocks the teacher in under 60 seconds — that\'s the cache TTL on the gate.') }}
                        </p>
                    </div>
                    <div class="corp-form-card__body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="corp-field">
                                    <label class="corp-field__label">{{ __('Permission') }}</label>
                                    <select name="permission_type" class="corp-select">
                                        <option value="manage" @selected($assignment->permission_type === 'manage')>
                                            {{ __('Manage — view, create, start live classes') }}
                                        </option>
                                    </select>
                                    <div class="corp-field__hint">{{ __('More granular permissions (view-only, host-only) coming soon.') }}</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="corp-field">
                                    <label class="corp-field__label">{{ __('Status') }}<span class="req">*</span></label>
                                    <select name="status" id="statusSel" class="corp-select" required>
                                        <option value="active"   @selected($assignment->status === 'active')>{{ __('Active — teacher has access') }}</option>
                                        <option value="inactive" @selected($assignment->status === 'inactive')>{{ __('Removed — teacher blocked') }}</option>
                                    </select>
                                    <div class="corp-field__hint" id="statusHint" style="display:none; color:#b91c1c;">
                                        <i class="fas fa-triangle-exclamation"></i>
                                        {{ __('The teacher will lose access to this batch immediately on save.') }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Sticky action bar ──────────────────────────── --}}
                <div class="corp-sticky-bar">
                    <div class="corp-sticky-bar__summary">
                        <i class="fas fa-info-circle" style="color:var(--corp-brand);"></i>
                        <span>
                            <strong>{{ $assignment->teacher?->name }}</strong>
                            <span class="text-muted">·</span>
                            <span>{{ $assignment->batch?->title }}</span>
                            <span class="text-muted">·</span>
                            <span id="stickyStatusLabel">
                                @if ($assignment->status === 'active')
                                    <span class="corp-pill corp-pill--success" style="padding:1px 8px;">{{ __('Active') }}</span>
                                @else
                                    <span class="corp-pill corp-pill--danger" style="padding:1px 8px;">{{ __('Removed') }}</span>
                                @endif
                            </span>
                        </span>
                    </div>
                    <div class="corp-sticky-bar__actions">
                        <a href="{{ route('instructor.teacher-batches.index') }}" class="btn-corp-secondary">{{ __('Cancel') }}</a>
                        <button type="submit" class="btn-corp-primary">
                            <i class="fas fa-check"></i> {{ __('Save Changes') }}
                        </button>
                    </div>
                </div>
            </div>

            {{-- ───────── Side context panel ───────── --}}
            <aside>
                <div class="corp-context">
                    <div class="corp-context__head"><i class="fas fa-info-circle"></i> {{ __('Assignment Metadata') }}</div>
                    <div class="corp-context__body">
                        <div class="corp-meta-row">
                            <span class="corp-meta-row__k">{{ __('Assigned') }}</span>
                            <span class="corp-meta-row__v">{{ optional($assignment->assigned_at ?: $assignment->created_at)->format('M j, Y g:i A') }}</span>
                        </div>
                        <div class="corp-meta-row">
                            <span class="corp-meta-row__k">{{ __('Last updated') }}</span>
                            <span class="corp-meta-row__v">{{ optional($assignment->updated_at)->diffForHumans() ?? '—' }}</span>
                        </div>
                        <div class="corp-meta-row">
                            <span class="corp-meta-row__k">{{ __('Permission type') }}</span>
                            <span class="corp-meta-row__v"><code>{{ $assignment->permission_type }}</code></span>
                        </div>
                        <div class="corp-meta-row">
                            <span class="corp-meta-row__k">{{ __('Current status') }}</span>
                            <span class="corp-meta-row__v">
                                @if ($assignment->status === 'active')
                                    <span class="corp-pill corp-pill--success">{{ __('Active') }}</span>
                                @else
                                    <span class="corp-pill corp-pill--danger">{{ __('Removed') }}</span>
                                @endif
                            </span>
                        </div>
                    </div>
                </div>

                <div class="corp-context">
                    <div class="corp-context__head"><i class="fas fa-shield-alt"></i> {{ __('What changes on save') }}</div>
                    <div class="corp-context__body">
                        <ul>
                            <li>{{ __('Status change takes effect immediately (within 60s cache TTL).') }}</li>
                            <li>{{ __('Switching to Removed revokes: live-class list visibility, create / edit / start / attendance.') }}</li>
                            <li>{{ __('Re-activating restores all of the above with the same row id (full audit trail preserved).') }}</li>
                        </ul>
                        <p style="margin:0; font-size:11px; color:var(--corp-muted);">
                            <i class="fas fa-info-circle"></i>
                            {{ __('To change the assigned batch entirely, remove this assignment and create a new one.') }}
                        </p>
                    </div>
                </div>
            </aside>
        </div>
    </form>
</div>

<script>
(function () {
    var sel = document.getElementById('statusSel');
    var hint = document.getElementById('statusHint');
    if (!sel || !hint) return;
    var initial = sel.value;
    sel.addEventListener('change', function () {
        // Only warn when the coach is FLIPPING from active to inactive.
        // A no-op change shouldn't trigger the scary message.
        hint.style.display = (initial === 'active' && sel.value === 'inactive') ? 'block' : 'none';
    });
})();
</script>
@endsection
