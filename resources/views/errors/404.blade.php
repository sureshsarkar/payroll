@extends('frontend.layouts.master')
@section('meta_title', 'Error' . ' || ' . $setting->app_name)

@section('contents')
    <!-- breadcrumb-area -->
    {{-- <x-frontend.breadcrumb :title="__('Error')" :links="[
        ['url' => route('home'), 'text' => __('Home')],
        ['url' => route('checkout.index'), 'text' => __('Error')],
    ]" /> --}}
    <!-- breadcrumb-area-end -->

    <style>
        .error-img{width: 60%;}
        .arrow-btn{padding: 7px 15px;background: #e2a300;border-radius: 33px;color: #fff;font-weight: 600;}
    </style>
    <section class="error-area bg-dark">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="error-wrap text-center">
                        <div class="error-img">
                            
                            <img src="{{ asset('frontend/img/others/error_img.svg') }}" alt="img" class="injectable">

                        </div>
                        <div class="error-content"> 
                            <div class="tg-button-wrap">
                                <a href="{{ url('/') }}" class="arrow-btn">{{ __('Go To Home Page') }}</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
