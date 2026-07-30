 
<section class="why-choose-wrapp">
    <div class="container"> 
        <div class="home-abt-con">
            <div class="home-abt-con-inn features-con-div">
                <h5><i class="fas fa-star-of-life"></i> Features</h5>    
              {!! clean($why_choose_us_section?->content?->why_choose_descreption ?? '') !!}
            </div>
        </div> 

        <div class="row">
            <div class="col-lg-4 col-md-6 col-12">
                <div class="features-box">
                    <img src="{{ asset('frontend/img/f1.jpg') }}" alt="" class="img-fluid">
                    <h3>Create & manage your courses</h3>
                    <p>Easily create, organize, and manage your courses with a powerful dashboard designed to simplify teaching and save time.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 col-12">
                <div class="features-box">
                    <img src="{{ asset('frontend/img/f2.jpg') }}" alt="" class="img-fluid">
                    <h3>Engage Your Community</h3>
                    <p>Connect with your students through live sessions, discussions, and updates to build a strong and interactive learning community.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 col-12">
                <div class="features-box">
                    <img src="{{ asset('frontend/img/f3.jpg') }}" alt="" class="img-fluid">
                    <h3>Grow Your Revenew</h3>
                    <p>Increase your earnings by selling courses, managing batches efficiently, and reaching more students across the globe.</p>
                </div>
            </div>
        </div>

 


        <div class="counter-wrapper wow fadeInUp">
            <div class="counter-main">
                <div class="container">
                    <div class="counter-inner">
                        <ul id="counter">
                            <li>
                                <div class="count-icon">
                                    <img src="{{ asset('frontend/img/icon-why-choose-counter-1.svg') }}"
                                        alt="Tawhid Academy" title="Tawhid Academy" />
                                </div>
                                <div class="count-contanet">
                                    <span class="count percent"
                                        data-count="{{ $setting->years_of_exprience }}">0</span>
                                    <p>{{ __('Years Of Experience') }}</p>
                                </div>
                            </li>
                            <li>
                                <div class="count-icon">
                                    <img src="{{ asset('frontend/img/icon-why-choose-counter-2.svg') }}"
                                        alt="Tawhid Academy" title="Tawhid Academy" />
                                </div>
                                <div class="count-contanet">
                                    <span class="count percent"
                                        data-count="{{ $setting->satisfied_clients }}">0</span>
                                    <p>{{ __('Satisfied clients') }}</p>
                                </div>
                            </li>
                            <li>
                                <div class="count-icon">
                                    <img src="{{ asset('frontend/img/icon-why-choose-counter-3.svg') }}"
                                        alt="Tawhid Academy" title="Tawhid Academy" />
                                </div>
                                <div class="count-contanet">
                                    <span class="count percent"
                                        data-count="{{ $setting->countries_reached }}">0</span>
                                    <p>{{ __('Countries Reached') }}</p>
                                </div>
                            </li>
                            <li>
                                <div class="count-icon">
                                    <img src="{{ asset('frontend/img/icon-why-choose-counter-4.svg') }}"
                                        alt="Tawhid Academy" title="Tawhid Academy" />
                                </div>
                                <div class="count-contanet">
                                    <span class="count percent"
                                        data-count="{{ $setting->classes_conducted }}">0</span>
                                    <p>{{ __('Classes Conducted') }}</p>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>



    </div>
</section>
