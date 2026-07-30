@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
    <div class="dashboard__content-wrap">
        <div class="dashboard__content-title">
            <h4 class="title">{{ __('Live Class') }}</h4>
        </div>
        <div class="alert alert-info">
            {{ __('This page was a Jitsi sandbox and is no longer in use. Live classes run on Zoom — manage them from the Live Classes page.') }}
        </div>
    @endsection
