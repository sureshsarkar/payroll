@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
    <div class="dashboard__content-wrap">
        <div class="dashboard__content-title d-flex justify-content-between">
            <h4 class="title">{{ __('Youtube live setting') }}</h4> 
            {{-- <a href="#" class="btn">Create Access Token</a> --}}
        </div>
        <div class="row">
            <div class="col-lg-12">
                <div class="instructor__profile-form-wrap">
                    <div class="row">
                        <form action="{{ route('instructor.youtube-setting.update') }}" method="POST"
                            class="col-xl-6 instructor__profile-form">
                            @csrf
                            @method('PUT')
                            <div class="form-grp">
                                <label for="api_key">{{ __('API KEY') }} <code>*</code></label>
                                <input id="api_key" name="api_key" type="text" value="{{ $credential?->api_key }}">
                            </div>
                            <div class="form-grp">
                                <label for="channel_id">{{ __('Channel ID') }} <code>*</code></label>
                                <input id="channel_id" name="channel_id" type="text"
                                    value="{{ $credential?->channel_id }}">
                            </div>
                            <div class="submit-btn mt-25">
                                <button type="submit" class="btn">✓ {{ __('Update') }}</button>
                            </div>
                        </form>
                        <div class="col-xl-6"> 
                            <div class="alert alert-warning" role="alert">
                                <h4 class="alert-heading">How to fetch Youtube channel videos?</h4>
                                <p>Google Console steps: <span></span></p>
                                <p>1. Create an app on Google Console  <a href="https://console.cloud.google.com/welcome" target="_blank">Google Console</a>
                                </p>
                                <p>2. Flow these steps: </p>
                                <p>APIs & Services =>Library =>YouTube =>YouTube Data API v3 =>Credentials</p> 
                                <p>You will see API KEY</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endsection
