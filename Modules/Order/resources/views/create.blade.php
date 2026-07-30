@extends('admin.master_layout')
@section('title')
    <title>{{ __('Order Details') }}</title>
@endsection
@section('admin-content')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>{{ __('Invoice') }}</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('admin.dashboard') }}">{{ __('Dashboard') }}</a>
                    </div>
                    <div class="breadcrumb-item">{{ __('Invoice') }}</div>
                </div>
            </div>

            <div class="section-body">
                <div class="invoice">
                    <div class="invoice-print">
                        <div class="row ">
                            <div class="col-md-12">
                                <form action="{{ route('admin.orders.store') }}" method="POST" class="d-print-none">
                                    @csrf
                                    <div class="row ">
                                        <div class="col-lg-4">
                                            <div class="section-title">{{ __('Select Student') }}*</div>
                                            <select name="user_id" class="form-control" required>
                                                <option value="">Choose Student</option>
                                                @foreach ($stidents as $c)
                                                    <option value="{{ $c->id }}">{{ $c->name }} </option>
                                                @endforeach
                                            </select>

                                        </div>
                                        <div class="col-lg-4">
                                            <div class="section-title">{{ __('Select Course') }}*</div>
                                            <select name="course_id" class="form-control" required>
                                                <option value="">Choose Student</option>
                                                @foreach ($courses as $c)
                                                    <option value="{{ $c->id }}">{{ $c->title }} </option>
                                                @endforeach
                                            </select>
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
