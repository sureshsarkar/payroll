@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
<div class="dashboard__content-wrap">
    <div class="dashboard__content-title d-flex flex-wrap justify-content-between align-items-center mb-3">
        <h4 class="title">{{ __('Edit Enquiry') }}</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('instructor.landing-page-enquiry.show', $data->id) }}" class="btn btn-outline-primary">{{ __('View detail') }}</a>
            <a href="{{ route('instructor.landing-page-enquiry.index') }}" class="btn"><i class="bi bi-arrow-left"></i> {{ __('Back') }}</a>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <div class="card">
        <div class="card-body">
            <form action="{{ route('instructor.landing-page-enquiry.update', $data->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="row g-3">
                    <div class="form-group col-md-3">
                        <label>{{ __('First Name') }} <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="first_name" value="{{ old('first_name', $data->first_name) }}" required>
                    </div>
                    <div class="form-group col-md-3">
                        <label>{{ __('Last Name') }}</label>
                        <input type="text" class="form-control" name="last_name" value="{{ old('last_name', $data->last_name) }}">
                    </div>
                    <div class="form-group col-md-3">
                        <label>{{ __('Email') }} <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" name="email" value="{{ old('email', $data->email) }}" required>
                    </div>
                    <div class="form-group col-md-3">
                        <label>{{ __('Phone') }} <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="phone" value="{{ old('phone', $data->phone) }}" required>
                    </div>
                    <div class="form-group col-md-4">
                        <label>{{ __('Service / Interest') }} <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="service" value="{{ old('service', $data->service) }}" required>
                    </div>
                    <div class="form-group col-md-2">
                        <label>{{ __('Deal value') }}</label>
                        <input type="number" step="0.01" min="0" class="form-control" name="value" value="{{ old('value', $data->value) }}" placeholder="0">
                    </div>
                    <div class="form-group col-md-3">
                        <label>{{ __('Stage') }} <span class="text-danger">*</span></label>
                        <select name="status" class="form-control" id="lpe-edit-status">
                            @foreach (\App\Models\LandingPageEnquiry::statusOptions() as $value => $opt)
                                <option value="{{ $value }}" @selected(old('status', $data->normalized_status) === $value)>{{ __($opt['label']) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-3" id="lpe-lost-wrap" style="{{ $data->normalized_status === \App\Models\LandingPageEnquiry::STATUS_LOST ? '' : 'display:none;' }}">
                        <label>{{ __('Lost reason') }}</label>
                        <input type="text" class="form-control" name="lost_reason" value="{{ old('lost_reason', $data->lost_reason) }}" placeholder="{{ __('e.g. Budget, chose competitor…') }}">
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle"></i> {{ __('Save') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script nonce="{{ csp_nonce() }}">
(function () {
    var sel = document.getElementById('lpe-edit-status');
    var wrap = document.getElementById('lpe-lost-wrap');
    if (sel && wrap) {
        sel.addEventListener('change', function () {
            wrap.style.display = (sel.value === '{{ \App\Models\LandingPageEnquiry::STATUS_LOST }}') ? '' : 'none';
        });
    }
})();
</script>
@endsection
