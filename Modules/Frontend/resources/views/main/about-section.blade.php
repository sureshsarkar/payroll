@extends('admin.master_layout')
@section('title')
    <title>{{ __('About Section') }}</title>
@endsection
@section('admin-content')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>{{ __('About Section') }}</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('admin.dashboard') }}">{{ __('Dashboard') }}</a>
                    </div>
                    <div class="breadcrumb-item">{{ __('About Section') }}</div>
                </div>
            </div>
            <div class="section-body row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="service_card">{{ __('Available Translations') }}</h5>

                            <hr>
                            @if ($code !== $languages->first()->code)
                                <button class="btn btn-primary" id="translate-btn">{{ __('Translate') }}</button>
                            @endif

                        </div>
                        <div class="card-body">
                            <div class="lang_list_top">
                                <ul class="lang_list">
                                    @foreach ($languages as $language)
                                        <li><a id="{{ request('code') == $language->code ? 'selected-language' : '' }}"
                                                href="{{ route('admin.about-section.index', ['code' => $language->code]) }}"><i
                                                    class="fas {{ request('code') == $language->code ? 'fa-eye' : 'fa-edit' }}"></i>
                                                {{ $language->name }}</a></li>
                                    @endforeach
                                </ul>
                            </div>

                            <div class="mt-2 alert alert-danger" role="alert">
                                @php
                                    $current_language = $languages->where('code', request()->get('code'))->first();
                                @endphp
                                <p>{{ __('Your editing mode') }} :
                                    <b>{{ $current_language?->name }}</b>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="section-body">
                <div class="mt-4 row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between">
                                <h4>{{ __('About Section') }}</h4>
                            </div>
                            <div class="card-body">
                                <form
                                    action="{{ route('admin.about-section.update', ['code' => $code]) }}"
                                    method="post" enctype="multipart/form-data">
                                    @csrf
                                    @method('PUT')

                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="short_title">{{ __('Short Title') }}<span
                                                    class="text-danger">*</span></label>
                                                <input data-translate="true" type="text" id="short_title"
                                                    name="short_title"
                                                    value="{{ $aboutSection->getTranslation($code)?->content?->short_title }}"
                                                    placeholder="{{ __('Enter Subtitle') }}" class="form-control">
                                                @error('short_title')
                                                    <span class="text-danger">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="title">{{ __('Title') }}<span
                                                        class="text-danger">*</span></label>
                                                <input data-translate="true" type="text" id="title" name="title"
                                                    value="{{ $aboutSection?->getTranslation($code)?->content?->title }}"
                                                    placeholder="{{ __('Enter Title') }}" class="form-control">
                                                <small>{{ __('wrap your word with [] for highlight and \ for break and {} for bold') }}</small>
                                                @error('title')
                                                    <span class="text-danger">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>

                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="description">{{ __('Description') }}<span
                                                        class="text-danger">*</span></label>
                                                <textarea data-translate="true" name="description" class="summernote_code">{{ $aboutSection?->getTranslation($code)?->content?->description }}</textarea>
                                                @error('description')
                                                    <span class="text-danger">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>

                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="our_courses_description">{{ __('Our Courses Description') }}<span
                                                        class="text-danger">*</span></label>
                                                <textarea data-translate="true" name="our_courses_description" class="summernote_code">{{ $aboutSection?->getTranslation($code)?->content?->our_courses_description }}</textarea>
                                                @error('our_courses_description')
                                                    <span class="text-danger">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>

                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="about_page_description">{{ __('About Page Description') }}<span
                                                        class="text-danger">*</span></label>
                                                <textarea data-translate="true" name="about_page_description" class="summernote_code">{{ $aboutSection?->getTranslation($code)?->content?->about_page_description }}</textarea>
                                                @error('about_page_description')
                                                    <span class="text-danger">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>

                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="our_mission_about_page_description">{{ __('Our Mission About Page Description') }}<span
                                                        class="text-danger">*</span></label>
                                                <textarea data-translate="true" name="our_mission_about_page_description" class="summernote_code">{{ $aboutSection?->getTranslation($code)?->content?->our_mission_about_page_description }}</textarea>
                                                @error('our_mission_about_page_description')
                                                    <span class="text-danger">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>

                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="terms_and_conditions_page_description">{{ __('Terms and Conditions Description') }}<span
                                                        class="text-danger">*</span></label>
                                                <textarea data-translate="true" name="terms_and_conditions_page_description" class="summernote_code">{{ $aboutSection?->getTranslation($code)?->content?->terms_and_conditions_page_description }}</textarea>
                                                @error('terms_and_conditions_page_description')
                                                    <span class="text-danger">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>

                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="privacy_policy_page_description">{{ __('Privacy Policy Description') }}<span
                                                        class="text-danger">*</span></label>
                                                <textarea data-translate="true" name="privacy_policy_page_description" class="summernote_code">{{ $aboutSection?->getTranslation($code)?->content?->privacy_policy_page_description }}</textarea>
                                                @error('privacy_policy_page_description')
                                                    <span class="text-danger">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>


                                        <div class="col-lg-6 {{ $code == $languages->first()->code ? '' : 'd-none' }}">
                                            <div class="form-group">
                                                <label for="video_url">{{ __('Video url') }}<span class="text-danger">({{ __('leave blank for hide') }})</span></label>
                                                <input type="text" id="video_url" name="video_url"
                                                    value="{{ $aboutSection?->global_content?->video_url }}"
                                                    placeholder="{{ __('Video url') }}" class="form-control">
                                                @error('video_url')
                                                    <span class="text-danger">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="button_text">{{ __('Button Text') }}<span class="text-danger">
                                                        ({{ __('leave blank for hide') }})</span></label>
                                                <input data-translate="true" type="text" id="button_text"
                                                    name="button_text"
                                                    value="{{ $aboutSection?->getTranslation($code)?->content?->button_text }}"
                                                    placeholder="{{ __('Button Text') }}" class="form-control">
                                                @error('button_text')
                                                    <span class="text-danger">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>

                                        <div class="col-md-6 {{ $code == $languages->first()->code ? '' : 'd-none' }}">
                                            <div class="form-group">
                                                <label for="button_url">{{ __('Button url') }}</label>
                                                <input type="text" id="button_url" name="button_url"
                                                    value="{{ $aboutSection?->global_content?->button_url }}"
                                                    placeholder="{{ __('Button Url') }}" class="form-control">
                                                @error('button_url')
                                                    <span class="text-danger">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-3 {{ $code == $languages->first()->code ? '' : 'd-none' }}">
                                            <div class="form-group">
                                                <label>{{ __('Image') }} <br><code>({{ __('Recommended') }}: 520X472 PX)</code></label>
                                                <div id="image-preview" class="image-preview">
                                                    <label for="image-upload"
                                                        id="image-label">{{ __('Image') }}</label>
                                                    <input type="file" name="image" id="image-upload">
                                                </div>
                                                @error('image')
                                                    <span class="text-danger">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>

                                        <div class="col-md-3 {{ $code == $languages->first()->code ? '' : 'd-none' }}">
                                            <div class="form-group">
                                                <label>{{ __('Banner Image') }} <br><code>({{ __('Recommended') }}: 520X472 PX)</code></label>
                                                <div id="banner_image-preview" class="image-preview">
                                                    <label for="banner_image-upload"
                                                        id="banner_image-label">{{ __('Banner Image') }}</label>
                                                    <input type="file" name="banner_image" id="banner_image-upload">
                                                </div>
                                                @error('banner_image')
                                                    <span class="text-danger">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="col-md-3 {{ $code == $languages->first()->code ? '' : 'd-none' }}">
                                            <div class="form-group">
                                                <label>{{ __('About Page Image') }} <br><code>({{ __('Recommended') }}: 520X472 PX)</code></label>
                                                <div id="about_image-preview" class="image-preview">
                                                    <label for="about_image-upload"
                                                        id="about_image-label">{{ __('About Page Image') }}</label>
                                                    <input type="file" name="about_image" id="about_image-upload">
                                                </div>
                                                @error('about_image')
                                                    <span class="text-danger">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>

                                        {{-- @php 
                                            echo  asset($aboutSection?->global_content?->image);
                                            exit;
                                        @endphp --}}

                                        <div class="col-md-3 {{ $code == $languages->first()->code ? '' : 'd-none' }}">
                                            <div class="form-group">
                                                <label>{{ __('Our Mission About Page Image') }} <br><code>({{ __('Recommended') }}: 520X472 PX)</code></label>
                                                <div id="our_mission-image-preview" class="image-preview">
                                                    <label for="image-upload"
                                                        id="our_mission-image-label">{{ __('Our Mission About Page Image') }}</label>
                                                    <input type="file" name="our_mission_image" id="our_mission-image-upload">
                                                </div>
                                                @error('our_mission-image')
                                                    <span class="text-danger">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="text-center col-12">
                                            <x-admin.save-button :text="__('Save')">
                                            </x-admin.save-button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection

@push('js')
@if ($code == $languages->first()->code)
    <script src="{{ asset('backend/js/jquery.uploadPreview.min.js') }}"></script>
    <script>
        $(document).ready(function() {
            $.uploadPreview({
                input_field: "#image-upload",
                preview_box: "#image-preview",
                label_field: "#image-label",
                label_default: "{{ __('Choose Image') }}",
                label_selected: "{{ __('Change Image') }}",
                no_label: false,
                success_callback: null
            });
            $('#image-preview').css({
                'background-image': "url('{{ asset($aboutSection?->global_content?->image) }}')",
                'background-size': 'contain',
                'background-position': 'center',
                'background-repeat': 'no-repeat'
            });

            $.uploadPreview({
                input_field: "#banner_image-upload",
                preview_box: "#banner_image-preview",
                label_field: "#banner_image-label",
                label_default: "{{ __('Choose Image') }}",
                label_selected: "{{ __('Change Image') }}",
                no_label: false,
                success_callback: null
            });
            $('#banner_image-preview').css({
                'background-image': "url('{{ asset($aboutSection?->global_content?->banner_image) }}')",
                'background-size': 'contain',
                'background-position': 'center',
                'background-repeat': 'no-repeat'
            });

            $.uploadPreview({
                input_field: "#about_image-upload",
                preview_box: "#about_image-preview",
                label_field: "#about_image-label",
                label_default: "{{ __('Choose Image') }}",
                label_selected: "{{ __('Change Image') }}",
                no_label: false,
                success_callback: null
            });
            $('#about_image-preview').css({
                'background-image': "url('{{ asset($aboutSection?->global_content?->about_image) }}')",
                'background-size': 'contain',
                'background-position': 'center',
                'background-repeat': 'no-repeat'
            });


            $.uploadPreview({
                input_field: "#our_mission-image-upload",
                preview_box: "#our_mission-image-preview",
                label_field: "#our_mission-image-label",
                label_default: "{{ __('Choose Image') }}",
                label_selected: "{{ __('Change Image') }}",
                no_label: false,
                success_callback: null
            });
            $('#our_mission-image-preview').css({
                'background-image': "url('{{ asset($aboutSection?->global_content?->our_mission_image) }}')",
                'background-size': 'contain',
                'background-position': 'center',
                'background-repeat': 'no-repeat'
            });

            $('#translate-btn').on('click', function() {
                translateAllTo("{{ $code }}");
            });
        });
    </script>
    @endif
    <script>
        $('#translate-btn').on('click', function() {
            translateAllTo("{{ $code }}");
        })
    </script>
@endpush
