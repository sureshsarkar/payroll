@extends('admin.master_layout') @section('title')
    <title>{{ __('General Setting') }}</title>
    @endsection @section('admin-content')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('admin.settings') }}" class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
                </div>
                <h1>{{ __('General Setting') }}</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active">
                        <a href="{{ route('admin.dashboard') }}">{{ __('Dashboard') }}</a>
                    </div>
                    <div class="breadcrumb-item active">
                        <a href="{{ route('admin.settings') }}">{{ __('Settings') }}</a>
                    </div>
                    <div class="breadcrumb-item">{{ __('General Setting') }}</div>
                </div>
            </div>
            <div class="section-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <ul class="nav nav-pills flex-column" id="generalTab" role="tablist">
                                    @include('globalsetting::settings.tabs.navbar')
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-body">
                                <div class="tab-content" id="myTabContent2">
                                    @include('globalsetting::settings.sections.general')
                                    @include('globalsetting::settings.sections.logo-favicon')
                                    @include('globalsetting::settings.sections.cookie')
                                    @include('globalsetting::settings.sections.custom-paginate')
                                    @include('globalsetting::settings.sections.breadcrump')
                                    @include('globalsetting::settings.sections.copyright')
                                    @include('globalsetting::settings.sections.maintenance-mode')
                                    @include('globalsetting::settings.sections.watermark')
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
    <script src="{{ asset('backend/js/jquery.uploadPreview.min.js') }}"></script>
    <script>
        //Tab active setup locally
        $(document).ready(function() {
            "use strict";
            var activeTab = localStorage.getItem("activeTab");
            if (activeTab) {
                $('#generalTab a[href="#' + activeTab + '"]').tab("show");
            } else {
                $("#generalTab a:first").tab("show");
            }

            $('a[data-toggle="tab"]').on("shown.bs.tab", function(e) {
                var newTab = $(e.target).attr("href").substring(1);
                localStorage.setItem("activeTab", newTab);
            });

            $.uploadPreview({
                input_field: "#image-upload-breadcrumb",
                preview_box: "#image-preview-breadcrumb",
                label_field: "#image-label-breadcrumb",
                label_default: "{{ __('Choose Image') }}",
                label_selected: "{{ __('Change Image') }}",
                no_label: false,
                success_callback: null
            });

            $('#image-preview-breadcrumb').css({
                'background-image': 'url({{ asset($setting->breadcrumb_image) }})',
                'background-size': 'contain',
                'background-position': 'center',
                'background-repeat': 'no-repeat'
            });

            $.uploadPreview({
                input_field: "#image-maintenance",
                preview_box: "#image-preview-maintenance",
                label_field: "#image-label-maintenance",
                label_default: "{{ __('Choose Image') }}",
                label_selected: "{{ __('Change Image') }}",
                no_label: false,
                success_callback: null
            });

            $('#image-preview-maintenance').css({
                'background-image': 'url({{ asset($setting?->maintenance_image) }})',
                'background-size': 'contain',
                'background-position': 'center',
                'background-repeat': 'no-repeat'
            });

            $.uploadPreview({
                input_field: "#image-upload-1",
                preview_box: "#image-preview-1",
                label_field: "#image-label-1",
                label_default: "{{ __('Choose Image') }}",
                label_selected: "{{ __('Change Image') }}",
                no_label: false,
                success_callback: null
            });
            $('#image-preview-1').css({
                'background-image': 'url({{ !empty($setting->logo) ? asset($setting->logo) : '' }})',
                'background-size': 'contain',
                'background-position': 'center',
                'background-repeat': 'no-repeat'
            });

            $.uploadPreview({
                input_field: "#image-upload-2",
                preview_box: "#image-preview-2",
                label_field: "#image-label-2",
                label_default: "{{ __('Choose Image') }}",
                label_selected: "{{ __('Change Image') }}",
                no_label: false,
                success_callback: null
            });
            $('#image-preview-2').css({
                'background-image': 'url({{ asset(@$setting->favicon) }})',
                'background-size': 'contain',
                'background-position': 'center',
                'background-repeat': 'no-repeat'
            });

            $.uploadPreview({
                input_field: "#image-upload-3",
                preview_box: "#image-preview-3",
                label_field: "#image-label-3",
                label_default: "{{ __('Choose Image') }}",
                label_selected: "{{ __('Change Image') }}",
                no_label: false,
                success_callback: null
            });
            $('#image-preview-3').css({
                'background-image': 'url({{ asset(@$setting->preloader) }})',
                'background-size': 'contain',
                'background-position': 'center',
                'background-repeat': 'no-repeat'
            });
            $.uploadPreview({
            input_field: "#image-upload-4",
            preview_box: "#image-preview-4",
            label_field: "#image-label-4",
            label_default: "{{ __('Choose Image') }}",
            label_selected: "{{ __('Change Image') }}",
            no_label: false,
            success_callback: null
        });
        $('#image-preview-4').css({
            'background-image': "url({{ asset(@$setting?->watermark_img ?? 'uploads/website-images/watermark.svg') }})",
            'background-size': 'contain',
            'background-position': 'center',
            'background-repeat': 'no-repeat'
        });

        });
        //Maintenance mode toggler
        function changeMaintenanceModeStatus() {
            var isDemo = "{{ strtoupper(config('app.app_mode')) ?? 'LIVE' }}"
            if (isDemo == 'DEMO') {
                toastr.error("{{ __('This Is Demo Version. You Can Not Change Anything') }}");
                return;
            }
            $.ajax({
                type: "put",
                data: {
                    _token: '{{ csrf_token() }}',
                },
                url: "{{ url('/admin/update-maintenance-mode-status') }}",
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                    } else {
                        toastr.warning(response.message);
                    }
                },
                error: function(err) {
                    console.log(err);

                }
            })
        }
    </script>

    <script>
        function copyText() {
            var textToCopy = document.getElementById("copyCronText");
            var range = document.createRange();
            range.selectNode(textToCopy);
            window.getSelection().removeAllRanges();
            window.getSelection().addRange(range);
            document.execCommand("copy");

            toastr.success("{{ __('Copied to clipboard') }}");
        }
    </script>
@endpush
