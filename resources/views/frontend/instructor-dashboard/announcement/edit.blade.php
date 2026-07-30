@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
    {{-- Edit an Announcement. 2026-07-04: rebuilt on the corp design system.
         Preserved 1:1: form (action=update, PUT, multipart) ·
         #announcement-course-select[name=course] · #announcement-batch-select
         [name=batches[]][data-load-url][data-current] · name=title ·
         name=is_pinned · textarea.text-editor[name=announcement] · current
         attachments list + .js-remove-attachment · attachments[] · both scripts. --}}
    @include('frontend.instructor-dashboard.settings.partials._corporate')

    <div class="corp-page" id="announcementEdit">
        <div class="corp-header">
            <div class="corp-header__title">
                <h4><i class="fas fa-bullhorn" style="color:var(--corp-brand);"></i> {{ __('Edit Announcement') }}</h4>
                <p>{{ __('Update the message, audience or attachments.') }}</p>
            </div>
            <div class="corp-header__actions">
                <a href="{{ route('instructor.announcements.index') }}" class="btn-corp-secondary">
                    <i class="fas fa-arrow-left"></i> {{ __('Back to Announcements') }}
                </a>
            </div>
        </div>

        @php $currentBatchIds = $announcement->batches->pluck('id')->all(); @endphp
        <form action="{{ route('instructor.announcements.update', $announcement->id) }}" method="POST" class="instructor__profile-form" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            {{-- Audience ───────────────────────────────────────────── --}}
            <div class="corp-form-card">
                <div class="corp-form-card__head">
                    <h6 class="corp-form-card__title"><i class="fas fa-users" style="color:var(--corp-brand);"></i> {{ __('Audience') }}</h6>
                </div>
                <div class="corp-form-card__body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="corp-field">
                                <label class="corp-field__label" for="announcement-course-select">{{ __('Course') }}</label>
                                <select name="course" id="announcement-course-select" class="form-select corp-select" required>
                                    <option value="">{{ __('Select') }}</option>
                                    @foreach ($courses as $course)
                                        <option @selected($course->id == $announcement->course_id) value="{{ $course->id }}">{{ $course->title }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="corp-field">
                                <label class="corp-field__label">{{ __('Batches') }}
                                    <span class="corp-field__hint" style="display:inline;">({{ __('multi-select; empty = course-wide') }})</span></label>
                                <select name="batches[]" id="announcement-batch-select" class="form-select corp-select" multiple size="4"
                                        data-load-url="{{ route('instructor.announcements.batches-for-course', ['course' => 0]) }}"
                                        data-current="{{ implode(',', $currentBatchIds) }}">
                                    @foreach ($announcement->batches as $b)
                                        <option value="{{ $b->id }}" selected>{{ $b->title }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Message ─────────────────────────────────────────────── --}}
            <div class="corp-form-card">
                <div class="corp-form-card__head">
                    <h6 class="corp-form-card__title"><i class="fas fa-align-left" style="color:var(--corp-brand);"></i> {{ __('Message') }}</h6>
                </div>
                <div class="corp-form-card__body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <div class="corp-field">
                                <label class="corp-field__label">{{ __('Title') }}<span class="req">*</span></label>
                                <input type="text" name="title" class="corp-input" value="{{ $announcement->title }}" required maxlength="255">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="corp-field">
                                <label class="corp-field__label">{{ __('Priority') }}</label>
                                <label style="display:flex;align-items:center;gap:10px;margin:0;cursor:pointer;padding:10px 14px;border:1px solid #fde68a;border-radius:10px;background:#fffbeb;height:56px;">
                                    <input type="checkbox" name="is_pinned" value="1" {{ $announcement->is_pinned ? 'checked' : '' }} style="width:18px;height:18px;accent-color:#f59e0b;">
                                    <span style="font-size:13px;"><i class="fas fa-thumbtack" style="color:#f59e0b;"></i> {{ __('Pin to top') }}</span>
                                </label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="corp-field">
                                <label class="corp-field__label">{{ __('Announcement') }}</label>
                                <textarea name="announcement" class="text-editor">{{ $announcement->announcement }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Attachments ─────────────────────────────────────────── --}}
            <div class="corp-form-card">
                <div class="corp-form-card__head">
                    <h6 class="corp-form-card__title"><i class="fas fa-paperclip" style="color:var(--corp-brand);"></i> {{ __('Attachments') }}</h6>
                </div>
                <div class="corp-form-card__body">
                    @if ($announcement->attachments->count())
                        <div class="corp-field">
                            <label class="corp-field__label">{{ __('Current attachments') }}</label>
                            <ul style="padding-left:0;list-style:none;margin:0;display:flex;flex-direction:column;gap:6px;">
                                @foreach ($announcement->attachments as $att)
                                    <li style="display:flex;align-items:center;gap:10px;padding:9px 12px;background:#f8fafc;border:1px solid var(--corp-line-soft);border-radius:9px;">
                                        <i class="fas {{ $att->isPdf() ? 'fa-file-pdf text-danger' : ($att->isImage() ? 'fa-file-image text-info' : 'fa-file text-secondary') }}"></i>
                                        <a href="{{ route('instructor.announcements.attachments.download', $att->id) }}" style="flex:1;color:var(--corp-text);font-size:13px;">
                                            {{ $att->filename }} <small style="color:var(--corp-muted);">({{ $att->humanSize() }})</small>
                                        </a>
                                        <button type="button" class="btn-corp-secondary btn-corp-sm js-remove-attachment" style="color:#dc2626;"
                                                data-url="{{ route('instructor.announcements.attachments.destroy', $att->id) }}"><i class="fas fa-times"></i></button>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    <div class="corp-field" style="margin-top:16px;">
                        <label class="corp-field__label">{{ __('Add more attachments') }}
                            <span class="corp-field__hint" style="display:inline;">({{ __('up to 5 files, 8 MB each') }})</span></label>
                        <input type="file" name="attachments[]" class="corp-input" style="height:auto;padding:9px 12px;" multiple
                               accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt">
                    </div>
                </div>
            </div>

            <div class="corp-sticky-bar">
                <div class="corp-sticky-bar__summary">
                    <i class="fas fa-info-circle" style="color:var(--corp-brand);"></i>
                    <span>{{ __('Changes apply to this announcement immediately.') }}</span>
                </div>
                <div class="corp-sticky-bar__actions">
                    <button type="submit" class="btn-corp-primary"><i class="fas fa-check"></i> {{ __('Update Announcement') }}</button>
                </div>
            </div>
        </form>
    </div>

    {{-- Handle "remove attachment" buttons. Unchanged. --}}
    <script>
        (function () {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            document.querySelectorAll('.js-remove-attachment').forEach(btn => {
                btn.addEventListener('click', async () => {
                    if (!confirm('{{ __('Remove this attachment?') }}')) return;
                    try {
                        const res = await fetch(btn.dataset.url, {
                            method: 'DELETE',
                            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                            credentials: 'same-origin',
                        });
                        const data = await res.json().catch(() => ({}));
                        if (res.ok && data.status === 'success') { btn.closest('li')?.remove(); }
                        else { alert(data.message || 'Failed'); }
                    } catch (e) { alert('Network error: ' + e.message); }
                });
            });
        })();
    </script>

    {{-- Re-load batches on course change (same JS as create.blade.php). Unchanged. --}}
    <script>
        (function () {
            const courseSelect = document.getElementById('announcement-course-select');
            const batchSelect = document.getElementById('announcement-batch-select');
            if (!courseSelect || !batchSelect) return;
            const baseUrl = batchSelect.dataset.loadUrl.replace(/\/0$/, '/');
            const current = batchSelect.dataset.current;
            const currentIds = new Set((current || '').split(',').filter(Boolean));
            courseSelect.addEventListener('change', async () => {
                const courseId = courseSelect.value;
                batchSelect.innerHTML = '';
                if (!courseId) return;
                try {
                    const res = await fetch(baseUrl + courseId, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    });
                    const data = await res.json();
                    let html = '';
                    (data.batches || []).forEach(b => {
                        const sel = currentIds.has(String(b.id)) ? ' selected' : '';
                        html += `<option value="${b.id}"${sel}>${b.title} (${b.start_date || ''} → ${b.end_date || ''})</option>`;
                    });
                    batchSelect.innerHTML = html;
                } catch (e) {
                    batchSelect.innerHTML = '<option value="" disabled>{{ __('Could not load batches') }}</option>';
                }
            });
        })();
    </script>
@endsection
