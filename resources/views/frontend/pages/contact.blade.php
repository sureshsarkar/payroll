@extends('frontend.layouts.master')
@section('meta_title', $seo_setting['contact_page']['seo_title'])
@section('meta_description', $seo_setting['contact_page']['seo_description'])
@section('contents')
    <!-- breadcrumb-area -->
    <!--   <x-frontend.breadcrumb :title="__('Contact Us')" :links="[['url' => route('home'), 'text' => __('Home')], ['url' => '', 'text' => __('Contact Us')]]" /> -->
    <!-- breadcrumb-area-end -->


    <section class="inner-banner-wrapper">
        <div class="inner-banner-img">
            <img src="{{ asset('frontend/img/innerbanner.png') }}" alt="Rent" title="Rent">
            <div class="inner-banner-main">
                <div class="container">
                    <div class=" inner-banner-con">
                        {{-- <div class="inner-banner-con-inn">
                            <h2>Contact Us</h2>
                        </div> --}}
                        <div class="bread-crumb">
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="{{ url('/') }}"><i
                                                class="fas fa-home"></i></a></li>
                                    <li class="breadcrumb-item active" aria-current="page">Contact Us</li>
                                </ol>
                            </nav>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>
 


    <section class="contact-page-wrapp">
        <div class="container">
            <div class="row">
                <div class="col-lg-6 col-md-12 col-12">
                    <div class="home-abt-con-inn">
                        <h5><i class="fas fa-star-of-life"></i> {{ __('Contact Us') }}</h5>
                        <h3>Get In Touch With Us</h3>
                        <p>Explore our top categories, thoughtfully curated to help you find the perfect path for learning,
                            growth, and success.</p>
                    </div>
                    <div class="contact-info-list">
                        <!-- Contact Info Item Start -->
                        <div class="contact-info-item">
                            <!-- Icon Box Start -->
                            <div class="icon-box">
                                <img src="{{ asset('frontend/img/icon-phone.svg') }}" alt="">
                            </div>
                            <!-- Icon Box End -->

                            <!-- Contact Item Content Start -->
                            <div class="contact-item-content">
                                <h3>Contact Us</h3>
                                <p><a href="callto:{{ $contact?->phone_one }}">{{ $contact?->phone_one }}</a></p>
                            </div>
                            <!-- Contact Item Content End -->
                        </div>
                        <!-- Contact Info Item End -->

                        <!-- Contact Info Item Start -->
                        <div class="contact-info-item">
                            <!-- Icon Box Start -->
                            <div class="icon-box">
                                <img src="{{ asset('frontend/img/icon-mail.svg') }}" alt="">
                            </div>
                            <!-- Icon Box End -->

                            <!-- Contact Item Content Start -->
                            <div class="contact-item-content">
                                <h3>Email us</h3>
                                <p><a href="mailto:{{ $contact?->email_one }}">{{ $contact?->email_one }}</a></p>
                            </div>
                            <!-- Contact Item Content End -->
                        </div>
                        <!-- Contact Info Item End -->

                        <!-- Contact Info Item Start -->
                        <div class="contact-info-item">
                            <!-- Icon Box Start -->
                            <div class="icon-box">
                                <img src="{{ asset('frontend/img/icon-location.svg') }}" alt="">
                            </div>
                            <!-- Icon Box End -->

                            <!-- Contact Item Content Start -->
                            <div class="contact-item-content">
                                <h3>Location</h3>
                                <p>{{ $contact?->address }}</p>
                            </div>
                            <!-- Contact Item Content End -->
                        </div>
                        <!-- Contact Info Item End -->

                        <!-- Contact Info Item Start -->
                        <div class="contact-info-item">
                            <!-- Icon Box Start -->
                            <div class="icon-box">
                                <img src="{{ asset('frontend/img/icon-clock.svg') }}" alt="">
                            </div>
                            <!-- Icon Box End -->

                            <!-- Contact Item Content Start -->
                            <div class="contact-item-content">
                                <h3>Open</h3>
                                <p>{{ $contact->timing }}</p>

                            </div>
                            <!-- Contact Item Content End -->
                        </div>
                        <!-- Contact Info Item End -->
                    </div>
                    <div class="contact-social-list">
                        <h3>Follow On Social :</h3>
                        <ul>
                            <li><a href="#" class="social-icon d-none"><i class="fab fa-instagram"></i></a></li>
                            @foreach (getSocialLinks() as $socialLink)
                                <li><a href="{{ $socialLink->link }}" class="social-icon"><i
                                            class="{{ $socialLink->icon }}"></i></a></li>
                            @endforeach
                        </ul>
                    </div>
                </div>
                <div class="col-lg-6 col-md-12 col-12">
                    <div class="contact-us-form">
                        <!-- Section Title Start -->
                        <div class="home-abt-con-inn">
                            <h5><i class="fas fa-star-of-life"></i>{{ __('Get In Touch') }}</h5>
                            <h3>{{ __('Send Us a') }} <span>{{ __('Message') }}</span></h3>
                            <p>{{ __('ContactUsPageDescriptionTwo') }}</p>
                        </div>
                        <!-- Section Title End -->

                        <!-- Contact Form Start -->
                        <div class="contact-form">
                            <form id="contact-form" action="" method="POST">
                                @csrf
                                <div class="row">
                                    <div class="form-group col-md-6 mb-4">
                                        <input type="text" name="fname" class="form-control" id="fname"
                                            placeholder="{{ __('First name') }}*"
                                            onkeypress="return /^[a-zA-Z ]$/.test(event.key)" required>
                                        <div class="help-block with-errors"></div>
                                    </div>

                                    <div class="form-group col-md-6 mb-4">
                                        <input type="text" name="lname" class="form-control" id="lname"
                                            placeholder="{{ __('Last name') }}"
                                            onkeydown="return /[a-z]/i.test(event.key)">
                                        <div class="help-block with-errors"></div>
                                    </div>

                                    <div class="form-group col-md-6 mb-4">
                                        <input type="email" name="email" class="form-control" id="email"
                                            placeholder="{{ __('E-mail') }}*" required>
                                        <div class="help-block with-errors"></div>
                                    </div>

                                    <div class="form-group col-md-6 mb-4">
                                        <input type="text" name="phone" class="form-control" id="phone"
                                            placeholder="{{ __('PhoneCaps') }}" required maxlength="14" minlength="10"
                                            onkeypress="return digitKeyOnly(event)">
                                        <div class="help-block with-errors"></div>
                                    </div>

                                    <div class="form-group col-md-12 mb-5">
                                        <textarea name="message" class="form-control" minlength="10" id="message" rows="3"
                                            placeholder="{{ __('Write Message') }}" required></textarea>
                                        <div class="help-block with-errors"></div>
                                    </div> 
                                    <div class="col-md-12">
                                        <button type="submit"
                                            class="btn-default disabled">{{ __('book An appointment') }}</button>
                                        <div id="msgSubmit" class="h3 hidden"></div>
                                    </div>
                                </div>
                            </form>
                            <p class="ajax-response mb-0"></p>
                        </div>
                        <!-- Contact Form End -->
                    </div>
                </div>
            </div>
        </div>
    </section> 

@endsection

@if (session('contactUs') && $setting->google_tagmanager_status == 'active' && $marketing_setting?->contact_page)
    @php
        $contactUs = session('contactUs');
        session()->forget('contactUs');
    @endphp
    @push('scripts')
        <script>
            $(function() {
                dataLayer.push({
                    'event': 'contactUs',
                    'contact_info': @json($contactUs)
                });
            });

            function digitKeyOnly(e) {

                var keyCode = e.keyCode == 0 ? e.charCode : e.keyCode;

                if ((keyCode >= 37 && keyCode <= 40) || (keyCode == 8 || keyCode == 9 || keyCode == 13) || (keyCode >= 48 &&

                        keyCode <= 57)) {

                    return true;

                }

                return false;

            }
        </script>
    @endpush
@endif
