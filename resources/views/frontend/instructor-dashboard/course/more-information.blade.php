@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
    {{-- Create Course — Step 2 (More Infos). 2026-07-04: rebuilt on the corp
         design system. Functional hooks preserved 1:1 for courses.js + the
         category AJAX script: form.course-form (action=update) · hidden
         course_id/step/next_step · #category[.select2.category] + #sub_category
         · #price · #discount_price · #tax_rate_id · #toggle-0[name=qna] switcher
         · #attendance_threshold_percent · hidden #toggle-1[certificate] /
         #toggle-2[downloadable] / levels[] / languages[]. --}}
    @include('frontend.instructor-dashboard.settings.partials._corporate')

    <div class="corp-page" id="courseMoreInfo">
        <div class="corp-header">
            <div class="corp-header__title">
                <h4>{{ __('Create Course') }}</h4>
                <p>{{ __('Categorise the course, set its price, and choose a few learning options.') }}</p>
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
            <input type="hidden" name="course_id" value="{{ $courseId }}">
            <input type="hidden" name="step" value="2">
            <input type="hidden" name="next_step" value="3">

            {{-- Category ───────────────────────────────────────────── --}}
            <div class="corp-form-card">
                <div class="corp-form-card__head">
                    <h6 class="corp-form-card__title">
                        <i class="fas fa-sitemap" style="color:var(--corp-brand);"></i> {{ __('Category') }}
                    </h6>
                    <p class="corp-form-card__sub">{{ __('Helps students find the course in browse and search.') }}</p>
                </div>
                <div class="corp-form-card__body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="corp-field">
                                <label class="corp-field__label" for="category">{{ __('Category') }}<span class="req">*</span></label>
                                <select class="select2 form-select corp-select category" id="category" name="category">
                                    <option value="">{{ __('Select') }}</option>
                                    @if ($categories->isNotEmpty())
                                        @foreach ($categories as $category)
                                            <option @selected($course?->category_id == $category->id) value="{{ $category->id }}">
                                                {{ $category->translation?->name }}
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                                @error('category')<span class="corp-field__hint" style="color:#b91c1c;">{{ $message }}</span>@enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="corp-field">
                                <label class="corp-field__label" for="sub_category">{{ __('Sub Category') }}<span class="req">*</span></label>
                                <select class="select2 form-select corp-select" id="sub_category" name="sub_category">
                                    @if ($course->sub_category_id != null)
                                        @foreach ($subcategory as $s)
                                            <option value="{{ $s->id }}" @selected($course?->sub_category_id == $s->id)>
                                                {{ $s->translation?->name }}</option>
                                        @endforeach
                                    @else
                                        <option value="">{{ __('Select') }}</option>
                                    @endif
                                </select>
                                @error('sub_category')<span class="corp-field__hint" style="color:#b91c1c;">{{ $message }}</span>@enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Pricing ─────────────────────────────────────────────── --}}
            @php
                $curr = \Illuminate\Support\Facades\Session::get('currency_icon', '$');
                $coachTaxRates = \App\Models\TaxRate::where('coach_id', $course->instructor_id)
                    ->where('status', 'active')->orderByDesc('is_default')->orderBy('id')->get();
            @endphp
            <div class="corp-form-card">
                <div class="corp-form-card__head">
                    <h6 class="corp-form-card__title">
                        <i class="fas fa-tag" style="color:var(--corp-brand);"></i> {{ __('Pricing') }}
                    </h6>
                    <p class="corp-form-card__sub">{{ __('Set the price students pay. Enter 0 to make the course free.') }}</p>
                </div>
                <div class="corp-form-card__body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="corp-field">
                                <label class="corp-field__label" for="price">{{ __('Price') }} ({{ __('In') }} {{ $curr }})<span class="req">*</span>
                                    <span class="corp-field__hint" style="display:inline;">{{ __('Put 0 for free') }}</span></label>
                                <input id="price" name="price" type="text" class="corp-input" value="{{ @$course?->price }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="corp-field">
                                <label class="corp-field__label" for="discount_price">{{ __('Discount Price') }} ({{ __('In') }} {{ $curr }})</label>
                                <input id="discount_price" name="discount_price" type="text" class="corp-input" value="{{ @$course?->discount }}">
                            </div>
                        </div>
                        @if($coachTaxRates->count())
                            <div class="col-md-6">
                                <div class="corp-field">
                                    <label class="corp-field__label" for="tax_rate_id">{{ __('Tax rate') }}
                                        <span class="corp-field__hint" style="display:inline;">({{ __('optional') }})</span></label>
                                    <select name="tax_rate_id" id="tax_rate_id" class="form-select corp-select">
                                        <option value="">{{ __('Use my default') }}</option>
                                        @foreach($coachTaxRates as $tr)
                                            <option value="{{ $tr->id }}" @selected(@$course->tax_rate_id == $tr->id)>
                                                {{ $tr->name }} ({{ rtrim(rtrim(number_format($tr->rate,3),'0'),'.') }}%)
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Learning options ────────────────────────────────────── --}}
            @php $courseFilterOption = $course->filtersOptions->pluck('filter_option_id')->toArray(); @endphp
            <div class="corp-form-card">
                <div class="corp-form-card__head">
                    <h6 class="corp-form-card__title">
                        <i class="fas fa-sliders-h" style="color:var(--corp-brand);"></i> {{ __('Learning Options') }}
                    </h6>
                    <p class="corp-form-card__sub">{{ __('A few defaults you can change any time.') }}</p>
                </div>
                <div class="corp-form-card__body">
                    <div class="corp-toggle-row" style="display:flex;align-items:center;justify-content:space-between;gap:14px;padding:6px 0 16px;border-bottom:1px solid var(--corp-line-soft);flex-wrap:wrap;">
                        <div>
                            <div style="font-size:14px;font-weight:650;color:var(--corp-text);">{{ __('Q&A') }}</div>
                            <div style="font-size:12px;color:var(--corp-muted);">{{ __('Let students ask questions on lessons.') }}</div>
                        </div>
                        <div class="switcher">
                            <label for="toggle-0">
                                <input type="checkbox" @checked($course?->qna == 1) id="toggle-0" value="1" name="qna" />
                                <span><small></small></span>
                            </label>
                        </div>
                    </div>

                    <div class="corp-field" style="margin-top:16px;max-width:320px;">
                        <label class="corp-field__label" for="attendance_threshold_percent">{{ __('Attendance threshold (%)') }}</label>
                        <input id="attendance_threshold_percent" name="attendance_threshold_percent" type="number"
                               min="0" max="100" step="1" class="corp-input"
                               value="{{ $course?->attendance_threshold_percent ?? 75 }}">
                        <div class="corp-field__hint">{{ __('Students attending fewer live classes than this are flagged as at-risk. Set 0 to disable.') }}</div>
                    </div>

                    {{-- Hidden / retained fields — keep the payload shape stable for store(). --}}
                    <div class="d-none">
                        <label for="toggle-1"><input type="checkbox" @checked($course?->certificate == 1) id="toggle-1" value="1" name="certificate" /></label>
                        <label for="toggle-2"><input type="checkbox" @checked($course?->downloadable == 1) id="toggle-2" value="1" name="downloadable" /></label>
                    </div>
                    <input type="hidden" name="levels[]" value="">
                    <input type="hidden" name="languages[]" value="">
                </div>
            </div>

            <div class="corp-sticky-bar">
                <div class="corp-sticky-bar__summary">
                    <i class="fas fa-info-circle" style="color:var(--corp-brand);"></i>
                    <span><strong>{{ __('Step 2 of 4') }}</strong> <span class="text-muted">·</span> {{ __('More Infos') }}</span>
                </div>
                <div class="corp-sticky-bar__actions">
                    <button class="btn-corp-primary" type="submit">{{ __('Save & Continue') }} <i class="fas fa-arrow-right"></i></button>
                </div>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('frontend/js/default/courses.js') }}"></script>
    <script>
        $(document).ready(function() {
            $('#category').on('change', function() {
                let categoryId = $(this).val();
                $('#sub_category').html('<option value="">Loading...</option>');
                if (categoryId) {
                    $.ajax({
                        url: "{{ route('courses.subcategories') }}",
                        type: "GET",
                        data: { category_id: categoryId },
                        success: function(response) {
                            let options = '<option value="">Select</option>';
                            response.forEach(function(sub) {
                                options += `<option value="${sub.id}">${sub.name}</option>`;
                            });
                            $('#sub_category').html(options);
                        }
                    });
                } else {
                    $('#sub_category').html('<option value="">Select</option>');
                }
            });
        });
    </script>
@endpush
