@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
<div class="dashboard__content-wrap">
    <div class="dashboard__content-title d-flex flex-wrap justify-content-between align-items-center mb-3">
        <h4 class="title">{{ __('Add Enquiry') }}</h4>
        <a href="{{ route('instructor.landing-page-enquiry.index') }}" class="btn btn-outline-primary">
            <i class="bi bi-arrow-left"></i> {{ __('Back to leads') }}
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <form action="{{ route('instructor.landing-page-enquiry.store') }}" method="POST">
                @csrf
                <div class="row g-3">
                    <div class="form-group col-md-3">
                        <label>{{ __('First Name') }} <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="first_name" value="{{ old('first_name') }}" required>
                    </div>
                    <div class="form-group col-md-3">
                        <label>{{ __('Last Name') }}</label>
                        <input type="text" class="form-control" name="last_name" value="{{ old('last_name') }}">
                    </div>
                    <div class="form-group col-md-3">
                        <label>{{ __('Email') }} <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" name="email" value="{{ old('email') }}" required>
                    </div>
                    <div class="form-group col-md-3">
                        <label>{{ __('Phone') }} <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="phone" value="{{ old('phone') }}" required>
                    </div>
                    <div class="form-group col-md-4">
                        <label>{{ __('Service / Interest') }} <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="service" value="{{ old('service') }}"
                               placeholder="{{ __('e.g. Web Designing course') }}" required>
                    </div>
                    <div class="form-group col-md-2">
                        <label>{{ __('Deal value') }}</label>
                        <input type="number" step="0.01" min="0" class="form-control" name="value" value="{{ old('value') }}" placeholder="0">
                    </div>
                    <div class="form-group col-md-6">
                        <label>{{ __('Stage') }} <span class="text-danger">*</span></label>
                        <select name="status" class="form-control">
                            @foreach (\App\Models\LandingPageEnquiry::statusOptions() as $key => $opt)
                                <option value="{{ $key }}" @selected(old('status', \App\Models\LandingPageEnquiry::STATUS_NEW) === $key)>
                                    {{ __($opt['label']) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-12">
                        <label>{{ __('Message / Notes') }}</label>
                        <textarea class="form-control" name="message" rows="4"
                                  placeholder="{{ __('What does this lead want? Any context for follow-up…') }}">{{ old('message') }}</textarea>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle"></i> {{ __('Save lead') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
