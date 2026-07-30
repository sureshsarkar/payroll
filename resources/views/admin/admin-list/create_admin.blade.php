@extends('admin.master_layout')
@section('title')
    <title>{{ __('Add Admin') }}</title>
@endsection
@section('admin-content')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('admin.admin.index') }}" class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
                </div>
                <h1>{{ __('Add Admin') }}</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('admin.dashboard') }}">{{ __('Dashboard') }}</a>
                    </div>
                    <div class="breadcrumb-item active"><a
                            href="{{ route('admin.admin.index') }}">{{ __('Manage Admin') }}</a>
                    </div>
                    <div class="breadcrumb-item">{{ __('Add Admin') }}</div>
                </div>
            </div>

            <div class="section-body">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between">
                                <h4>{{ __('Add Admin') }}</h4>
                                <div>
                                    @adminCan('admin.view')
                                        <a href="{{ route('admin.admin.index') }}" class="btn btn-primary"><i
                                                class="fa fa-arrow-left"></i> {{ __('Back') }}</a>
                                    @endadminCan
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8 offset-md-2">
                                        <form action="{{ route('admin.admin.store') }}" method="POST">
                                            @csrf
                                            {{-- M7 fix (2026-05-12) — labels now associate with
                                                 their inputs via for/id. Also fixes a stale
                                                 id="slug" on the email input (copy-paste from
                                                 another form) — screen-reader users heard
                                                 "slug, edit text" instead of "email". --}}
                                            <div class="row">
                                                <div class="form-group col-12">
                                                    <label for="admin-name">{{ __('Name') }} <span class="text-danger">*</span></label>
                                                    <input type="text" id="admin-name" class="form-control"
                                                        name="name" autocomplete="name" required>
                                                </div>
                                                <div class="form-group col-12">
                                                    <label for="admin-email">{{ __('Email') }} <span class="text-danger">*</span></label>
                                                    <input type="email" id="admin-email" class="form-control"
                                                        name="email" autocomplete="email" required>
                                                </div>
                                                <div class="form-group col-12">
                                                    <label for="admin-password">{{ __('Password') }} <span class="text-danger">*</span></label>
                                                    <input type="password" id="admin-password" class="form-control"
                                                        name="password" autocomplete="new-password" required>
                                                </div>

                                                <div class="form-group col-12">
                                                    <label for="admin-status">{{ __('Status') }} <span class="text-danger">*</span></label>
                                                    <select name="status" id="admin-status" class="form-control" required>
                                                        <option value="active">{{ __('Active') }}</option>
                                                        <option value="inactive">{{ __('Inactive') }}</option>
                                                    </select>
                                                </div>
                                                <div class="form-group col-12">
                                                    <label for="role">{{ __('Assign Role') }} <span
                                                            class="text-danger">*</span></label>
                                                    <select name="role[]" id="role"
                                                        class="form-control select2 @error('role') is-invalid @enderror"
                                                        multiple>
                                                        <option value="" disabled>{{ __('Select Role') }}</option>
                                                        @foreach ($roles as $role)
                                                            <option value="{{ $role->name }}">{{ $role->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="text-center col-md-8 offset-md-2">
                                                    <x-admin.save-button :text="__('Save')"></x-admin.save-button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
