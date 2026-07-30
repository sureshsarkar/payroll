@extends('frontend.layouts.master')

@section('meta_title', $seo_setting['about_page']['seo_title'])
@section('meta_description', $seo_setting['about_page']['seo_description'])

@section('contents')
    <!-- breadcrumb-area -->
    <!--     <x-frontend.breadcrumb :title="__('About Us')" :links="[['url' => route('home'), 'text' => __('Home')], ['url' => '', 'text' => __('about us')]]" /> -->
    <!-- breadcrumb-area-end -->
 

    <section class="inner-banner-wrapper">
        <div class="inner-banner-img">
            <img src="{{ asset('frontend/img/innerbanner.png') }}" alt="Rent" title="Rent">
            <div class="inner-banner-main">
                <div class="container">
                    <div class=" inner-banner-con">
                        {{-- <div class="inner-banner-con-inn">
                            <h2>About Us</h2>
                        </div> --}}
                        <div class="bread-crumb">
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="{{ url('/') }}"><i
                                                class="fas fa-home"></i></a></li>
                                    <li class="breadcrumb-item active" aria-current="page">About Us</li>
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
                <div class="col-lg-6 col-md-6 col-12">
                    <div class="home-about-image">
                        <img src="{{ asset($aboutSection?->global_content?->image ?? 'frontend/img/about-new.png') }}"
                            alt="" class="img-fluid">

                    </div>
                </div>
                <div class="col-lg-6 col-md-6 col-12">
                    <div class="home-abt-con">
                        <div class="home-abt-con-inn">
                            {{-- <h5><i class="fas fa-star-of-life"></i> About Us</h5> --}}

                            {!! clean($aboutSection?->content?->description) !!}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>




    <section class="our-approach-wrpp">
        <div class="container">
            <div class="row">
                <div class="col-lg-6 col-md-6 col-12">
                     {{-- {!! clean($aboutSection?->content?->our_mission_about_page_description) !!} --}}
                    <div class="home-abt-con-inn">
                        <h5><i class="fas fa-star-of-life"></i> Our Approach</h5>
                        <h3>Transforming Lives Through <span>Mindful Practices</span></h3>
                        <p>Explore our top categories, thoughtfully curated to help you find the perfect path for learning, growth, and success.</p>
                    </div>

                  {!! clean($aboutSection?->content?->our_mission_about_page_description) !!}

                </div>
                <div class="col-lg-6 col-md-6 col-12">
                    <div class="approach-image">
                        <img src="{{ asset($aboutSection?->global_content?->about_image ??'frontend/img/abt-bottom.png') }}">
                    </div>
                </div>
            </div>
        </div>
    </section>


    {{-- @include('frontend.home.main.sections.certificate-section') --}}



    <section class="certificte-wrapps about-cert-wrapp">
        @include('frontend.home.main.sections.certificate-section')
    </section>


















@endsection
