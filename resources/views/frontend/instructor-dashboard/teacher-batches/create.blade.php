@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
@include('frontend.instructor-dashboard.settings.partials._corporate')

<style>
    /* Scoped extensions to the corporate design system —
       the batch-picker grid and chip strip have no built-in
       primitive yet, so we add them here. Everything else
       reuses corp-form-card / corp-2col / corp-sticky-bar
       / corp-context. */

    .tba-batch-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 10px;
        max-height: 380px;
        overflow-y: auto;
        padding: 2px;
    }
    .tba-batch-card {
        position: relative;
        border: 1px solid var(--corp-line);
        border-radius: 9px;
        padding: 12px 14px 12px 40px;
        background: #fff;
        cursor: pointer;
        transition: border-color .14s, background .14s, box-shadow .14s;
    }
    .tba-batch-card:hover {
        border-color: var(--corp-brand);
        background: var(--corp-brand-bg);
    }
    .tba-batch-card:has(input:checked) {
        border-color: var(--corp-brand);
        background: var(--corp-brand-bg);
        box-shadow: 0 0 0 2px rgba(16, 185, 129, .14);
    }
    .tba-batch-card input[type=checkbox] {
        position: absolute;
        top: 14px;
        left: 14px;
        width: 16px;
        height: 16px;
        cursor: pointer;
        accent-color: var(--corp-brand);
    }
    .tba-batch-card__title {
        font-weight: 600;
        font-size: 13px;
        color: var(--corp-text);
        margin-bottom: 3px;
    }
    .tba-batch-card__meta {
        font-size: 11px;
        color: var(--corp-muted);
    }
    .tba-batch-card__pill {
        position: absolute;
        top: 10px;
        right: 10px;
        font-size: 10px;
        font-weight: 600;
        padding: 2px 7px;
        border-radius: 999px;
    }
    .tba-batch-card__pill--active   { background: #ecfdf5; color: #047857; }
    .tba-batch-card__pill--inactive { background: #f3f4f6; color: #6b7280; }

    .tba-bulkbar {
        display: flex;
        gap: 8px;
        align-items: center;
        padding: 12px 18px;
        border-bottom: 1px solid var(--corp-line-soft);
        background: #fafbfc;
    }
    .tba-bulk-btn {
        background: #fff;
        border: 1px solid var(--corp-line);
        border-radius: 6px;
        padding: 5px 12px;
        font-size: 12px;
        color: var(--corp-muted);
        cursor: pointer;
    }
    .tba-bulk-btn:hover {
        border-color: var(--corp-brand);
        color: var(--corp-brand);
    }
    .tba-bulkbar .tba-search {
        margin-left: auto;
        height: 32px;
        padding: 4px 10px;
        font-size: 12px;
        border: 1px solid var(--corp-line);
        border-radius: 6px;
        min-width: 200px;
    }
    .tba-bulkbar .tba-count {
        color: var(--corp-muted);
        font-size: 12px;
        font-weight: 600;
    }

    .tba-placeholder {
        text-align: center;
        padding: 36px 24px;
        color: var(--corp-muted);
        background: #fafbfc;
        border: 1px dashed var(--corp-line);
        border-radius: 9px;
        margin: 18px;
    }
    .tba-placeholder i { font-size: 22px; display: block; margin-bottom: 8px; color: var(--corp-subtle); }

    /* Live "you selected these batches" chip strip — built on top of
       corp-effective so it inherits the layout, just custom-renders
       chip contents per-batch with a remove (×) action. */
    .tba-chosen-chip__close {
        margin-left: 4px;
        cursor: pointer;
        opacity: .6;
        font-size: 12px;
    }
    .tba-chosen-chip__close:hover { opacity: 1; color: #b91c1c; }

    /* 2026-07-10 (New Changes for UI #4) — dark mode for this page's bespoke components. */
    html[data-theme="dark"] .tba-batch-card { background: #1e293b; }
    html[data-theme="dark"] .tba-batch-card__pill--inactive { background: #22304a; color: #94a3b8; }
    html[data-theme="dark"] .tba-bulkbar { background: #17233a; }
    html[data-theme="dark"] .tba-bulk-btn { background: #1e293b; }
    html[data-theme="dark"] .tba-bulkbar .tba-search { background: #1e293b; color: #e2e8f0; }
    html[data-theme="dark"] .tba-placeholder { background: #17233a; }
</style>

<div class="corp-page">

    {{-- Header ────────────────────────────────────────────────── --}}
    <div class="corp-header">
        <div class="corp-header__title">
            <h4>{{ __('Assign Batches to Teacher') }}</h4>
            <p>{{ __('Pick one of your teachers, choose a course, and grant access to the batches they should manage. The teacher only sees what you grant here.') }}</p>
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

    <form method="POST" action="{{ route('instructor.teacher-batches.store') }}" id="tbaForm">
        @csrf

        <div class="corp-2col">

            {{-- ───────── Main column ───────── --}}
            <div>

                {{-- Card 1 · Teacher + Course ───────────────────── --}}
                <div class="corp-form-card">
                    <div class="corp-form-card__head">
                        <h6 class="corp-form-card__title">
                            <i class="fas fa-user-tag" style="color:var(--corp-brand);"></i>
                            {{ __('Teacher & Course') }}
                        </h6>
                        <p class="corp-form-card__sub">{{ __('Both required. The batch list below loads after a course is selected.') }}</p>
                    </div>
                    <div class="corp-form-card__body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="corp-field">
                                    <label class="corp-field__label">{{ __('Teacher') }}<span class="req">*</span></label>
                                    <select name="teacher_id" id="teacher_id" class="corp-select" required>
                                        <option value="">{{ __('Select a teacher…') }}</option>
                                        @foreach ($teachers as $t)
                                            <option value="{{ $t->id }}" @selected(old('teacher_id') == $t->id)>
                                                {{ $t->name }} — {{ $t->email }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @if ($teachers->isEmpty())
                                        <div class="corp-field__hint" style="color:#b91c1c;">
                                            <i class="fas fa-triangle-exclamation"></i>
                                            {{ __('You have no staff yet. Add one in Settings → Staff.') }}
                                        </div>
                                    @else
                                        <div class="corp-field__hint">{{ __('Only your own staff appears here.') }}</div>
                                    @endif
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="corp-field">
                                    <label class="corp-field__label">{{ __('Course') }}<span class="req">*</span></label>
                                    <select name="course_id" id="course_id" class="corp-select" required>
                                        <option value="">{{ __('Select a course…') }}</option>
                                        @foreach ($courses as $c)
                                            <option value="{{ $c->id }}" @selected(old('course_id') == $c->id)>{{ $c->title }}</option>
                                        @endforeach
                                    </select>
                                    @if ($courses->isEmpty())
                                        <div class="corp-field__hint" style="color:#b91c1c;">
                                            <i class="fas fa-triangle-exclamation"></i>
                                            {{ __('You have no courses yet.') }}
                                        </div>
                                    @else
                                        <div class="corp-field__hint">{{ __('Only your own courses appears here.') }}</div>
                                    @endif
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="corp-field">
                                    <label class="corp-field__label">{{ __('Permission') }}</label>
                                    <select name="permission_type" class="corp-select">
                                        <option value="manage" @selected(old('permission_type', 'manage') === 'manage')>
                                            {{ __('Manage — view, create, start live classes') }}
                                        </option>
                                    </select>
                                    <div class="corp-field__hint">{{ __('More granular permissions (view-only, host-only) coming soon.') }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Card 2 · Batches ────────────────────────────── --}}
                <div class="corp-form-card">
                    <div class="corp-form-card__head">
                        <h6 class="corp-form-card__title">
                            <i class="fas fa-layer-group" style="color:var(--corp-brand);"></i>
                            {{ __('Batches') }} <span class="req">*</span>
                        </h6>
                        <p class="corp-form-card__sub">{{ __('Tick every batch the teacher should be able to manage.') }}</p>
                    </div>

                    <div class="tba-bulkbar" id="bulkBar" style="display:none;">
                        <button type="button" class="tba-bulk-btn" id="selectAllBatches">
                            <i class="fas fa-check-double"></i> {{ __('Select all') }}
                        </button>
                        <button type="button" class="tba-bulk-btn" id="clearAllBatches">
                            <i class="fas fa-eraser"></i> {{ __('Clear') }}
                        </button>
                        <input type="search" class="tba-search" id="batchSearch" placeholder="{{ __('Filter batches…') }}">
                        <span class="tba-count" id="selectedCount">0 {{ __('selected') }}</span>
                    </div>

                    <div class="corp-form-card__body" style="padding-top:14px;" id="batchHost">
                        <div class="tba-placeholder">
                            <i class="fas fa-arrow-up"></i>
                            {{ __('Choose a course above to load available batches.') }}
                        </div>
                    </div>

                    {{-- Live preview chip strip — uses corp-effective primitive. --}}
                    <div class="corp-effective" id="chosenStrip" style="display:none;">
                        <span class="corp-effective__label"><i class="fas fa-check-circle"></i> {{ __('Selected') }}</span>
                        <div id="chosenChips" style="display:flex; gap:6px; flex-wrap:wrap;"></div>
                    </div>
                </div>

                {{-- Sticky action bar ──────────────────────────── --}}
                <div class="corp-sticky-bar">
                    <div class="corp-sticky-bar__summary">
                        <i class="fas fa-info-circle" style="color:var(--corp-brand);"></i>
                        <span>
                            <strong id="stickyTeacher">{{ __('No teacher chosen') }}</strong>
                            <span class="text-muted">·</span>
                            <span id="stickyCourse">{{ __('no course') }}</span>
                            <span class="text-muted">·</span>
                            <span id="stickyBatches">0 {{ __('batches') }}</span>
                        </span>
                    </div>
                    <div class="corp-sticky-bar__actions">
                        <a href="{{ route('instructor.teacher-batches.index') }}" class="btn-corp-secondary">{{ __('Cancel') }}</a>
                        <button type="submit" class="btn-corp-primary" id="submitBtn" disabled>
                            <i class="fas fa-check"></i> {{ __('Save Assignments') }}
                        </button>
                    </div>
                </div>
            </div>

            {{-- ───────── Side context panel ───────── --}}
            <aside>
                <div class="corp-context">
                    <div class="corp-context__head"><i class="fas fa-lightbulb"></i> {{ __('Tips') }}</div>
                    <div class="corp-context__body">
                        <ul>
                            <li>{{ __('Pick a teacher first, then a course — the batch list loads on course change.') }}</li>
                            <li>{{ __('A teacher can be assigned to as many batches as you like, across multiple courses.') }}</li>
                            <li>{{ __('Re-assigning a previously removed batch silently re-activates it. No duplicates.') }}</li>
                            <li>{{ __('Removed assignments block access immediately — no cache delay over 60 seconds.') }}</li>
                        </ul>
                    </div>
                </div>

                <div class="corp-context">
                    <div class="corp-context__head"><i class="fas fa-shield-alt"></i> {{ __('What this controls') }}</div>
                    <div class="corp-context__body">
                        <p style="margin:0 0 8px;">{{ __('Without an assignment, a teacher can\'t:') }}</p>
                        <ul style="margin:0 0 8px;">
                            <li>{{ __('See a batch in their live-class list') }}</li>
                            <li>{{ __('Create or edit live classes for it') }}</li>
                            <li>{{ __('Start the Zoom meeting as host') }}</li>
                            <li>{{ __('Mark attendance for its students') }}</li>
                        </ul>
                        <p style="margin:0; font-size:11px; color:var(--corp-muted);">
                            <i class="fas fa-info-circle"></i>
                            {{ __('Students see only live classes for their enrolled course AND assigned batch.') }}
                        </p>
                    </div>
                </div>
            </aside>
        </div>
    </form>
</div>

<script>
(function () {
    const courseEl  = document.getElementById('course_id');
    const teacherEl = document.getElementById('teacher_id');
    const host      = document.getElementById('batchHost');
    const bulkBar   = document.getElementById('bulkBar');
    const countEl   = document.getElementById('selectedCount');
    const submitBtn = document.getElementById('submitBtn');
    const stickyTeacher = document.getElementById('stickyTeacher');
    const stickyCourse  = document.getElementById('stickyCourse');
    const stickyBatches = document.getElementById('stickyBatches');
    const chosenStrip   = document.getElementById('chosenStrip');
    const chosenChips   = document.getElementById('chosenChips');
    const url           = "{{ url('instructor/teacher-batches/batches') }}";

    function placeholder(msg, danger) {
        const color = danger ? '#b91c1c' : '';
        return `<div class="tba-placeholder" style="color:${color};">
            <i class="fas fa-${danger ? 'circle-exclamation' : 'info-circle'}"></i>
            ${msg}
        </div>`;
    }

    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    function refreshState() {
        const checked = host.querySelectorAll('input[name="batch_ids[]"]:checked');
        const n = checked.length;
        countEl.textContent = n + ' {{ __('selected') }}';

        const teacherLabel = teacherEl.selectedOptions[0]?.text?.split('—')[0]?.trim() || '{{ __('No teacher chosen') }}';
        const courseLabel  = courseEl.selectedOptions[0]?.text || '{{ __('no course') }}';
        stickyTeacher.textContent = teacherLabel || '{{ __('No teacher chosen') }}';
        stickyCourse.textContent  = courseLabel || '{{ __('no course') }}';
        stickyBatches.textContent = n + ' {{ __('batches') }}';

        submitBtn.disabled = !(courseEl.value && teacherEl.value && n > 0);

        // Build chip strip — gives the coach a final, scannable preview
        // of what they're about to grant.
        if (n === 0) {
            chosenStrip.style.display = 'none';
            return;
        }
        chosenStrip.style.display = 'flex';
        chosenChips.innerHTML = Array.from(checked).map(cb => {
            const title = cb.dataset.title || ('Batch #' + cb.value);
            return `<span class="corp-effective__chip corp-effective__chip--brand" data-cb-id="${cb.value}">
                ${escapeHtml(title)}
                <span class="tba-chosen-chip__close" title="{{ __('Remove') }}">×</span>
            </span>`;
        }).join('');
        chosenChips.querySelectorAll('.tba-chosen-chip__close').forEach(x => {
            x.addEventListener('click', e => {
                const id = e.currentTarget.parentElement.dataset.cbId;
                const cb = host.querySelector(`input[value="${id}"]`);
                if (cb) { cb.checked = false; refreshState(); }
            });
        });
    }

    function renderBatches(batches) {
        if (!batches || batches.length === 0) {
            host.innerHTML = placeholder('{{ __('No batches found for this course. Create batches first under Course Batches.') }}');
            bulkBar.style.display = 'none';
            refreshState();
            return;
        }
        const html = batches.map(b => {
            const isActive = (b.status || '').toString().toLowerCase() === 'active';
            const fmt = (d) => d ? new Date(d).toLocaleDateString() : '';
            const meta = [fmt(b.start_date), b.end_date ? '→ ' + fmt(b.end_date) : ''].filter(Boolean).join(' ');
            // 2026-07-08 — a batch has at most ONE active teacher; surface who
            // currently owns it so the coach can decide before transferring.
            const curId   = b.current_teacher_id ? String(b.current_teacher_id) : '';
            const curName = b.current_teacher_name || '';
            const ownerTag = curName
                ? `<div class="tba-batch-card__owner" style="font-size:11.5px;color:#b45309;margin-top:4px;"><i class="fas fa-user-check"></i> {{ __('Assigned to') }} ${escapeHtml(curName)}</div>`
                : '';
            return `<label class="tba-batch-card">
                <input type="checkbox" name="batch_ids[]" value="${b.id}" data-title="${escapeHtml(b.title || ('Batch #' + b.id))}"
                    data-current-teacher-id="${curId}" data-current-teacher-name="${escapeHtml(curName)}">
                <div class="tba-batch-card__title">${escapeHtml(b.title || ('Batch #' + b.id))}</div>
                <div class="tba-batch-card__meta">${escapeHtml(meta || '{{ __('no dates') }}')}</div>
                ${ownerTag}
                <span class="tba-batch-card__pill tba-batch-card__pill--${isActive ? 'active' : 'inactive'}">
                    ${isActive ? '{{ __('Active') }}' : '{{ __('Inactive') }}'}
                </span>
            </label>`;
        }).join('');
        host.innerHTML = `<div class="tba-batch-grid">${html}</div>`;
        bulkBar.style.display = 'flex';
        host.querySelectorAll('input[name="batch_ids[]"]').forEach(cb => {
            cb.addEventListener('change', refreshState);
        });
        refreshState();
    }

    courseEl.addEventListener('change', function () {
        const id = this.value;
        if (!id) {
            host.innerHTML = placeholder('{{ __('Choose a course above to load available batches.') }}');
            bulkBar.style.display = 'none';
            refreshState();
            return;
        }
        host.innerHTML = placeholder('{{ __('Loading batches…') }}');
        fetch(url + '/' + id, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(r => r.json())
            .then(j => renderBatches(j.batches || []))
            .catch(() => {
                host.innerHTML = placeholder('{{ __('Could not load batches. Please try again.') }}', true);
            });
    });

    teacherEl.addEventListener('change', refreshState);

    document.getElementById('selectAllBatches').addEventListener('click', function () {
        host.querySelectorAll('input[name="batch_ids[]"]').forEach(cb => {
            // Respect search filter — only toggle visible cards.
            if (cb.closest('.tba-batch-card').style.display !== 'none') cb.checked = true;
        });
        refreshState();
    });
    document.getElementById('clearAllBatches').addEventListener('click', function () {
        host.querySelectorAll('input[name="batch_ids[]"]').forEach(cb => cb.checked = false);
        refreshState();
    });

    // Filter — hide non-matching batch cards. Selections survive.
    document.getElementById('batchSearch').addEventListener('input', function () {
        const q = this.value.trim().toLowerCase();
        host.querySelectorAll('.tba-batch-card').forEach(card => {
            const t = (card.querySelector('.tba-batch-card__title')?.textContent || '').toLowerCase();
            card.style.display = (!q || t.includes(q)) ? '' : 'none';
        });
    });

    // 2026-07-08 ("One teacher per batch") — before submitting, if any selected
    // batch is currently owned by a DIFFERENT teacher, ask the coach to confirm
    // the ownership transfer. Cancel keeps everything; Confirm re-submits with
    // confirm_transfer=1 (the backend enforces the rule regardless).
    const form = document.getElementById('tbaForm');
    form.addEventListener('submit', function (e) {
        if (form.dataset.confirmed === '1') return;          // already confirmed
        const teacherId = teacherEl.value;
        const conflicts = Array.from(host.querySelectorAll('input[name="batch_ids[]"]:checked'))
            .filter(cb => cb.dataset.currentTeacherId && cb.dataset.currentTeacherId !== teacherId)
            .map(cb => ({ title: cb.dataset.title, teacher: cb.dataset.currentTeacherName }));

        if (conflicts.length === 0) return;                  // no conflict → submit normally

        e.preventDefault();
        const names = [...new Set(conflicts.map(c => c.teacher).filter(Boolean))].join(', ');
        const msg = conflicts.length === 1
            ? `{{ __('This batch is currently assigned to') }} ${conflicts[0].teacher}. `
              + `{{ __('Would you like to change the batch owner? This action will remove the batch from the current teacher and assign it to the selected teacher. No data will be lost from all panels.') }}`
            : `${conflicts.length} {{ __('of the selected batches are currently assigned to another teacher') }} (${names}). `
              + `{{ __('Would you like to change the batch owner? This action will remove them from the current teacher and assign them to the selected teacher. No data will be lost from all panels.') }}`;

        if (window.confirm(msg)) {
            let h = form.querySelector('input[name="confirm_transfer"]');
            if (!h) { h = document.createElement('input'); h.type = 'hidden'; h.name = 'confirm_transfer'; form.appendChild(h); }
            h.value = '1';
            form.dataset.confirmed = '1';
            form.submit();
        }
    });

    // Restore on validation failure.
    if (courseEl.value) {
        courseEl.dispatchEvent(new Event('change'));
    }
    refreshState();
})();
</script>
@endsection
