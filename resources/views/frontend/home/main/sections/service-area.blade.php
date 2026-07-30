<section class="service-area-wrpp">
    <div class="container">
        <div class="row">

            <div class="col-lg-12 col-md-12 col-12">
                <div class="service-head-box">
                    <div class="home-abt-con-inn">
                        <!-- {!! clean($aboutSection?->content?->our_courses_description) !!} -->
                        <h5><i class="fas fa-star-of-life"></i>Categories</h5>
                        <h3>{{ __('TopCategory') }} <span>{{ __('We Have') }}</span></h3>
                        <p>{{ __('TrecatDescription') }}</p>

                        <!-- <a href="{{ route('courses') }}">{{ __('All Courses') }}</a> -->
                    </div>
                </div>
            </div>

            @foreach ($trendingCategories as $category)
                <div class="col-lg-2 col-md-6 col-12">
                    <div class="service-main-box">
                        <div class="serv-img">
                            <img src="{{ asset('frontend/img/lms.png') }}">
                        </div>
                        <h3>{{ $category?->translation?->name }}g</h3>
                    </div>
                </div>
            @endforeach

        </div>
    </div>
</section>
