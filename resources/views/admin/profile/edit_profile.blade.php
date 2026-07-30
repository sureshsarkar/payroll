@extends('admin.master_layout')
@section('title')
    <title>{{ __('Edit Profile') }}</title>
@endsection
@section('page-title', __('Edit Profile'))
@section('admin-content')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>{{ __('Edit Profile') }}</h1>
            </div>

            {{-- edit profile area  --}}
            <div class="section-body">
                <div class="row">
                    <div class="col-12">
                        <div class="card profile-widget">
                            <div class="profile-widget-header">
                                {{-- M12 fix (2026-05-12) — was alt="image" with empty src=""
                                     when admin had no picture. Now uses a default avatar and
                                     announces "{name} avatar" to screen readers. --}}
                                <img alt="{{ $admin->name }} {{ __('avatar') }}"
                                    id="profileImgPreview"
                                    src="{{ $admin->image ? asset($admin->image) : asset('backend/img/avatar-1.png') }}"
                                    class="rounded-circle profile-widget-picture">
                            </div>

                            <div class="profile-widget-description">

                                <form @adminCan('admin.profile.update') action="{{ route('admin.profile-update') }}"
                                    @endadminCan enctype="multipart/form-data" method="POST">
                                    @csrf
                                    @method('PUT')
                                    {{-- M7 fix (2026-05-12) — every label now associates with its
                                         input via for/id; pre-fix the labels were unattached and
                                         clicking them didn't focus the field, plus screen readers
                                         couldn't announce them. --}}
                                    <div class="row">
                                        <div class="form-group col-12">
                                            <label for="profileImgInput">{{ __('New Image') }} <code>({{ __('Recommended') }}: 400X400 PX)</code></label>
                                            <input id="profileImgInput" type="file" class="form-control-file"
                                                name="image" accept="image/*">
                                        </div>

                                        <div class="form-group col-12">
                                            <label for="profile-name">{{ __('Name') }} <span class="text-danger">*</span></label>
                                            <input id="profile-name" type="text" class="form-control" value="{{ $admin->name }}"
                                                name="name" autocomplete="name" required>
                                        </div>

                                        <div class="form-group col-12">
                                            <label for="profile-email">{{ __('Email') }} <span class="text-danger">*</span></label>
                                            <input id="profile-email" type="email" class="form-control" value="{{ $admin->email }}"
                                                name="email" autocomplete="email" required>
                                        </div>
                                        <div class="form-group col-12">
                                            <label for="profile-bio">{{ __('Bio') }} <span class="text-danger">*</span></label>
                                            <textarea id="profile-bio" name="bio" class="form-control" rows="4">{{ $admin->bio }}</textarea>
                                        </div>
                                    </div>
                                    @adminCan('admin.profile.update')
                                        <div class="row">
                                            <div class="col-12">
                                                <button class="btn btn-primary">{{ __('Update') }}</button>
                                            </div>
                                        </div>
                                    @endadminCan
                                </form>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
            {{-- edit profile area  --}}

            {{-- edit password area --}}

            <div class="section-body">
                <div class="row">
                    <div class="col-12">
                        <div class="card ">
                            <div class="card-body">
                                <form @adminCan('admin.profile.update') action="{{ route('admin.update-password') }}"
                                    @endadminCan enctype="multipart/form-data" method="POST">
                                    @csrf
                                    @method('PUT')
                                    <div class="row">

                                        <div class="form-group col-12">
                                            <label for="profile-current-password">{{ __('Current Password') }} <span class="text-danger">*</span></label>
                                            <input id="profile-current-password" type="password" class="form-control" name="current_password" autocomplete="current-password" required>
                                        </div>

                                        <div class="form-group col-12">
                                            <label for="profile-new-password">{{ __('Password') }} <span class="text-danger">*</span></label>
                                            <input id="profile-new-password" type="password" class="form-control" name="password" autocomplete="new-password" required minlength="8">
                                        </div>

                                        <div class="form-group col-12">
                                            <label for="profile-password-confirmation">{{ __('Confirm Password') }} <span class="text-danger">*</span></label>
                                            <input id="profile-password-confirmation" type="password" class="form-control" name="password_confirmation" autocomplete="new-password" required minlength="8">
                                        </div>

                                    </div>
                                    @adminCan('admin.profile.update')
                                        <div class="row">
                                            <div class="col-12">
                                                <button class="btn btn-primary">{{ __('Update') }}</button>
                                            </div>
                                        </div>
                                    @endadminCan
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- edit password area --}}

        </section>
    </div>
@endsection
@push('js')
    <script>
        //input image preview function
        "use strict";
        function setupImagePreview(inputSelector, imageElementId) {
            $(document).on("input", "#" + inputSelector, function() {
                var reader = new FileReader();
                reader.onload = function(e) {
                    $("#" + imageElementId).attr("src", e.target.result);
                };
                reader.readAsDataURL(this.files[0]);
            });
        }

        $(document).ready(function() {
            setupImagePreview('profileImgInput', 'profileImgPreview');
        });
    </script>
@endpush
