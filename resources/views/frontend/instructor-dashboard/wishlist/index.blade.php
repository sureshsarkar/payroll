@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
    <div class="dashboard__content-wrap">
        <div class="dashboard__content-title d-flex justify-content-between">
            <h4 class="title">{{ __('Wishlist') }}</h4>
        </div>
        <div class="row">
            <div class="preloader-two preloader-two-fixed d-none">
                <div class="loader-icon-two"><img src="{{ asset(Cache::get('setting')->preloader) }}" alt="Preloader"></div>
            </div>
            <div class="col-12 wishlist-content">
                @include('frontend.wishlist.wishlist-card')
            </div>
        </div>
    </div>
@endsection
