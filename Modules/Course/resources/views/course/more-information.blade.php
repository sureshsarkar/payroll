@extends('admin.master_layout')
@section('title')
    <title>{{ __('Course Create') }}</title>
@endsection
@section('admin-content')
    {{-- @php 
        echo "<pre>",print_r($categories);
        exit;
     @endphp --}}
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1 class="text-primary">{{ __('Course') }}</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('admin.dashboard') }}">{{ __('Dashboard') }}</a>
                    </div>
                    <div class="breadcrumb-item">{{ __('Course Create') }}</div>
                </div>
            </div>
            <div class="section-body">
                <div class="row">
                    <div class="col-12">
                        @include('course::course.navigation')

                        <div class="card">
                            <div class="card-body">
                                <div class="instructor__profile-form-wrap">
                                    <form action="{{ route('admin.courses.update') }}"
                                        class="instructor__profile-form course-form">
                                        @csrf
                                        <input type="hidden" name="course_id" id="" value="{{ $courseId }}">
                                        <input type="hidden" name="step" id="" value="2">
                                        <input type="hidden" name="next_step" value="3">

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="from-group">
                                                    <label for="capacity">{{ __('Capacity') }} <code></code></label>
                                                    <input id="capacity" name="capacity" class="form-control"
                                                        type="text" value="{{ $course?->capacity }}">
                                                    <code>{{ __('leave blank for unlimited') }}</code>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="from-group">
                                                    <label for="course_duration">{{ __('Course Duration (Minutes)') }}
                                                        <code>*</code></label>
                                                    <input id="course_duration" name="course_duration" class="form-control"
                                                        type="text" value="{{ $course?->duration }}">
                                                </div>
                                            </div>

                                            <div class="col-12 my-2">

                                                <div class="row w-100">
                                                    <div class="col-md-6">
                                                        <p>{{ __('Q&A') }}</p>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="switcher ms-3">
                                                            <label for="toggle-0">
                                                                <input type="checkbox" @checked($course?->qna == 1)
                                                                    id="toggle-0" value="1" name="qna" />
                                                                <span><small></small></span>
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>

                                                {{-- 2026-05-29 Doc-A2 / Doc-C-Course: Completion Certificate
                                                     toggle removed from the UI per product feedback (cert
                                                     issuance is not a coach-configurable workflow yet).
                                                     The DB column + controller path are preserved; a hidden
                                                     input echoes the existing value so save round-trips
                                                     don't accidentally null it. --}}
                                                <input type="hidden" name="certificate" value="{{ $course?->certificate ?? 0 }}">

                                                {{-- <div class="row w-100">
                                                    <div class="col-md-6">
                                                        <p>{{ __('Downloadable') }}</p>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="switcher ms-3">
                                                            <label for="toggle-2">
                                                                <input type="checkbox" @checked($course?->downloadable == 1) id="toggle-2"
                                                                    value="1" name="downloadable" />
                                                                <span><small></small></span>
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div> --}}

                                                {{-- <div class="row w-100">
                                                    <div class="col-md-6">
                                                        <p>{{ __('Patner instructor') }}</p>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="switcher ms-3">
                                                            <label for="toggle-3">
                                                                <input class="partner_instructor_btn"
                                                                    @checked($course?->partner_instructor == 1) value="1"
                                                                    type="checkbox" id="toggle-3"
                                                                    name="partner_instructor" />
                                                                <span><small></small></span>
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-md-12">
                                                <div
                                                    class="partner_instructor_list {{ $course?->partner_instructor == 0 ? 'd-none' : '' }}">
                                                    <label for="cpacity">{{ __('Select a partner instructor') }}
                                                        <code></code></label>
                                                    <select class="select2 partner_instructor_select"
                                                        name="partner_instructors[]" multiple="multiple">
                                                        @foreach ($course?->partnerInstructors as $instructor)
                                                            <option value="{{ $instructor->instructor->id }}"
                                                                selected="selected">
                                                                {{ $instructor?->instructor->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div> --}}



                                            {{-- <div class="col-md-6 mt-2">
                                                <div class="from-group">
                                                    <label for="category">{{ __('Category') }}<span
                                                            class="text-danger">*</span></label>  
                                                    <select class="select2 form-group category" name="category"> 
                                                        <option value="">{{ __('Select') }}</option>
                                                        
                                                        @foreach ($categories as $category)
                                                            @if ($category->subCategories->isNotEmpty())
                                                               
                                                                <optgroup label="{{ $category->translation?->name }}">
                                                                    @foreach ($category->subCategories as $subCategory)
                                                                        <option @selected($course?->category_id == $subCategory->id)
                                                                            value="{{ $subCategory->id }}">
                                                                            {{ $subCategory->translation?->name }}
                                                                        </option>
                                                                    @endforeach
                                                                </optgroup>
                                                            @endif
                                                        @endforeach
                                                    </select>
                                                    @error('category')
                                                        <span class="text-danger">{{ $message }}</span>
                                                    @enderror
                                                </div>
                                            </div> --}}


                              <div class="row">              
                            <div class="col-md-6">
                                <div class="from-group">
                                    <label for="category">{{ __('Category') }}<span class="text-danger">*</span></label>
                                    <select class="select2 form-select category" id="category" name="category">
                                        <option value="">{{ __('Select') }}</option>
                                        @if ($categories->isNotEmpty())
                                            @foreach ($categories as $category)
                                                <option @selected($course?->category_id == $category->id) value="{{ $category->id }}">
                                                    {{ $category->translation?->name }}
                                                </option>
                                            @endforeach
                                        @endif
                                    </select>
                                    @error('category')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="from-group">
                                    <label for="category">{{ __('Sub Category') }}<span
                                            class="text-danger">*</span></label>
                                    <select class="select2 form-select" id="sub_category" name="sub_category">
                                        @if ($course->sub_category_id !=null)
                                        @foreach ($subcategory as $s)
                                           <option value="{{ $s->id }}" @selected($course?->sub_category_id == $s->id)>{{$s->translation?->name }}</option> 
                                        @endforeach 
                                        @else 
                                        <option value="">{{ __('Select') }}</option>
                                        @endif
                                    </select>
                                    @error('sub_category')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                         </div>




                                            {{-- 2026-05-29 Doc-A2 / Doc-C-Course: Level + Language
                                                 sections removed from the UI per product feedback.
                                                 Existing data + relations preserved; hidden inputs
                                                 echo the current selections back so save round-trips
                                                 don't drop them.

                                                 To restore: revert this commit; the original markup
                                                 is in git history. The $levels + $languages payloads
                                                 from the controller are still passed in, so this
                                                 view + the form data flow remain backward-compatible. --}}
                                            @php
                                                $courseLevel = $course->levels->pluck('level_id')->toArray();
                                                $courseLanguage = $course->languages->pluck('language_id')->toArray();
                                                $courseFilterOption = $course->filtersOptions->pluck('filter_option_id')->toArray();
                                            @endphp
                                            @foreach ($courseLevel as $existingLevelId)
                                                <input type="hidden" name="levels[]" value="{{ $existingLevelId }}">
                                            @endforeach
                                            @foreach ($courseLanguage as $existingLangId)
                                                <input type="hidden" name="languages[]" value="{{ $existingLangId }}">
                                            @endforeach

                                        </div>
                                </div>

                                <div class="col-md-12 mt-3">
                                    <button class="btn btn-primary" type="submit">{{ __('Save') }}</button>
                                </div>
                            </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
    </div>
    </div>
    </section>
    </div>
@endsection

@push('js')
    {{-- <script>
        $(document).ready(function() {
           
        });
    </script> --}}

    <script src="{{ asset('backend/js/default/courses.js') }}"></script>


    
    <script>
        $(document).ready(function() {

            $('#category').on('change', function() {
                let categoryId = $(this).val();

                $('#sub_category').html('<option value="">Loading...</option>');

                if (categoryId) {
                    $.ajax({
                        url: "{{ route('courses.subcategories') }}",
                        type: "GET",
                        data: {
                            category_id: categoryId
                        },
                        success: function(response) {

                            let options = '<option value="">Select</option>';

                            response.forEach(function(sub) {
                                options +=
                                    `<option value="${sub.id}">${sub.name}</option>`;
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



    <script>
        $(document).ready(function() {
            const $name = $("#title"),
                $slug = $("#slug");

            $name.on("keyup", function(e) {
                $slug.val(convertToSlug($name.val()));
            });

            function convertToSlug(text) {
                return text
                    .toLowerCase()
                    .replace(/[^a-z\s-]/g, "") // Remove all non-word characters (except -)
                    .replace(/\s+/g, "-") // Replace spaces with -
                    .replace(/-+/g, "-"); // Replace multiple - with single -
            }
        })
    </script>
@endpush

@push('css')
    <style>
        .dd-custom-css {
            position: absolute;
            will-change: transform;
            top: 0px;
            left: 0px;
            transform: translate3d(0px, -131px, 0px);
        }

        .max-h-400 {
            min-height: 400px;
        }
    </style>
@endpush
