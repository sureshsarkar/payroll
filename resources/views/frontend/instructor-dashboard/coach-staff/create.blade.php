@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
@include('frontend.instructor-dashboard.settings.partials._corporate')

<div class="corp-page">

    {{-- Header ────────────────────────────────────────────────── --}}
    <div class="corp-header">
        <div class="corp-header__title">
            <h4>{{ __('Add Staff Member') }}</h4>
            <p>{{ __('Three quick steps — identity, account access, role-based permissions.') }}</p>
        </div>
        <div class="corp-header__actions">
            <a href="{{ route('instructor.coach-staff.index') }}" class="btn-corp-secondary">
                <i class="fas fa-arrow-left"></i> {{ __('Back to Staff') }}
            </a>
        </div>
    </div>

    {{-- Stepper ───────────────────────────────────────────────── --}}
    <div class="corp-stepper" id="staff-stepper">
        <div class="corp-stepper__item is-active" data-step="1">
            <span class="corp-stepper__num">1</span>
            <span>{{ __('Identity') }}</span>
        </div>
        <span class="corp-stepper__connector"></span>
        <div class="corp-stepper__item" data-step="2">
            <span class="corp-stepper__num">2</span>
            <span>{{ __('Account Access') }}</span>
        </div>
        <span class="corp-stepper__connector"></span>
        <div class="corp-stepper__item" data-step="3">
            <span class="corp-stepper__num">3</span>
            <span>{{ __('Role & Permissions') }}</span>
        </div>
    </div>

    <div class="corp-2col">
        <div>
            <form action="{{ route('instructor.coach-staff.store') }}" method="POST" id="staff-create-form">
                @csrf

                {{-- STEP 1 — Identity ─────────────────────────── --}}
                <div class="corp-step is-active" data-step="1">
                    <div class="corp-form-card">
                        <div class="corp-form-card__head">
                            <h6 class="corp-form-card__title">
                                <i class="fas fa-user-circle" style="color:var(--corp-brand);"></i>
                                {{ __('Identity') }}
                            </h6>
                            <p class="corp-form-card__sub">{{ __('Who is this teammate?') }}</p>
                        </div>
                        <div class="corp-form-card__body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="corp-field">
                                        <label class="corp-field__label">{{ __('Full Name') }}<span class="req">*</span></label>
                                        <input type="text" name="name" id="staff-name"
                                               class="corp-input @error('name') is-invalid @enderror"
                                               value="{{ old('name') }}" required
                                               placeholder="e.g. Priya Sharma">
                                        @error('name')<div class="corp-field__hint" style="color:#b91c1c;">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="corp-field">
                                        <label class="corp-field__label">{{ __('Email Address') }}<span class="req">*</span></label>
                                        <input type="email" name="email" id="staff-email"
                                               class="corp-input @error('email') is-invalid @enderror"
                                               value="{{ old('email') }}" required
                                               placeholder="priya@example.com">
                                        <div class="corp-field__hint">{{ __('Sign-in identity. Must be unique.') }}</div>
                                        @error('email')<div class="corp-field__hint" style="color:#b91c1c;">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- STEP 2 — Account Access ───────────────────── --}}
                <div class="corp-step" data-step="2">
                    <div class="corp-form-card">
                        <div class="corp-form-card__head">
                            <h6 class="corp-form-card__title">
                                <i class="fas fa-lock" style="color:var(--corp-brand);"></i>
                                {{ __('Account Access') }}
                            </h6>
                            <p class="corp-form-card__sub">{{ __('Initial password + account status.') }}</p>
                        </div>
                        <div class="corp-form-card__body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="corp-field">
                                        <label class="corp-field__label">{{ __('Password') }}<span class="req">*</span></label>
                                        <input type="password" name="password" id="staff-password"
                                               class="corp-input @error('password') is-invalid @enderror"
                                               required minlength="6"
                                               placeholder="{{ __('Min 6 characters') }}">
                                        <div class="corp-field__hint">{{ __('Share securely. The teammate can change it after first sign-in.') }}</div>
                                        @error('password')<div class="corp-field__hint" style="color:#b91c1c;">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="corp-field">
                                        <label class="corp-field__label">{{ __('Account Status') }}<span class="req">*</span></label>
                                        <select name="status" id="staff-status" class="corp-select">
                                            <option value="active" @selected(old('status', 'active') == 'active')>{{ __('Active — can sign in') }}</option>
                                            <option value="inactive" @selected(old('status') == 'inactive')>{{ __('Inactive — no access') }}</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- STEP 3 — Role & Permissions ──────────────── --}}
                <div class="corp-step" data-step="3">
                    <div class="corp-form-card">
                        <div class="corp-form-card__head">
                            <h6 class="corp-form-card__title">
                                <i class="fas fa-shield-alt" style="color:var(--corp-brand);"></i>
                                {{ __('Role & Permissions') }}
                            </h6>
                            <p class="corp-form-card__sub">{{ __('Pick a role to load its baseline. Tune per-teammate below.') }}</p>
                        </div>
                        <div class="corp-form-card__body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="corp-field">
                                        <label class="corp-field__label">{{ __('Role') }}<span class="req">*</span></label>
                                        <select class="corp-select role" name="role_id" id="role" required>
                                            <option value="">{{ __('— Select a role —') }}</option>
                                            @foreach ($roles as $role)
                                                <option data-role-id="{{ $role->id }}"
                                                        data-role-slug="{{ $role->slug ?? '' }}"
                                                        data-role-name="{{ $role->role_name }}"
                                                        value="{{ $role->id }}">{{ $role->role_name }}</option>
                                            @endforeach
                                        </select>
                                        <div class="corp-field__hint">
                                            {{ __('Need a different role?') }}
                                            <a href="{{ route('instructor.coach-staff-role.create') }}" target="_blank"
                                               style="color:var(--corp-brand); font-weight:600;">
                                                {{ __('Create one') }} <i class="fas fa-external-link-alt" style="font-size:9px;"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Permission picker container — server-rendered matrix
                             gets injected here when a role is selected. The
                             matrix partial brings its own toolbar, effective
                             chips, diff pills, search and styles. --}}
                        <div id="permissions_box" style="display:none; border-top:1px solid var(--corp-line-soft);">
                            <div class="corp-effective" style="border-top:none;">
                                <span class="corp-effective__label">{{ __('Customise:') }}</span>
                                <span style="font-size:12px; color:var(--corp-muted);">
                                    {{ __('Defaults come from the role. Tweak any cell below — click a row name or column header to bulk-toggle.') }}
                                </span>
                            </div>
                            <div id="staff-picker-host"></div>
                        </div>
                    </div>
                </div>

                {{-- Sticky action bar — visible on every step ─── --}}
                <div class="corp-sticky-bar">
                    <div class="corp-sticky-bar__summary">
                        <i class="fas fa-info-circle" style="color:var(--corp-brand);"></i>
                        <span>
                            <strong id="summary-name">{{ __('New staff') }}</strong>
                            <span class="text-muted">·</span>
                            <span id="summary-role">{{ __('no role yet') }}</span>
                            <span class="text-muted">·</span>
                            <span id="summary-perms">0 {{ __('permissions') }}</span>
                        </span>
                    </div>
                    <div class="corp-sticky-bar__actions">
                        <button type="button" class="btn-corp-ghost" id="btn-prev" style="display:none;">
                            <i class="fas fa-arrow-left"></i> {{ __('Previous') }}
                        </button>
                        <a href="{{ route('instructor.coach-staff.index') }}" class="btn-corp-secondary">{{ __('Cancel') }}</a>
                        <button type="button" class="btn-corp-primary" id="btn-next">
                            {{ __('Next') }} <i class="fas fa-arrow-right"></i>
                        </button>
                        <button type="submit" class="btn-corp-primary" id="btn-save" style="display:none;">
                            <i class="fas fa-check"></i> {{ __('Create Staff') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>

        {{-- Side context panel ─────────────────────────────────── --}}
        <aside>
            <div class="corp-context">
                <div class="corp-context__head"><i class="fas fa-lightbulb"></i> {{ __('Tips') }}</div>
                <div class="corp-context__body">
                    <ul>
                        <li>{{ __('Email must be unique platform-wide. Use a work address so password resets reach the right inbox.') }}</li>
                        <li>{{ __('Picking a role auto-loads its permissions; you can then add/remove specific ones for this person.') }}</li>
                        <li>{{ __('Hold a permission you forgot? Search by name or slug — typing') }} <code>course</code> {{ __('filters to Course module items only.') }}</li>
                        <li>{{ __('Press') }} <code>/</code> {{ __('any time to jump to the search field.') }}</li>
                    </ul>
                </div>
            </div>
            <div class="corp-context">
                <div class="corp-context__head"><i class="fas fa-shield-alt"></i> {{ __('Security') }}</div>
                <div class="corp-context__body" style="font-size:11px;">
                    <div class="corp-meta-row">
                        <span class="corp-meta-row__k">{{ __('Sign-in via') }}</span>
                        <span class="corp-meta-row__v">{{ __('email + password') }}</span>
                    </div>
                    <div class="corp-meta-row">
                        <span class="corp-meta-row__k">{{ __('Audit trail') }}</span>
                        <span class="corp-meta-row__v">{{ __('every change logged') }}</span>
                    </div>
                    <div class="corp-meta-row">
                        <span class="corp-meta-row__k">{{ __('Revoke access') }}</span>
                        <span class="corp-meta-row__v">{{ __('flip status to inactive') }}</span>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script>
(function () {
    var TOTAL_STEPS = 3;
    var step = 1;
    var stepper = document.getElementById('staff-stepper');
    var steps   = document.querySelectorAll('.corp-step');
    var btnPrev = document.getElementById('btn-prev');
    var btnNext = document.getElementById('btn-next');
    var btnSave = document.getElementById('btn-save');

    function showStep(n) {
        step = n;
        steps.forEach(function (el) {
            el.classList.toggle('is-active', Number(el.dataset.step) === n);
        });
        stepper.querySelectorAll('.corp-stepper__item').forEach(function (el) {
            var s = Number(el.dataset.step);
            el.classList.toggle('is-active', s === n);
            el.classList.toggle('is-done', s < n);
        });
        btnPrev.style.display = n > 1 ? '' : 'none';
        btnNext.style.display = n < TOTAL_STEPS ? '' : 'none';
        btnSave.style.display = n === TOTAL_STEPS ? '' : 'none';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // Validate ONLY the fields inside the current step before advancing.
    function validateCurrentStep() {
        var current = document.querySelector('.corp-step.is-active');
        var inputs = current.querySelectorAll('input[required], select[required]');
        for (var i = 0; i < inputs.length; i++) {
            if (!inputs[i].checkValidity()) {
                inputs[i].reportValidity();
                return false;
            }
        }
        return true;
    }

    btnNext.addEventListener('click', function () {
        if (!validateCurrentStep()) return;
        if (step < TOTAL_STEPS) showStep(step + 1);
    });
    btnPrev.addEventListener('click', function () { if (step > 1) showStep(step - 1); });

    // Click a stepper item to jump (only backwards, or to completed steps).
    stepper.querySelectorAll('.corp-stepper__item').forEach(function (el) {
        el.style.cursor = 'pointer';
        el.addEventListener('click', function () {
            var target = Number(el.dataset.step);
            if (target <= step) { showStep(target); return; }
            // Forward jumps require validation of every step up to target-1.
            for (var s = step; s < target; s++) {
                if (!validateCurrentStep()) return;
                showStep(s + 1);
            }
        });
    });

    /* ── live sticky-bar summary ────────────────────────────── */
    var sName = document.getElementById('summary-name');
    var sRole = document.getElementById('summary-role');
    var sPerms = document.getElementById('summary-perms');

    document.getElementById('staff-name').addEventListener('input', function () {
        sName.textContent = this.value.trim() || '{{ __('New staff') }}';
    });

    /* ── Permission picker — server-rendered matrix injected here.
       When the role changes we fetch fully-rendered HTML (the
       _permission-picker partial) and drop it into #staff-picker-host.
       The partial's inline <script> is then re-created so its
       click handlers + counters wire up. ─────────────────────── */
    function executeScriptsIn(root) {
        root.querySelectorAll('script').forEach(function (oldScript) {
            var s = document.createElement('script');
            // Copy attributes so external src= keeps working.
            for (var i = 0; i < oldScript.attributes.length; i++) {
                var a = oldScript.attributes[i];
                s.setAttribute(a.name, a.value);
            }
            s.textContent = oldScript.textContent;
            oldScript.parentNode.replaceChild(s, oldScript);
        });
    }

    function syncStickyFromPicker(detail) {
        var checked = (detail && detail.checked) || 0;
        sPerms.textContent = checked + ' {{ __('permissions') }}';
    }

    $('#role').on('change', function () {
        var opt = $(this).find(':selected');
        var roleId = opt.data('role-id');
        sRole.textContent = opt.text() || '{{ __('no role yet') }}';
        var host = document.getElementById('staff-picker-host');
        if (!roleId) {
            $('#permissions_box').hide();
            host.innerHTML = '';
            sPerms.textContent = '0 {{ __('permissions') }}';
            return;
        }
        $('#permissions_box').show();
        host.innerHTML = '<div style="padding:48px 24px;text-align:center;color:#6b7280;">' +
            '<i class="fas fa-spinner fa-spin" style="font-size:24px;color:#d1d5db;"></i>' +
            '<div style="margin-top:8px;">{{ __('Loading permission matrix…') }}</div></div>';
        $.ajax({
            url: "{{ route('instructor.coach-staff.create') }}",
            method: 'GET', data: { role_id: roleId },
            dataType: 'json',
            success: function (resp) {
                if (!resp || !resp.html) {
                    host.innerHTML = '<div style="padding:48px 24px;text-align:center;color:#6b7280;">' +
                        '{{ __('This role has no permissions yet.') }}</div>';
                    sPerms.textContent = '0 {{ __('permissions') }}';
                    return;
                }
                host.innerHTML = resp.html;
                executeScriptsIn(host);
                // Listen to the partial's pp:change for the sticky bar.
                var picker = host.querySelector('[id^="staff-perm-picker"], [id="staff-perm-picker"]');
                if (picker) {
                    picker.addEventListener('pp:change', function (e) {
                        syncStickyFromPicker(e.detail);
                    });
                    // Initial sync — partial fires pp:change on its own init.
                }
            },
            error: function () {
                host.innerHTML = '<div style="padding:48px 24px;text-align:center;color:#b91c1c;">' +
                    '{{ __('Failed to load permissions. Please try again.') }}</div>';
            }
        });
    });

    showStep(1);
})();
</script>
@endsection
