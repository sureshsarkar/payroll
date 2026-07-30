@extends('admin.master_layout')
@section('title')
    <title>{{ __('Create Student') }}</title>
@endsection
@section('admin-content')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>{{ __('Create Student') }}</h1>
            </div>

            <div class="section-body">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between">
                                <h4>{{ __('Create Student') }}</h4>
                                <div>
                                    <a href="{{ route('admin.all-customers') }}" class="btn btn-primary"><i
                                            class="fa fa-arrow-left"></i> {{ __('Back') }}</a>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8 offset-md-2">
                                        <form action="{{ route('admin.store-customer') }}" method="POST">
                                            @csrf
                                            <div class="row">
                                                <div class="form-group col-12">
                                                    <label for="name">{{ __('Name') }} <span class="text-danger">*</span></label>
                                                    <input type="text" id="name" class="form-control"
                                                        name="name" value="{{old('name')}}">
                                                </div>
                                                <div class="form-group col-12">
                                                    <label for="email">{{ __('Email') }} <span class="text-danger">*</span></label>
                                                    <input type="email" id="slug" class="form-control"
                                                        name="email" value="{{old('email')}}">
                                                </div>
                                                <div class="form-group col-12">
                                                    <label for="password">{{ __('Password') }} <span class="text-danger">*</span></label>
                                                    <input type="password" id="password" class="form-control"
                                                        name="password">
                                                </div>

                                                <div class="form-group col-12">
                                                    <label for="status">{{ __('Status') }} <span class="text-danger">*</span></label>
                                                    <select id="status" name="status" class="form-control">
                                                        <option @selected(old('status') == 'active') value="active">{{ __('Active') }}</option>
                                                        <option @selected(old('status') == 'inactive') value="inactive">{{ __('Inactive') }}</option>
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
