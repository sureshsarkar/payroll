@extends('frontend.instructor-dashboard.layouts.master')

<style>
    .batch-modal .modal-content {
        max-height: 90vh; display: flex; border: none; border-radius: 24px;
        overflow-y: scroll; box-shadow: var(--shadow-xl); font-family: var(--ff-body);
        animation: batchSlideUp .38s cubic-bezier(.22, 1, .36, 1) both;
    }
    .batch-header { background: linear-gradient(135deg, #2eb872, #34c38f); padding: 6px 26px; color: white; position: relative; }
    .batch-badge { display: inline-block; background: rgba(255,255,255,.2); padding: 8px 16px; border-radius: 30px; font-weight: 600; margin-bottom: 10px; font-size: 13px; }
    .batch-header p { opacity: .9; font-size: 15px; }
    .close-btn { position: absolute; right: 20px; top: 20px; border: none; background: rgba(255,255,255,.25); color: white; width: 40px; height: 40px; border-radius: 50%; font-size: 22px; }
    .section-title { font-size: 13px; font-weight: 700; color: #28a745; margin-top: 0; margin-bottom: 15px; border-bottom: 1px solid #e5e5e5; padding-bottom: 6px; text-transform: uppercase; }
    .custom-input { height: 48px; border-radius: 10px; border: 1px solid #e6e6e6; }
    .custom-input:focus { border-color: #2eb872; box-shadow: 0 0 0 3px rgba(46,184,114,.15); }
    .required-dot { display: inline-block; width: 6px; height: 6px; background: #28a745; border-radius: 50%; margin-left: 4px; }
    .days-grid { display: flex; gap: 12px; flex-wrap: wrap; }
    .day-chip { border: 1px solid #e5e5e5; padding: 8px 18px; border-radius: 10px; cursor: pointer; transition: .2s; }
    .day-chip input { display: none; }
    .day-chip:hover { border-color: #28a745; }
    .day-chip input:checked + span { color: #28a745; font-weight: 600; }
    .custom-footer { border-top: 1px solid #eee; padding: 20px; }
    .cancel-btn { border: 1px solid #cfd4da; background: white; border-radius: 30px; padding: 8px 20px; }
    .save-btn { background: #2eb872; color: white; border-radius: 30px; padding: 8px 24px; }
    .save-btn:hover { background: #26a96c; }
    #batchModal .modal-footer { position: sticky; bottom: 0; z-index: 5; padding: 20px 32px 28px; border-top: 1px solid var(--n100); background: #fff; gap: 12px; }
</style>

<style>
    /* 2026-07-10 (New Changes for UI #4) — dark mode for this page's bespoke components. */
    html[data-theme="dark"] .section-title { border-bottom-color: #2a3a55; }
    html[data-theme="dark"] .custom-input { border-color: #2a3a55; }
    html[data-theme="dark"] .day-chip { border-color: #2a3a55; }
    html[data-theme="dark"] .custom-footer { border-top-color: #2a3a55; }
    html[data-theme="dark"] .cancel-btn { background: #1e293b; border-color: #2a3a55; color: #e2e8f0; }
    html[data-theme="dark"] #batchModal .modal-footer { background: #1e293b; }
</style>

@section('dashboard-contents')
    {{-- Create Course — Step 5 (Finish). 2026-07-04: rebuilt on the corp design
         system. Preserved: form.course-form (action=update) · hidden
         course_id/step=5/next_step=5 · select[name=status] · textarea
         [name=message_for_reviewer]. The Add-Batch modal + its JS below are
         unchanged. --}}
    @include('frontend.instructor-dashboard.settings.partials._corporate')

    <div class="corp-page" id="courseFinish">
        <div class="corp-header">
            <div class="corp-header__title">
                <h4>{{ __('Create Course') }}</h4>
                <p>{{ __('Choose whether to publish now, save as a draft, or submit for review.') }}</p>
            </div>
            <div class="corp-header__actions">
                <a href="{{ route('instructor.courses.index') }}" class="btn-corp-secondary">
                    <i class="fas fa-arrow-left"></i> {{ __('Back to Courses') }}
                </a>
            </div>
        </div>

        @include('frontend.instructor-dashboard.course.navigation')

        <form action="{{ route('instructor.courses.update') }}" class="instructor__profile-form course-form">
            @csrf
            <input type="hidden" name="course_id" value="{{ $course->id }}">
            <input type="hidden" name="step" value="5">
            <input type="hidden" name="next_step" value="5">

            <div class="corp-form-card">
                <div class="corp-form-card__head">
                    <h6 class="corp-form-card__title">
                        <i class="fas fa-rocket" style="color:var(--corp-brand);"></i> {{ __('Publish') }}
                    </h6>
                    <p class="corp-form-card__sub">{{ __('Published courses go live to students. Drafts stay private until you’re ready.') }}</p>
                </div>
                <div class="corp-form-card__body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="corp-field">
                                <label class="corp-field__label">{{ __('Status') }}<span class="req">*</span></label>
                                <select name="status" class="form-select corp-select">
                                    <option value="">{{ __('Select') }}</option>
                                    <option @selected($course->status == 'active') value="active">{{ __('Publish') }}</option>
                                    <option @selected($course->status == 'inactive') value="inactive">{{ __('UnPublish') }}</option>
                                    <option @selected($course->status == 'is_draft') value="is_draft">{{ __('Draft') }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="corp-field">
                                <label class="corp-field__label">{{ __('Message for Reviewer') }}</label>
                                <textarea name="message_for_reviewer" class="form-control corp-input" rows="4"
                                          style="height:auto;padding:12px 14px;">{{ $course->message_for_reviewer }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="corp-sticky-bar">
                <div class="corp-sticky-bar__summary">
                    <i class="fas fa-info-circle" style="color:var(--corp-brand);"></i>
                    <span><strong>{{ __('Step 4 of 4') }}</strong> <span class="text-muted">·</span> {{ __('Finish') }}</span>
                </div>
                <div class="corp-sticky-bar__actions">
                    <button class="btn-corp-primary" type="submit"><i class="fas fa-check"></i> {{ __('Save Course') }}</button>
                </div>
            </div>
        </form>
    </div>
@endsection


{{-- Audit 2026-05-19 — only render the Add Batch modal when this course can
     actually host batches. Recorded-only courses have no live sessions. --}}
@if (in_array(($course->type ?? null), ['live', 'hybrid', 'course', 'webinar'], true) && ($course->type ?? null) !== 'recorded')
<div class="modal fade batch-modal" id="batchModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="batch-header">
                <div class="batch-header-content">
                    <div class="batch-badge"><i class="fa fa-layer-group"></i> ADD NEW BATCH</div>
                    <p class="text-light">Fill in the details to schedule a new batch session.</p>
                </div>
                <button class="close-btn" data-bs-dismiss="modal">×</button>
            </div>
            <form id="batchForm">
                @csrf
                <div class="modal-body">
                    <input type="hidden" name="course_id" value="{{ $course->id }}">
                    <div class="section-title">Batch Info</div>
                    <div class="form-group mb-4">
                        <label>Batch Title <span class="required-dot"></span></label>
                        <input type="text" name="title" class="form-control custom-input" placeholder="e.g. Morning Cohort – July 2025">
                    </div>
                    <div class="section-title">Schedule</div>
                    <div class="row">
                        <div class="col-md-6 mb-4"><label>Start Date <span class="required-dot"></span></label><input type="date" name="start_date" class="form-control custom-input"></div>
                        <div class="col-md-6 mb-4"><label>End Date <span class="required-dot"></span></label><input type="date" name="end_date" class="form-control custom-input"></div>
                        <div class="col-md-6 mb-4"><label>Start Time <span class="required-dot"></span></label><input type="time" name="start_time" class="form-control custom-input"></div>
                        <div class="col-md-6 mb-4"><label>End Time <span class="required-dot"></span></label><input type="time" name="end_time" class="form-control custom-input"></div>
                    </div>
                    <div class="section-title">Days</div>
                    <div class="days-grid">
                        @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $day)
                            <label class="day-chip"><input type="checkbox" name="days[]" value="{{ $day }}"><span>{{ $day }}</span></label>
                        @endforeach
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-6"><div class="form-group"><label for="capacity">{{ __('Capacity') }}</label><input type="number" name="capacity" id="capacity" class="form-control custom-input" placeholder="{{ __('Leave blank for unlimited') }}"></div></div>
                        <div class="col-md-6"><div class="form-group"><label for="status">{{ __('Status') }}</label><select name="status" id="status" class="form-control custom-input"><option value="active">{{ __('Active') }}</option><option value="inactive">{{ __('Inactive') }}</option></select></div></div>
                    </div>
                </div>
                <div class="modal-footer custom-footer">
                    <button class="btn cancel-btn" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn save-btn">✔ Save Batch</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

<script>
    document.getElementById('batchForm').addEventListener('submit', function(e) {
        e.preventDefault();
        let url = "{{ route('instructor.course-batches.store') }}";
        let method = "POST";
        let formData = new FormData(this);
        fetch(url, {
                method: method,
                headers: { 'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value, 'Accept': 'application/json' },
                body: formData
            })
            .then(async response => { let data = await response.json(); if (!response.ok) { throw data; } return data; })
            .then(data => {
                if (data.status === "success") {
                    toastr.success(data.message);
                    let modalEl = document.getElementById('batchModal');
                    let modal = bootstrap.Modal.getInstance(modalEl);
                    modal.hide();
                }
            })
            .catch(error => {
                if (error.errors) { for (let key in error.errors) { toastr.error(error.errors[key][0]); } }
                else { toastr.error("Something went wrong"); }
            });
    });
</script>

@push('scripts')
    <script src="{{ asset('frontend/js/default/courses.js') }}"></script>
@endpush
