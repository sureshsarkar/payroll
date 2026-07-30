@extends('frontend.layouts.master')
@section('meta_title', __('All Instructors') . ' || ' . $setting->app_name)
@section('contents')
    <!-- breadcrumb-area -->
    <!-- <x-frontend.breadcrumb :title="__('All Instructors')" :links="[['url' => route('home'), 'text' => __('Home')], ['url' => '', 'text' => __('All Instructors')]]" /> -->
    <!-- breadcrumb-area-end -->
    <!-- instructor-area -->


        <section class="inner-banner-wrapper">
    <div class="inner-banner-img">
        <img src="{{ asset('frontend/img/innerbanner.png') }}" alt="Rent" title="Rent">
        <div class="inner-banner-main">
        <div class="container">
            <div class=" inner-banner-con">
                {{-- <div class="inner-banner-con-inn">
                    <h2>{{__('Instructors')}}</h2>
                </div> --}}
        <div class="bread-crumb">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{url('/')}}"><i class="fas fa-home"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{__('Instructors')}}</li>
                </ol>
            </nav>
        </div>
    </div>
        </div>
        
    </div>
    </div>
    
</section>



<section class="instructor-page-wrapp">
    <div class="container">
        <div class="home-abt-con-inn categories-head-box">
             <h5><i class="fas fa-star-of-life"></i> {{__('Instructo')}}</h5>
             <h3>{{__('Meet Our')}} <span>{{__('Instructo')}}</span></h3>
             <p>{{__('InstructorPageDescription')}}</p>
          </div>
          <div class="row">
              @foreach ($instructors as $instructor)
               @if($instructor->courses()->where(['status' => 'active', 'is_approved' => 'approved'])->count() > 0)
              <div class="col-lg-6 col-md-6 col-12">
                  <div class="team-member-item">
                    <div class="team-image">
                        <a href="#" data-cursor-text="View">
                            <figure class="image-anime">
                                <img src="{{ asset($instructor->image) }}" alt="{{$instructor->name}}">
                            </figure>
                        </a>
                    </div>
                    <!-- Team Body Start -->
                    <div class="team-body">
                        <!-- Team Content Start -->
                        <div class="team-content">
                            <p>{{ $instructor->job_title }}</p>
                            <h2><a href="#">{{$instructor->name}}</a></h2>
                        </div>
                        <!-- Team Content End -->
                        <!-- Team Body Content Start -->
                        <div class="team-body-content">
                            <p>{{ $instructor->short_bio }}</p>
                            <p>{{$instructor->courses->count() }} Course</p>
                            <p class="avg-rating"> <i class="fas fa-star"></i> {{ number_format($instructor->courses->avg('avg_rating'), 1) }} Ratings </p> </div>
                        <!-- Team Body Content End -->
                        <!-- Team Social Icon Start -->
                        <div class="team-social-icon">

                        <ul>
                         @if($instructor->facebook)
                        <li><a href="{{$instructor->facebook}}" class="social-icon"><i class="fab fa-facebook-f"></i></a></li>
                        @endif
                        @if( $instructor->instagram)
                        <li><a href="{{$instructor->instagram}}" class="social-icon"><i class="fab fa-instagram"></i></a></li>
                        @endif
                        @if( $instructor->linkedin)
                        <li><a href="{{$instructor->linkedin}}" class="social-icon"><i class="fab fa-linkedin-in"></i></a></li>
                        @endif
                        @if($instructor->youtube)
                        <li><a href="{{$instructor->youtube}}" class="social-icon"><i class="fab fa-youtube"></i></a></li>
                        @endif
                    </ul>
                    </div>
                        <!-- Team Social Icon End -->
                    </div>
                    <!-- Team Body End -->
                </div>
              </div>

              @endif
              @endforeach

            <nav class="pagination__wrap mt-25">
                    {{ $instructors->links() }}
            </nav>


              {{-- <div class="col-lg-6 col-md-6 col-12">
                  <div class="team-member-item">
                    <div class="team-image">
                        <a href="#" data-cursor-text="View">
                            <figure class="image-anime">
                                <img src="{{ asset('frontend/img/in1.jpg') }}" alt="">
                            </figure>
                        </a>
                    </div>
                    <!-- Team Body Start -->
                    <div class="team-body">
                        <!-- Team Content Start -->
                        <div class="team-content">
                            <p>Lead Yoga</p>
                            <h2><a href="#">Virendra Strength yoga</a></h2>
                        </div>
                        <!-- Team Content End -->
                        <!-- Team Body Content Start -->
                        <div class="team-body-content">
                            <p>Darlene of meditation session have been life-changing.</p>
                            <p>1 Course</p>
                            <p class="avg-rating"> <i class="fas fa-star"></i> 0.0 Ratings </p> </div>
                        <!-- Team Body Content End -->
                        <!-- Team Social Icon Start -->
                        <div class="team-social-icon">
                          
                            <ul>
                        <li><a href="#" class="social-icon"><i class="fab fa-facebook-f"></i></a></li>
                        <li><a href="#" class="social-icon"><i class="fab fa-instagram"></i></a></li>
                        <li><a href="#" class="social-icon"><i class="fab fa-linkedin-in"></i></a></li>
                        <li><a href="#" class="social-icon"><i class="fab fa-youtube"></i></a></li>
                    </ul>
                        </div>
                        <!-- Team Social Icon End -->
                    </div>
                    <!-- Team Body End -->
                </div>
              </div>
              <div class="col-lg-6 col-md-6 col-12">
                  <div class="team-member-item">
                    <div class="team-image">
                        <a href="#" data-cursor-text="View">
                            <figure class="image-anime">
                                <img src="{{ asset('frontend/img/in1.jpg') }}" alt="">
                            </figure>
                        </a>
                    </div>
                    <!-- Team Body Start -->
                    <div class="team-body">
                        <!-- Team Content Start -->
                        <div class="team-content">
                            <p>Lead Yoga</p>
                            <h2><a href="#">Virendra Strength yoga</a></h2>
                        </div>
                        <!-- Team Content End -->
                        <!-- Team Body Content Start -->
                        <div class="team-body-content">
                            <p>Darlene of meditation session have been life-changing.</p>
                            <p>1 Course</p>
                            <p class="avg-rating"> <i class="fas fa-star"></i> 0.0 Ratings </p> </div>
                        <!-- Team Body Content End -->
                        <!-- Team Social Icon Start -->
                        <div class="team-social-icon">
                          
                            <ul>
                        <li><a href="#" class="social-icon"><i class="fab fa-facebook-f"></i></a></li>
                        <li><a href="#" class="social-icon"><i class="fab fa-instagram"></i></a></li>
                        <li><a href="#" class="social-icon"><i class="fab fa-linkedin-in"></i></a></li>
                        <li><a href="#" class="social-icon"><i class="fab fa-youtube"></i></a></li>
                    </ul>
                        </div>
                        <!-- Team Social Icon End -->
                    </div>
                    <!-- Team Body End -->
                </div>
              </div>
              <div class="col-lg-6 col-md-6 col-12">
                  <div class="team-member-item">
                    <div class="team-image">
                        <a href="#" data-cursor-text="View">
                            <figure class="image-anime">
                                <img src="{{ asset('frontend/img/in1.jpg') }}" alt="">
                            </figure>
                        </a>
                    </div>
                    <!-- Team Body Start -->
                    <div class="team-body">
                        <!-- Team Content Start -->
                        <div class="team-content">
                            <p>Lead Yoga</p>
                            <h2><a href="#">Virendra Strength yoga</a></h2>
                        </div>
                        <!-- Team Content End -->
                        <!-- Team Body Content Start -->
                        <div class="team-body-content">
                            <p>Darlene of meditation session have been life-changing.</p>
                            <p>1 Course</p>
                            <p class="avg-rating"> <i class="fas fa-star"></i> 0.0 Ratings </p> </div>
                        <!-- Team Body Content End -->
                        <!-- Team Social Icon Start -->
                        <div class="team-social-icon">
                          
                            <ul>
                        <li><a href="#" class="social-icon"><i class="fab fa-facebook-f"></i></a></li>
                        <li><a href="#" class="social-icon"><i class="fab fa-instagram"></i></a></li>
                        <li><a href="#" class="social-icon"><i class="fab fa-linkedin-in"></i></a></li>
                        <li><a href="#" class="social-icon"><i class="fab fa-youtube"></i></a></li>
                    </ul>
                        </div>
                        <!-- Team Social Icon End -->
                    </div>
                    <!-- Team Body End -->
                </div>
              </div>
              <div class="col-lg-6 col-md-6 col-12">
                  <div class="team-member-item">
                    <div class="team-image">
                        <a href="#" data-cursor-text="View">
                            <figure class="image-anime">
                                <img src="{{ asset('frontend/img/in1.jpg') }}" alt="">
                            </figure>
                        </a>
                    </div>
                    <!-- Team Body Start -->
                    <div class="team-body">
                        <!-- Team Content Start -->
                        <div class="team-content">
                            <p>Lead Yoga</p>
                            <h2><a href="#">Virendra Strength yoga</a></h2>
                        </div>
                        <!-- Team Content End -->
                        <!-- Team Body Content Start -->
                        <div class="team-body-content">
                            <p>Darlene of meditation session have been life-changing.</p>
                            <p>1 Course</p>
                            <p class="avg-rating"> <i class="fas fa-star"></i> 0.0 Ratings </p> </div>
                        <!-- Team Body Content End -->
                        <!-- Team Social Icon Start -->
                        <div class="team-social-icon">
                          
                            <ul>
                        <li><a href="#" class="social-icon"><i class="fab fa-facebook-f"></i></a></li>
                        <li><a href="#" class="social-icon"><i class="fab fa-instagram"></i></a></li>
                        <li><a href="#" class="social-icon"><i class="fab fa-linkedin-in"></i></a></li>
                        <li><a href="#" class="social-icon"><i class="fab fa-youtube"></i></a></li>
                    </ul>
                        </div>
                        <!-- Team Social Icon End -->
                    </div>
                    <!-- Team Body End -->
                </div>
              </div>
              <div class="col-lg-6 col-md-6 col-12">
                  <div class="team-member-item">
                    <div class="team-image">
                        <a href="#" data-cursor-text="View">
                            <figure class="image-anime">
                                <img src="{{ asset('frontend/img/in1.jpg') }}" alt="">
                            </figure>
                        </a>
                    </div>
                    <!-- Team Body Start -->
                    <div class="team-body">
                        <!-- Team Content Start -->
                        <div class="team-content">
                            <p>Lead Yoga</p>
                            <h2><a href="#">Virendra Strength yoga</a></h2>
                        </div>
                        <!-- Team Content End -->
                        <!-- Team Body Content Start -->
                        <div class="team-body-content">
                            <p>Darlene of meditation session have been life-changing.</p>
                            <p>1 Course</p>
                            <p class="avg-rating"> <i class="fas fa-star"></i> 0.0 Ratings </p> </div>
                        <!-- Team Body Content End -->
                        <!-- Team Social Icon Start -->
                        <div class="team-social-icon">
                          
                            <ul>
                        <li><a href="#" class="social-icon"><i class="fab fa-facebook-f"></i></a></li>
                        <li><a href="#" class="social-icon"><i class="fab fa-instagram"></i></a></li>
                        <li><a href="#" class="social-icon"><i class="fab fa-linkedin-in"></i></a></li>
                        <li><a href="#" class="social-icon"><i class="fab fa-youtube"></i></a></li>
                    </ul>
                        </div>
                        <!-- Team Social Icon End -->
                    </div>
                    <!-- Team Body End -->
                </div>
              </div> --}}
              
          </div>


    </div>
</section>


<!-- 
    <section class="instructor__area">
        <div class="container">
            <div class="row">
                @foreach ($instructors as $instructor)
                    @if ($instructor->courses()->where(['status' => 'active', 'is_approved' => 'approved'])->count() > 0)
                        <div class="col-xl-4 col-sm-6">
                            <div class="instructor__item">
                                <div class="instructor__thumb">
                                    <a
                                        href="{{ route('instructor-details', ['id' => $instructor->id, 'slug' => Str::slug($instructor->name)]) }}"><img
                                            src="{{ asset($instructor->image) }}" alt="img"></a>
                                </div>
                                <div class="instructor__content">
                                    <h2 class="title"><a
                                            href="{{ route('instructor-details', ['id' => $instructor->id, 'slug' => Str::slug($instructor->name)]) }}">{{ $instructor->name }}</a>
                                    </h2>
                                    <span class="designation">{{ $instructor->job_title }}</span>
                                    <span>{{ $instructor->courses->count() }} {{ __('Courses') }}</span>
                                    <p class="avg-rating">
                                        <i class="fas fa-star"></i>
                                        {{ number_format($instructor->courses->avg('avg_rating'), 1) }} {{ __('Ratings') }}
                                    </p>
                                    <div class="instructor__social">
                                        <ul class="list-wrap">
                                            @if($instructor->facebook)
                                                <li><a href="{{ $instructor->facebook }}" aria-label="Facebook"><i
                                                            class="fab fa-facebook-f"></i></a></li>
                                            @endif
                                            @if ($instructor->twitter)
                                                <li><a href="{{ $instructor->twitter }}" aria-label="Twitter"><i
                                                            class="fab fa-twitter"></i></a></li>
                                            @endif
                                            @if ($instructor->linkedin)
                                                <li><a href="{{ $instructor->linkedin }}" aria-label="Linkedin"><i
                                                            class="fab fa-linkedin"></i></a></li>
                                            @endif
                                            @if ($instructor->github)
                                                <li><a href="{{ $instructor->github }}" aria-label="Github"><i
                                                            class="fab fa-github"></i></a></li>
                                            @endif
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                @endforeach
                <nav class="pagination__wrap mt-25">
                    {{ $instructors->links() }}
                </nav>
            </div>
        </div>
    </section>
 -->


    <!-- instructor-area-end -->










@endsection
