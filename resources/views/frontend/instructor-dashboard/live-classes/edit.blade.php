@extends('frontend.instructor-dashboard.layouts.master')

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<style>
.modal-backdrop { display: none !important; }

/* ── Root ── */
:root {
    --lc-accent:  #00c896;
    --lc-accent2: #0e9de8;
    --lc-accent-soft:   rgba(0,200,150,0.08);
    --lc-accent-border: rgba(0,200,150,0.25);
    --lc-text:   #0f1f2e;
    --lc-muted:  #6b7e96;
    --lc-border: #e2e8f0;
    --lc-danger: #ef4444;
    --lc-input:  #f8fafb;
    --lc-card:   #ffffff;
    --lc-radius: 12px;
}

/* ── Page Header ── */
.lc-page-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 24px; flex-wrap: wrap; gap: 12px;
}
.lc-page-left { display: flex; align-items: center; gap: 14px; }
.lc-page-icon {
    width: 46px; height: 46px; border-radius: 12px;
    background: linear-gradient(135deg, var(--lc-accent), var(--lc-accent2));
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 6px 20px rgba(0,200,150,0.25); flex-shrink: 0;
}
.lc-page-icon i { color: #fff; font-size: 20px; }
.lc-page-title { font-family: 'Outfit', sans-serif; font-size: 22px; font-weight: 800; color: var(--lc-text); line-height: 1; }
.lc-page-sub { font-size: 12px; color: var(--lc-muted); margin-top: 3px; }

.btn-lc-back {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 9px 20px; border-radius: 50px;
    border: 1.5px solid var(--lc-accent-border);
    background: var(--lc-accent-soft);
    color: var(--lc-accent); font-size: 12.5px; font-weight: 600;
    cursor: pointer; text-decoration: none;
    transition: all .2s ease; font-family: 'Outfit', sans-serif;
}
.btn-lc-back:hover {
    background: var(--lc-accent); color: #fff;
    border-color: var(--lc-accent); text-decoration: none;
    box-shadow: 0 4px 14px rgba(0,200,150,0.3);
}

/* ── Card ── */
.lc-card {
    background: var(--lc-card);
    border-radius: 18px;
    border: 1px solid var(--lc-border);
    box-shadow: 0 4px 24px rgba(0,0,0,0.05);
    overflow: hidden;
    font-family: 'Outfit', sans-serif;
}
.lc-card-header {
    padding: 20px 28px 18px;
    border-bottom: 1px solid var(--lc-border);
    display: flex; align-items: center; gap: 10px;
    background: linear-gradient(135deg, rgba(0,200,150,0.04), rgba(14,157,232,0.03));
}
.lc-card-dot {
    width: 8px; height: 8px; border-radius: 50%;
    background: var(--lc-accent); box-shadow: 0 0 8px var(--lc-accent);
    animation: lc-blink 2s ease-in-out infinite;
}
@keyframes lc-blink { 0%,100%{opacity:1;} 50%{opacity:.4;} }
.lc-card-title { font-size: 13px; font-weight: 700; color: var(--lc-text); text-transform: uppercase; letter-spacing: .06em; }
.lc-card-badge {
    margin-left: auto; padding: 3px 10px; border-radius: 20px;
    font-size: 10px; font-weight: 700;
    background: rgba(14,157,232,0.1); color: var(--lc-accent2);
    border: 1px solid rgba(14,157,232,0.2);
}
.lc-card-body { padding: 28px; }

/* ── Section Title ── */
.lc-section-title {
    font-size: 11px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .1em; color: var(--lc-muted); margin-bottom: 16px;
    display: flex; align-items: center; gap: 8px;
}
.lc-section-title::after { content: ''; flex: 1; height: 1px; background: var(--lc-border); }

/* ── Form Groups ── */
.lc-form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 0; }
.lc-label {
    font-size: 12px; font-weight: 600; color: var(--lc-text);
    display: flex; align-items: center; gap: 5px;
}
.lc-label .lc-lbl-icon {
    width: 18px; height: 18px; border-radius: 5px;
    background: var(--lc-accent-soft);
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 10px; color: var(--lc-accent);
}
.lc-label .req { color: var(--lc-danger); font-size: 11px; }
.lc-label .lc-hint { font-size: 10px; color: var(--lc-muted); font-weight: 400; }

/* ── Inputs ── */
.lc-input-wrap { position: relative; }
.lc-input-wrap .form-control,
.lc-input-wrap .form-select { padding-left: 40px !important; }
.lc-input-icon {
    position: absolute; left: 13px; top: 50%; transform: translateY(-50%);
    font-size: 15px; color: var(--lc-muted); pointer-events: none;
    transition: color .2s ease;
}
.lc-input-wrap:focus-within .lc-input-icon { color: var(--lc-accent); }

.lc-card .form-control,
.lc-card .form-select {
    font-family: 'Outfit', sans-serif !important;
    font-size: 13.5px !important; font-weight: 500 !important;
    border: 1.5px solid var(--lc-border) !important;
    border-radius: 10px !important;
    background: var(--lc-input) !important;
    color: var(--lc-text) !important;
    padding: 11px 14px !important;
    height: auto !important;
    transition: all .2s ease !important;
    box-shadow: none !important;
}
.lc-card .form-control:focus,
.lc-card .form-select:focus {
    border-color: var(--lc-accent) !important;
    background: #fff !important;
    box-shadow: 0 0 0 3px rgba(0,200,150,0.1) !important;
}

/* ── Divider ── */
.lc-divider { height: 1px; background: var(--lc-border); margin: 24px 0; }

/* ── Checkbox ── */
.lc-check-wrap {
    display: flex; align-items: flex-start; gap: 12px;
    padding: 14px 16px;
    background: rgba(14,157,232,0.05);
    border: 1px solid rgba(14,157,232,0.15);
    border-radius: 10px; cursor: pointer;
    transition: all .2s ease;
}
.lc-check-wrap:hover { border-color: rgba(14,157,232,0.3); background: rgba(14,157,232,0.08); }
.lc-check-wrap .form-check-input {
    width: 18px !important; height: 18px !important;
    margin: 0 !important; flex-shrink: 0; cursor: pointer;
    border: 1.5px solid var(--lc-border) !important;
    border-radius: 5px !important;
    accent-color: var(--lc-accent);
}
.lc-check-label {
    display: flex; flex-direction: column; cursor: pointer;
}
.lc-check-label strong { font-size: 13px; font-weight: 600; color: var(--lc-text); }
.lc-check-label span { font-size: 11px; color: var(--lc-muted); margin-top: 2px; }

/* ── Card Footer ── */
.lc-card-footer {
    padding: 18px 28px;
    border-top: 1px solid var(--lc-border);
    display: flex; align-items: center; justify-content: space-between;
    background: #fafcfb; flex-wrap: wrap; gap: 12px;
}
.lc-footer-hint { font-size: 11px; color: var(--lc-muted); display: flex; align-items: center; gap: 5px; }
.lc-footer-hint i { color: var(--lc-accent); font-size: 13px; }

.btn-lc-save {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 11px 28px; border-radius: 50px;
    background:#fff; linear-gradient(135deg, var(--lc-accent), #00b085);
    color: #10b981; font-size: 13.5px; font-weight: 700;
    border: 2px solid #00c896; cursor: pointer; 
    transition: all .2s ease; font-family: 'Outfit', sans-serif;
}
.btn-lc-save:hover { transform: translateY(-2px);background:#e4fff6f3; color: #10b981; }
.btn-lc-save:active { transform: translateY(0); }
</style>

<style>
/* 2026-07-10 (New Changes for UI #4) — dark mode for this page's bespoke components. */
/* This page owns page-local --lc-* tokens (not globally themed), so remap the
   neutral surface/border/text ones under dark; accent/danger tokens kept. */
html[data-theme="dark"] {
    --lc-text:   #e2e8f0;
    --lc-muted:  #94a3b8;
    --lc-border: #2a3a55;
    --lc-input:  #17233a;
    --lc-card:   #1e293b;
}
html[data-theme="dark"] .lc-card .form-control:focus,
html[data-theme="dark"] .lc-card .form-select:focus { background: #1e293b !important; }
html[data-theme="dark"] .lc-card-footer { background: #17233a; }
html[data-theme="dark"] .btn-lc-save { background: #1e293b; }
html[data-theme="dark"] .btn-lc-save:hover { background: #22304a; }
</style>

@section('dashboard-contents')
<div class="dashboard__content-wrap">

    <!-- ── Page Header ── -->
    <div class="lc-page-header">
        <div class="lc-page-left">
            <div class="lc-page-icon">
                <i class="bi bi-camera-reels"></i>
            </div>
            <div>
                <div class="lc-page-title">{{ __('Edit Live Class') }}</div>
                <div class="lc-page-sub">{{ __('Update live class details and schedule') }}</div>
            </div>
        </div>
        <a href="{{ route('instructor.live-classes.index') }}" class="btn-lc-back">
            <i class="bi bi-arrow-left"></i> {{ __('Back to Live Classes') }}
        </a>
    </div>

    <!-- ── Main Card ── -->
    <div class="lc-card">

        <!-- Card Header -->
        <div class="lc-card-header">
            <span class="lc-card-dot"></span>
            <span class="lc-card-title">{{ __('Class Information') }}</span>
            <span class="lc-card-badge">
                <i class="bi bi-pencil-square" style="font-size:9px;margin-right:3px;"></i>
                {{ __('Editing') }}
            </span>
        </div>

        <form action="{{ route('instructor.live-class.update', $editdata->id) }}" method="POST">
            <input type="hidden" name="chapter_item_id" value="{{ $lessiondata->chapter_item_id }}">
            <input type="hidden" name="lession_id" value="{{ $lessiondata->id }}">
            @csrf

            <div class="lc-card-body">

                <!-- Section: Basic Details -->
                <div class="lc-section-title">{{ __('Basic Details') }}</div>

                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="lc-form-group">
                            <label class="lc-label">
                                <span class="lc-lbl-icon"><i class="bi bi-type"></i></span>
                                {{ __('Live Class Title') }} <span class="req">*</span>
                            </label>
                            <div class="lc-input-wrap"> 
                                <input type="text" name="live_class_title" class="form-control"
                                    value="{{ $lessiondata->title }}"
                                    placeholder="{{ __('Enter class title') }}" required>
                            </div>
                            @error('live_class_title')
                                <span class="text-danger" style="font-size:11px;">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="lc-form-group">
                            <label class="lc-label">
                                <span class="lc-lbl-icon"><i class="bi bi-book"></i></span>
                                {{ __('Course') }} <span class="req">*</span>
                            </label>
                            <div class="lc-input-wrap"> 
                                <select name="course_id" id="course_id" class="form-select" required>
                                    <option value="">{{ __('Select Course') }}</option>
                                    @foreach ($courses as $course)
                                        <option value="{{ $course->id }}"
                                            {{ $course->id == $lessiondata->course_id ? 'selected' : '' }}>
                                            {{ $course->title }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @error('course_id')
                                <span class="text-danger" style="font-size:11px;">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    {{-- Audit 2026-05-19 phase 4 — Chapter dropdown removed
                         per SRS-CLC-001. The existing chapter_id is
                         preserved server-side via the lesson relation. --}}

                    <div class="col-md-6">
                        <div class="lc-form-group">
                            <label class="lc-label">
                                <span class="lc-lbl-icon"><i class="bi bi-collection"></i></span>
                                {{ __('Batch') }} <span class="req">*</span>
                            </label>
                            <div class="lc-input-wrap"> 
                                <select name="batch_id" id="batch_id" class="form-select" required>
                                    <option value="">{{ __('Select Batch') }}</option>
                                    @foreach ($batches as $batch)
                                        <option value="{{ $batch->id }}"
                                            {{ $batch->id == $editdata->batch_id ? 'selected' : '' }}>
                                            {{ $batch->title }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @error('batch_id')
                                <span class="text-danger" style="font-size:11px;">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="lc-divider"></div>

                <!-- Section: Schedule -->
                <div class="lc-section-title">{{ __('Schedule & Platform') }}</div>

                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <div class="lc-form-group">
                            <label class="lc-label">
                                <span class="lc-lbl-icon"><i class="bi bi-broadcast"></i></span>
                                {{ __('Live Platform') }} <span class="req">*</span>
                            </label>
                            <div class="lc-input-wrap"> 
                                <select name="live_type" id="live_type" class="form-select">
                                    @foreach (config('course.live_types') as $key => $value)
                                        <option value="{{ $key }}"
                                            {{ $key == $editdata->type ? 'selected' : '' }}>
                                            {{ $value }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @error('live_type')
                                <span class="text-danger" style="font-size:11px;">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="lc-form-group">
                            <label class="lc-label">
                                <span class="lc-lbl-icon"><i class="bi bi-calendar"></i></span>
                                {{ __('Start Time') }} <span class="req">*</span>
                            </label>
                            <div class="lc-input-wrap"> 
                                <input id="start_time" name="start_time"
                                    value="{{ $editdata->start_time }}"
                                    type="datetime-local" class="form-control">
                            </div>
                            @error('start_time')
                                <span class="text-danger" style="font-size:11px;">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="lc-form-group">
                            <label class="lc-label">
                                <span class="lc-lbl-icon"><i class="bi bi-hourglass"></i></span>
                                {{ __('Duration') }} <span class="req">*</span>
                                <span class="lc-hint">({{ __('in minutes') }})</span>
                            </label>
                            <div class="lc-input-wrap"> 
                                <input id="duration" name="duration" type="text"
                                    value="{{ $lessiondata->duration }}"
                                    class="form-control" placeholder="e.g. 60">
                            </div>
                            @error('duration')
                                <span class="text-danger" style="font-size:11px;">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="lc-divider"></div>

                <!-- Section: Additional Info -->
                <div class="lc-section-title">{{ __('Additional Info') }}</div>

                <div class="row g-4 mb-4">
                    <div class="col-md-12">
                        <div class="lc-form-group">
                            <label class="lc-label">
                                <span class="lc-lbl-icon"><i class="bi bi-text-paragraph"></i></span>
                                {{ __('Description') }}
                            </label>
                            <div class="lc-input-wrap"> 
                                <input id="description" name="description" type="text"
                                    value="{{ $lessiondata->description }}"
                                    class="form-control"
                                    placeholder="{{ __('Brief description about this class') }}">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Email Checkbox -->
                <label class="lc-check-wrap">
                    <input id="student_mail_sent" type="checkbox"
                        class="form-check-input" name="student_mail_sent">
                    <div class="lc-check-label">
                        <strong>{{ __('Notify all enrolled students') }}</strong>
                        <span>{{ __('Send an email notification to all students enrolled in this course batch.') }}</span>
                    </div>
                </label>

            </div><!-- /card-body -->

            <!-- Card Footer -->
            <div class="lc-card-footer">
                <div class="lc-footer-hint">
                    <i class="bi bi-info-circle"></i>
                    {{ __('Fields marked with') }}
                    <span style="color:var(--lc-danger);margin:0 2px;">*</span>
                    {{ __('are required') }}
                </div>
                <button type="submit" class="btn-lc-save">
                    <i class="bi bi-check-circle"></i>
                    {{ __('Save Changes') }}
                </button>
            </div>

        </form>
    </div><!-- /lc-card -->

</div>
@endsection

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Audit 2026-05-19 phase 4 — Chapter dropdown removed per SRS-CLC-001.
    // Only Batch is refreshed when the course changes; chapter is resolved
    // server-side at submit.
    const courseEl = document.getElementById('course_id');
    if (!courseEl) return;
    courseEl.addEventListener('change', function () {
        let courseId = this.value;
        let batchSelect = document.getElementById('batch_id');
        if (!batchSelect) return;

        batchSelect.innerHTML = '<option value="">Loading...</option>';

        if (courseId !== '') {
            let url = "{{ route('instructor.get-batches-by-course', '') }}/" + courseId;
            fetch(url)
                .then(r => r.json())
                .then(data => {
                    let bo = '<option value="">{{ __("Select Batch") }}</option>';
                    (data.batches || []).forEach(b => { bo += `<option value="${b.id}">${b.title}</option>`; });
                    batchSelect.innerHTML = bo;
                })
                .catch(() => {
                    batchSelect.innerHTML = '<option value="">{{ __("Error loading") }}</option>';
                });
        } else {
            batchSelect.innerHTML = '<option value="">{{ __("Select Batch") }}</option>';
        }
    });
});
</script>