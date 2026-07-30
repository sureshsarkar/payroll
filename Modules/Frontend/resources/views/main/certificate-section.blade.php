@extends('admin.master_layout')
@section('title')
    <title>{{ __('Certificate Section') }}</title>
@endsection
@section('admin-content')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>{{ __('Certificate Section') }}</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('admin.dashboard') }}">{{ __('Dashboard') }}</a>
                    </div>
                    <div class="breadcrumb-item">{{ __('Certificate Section') }}</div>
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
                                                href="{{ route('admin.certificate-section.update', ['code' => $language->code]) }}"><i
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
                                <h4>{{ __('Certificate Section') }}</h4>
                            </div>
                            <div class="card-body">
                                <div class="d-flex"> 
                                    @foreach ($certificateSection?->global_content?->images ?? [] as $img)
                                        <div class="m-2 d-flex flex-wrap gap-3">
                                            <img src="{{ asset($img) }}" width="80" height="80"
                                                style="object-fit:cover; border:1px solid #ddd;">
                                        </div>
                                    @endforeach
                                </div>

                                <form action="{{ route('admin.certificate-section.update', ['code' => $code]) }}"
                                    method="post" enctype="multipart/form-data">
                                    @csrf
                                    @method('PUT')


                                    <div class="col-md-4 {{ $code == $languages->first()->code ? '' : 'd-none' }}">
                                        <div class="form-group">
                                            <label>{{ __('Image') }} <br><code>({{ __('Recommended') }}: 370X480
                                                    PX)</code></label>
                                            <div id="image-preview" class="image-preview">
                                                <label for="image-upload" id="image-label">{{ __('Image') }}</label>
                                                <input type="file" name="images[]" id="image-upload" multiple>
                                            </div>
                                            @error('images')
                                                <span class="text-danger">{{ $message }}</span>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="text-center col">
                                        <x-admin.save-button :text="__('Save')">
                                        </x-admin.save-button>
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
@php
    $images = $certificateSection?->global_content?->images ?? [];
    $firstImage = $images[0] ?? null;
@endphp
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
                    // 'background-image': 'url({{ asset($firstImage) }})',
                    'background-size': 'contain',
                    'background-position': 'center',
                    'background-repeat': 'no-repeat'
                });
            });
        </script>
    @endif
    <script>
        $(document).ready(function() {
            $('#translate-btn').on('click', function() {
                translateAllTo("{{ $code }}");
            })
        });
    </script>
@endpush
