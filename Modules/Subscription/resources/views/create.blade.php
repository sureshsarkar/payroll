@extends('admin.master_layout')
@section('title')
    <title>{{ __('Order Details') }}</title>
@endsection
@section('admin-content')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>{{ __('Subscription') }}</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('admin.dashboard') }}">{{ __('Dashboard') }}</a>
                    </div>
                    <div class="breadcrumb-item">{{ __('Subscription') }}</div>
                </div>
            </div>

            <div class="section-body">
                <div class="invoice">
                    <div class="invoice-print">
                        <div class="row ">
                            <div class="col-md-12">
                                <form action="{{ route('admin.subscriptions.store') }}" method="POST" class="d-print-none">
                                    @csrf
                                    <div class="row ">
                                        <div class="col-lg-4">
                                            <div class="section-title">{{ __('Name') }}*</div>
                                            <div class="form-group">
                                                <input name="name" type="text"
                                                    class="form-control @error('name') is-invalid @enderror" id="role_name"
                                                    placeholder="{{ __('Enter Name') }}">
                                                @error('name')
                                                    <span class="invalid-feedback"
                                                        role="alert"><strong>{{ $message }}</strong></span>
                                                @enderror
                                            </div>
                                        </div>

                                        <div class="col-lg-4">
                                            <div class="section-title">{{ __('Price') }}*</div>
                                            <div class="form-group">
                                                <input name="price" type="number"
                                                    class="form-control @error('price') is-invalid @enderror" id="role_name"
                                                    placeholder="{{ __('Enter Price') }}">
                                                @error('price')
                                                    <span class="invalid-feedback"
                                                        role="alert"><strong>{{ $message }}</strong></span>
                                                @enderror
                                            </div>
                                        </div>

                                        <div class="col-lg-4">
                                            <div class="section-title">{{ __('Enter Duration Days') }}*</div>
                                            <div class="form-group">
                                                <input name="duration_days" type="number"
                                                    class="form-control @error('duration_days') is-invalid @enderror"
                                                    id="role_name" placeholder="{{ __('Enter Duration Days') }}">
                                                @error('duration_days')
                                                    <span class="invalid-feedback"
                                                        role="alert"><strong>{{ $message }}</strong></span>
                                                @enderror
                                            </div>
                                        </div>


                                        <div class="col-lg-4">
                                            <div class="section-title">{{ __('Subscription Status') }}*</div>
                                            <select name="status" class="form-control" required>
                                                <option value="">Choose Status</option>
                                                <option value="active">Active</option>
                                                <option value="inactive">Inactive</option>
                                            </select>
                                        </div>

                                        <div class="col-lg-8">
                                            <div class="section-title">{{ __('Description') }}*</div>
                                            <div class="form-group">
                                                <input name="description" type="text"
                                                    class="form-control @error('description') is-invalid @enderror"
                                                    id="description" placeholder="{{ __('Enter Description') }}">
                                                @error('description')
                                                    <span class="invalid-feedback"
                                                        role="alert"><strong>{{ $message }}</strong></span>
                                                @enderror
                                            </div>
                                        </div>


                                    </div>
                                    <button type="submit" class="btn btn-primary mt-4">{{ __('Submit') }}</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="text-md-right">
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
