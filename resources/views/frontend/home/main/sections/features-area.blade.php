<!-- <section class="features__area">
  <div class="container">
      <div class="row justify-content-center">
          <div class="col-xl-6">
              <div class="section__title white-title text-center mb-50">
                  <span class="sub-title">{{ __('How We Start Journey') }}</span>
                  <h2 class="title">{{ __('Start your Learning Journey Today!') }}</h2>
                  <p>{{ __('Discover a World of Knowledge and Skills at Your Fingertips – Unlock Your Potential and Achieve Your Dreams with Our Comprehensive Learning Resources!') }}</p>
              </div>
          </div>
      </div>
      <div class="row justify-content-center">
        <div class="col-xl-3 col-lg-4 col-md-6">
            <div class="features__item">
                <div class="features__icon">
                    <img src="{{ asset($ourFeatures?->global_content?->image_one) }}" alt="img">
                </div>
                <div class="features__content">
                    <p class="title lh-base">{{ $ourFeatures?->content?->title_one }}</p>
                    <p>{{ $ourFeatures?->content?->sub_title_one }}</p>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-4 col-md-6">
            <div class="features__item">
                <div class="features__icon">
                    <img src="{{ asset($ourFeatures?->global_content?->image_two) }}" alt="img">
                </div>
                <div class="features__content">
                    <p class="title lh-base">{{ $ourFeatures?->content?->title_two }}</p>
                    <p>{{ $ourFeatures?->content?->sub_title_two }}</p>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-4 col-md-6">
            <div class="features__item">
                <div class="features__icon">
                    <img src="{{ asset($ourFeatures?->global_content?->image_three) }}" alt="img">
                </div>
                <div class="features__content">
                    <p class="title lh-base">{{ $ourFeatures?->content?->title_three }}</p>
                    <p>{{ $ourFeatures?->content?->sub_title_three }}</p>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-4 col-md-6">
            <div class="features__item">
                <div class="features__icon">
                    <img src="{{ asset($ourFeatures?->global_content?->image_four) }}" alt="img">
                </div>
                <div class="features__content">
                    <p class="title lh-base">{{ $ourFeatures?->content?->title_four }}</p>
                    <p>{{ $ourFeatures?->content?->sub_title_four }}</p>
                </div>
            </div>
        </div>
    </div>
    
  </div>
</section> -->



<div class="our-benefits benefits-wrapp">
    <div class="container">
        <div class="home-abt-con-inn categories-head-box">
            <h5><i class="fas fa-star-of-life"></i> {{ __('Benefits') }}</h5>
            <h3>{{ __('BenfitsOf') }} <span>{{ __('yoga') }}</span></h3>
            <p>{{ __('BenfDescription') }}</p>
        </div>

        <div class="row align-items-center">
            <div class="col-lg-4 col-md-6 order-1">
                <!-- OUr Benefits Box Start -->
                <div class="our-benefits-box">
                    <!-- Benefit Item Start -->
                    <div class="benefit-item">
                        <div class="icon-box">
                            <img src="{{ asset($ourFeatures?->global_content?->image_one) }}" alt="">
                        </div>
                        <div class="benefit-item-content">
                            <h3>{{ $ourFeatures?->content?->title_one }}</h3>
                            <p>{{ $ourFeatures?->content?->sub_title_one }}</p>
                        </div>
                    </div>
                    <!-- Benefit Item End -->

                    <!-- Benefit Item Start -->
                    <div class="benefit-item">
                        <div class="icon-box">
                            <img src="{{ asset($ourFeatures?->global_content?->image_two) }}" alt="">
                        </div>
                        <div class="benefit-item-content">
                            <h3>{{ $ourFeatures?->content?->title_two }}</h3>
                            <p>{{ $ourFeatures?->content?->sub_title_two }}</p>
                        </div>
                    </div>
                    <!-- Benefit Item End -->

                    <!-- Benefit Item Start -->
                    <div class="benefit-item">
                        <div class="icon-box">
                            <img src="{{ asset($ourFeatures?->global_content?->image_three) }}" alt="">
                        </div>
                        <div class="benefit-item-content">
                            <h3>{{ $ourFeatures?->content?->title_three }}</h3>
                            <p>{{ $ourFeatures?->content?->sub_title_three }}</p>
                        </div>
                    </div>
                    <!-- Benefit Item End -->
                </div>
                <!-- OUr Benefits Box End -->
            </div>

            <div class="col-lg-4 order-lg-2 order-md-3 order-2">
                <!-- Our Benefits Image Start -->
                <div class="our-benefits-image">
                    <figure>
                        <img src="{{ asset($ourFeatures?->global_content?->image_four) }}" alt="">
                    </figure>
                </div>
                <!-- Our Benefits Image End -->
            </div>

            <div class="col-lg-4 col-md-6 order-lg-3 order-md-2 order-3">
                <!-- Our Benefits Box Start -->
                <div class="our-benefits-box">
                    <!-- Benefit Item Start -->
                    <div class="benefit-item">
                        <div class="icon-box">
                            <img src="{{ asset($ourFeatures?->global_content?->image_five) }}" alt="">
                        </div>
                        <div class="benefit-item-content">
                            <h3>{{ $ourFeatures?->content?->title_five }}</h3>
                            <p>{{$ourFeatures?->content?->sub_title_five}}</p>
                        </div>
                    </div>
                    <!-- Benefit Item End -->

                    <!-- Benefit Item Start -->
                    <div class="benefit-item">
                        <div class="icon-box">
                            <img src="{{ asset($ourFeatures?->global_content?->image_six) }}" alt="">
                        </div>
                        <div class="benefit-item-content">
                            <h3>{{ $ourFeatures?->content?->title_six }}</h3>
                            <p>{{$ourFeatures?->content?->sub_title_six}}</p>
                        </div>
                    </div>
                    <!-- Benefit Item End -->
                    <!-- Benefit Item Start -->
                    <div class="benefit-item">
                        <div class="icon-box">
                            <img src="{{ asset($ourFeatures?->global_content?->image_seven) }}" alt="">
                        </div>
                        <div class="benefit-item-content">
                            <h3>{{ $ourFeatures?->content?->title_seven }}</h3>
                            <p>{{$ourFeatures?->content?->sub_title_seven}}</p>
                        </div>
                    </div>
                    <!-- Benefit Item End -->
                </div>
                <!-- Our Benefits Box End -->
            </div>
        </div>
    </div>
</div>
