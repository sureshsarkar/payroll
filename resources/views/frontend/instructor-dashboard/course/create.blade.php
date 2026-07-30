@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
    {{--
        Create Course — Step 1 (Basic Infos). 2026-07-04: rebuilt on the corp
        design system (corp-page / corp-form-card / corp-field / corp-sticky-bar)
        to match the Roles/Settings pages. Every functional hook is preserved
        1:1 for courses.js + navigation.blade:
          form.course-form · hidden step/next_step/edit_mode ·
          #title[name=title] · #type[name=type] · file-manager Choose
          (.file-manager-image/.file-manager + #thumbnail/#path + .file-manager-input) ·
          #demo_video_storage · .upload / .external_link toggled wrappers ·
          name=thumbnail/upload_path/external_path · textarea.text-editor[name=description]
    --}}
    @include('frontend.instructor-dashboard.settings.partials._corporate')

    <div class="corp-page" id="courseCreate">
        <div class="corp-header">
            <div class="corp-header__title">
                <h4>{{ __('Create Course') }}</h4>
                <p>{{ __('Set up the essentials. You can refine content, pricing and settings in the next steps.') }}</p>
            </div>
            <div class="corp-header__actions">
                <a href="{{ route('instructor.courses.index') }}" class="btn-corp-secondary">
                    <i class="fas fa-arrow-left"></i> {{ __('Back to Courses') }}
                </a>
            </div>
        </div>

        @include('frontend.instructor-dashboard.course.navigation')

        <form action="{{ route('instructor.courses.store', ['id' => @$course?->id]) }}"
              class="instructor__profile-form course-form">
            @csrf
            <input type="hidden" name="step" value="1">
            <input type="hidden" name="next_step" value="2">
            <input type="hidden" name="edit_mode"
                   value="{{ isset($editMode) && $editMode == true ? true : false }}">

            {{-- Course basics ─────────────────────────────────────── --}}
            <div class="corp-form-card">
                <div class="corp-form-card__head">
                    <h6 class="corp-form-card__title">
                        <i class="fas fa-mortar-board" style="color:var(--corp-brand);"></i>
                        {{ __('Course Basics') }}
                    </h6>
                    <p class="corp-form-card__sub">{{ __('Give the course a clear title and choose how it will be delivered.') }}</p>
                </div>
                <div class="corp-form-card__body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="corp-field">
                                <label class="corp-field__label" for="title">{{ __('Title') }}<span class="req">*</span></label>
                                <input id="title" name="title" type="text" class="corp-input"
                                       value="{{ @$course?->title }}" placeholder="{{ __('e.g. Morning Hatha Yoga — 6 Week Live Program') }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            @php
                                $currentType = @$course?->type ?: 'live';
                                $typeOptions = [
                                    'live'     => __('Live Class'),
                                    'recorded' => __('Recorded Course'),
                                    'hybrid'   => __('Live + Recorded'),
                                ];
                            @endphp
                            <div class="corp-field">
                                <label class="corp-field__label" for="type">{{ __('Course Type') }}<span class="req">*</span></label>
                                <select name="type" id="type" class="form-select corp-select">
                                    @foreach ($typeOptions as $value => $label)
                                        <option value="{{ $value }}" @selected($currentType === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Media ──────────────────────────────────────────────── --}}
            <div class="corp-form-card">
                <div class="corp-form-card__head">
                    <h6 class="corp-form-card__title">
                        <i class="fas fa-image" style="color:var(--corp-brand);"></i>
                        {{ __('Media') }}
                    </h6>
                    <p class="corp-form-card__sub">{{ __('The thumbnail is what students see first. A demo video is optional but boosts enrolments.') }}</p>
                </div>
                <div class="corp-form-card__body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="corp-field">
                                <label class="corp-field__label">{{ __('Thumbnail') }}<span class="req">*</span>
                                    <span class="corp-field__hint" style="display:inline;">({{ __('Recommended') }}: 690×420 px)</span></label>
                                <div class="input-group">
                                    <span class="input-group-text" id="basic-addon1">
                                        <a data-input="thumbnail" data-preview="holder" class="file-manager-image">
                                            <i class="fa fa-picture-o"></i> {{ __('Choose') }}
                                        </a>
                                    </span>
                                    <input id="thumbnail" readonly class="form-control file-manager-input"
                                           type="text" name="thumbnail" value="{{ @$course?->thumbnail }}"
                                           placeholder="{{ __('No file selected') }}">
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="corp-field">
                                <label class="corp-field__label" for="demo_video_storage">{{ __('Demo Video') }}
                                    <span class="corp-field__hint" style="display:inline;">({{ __('optional') }})</span></label>
                                <select name="demo_video_storage" id="demo_video_storage" class="form-select corp-select">
                                    <option @selected(@$course?->demo_video_storage == 'upload') value="upload">{{ __('upload') }}</option>
                                    <option @selected(@$course?->demo_video_storage == 'youtube') value="youtube">{{ __('youtube') }}</option>
                                    <option @selected(@$course?->demo_video_storage == 'external_link') value="external_link">{{ __('external_link') }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6 upload {{ @$course?->demo_video_storage == 'upload' ? '' : 'd-none' }}">
                            <div class="corp-field">
                                <label class="corp-field__label">{{ __('Path') }}</label>
                                <div class="input-group">
                                    <span class="input-group-text" id="basic-addon1">
                                        <a data-input="path" data-preview="holder" class="file-manager">
                                            <i class="fa fa-picture-o"></i> {{ __('Choose') }}
                                        </a>
                                    </span>
                                    <input id="path" readonly class="form-control file-manager-input"
                                           type="text" name="upload_path" value="{{ @$course?->demo_video_source }}"
                                           placeholder="{{ __('No file selected') }}">
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 external_link {{ @$course?->demo_video_storage != 'upload' ? '' : 'd-none' }}">
                            <div class="corp-field">
                                <label class="corp-field__label">{{ __('Path') }}</label>
                                <div class="input-group">
                                    <span class="input-group-text" id="basic-addon1"><i class="fas fa-link"></i></span>
                                    <input type="text" class="form-control" name="external_path"
                                           placeholder="{{ __('Paste your external link') }}"
                                           value="{{ @$course?->demo_video_source }}">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Description ─────────────────────────────────────────── --}}
            <div class="corp-form-card">
                <div class="corp-form-card__head">
                    <h6 class="corp-form-card__title">
                        <i class="fas fa-align-left" style="color:var(--corp-brand);"></i>
                        {{ __('Description') }}<span class="req" style="color:#e11d63;">*</span>
                    </h6>
                    <p class="corp-form-card__sub">{{ __('Tell students what they’ll learn, who it’s for, and what they need to get started.') }}</p>
                </div>
                <div class="corp-form-card__body">
                    <div class="corp-field">
                        <textarea name="description" class="text-editor">{!! clean(@$course?->description) !!}</textarea>
                    </div>
                </div>
            </div>

            {{-- Sticky action bar ───────────────────────────────────── --}}
            <div class="corp-sticky-bar">
                <div class="corp-sticky-bar__summary">
                    <i class="fas fa-info-circle" style="color:var(--corp-brand);"></i>
                    <span><strong>{{ __('Step 1 of 4') }}</strong> <span class="text-muted">·</span> {{ __('Basic Infos') }}</span>
                </div>
                <div class="corp-sticky-bar__actions">
                    <button class="btn-corp-primary" type="submit">
                        {{ __('Save & Continue') }} <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('frontend/js/default/courses.js') }}"></script>
@endpush
