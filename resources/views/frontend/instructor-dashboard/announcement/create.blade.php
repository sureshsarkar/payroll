@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
    {{-- Create an Announcement. 2026-07-04: rebuilt on the corp design system.
         Preserved 1:1: form (action=store, multipart) · hidden audience_type ·
         #announcement-course-select[name=course] · #announcement-batch-select
         [name=batches[]][data-load-url] + Select-all/Clear · name=title ·
         #scheduled_at[name=scheduled_at] · name=is_pinned · textarea.text-editor
         [name=announcement] · attachments[] · the batch-load <script> below. --}}
    @include('frontend.instructor-dashboard.settings.partials._corporate')

    <div class="corp-page" id="announcementCreate">
        <div class="corp-header">
            <div class="corp-header__title">
                <h4><i class="fas fa-bullhorn" style="color:var(--corp-brand);"></i> {{ __('Create an Announcement') }}</h4>
                <p>{{ __('Write it once — it’s delivered to every student in the batches you pick.') }}</p>
            </div>
            <div class="corp-header__actions">
                <a href="{{ route('instructor.announcements.index') }}" class="btn-corp-secondary">
                    <i class="fas fa-arrow-left"></i> {{ __('Back to Announcements') }}
                </a>
            </div>
        </div>

        <form action="{{ route('instructor.announcements.store') }}" method="POST" class="instructor__profile-form" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="audience_type" value="batch_specific">

            <div class="corp-form-card" style="border-color:var(--corp-brand-border);background:var(--corp-brand-bg);">
                <div class="corp-form-card__body" style="display:flex;align-items:center;gap:10px;padding:12px 16px;font-size:12.5px;color:var(--corp-brand-deep);">
                    <i class="fas fa-info-circle"></i>
                    {{ __('Coach-side announcements are sent only to your selected batch(es). To reach all students platform-wide, ask an admin.') }}
                </div>
            </div>

            {{-- Audience ───────────────────────────────────────────── --}}
            <div class="corp-form-card">
                <div class="corp-form-card__head">
                    <h6 class="corp-form-card__title"><i class="fas fa-users" style="color:var(--corp-brand);"></i> {{ __('Audience') }}</h6>
                    <p class="corp-form-card__sub">{{ __('Pick a course, then the batches to notify. Leave batches empty to send to the whole course.') }}</p>
                </div>
                <div class="corp-form-card__body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="corp-field">
                                <label class="corp-field__label" for="announcement-course-select">{{ __('Course') }}</label>
                                <select name="course" id="announcement-course-select" class="form-select corp-select" required>
                                    <option value="">{{ __('Select') }}</option>
                                    @foreach ($courses as $course)
                                        <option value="{{ $course->id }}" {{ old('course') == $course->id ? 'selected' : '' }}>{{ $course->title }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="corp-field">
                                <label class="corp-field__label" style="display:flex;align-items:center;justify-content:space-between;gap:10px;">
                                    <span>{{ __('Send to which batches?') }}</span>
                                    <span style="display:inline-flex;gap:8px;font-size:11px;font-weight:600;">
                                        <button type="button" onclick="document.querySelectorAll('#announcement-batch-select option').forEach(o=>o.selected=true);" style="border:0;background:var(--corp-brand-bg);color:var(--corp-brand-deep);padding:3px 8px;border-radius:5px;cursor:pointer;">{{ __('Select all') }}</button>
                                        <button type="button" onclick="document.querySelectorAll('#announcement-batch-select option').forEach(o=>o.selected=false);" style="border:0;background:#FEE2E2;color:#B91C1C;padding:3px 8px;border-radius:5px;cursor:pointer;">{{ __('Clear') }}</button>
                                    </span>
                                </label>
                                <select name="batches[]" id="announcement-batch-select" class="form-select corp-select"
                                        data-load-url="{{ route('instructor.announcements.batches-for-course', ['course' => 0]) }}" multiple>
                                </select>
                                <div class="corp-field__hint"><i class="fas fa-info-circle"></i> {{ __('Use the buttons above or Ctrl-click to pick multiple. Leave empty to send to the whole course.') }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Content ─────────────────────────────────────────────── --}}
            <div class="corp-form-card">
                <div class="corp-form-card__head">
                    <h6 class="corp-form-card__title"><i class="fas fa-align-left" style="color:var(--corp-brand);"></i> {{ __('Message') }}</h6>
                </div>
                <div class="corp-form-card__body">
                    <div class="corp-field">
                        <label class="corp-field__label">{{ __('Title') }}<span class="req">*</span></label>
                        <input type="text" name="title" class="corp-input" value="{{ old('title') }}" required maxlength="255">
                    </div>
                    <div class="corp-field" style="margin-top:16px;">
                        <label class="corp-field__label">{{ __('Announcement') }}<span class="req">*</span></label>
                        <textarea name="announcement" class="text-editor" required>{{ old('announcement') }}</textarea>
                    </div>
                </div>
            </div>

            {{-- Delivery options ────────────────────────────────────── --}}
            <div class="corp-form-card">
                <div class="corp-form-card__head">
                    <h6 class="corp-form-card__title"><i class="fas fa-sliders-h" style="color:var(--corp-brand);"></i> {{ __('Delivery Options') }}</h6>
                </div>
                <div class="corp-form-card__body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="corp-field">
                                <label class="corp-field__label" for="scheduled_at">{{ __('Schedule for later') }}
                                    <span class="corp-field__hint" style="display:inline;">({{ __('leave empty to send immediately') }})</span></label>
                                <input type="datetime-local" name="scheduled_at" id="scheduled_at" class="corp-input" value="{{ old('scheduled_at') }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="corp-field">
                                <label class="corp-field__label">{{ __('Priority') }}</label>
                                <label style="display:flex;align-items:center;gap:10px;margin:0;cursor:pointer;padding:10px 14px;border:1px solid #fde68a;border-radius:10px;background:#fffbeb;height:56px;">
                                    <input type="checkbox" name="is_pinned" value="1" {{ old('is_pinned') ? 'checked' : '' }} style="width:18px;height:18px;accent-color:#f59e0b;">
                                    <span style="font-size:13.5px;"><i class="fas fa-thumbtack" style="color:#f59e0b;"></i> {{ __('Pin to top — urgent announcement') }}</span>
                                </label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="corp-field">
                                <label class="corp-field__label"><i class="fas fa-paperclip"></i> {{ __('Attachments') }}
                                    <span class="corp-field__hint" style="display:inline;">({{ __('up to 5 files, 8 MB each — PDF / image / DOC / XLSX / TXT') }})</span></label>
                                <input type="file" name="attachments[]" class="corp-input" style="height:auto;padding:9px 12px;" multiple
                                       accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="corp-sticky-bar">
                <div class="corp-sticky-bar__summary">
                    <i class="fas fa-info-circle" style="color:var(--corp-brand);"></i>
                    <span>{{ __('Students in the selected batches will be notified.') }}</span>
                </div>
                <div class="corp-sticky-bar__actions">
                    <button type="submit" class="btn-corp-primary"><i class="fas fa-paper-plane"></i> {{ __('Create Announcement') }}</button>
                </div>
            </div>
        </form>
    </div>

    {{-- Load batches when the course changes. Unchanged. --}}
    <script>
        (function () {
            const courseSelect = document.getElementById('announcement-course-select');
            const batchSelect = document.getElementById('announcement-batch-select');
            if (!courseSelect || !batchSelect) return;
            const baseUrl = batchSelect.dataset.loadUrl.replace(/\/0$/, '/');
            courseSelect.addEventListener('change', async () => {
                const courseId = courseSelect.value;
                batchSelect.innerHTML = '';
                if (!courseId) return;
                try {
                    const res = await fetch(baseUrl + courseId, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    });
                    if (!res.ok) throw new Error('fetch-failed');
                    const data = await res.json();
                    const fmt = (iso) => {
                        if (!iso) return '';
                        const d = new Date(iso);
                        if (isNaN(d.getTime())) return iso;
                        return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
                    };
                    let html = '';
                    (data.batches || []).forEach(b => {
                        const range = (b.start_date || b.end_date) ? ` · ${fmt(b.start_date)} – ${fmt(b.end_date)}` : '';
                        html += `<option value="${b.id}">${b.title}${range}</option>`;
                    });
                    batchSelect.innerHTML = html;
                } catch (e) {
                    batchSelect.innerHTML = '<option value="" disabled>{{ __('Could not load batches') }}</option>';
                }
            });
        })();
    </script>
@endsection
