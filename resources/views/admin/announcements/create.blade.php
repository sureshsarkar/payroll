@extends('admin.master_layout')
@section('title')
    <title>{{ __('Create Announcement') }}</title>
@endsection
@section('admin-content')
{{-- ============================================================
     Audit 2026-05-20 — Admin-side announcement create.
     Phase C of the 2026-05-20 announcement upgrade. Admin can choose
     to send to ALL students OR to specific batch(es) of a course.
     ============================================================ --}}
<div class="main-content">
    <section class="section">
        <div class="section-header">
            <h1>{{ __('Create Announcement') }}</h1>
            <div class="section-header-breadcrumb">
                <a class="btn btn-light" href="{{ route('admin.announcements.index') }}">
                    <i class="fas fa-arrow-left"></i> {{ __('Back to list') }}
                </a>
            </div>
        </div>

        <div class="section-body">
            <div class="card">
                <form action="{{ route('admin.announcements.store') }}" method="POST">
                    @csrf
                    <div class="card-body">

                        {{-- Audience selector ──────────────────────────────── --}}
                        <div class="form-group">
                            <label style="font-weight:600; font-size:13px;">
                                {{ __('Audience') }} <span class="text-danger">*</span>
                            </label>
                            <div class="d-flex gap-3 flex-wrap" style="margin-top:6px;">
                                <label class="audience-pill" data-target="all">
                                    <input type="radio" name="audience_type" value="all_students"
                                           {{ old('audience_type', 'batch_specific') === 'all_students' ? 'checked' : '' }}>
                                    <span>🌐 {{ __('All Students') }}</span>
                                    <small class="d-block text-muted" style="font-size:11px;">
                                        {{ __('Reaches every student on the platform') }}
                                    </small>
                                </label>
                                <label class="audience-pill" data-target="batch">
                                    <input type="radio" name="audience_type" value="batch_specific"
                                           {{ old('audience_type', 'batch_specific') === 'batch_specific' ? 'checked' : '' }}>
                                    <span>📚 {{ __('Batch-wise Students') }}</span>
                                    <small class="d-block text-muted" style="font-size:11px;">
                                        {{ __('Only students enrolled in the selected batch(es)') }}
                                    </small>
                                </label>
                            </div>
                            @error('audience_type')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>

                        {{-- Course + Batch (only for batch_specific) ──────── --}}
                        <div id="batch-scope-block" class="row" style="display:none;">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label style="font-weight:600; font-size:13px;">
                                        {{ __('Course') }} <span class="text-danger">*</span>
                                    </label>
                                    <select name="course_id" id="admin-ann-course"
                                            class="form-control"
                                            data-load-url="{{ route('admin.announcements.batches-for-course', ['course' => 0]) }}">
                                        <option value="">{{ __('— Select a course —') }}</option>
                                        @foreach ($courses as $c)
                                            <option value="{{ $c->id }}" @selected(old('course_id') == $c->id)>{{ $c->title }}</option>
                                        @endforeach
                                    </select>
                                    @error('course_id')<small class="text-danger">{{ $message }}</small>@enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label style="font-weight:600; font-size:13px;">
                                        {{ __('Batches') }} <span class="text-danger">*</span>
                                        <small class="text-muted">({{ __('hold Ctrl/Cmd to multi-select') }})</small>
                                    </label>
                                    <select name="batches[]" id="admin-ann-batches" class="form-control" multiple size="4">
                                        <option value="">{{ __('— Select a course first —') }}</option>
                                    </select>
                                    @error('batches')<small class="text-danger">{{ $message }}</small>@enderror
                                </div>
                            </div>
                        </div>

                        {{-- Title ─────────────────────────────────────────── --}}
                        <div class="form-group">
                            <label style="font-weight:600; font-size:13px;">
                                {{ __('Title') }} <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="title" class="form-control" required
                                   maxlength="255" value="{{ old('title') }}"
                                   placeholder="{{ __('Short headline shown in the student bell') }}">
                            @error('title')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>

                        {{-- Message ───────────────────────────────────────── --}}
                        <div class="form-group">
                            <label style="font-weight:600; font-size:13px;">
                                {{ __('Message') }} <span class="text-danger">*</span>
                            </label>
                            <textarea name="announcement" rows="6" class="form-control"
                                      required>{{ old('announcement') }}</textarea>
                            @error('announcement')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>

                        <div class="row">
                            {{-- Status ────────────────────────────────────── --}}
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label style="font-weight:600; font-size:13px;">
                                        {{ __('Status') }} <span class="text-danger">*</span>
                                    </label>
                                    <select name="status" class="form-control" required>
                                        <option value="active" @selected(old('status','active')=='active')>
                                            {{ __('Published (visible to students)') }}
                                        </option>
                                        <option value="inactive" @selected(old('status')=='inactive')>
                                            {{ __('Draft (hidden)') }}
                                        </option>
                                    </select>
                                </div>
                            </div>

                            {{-- Schedule for later ────────────────────────── --}}
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label style="font-weight:600; font-size:13px;">
                                        {{ __('Publish at') }}
                                        <small class="text-muted">({{ __('optional') }})</small>
                                    </label>
                                    <input type="datetime-local" name="scheduled_at" class="form-control"
                                           value="{{ old('scheduled_at') }}">
                                </div>
                            </div>

                            {{-- Pin to top ────────────────────────────────── --}}
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label style="font-weight:600; font-size:13px;">&nbsp;</label>
                                    <div style="padding:8px 12px; border:1px solid #e5e7eb; border-radius:6px; background:#fffbeb;">
                                        <label style="margin:0; cursor:pointer; font-weight:500;">
                                            <input type="checkbox" name="is_pinned" value="1" {{ old('is_pinned') ? 'checked' : '' }}>
                                            <i class="fas fa-thumbtack" style="color:#f59e0b;"></i>
                                            {{ __('Pin to top (urgent)') }}
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer text-right">
                        <a href="{{ route('admin.announcements.index') }}" class="btn btn-light">{{ __('Cancel') }}</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> {{ __('Publish announcement') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>
</div>

<style>
.audience-pill {
    display:flex; flex-direction:column; align-items:flex-start;
    border:2px solid #e5e7eb; border-radius:10px; padding:10px 14px;
    cursor:pointer; min-width:240px; transition:all .15s;
    background:#fff;
}
.audience-pill input { margin-right:6px; }
.audience-pill:has(input:checked) {
    border-color:#5751e1; background:#f3f2ff; box-shadow:0 0 0 3px rgba(87,81,225,0.08);
}
.audience-pill > span { font-weight:600; font-size:13px; color:#1c1a4a; }
</style>

<script>
(function () {
    function refreshAudienceVisibility() {
        var picked = document.querySelector('input[name="audience_type"]:checked');
        var block = document.getElementById('batch-scope-block');
        if (!picked || !block) return;
        block.style.display = picked.value === 'batch_specific' ? 'flex' : 'none';
    }
    document.querySelectorAll('input[name="audience_type"]').forEach(function (el) {
        el.addEventListener('change', refreshAudienceVisibility);
    });
    refreshAudienceVisibility();

    // Course → batches AJAX.
    var courseEl = document.getElementById('admin-ann-course');
    var batchEl  = document.getElementById('admin-ann-batches');
    if (!courseEl || !batchEl) return;
    courseEl.addEventListener('change', function () {
        var courseId = this.value;
        var loadUrl = this.dataset.loadUrl.replace('/0', '/' + courseId);
        batchEl.innerHTML = '<option value="">{{ __('Loading…') }}</option>';
        if (!courseId) { batchEl.innerHTML = '<option value="">{{ __('— Select a course first —') }}</option>'; return; }
        fetch(loadUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var opts = '';
                (data.batches || []).forEach(function (b) {
                    opts += '<option value="' + b.id + '">' + b.title + '</option>';
                });
                batchEl.innerHTML = opts || '<option value="">{{ __('No batches in this course') }}</option>';
            })
            .catch(function () {
                batchEl.innerHTML = '<option value="">{{ __('Failed to load batches') }}</option>';
            });
    });
})();
</script>
@endsection
