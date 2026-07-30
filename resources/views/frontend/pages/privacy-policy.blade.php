@extends('frontend.layouts.master')

@section('meta_title', $seo_setting['privacy_policy']['seo_title'])
@section('meta_description', $seo_setting['privacy_policy']['seo_description'])

@section('contents')

<section class="inner-banner-wrapper">
        <div class="inner-banner-img">
            <img src="{{ asset('frontend/img/inner-banner.png') }}" alt="Rent" title="Rent">
            {{-- <img src="{{ asset($aboutSection?->global_content?->banner_image) }}" alt="Rent" title="Rent"> --}}
            <div class="inner-banner-main">
                <div class="container">
                    <div class=" inner-banner-con">
                        {{-- <div class="inner-banner-con-inn">
                            <h2>{{__('Privacy policy')}}</h2>
                        </div> --}}
                        <div class="bread-crumb">
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="{{ url('/') }}"><i
                                                class="fas fa-home"></i></a></li>
                                    <li class="breadcrumb-item active" aria-current="page">{{__('Privacy policy')}}</li>
                                </ol>
                            </nav>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </section>




    <section class="about-sec-wrapper">
        <div class="container">
            <div class="row">
                
                <div class="col-lg-12 col-md-12 col-12">
                    <div class="home-abt-con">
                        <div class="home-abt-con-inn">
                            {!! clean($aboutSection?->content?->privacy_policy_page_description) !!}
                            
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    @endsection