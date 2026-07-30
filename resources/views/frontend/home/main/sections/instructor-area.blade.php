<!-- <section class="instructor__area">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-xl-4">
                <div class="instructor__content-wrap">
                    <div class="section__title mb-15">
                        <span class="sub-title">{{ __('Skilled Introduce') }}</span>
                        <h2 class="title">{!! clean(processText($featuredInstructorSection?->translation?->title)) !!}</h2>
                    </div>
                    <p>{!! clean(processText($featuredInstructorSection?->translation?->sub_title)) !!}</p>
                    <div class="tg-button-wrap">
                        <a href="{{ $featuredInstructorSection->button_url }}"
                            class="btn arrow-btn">{{ $featuredInstructorSection?->translation?->button_text }} <img
                                src="{{ asset('frontend/img/icons/right_arrow.svg') }}" alt="img"
                                class="injectable"></a>
                    </div>
                </div>
            </div>
            <div class="col-xl-8">
                <div class="instructor__item-wrap">
                    <div class="row">
                        @foreach ($selectedInstructors as $index => $instructor)
                            @if ($index < 4)
                                <div class="col-sm-6">
                                    <div class="instructor__item">
                                        <div class="instructor__thumb">
                                            <a href="{{ route('instructor-details', $instructor->id) }}"><img
                                                    src="{{ asset($instructor->image) }}" alt="img"></a>
                                        </div>
                                        <div class="instructor__content">
                                            <h2 class="title"><a
                                                    href="{{ route('instructor-details', $instructor->id) }}">{{ $instructor->name }}</a>
                                            </h2>
                                            <span class="designation">{{ $instructor->job_title }}</span>
                                            <p class="avg-rating">
                                                <i class="fas fa-star"></i>
                                                {{ number_format($instructor->courses->avg('avg_rating'), 1) }} {{ __('Ratings') }}
                                            </p>
                                            <div class="instructor__social">
                                                <ul class="list-wrap">
                                                    @if ($instructor->facebook)
                                                        <li><a href="{{ $instructor->facebook }}" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a></li>
                                                    @endif
                                                    @if ($instructor->twitter)
                                                        <li><a href="{{ $instructor->twitter }}" aria-label="Twitter"><i class="fab fa-twitter"></i></a></li>
                                                    @endif
                                                    @if ($instructor->linkedin)
                                                        <li><a href="{{ $instructor->linkedin }}" aria-label="Linkedin"><i class="fab fa-linkedin"></i></a></li>
                                                    @endif
                                                    @if ($instructor->github)
                                                        <li><a href="{{ $instructor->github }}" aria-label="Github"><i class="fab fa-github"></i></a></li>
                                                    @endif
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
 -->


<section class="instructor-wrapp">
    <div class="container">
        <div class="home-abt-con-inn categories-head-box">
             <h5><i class="fas fa-star-of-life"></i> {{__('Instructor')}}</h5>
             <h3>{{__('Meet Our')}} <span>{{__('Instructor')}}</span></h3>
             <p>{{__('MeetOurDescription')}}</p>
          </div>
        <div class="row">
            @foreach($selectedInstructors as $index => $instructor)
            @if ($index < 4)
            <div class="col-lg-3 col-md-6 col-12">
                <div class="instructor-box">
                    <img src="{{ asset($instructor->image) }}">
                    <div class="ins-con">
                    <h3>{{ $instructor->name }}</h3>
                    <p>{{ $instructor->job_title }}</p>
                    <ul>
                        <li><a href="{{ $instructor->facebook }}"><i class="fab fa-facebook-f"></i></a></li>
                        <li><a href="{{ $instructor->instagram }}"><i class="fab fa-instagram"></i></a></li>
                        <li><a href="{{ $instructor->linkedin }}"><i class="fab fa-linkedin-in"></i></a></li>
                        <li><a href="{{ $instructor->youtube }}"><i class="fab fa-youtube"></i></a></li>
                    </ul>
                    </div>
                </div>
            </div>
            @endif
            @endforeach
            {{-- <div class="col-lg-3 col-md-6 col-12">
                <div class="instructor-box">
                    <img src="{{ asset('frontend/img/in2.jpg') }}">
                    <div class="ins-con">
                    <h3>Robert Fox</h3>
                    <p>Yoga Teacher</p>
                    <ul>
                        <li><a href="#"><i class="fab fa-facebook-f"></i></a></li>
                        <li><a href="#"><i class="fab fa-instagram"></i></a></li>
                        <li><a href="#"><i class="fab fa-linkedin-in"></i></a></li>
                        <li><a href="#"><i class="fab fa-youtube"></i></a></li>
                    </ul>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-12">
                <div class="instructor-box">
                    <img src="{{ asset('frontend/img/in3.jpg') }}">
                    <div class="ins-con">
                    <h3>Kristin Watson</h3>
                    <p>Yoga Teacher</p>
                    <ul>
                        <li><a href="#"><i class="fab fa-facebook-f"></i></a></li>
                        <li><a href="#"><i class="fab fa-instagram"></i></a></li>
                        <li><a href="#"><i class="fab fa-linkedin-in"></i></a></li>
                        <li><a href="#"><i class="fab fa-youtube"></i></a></li>
                    </ul>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-12">
                <div class="instructor-box">
                    <img src="{{ asset('frontend/img/in2.jpg') }}">
                    <div class="ins-con">
                    <h3>Robert Fox</h3>
                    <p>Yoga Teacher</p>
                    <ul>
                        <li><a href="#"><i class="fab fa-facebook-f"></i></a></li>
                        <li><a href="#"><i class="fab fa-instagram"></i></a></li>
                        <li><a href="#"><i class="fab fa-linkedin-in"></i></a></li>
                        <li><a href="#"><i class="fab fa-youtube"></i></a></li>
                    </ul>
                    </div>
                </div>
            </div> --}}
        </div> 
    </div>
</section>