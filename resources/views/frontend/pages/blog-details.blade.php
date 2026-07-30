@extends('frontend.layouts.master')
@section('meta_title', $blog->translation->title . ' || ' . $setting->app_name)

@push('custom_meta')
    <meta name="description" content="{{ $blog->translation->seo_description }}">
    <meta property="keywords" content="{{ $blog->tags ? getTags(json_decode($blog->tags)) : '' }}" />
    <meta property="og:title" content="{{ $blog->translation->seo_title }}" />
    <meta property="og:description" content="{{ $blog->translation->seo_description }}" />
    <meta property="og:image" content="{{ asset($blog->image) }}" />
    <meta property="og:URL" content="{{ url()->current() }}" />
    <meta property="og:type" content="website" />
@endpush
@push('styles')
    <link rel="stylesheet" href="{{ asset('frontend/css/shareon.min.css') }}">
@endpush
@section('meta_title', $setting->app_name . ' | ' . $blog->title)
@section('contents')
    <!-- breadcrumb-area -->
    <!-- <x-frontend.breadcrumb :title="__('Blog Details')" :links="[
        ['url' => route('home'), 'text' => __('Home')],
        ['url' => route('blogs'), 'text' => __('Blogs')],
        ['url' => '', 'text' => $blog->title],
    ]" /> -->
    <!-- breadcrumb-area-end -->


    <section class="inner-banner-wrapper">
        <div class="inner-banner-img">
            <img src="{{ asset('frontend/img/innerbanner.png') }}" alt="Banner Image" title="Banner Image">
            <div class="inner-banner-main">
                <div class="container">
                    <div class=" inner-banner-con">
                        <div class="inner-banner-con-inn">
                            <h2>{{ __('Blog Details') }}</h2>
                        </div>
                        <div class="bread-crumb">
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="{{ url('/') }}"><i
                                                class="fas fa-home"></i></a></li>
                                    <li class="breadcrumb-item active" aria-current="page">{{ __('Blog Details') }}</li>
                                </ol>
                            </nav>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </section>


    <!--===================================blog detail Section start ===================================-->
    <section class="blog-detail-wrapper ">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 col-xs-12 col-md-12">
                    <div class="blog-detail-left">
                        <div class="blog-detail-image">
                            <img src="{{ asset($blog->image) }}" alt="{{ truncate($blog->translation->title, 50) }}">
                        </div>
                        <div class="blog-detail-title">
                            <h3>{{ truncate($blog->translation->title, 50) }}</h3>
                        </div>
                        <div class="feat_blog_con">
                            <p>
                                <span><i class="fas fa-calendar-alt" aria-hidden="true"></i>
                                    {{ formatDate($blog->created_at) }}</span>
                                &nbsp;&nbsp;
                                <span><i class="fas fa-globe" aria-hidden="true"></i><a
                                        href="#">{{ $blog->category->translation->title }}</a></span>
                            </p>
                        </div>
                        <div class="blod-detail-description mb-5">

                            {{-- {{$blog->translation->description}} --}}
                            {!! clean($blog->getTranslation($code)->description) !!}
                            {{-- <p>Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod
            tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam,
            quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo
            consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse
            cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non
            proident, sunt in culpa qui officia deserunt mollit anim id est laborum. 
            </p>
            <h2>Lorem ipsum dolor sit amet, consectetur adipisicing elit</h2>
           <p>Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod
            tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam,
            quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo
            consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse
            cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non
            proident, sunt in culpa qui officia deserunt mollit anim id est laborum. 
            </p>
           <p>Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod
            tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam,
            quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo
            consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse
            cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non
            proident, sunt in culpa qui officia deserunt mollit anim id est laborum. 
            </p>

            <h2>Lorem ipsum dolor sit amet, consectetur adipisicing elit</h2>
            <p>Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod
            tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam,
            quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo
            consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse
            cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non
            proident, sunt in culpa qui officia deserunt mollit anim id est laborum. 
            </p>
            <p>Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod
            tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam,
            quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo
            consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse
            cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non
            proident, sunt in culpa qui officia deserunt mollit anim id est laborum. 
            </p> --}}




                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-xs-12 col-md-12">

                    <section id="recent-posts-2" class="widget widget_recent_entries" d>
                        <h2 class="widget-title">{{ __('Recent Post') }}</h2>
                        <ul>
                            @forelse($latestBlogs as $lblog)
                                <li class="item-recent-post">
                                    <div class="thumbnail-post">
                                        <img src="{{ asset($lblog->image) }}">
                                    </div>
                                    <div class="title-post"><a
                                            href="{{ route('blog.show', $lblog->slug) }}">{{ truncate($lblog->translation->title, 50) }}</a> <span
                                            class="post-date"><i class="far fa-calendar-check" aria-hidden="true"></i>
                                            {{ formatDate($lblog->created_at) }}</span></div>
                                </li>
                                {{-- <li class="item-recent-post">
              <div class="thumbnail-post">
                <img src="{{ asset('frontend/img/blog-01b.webp') }}">
              </div>
              <div class="title-post"><a href="{{ route('blog.show', $lblog->slug) }}">Blog Heading</a> <span class="post-date"><i class="far fa-calendar-check" aria-hidden="true"></i> 24 Jan 2025</span></div>
            </li>
            <li class="item-recent-post">
              <div class="thumbnail-post">
                <img src="{{ asset('frontend/img/blog-01b.webp') }}">
              </div>
              <div class="title-post"><a href="#">Blog Heading</a> <span class="post-date"><i class="far fa-calendar-check" aria-hidden="true"></i> 24 Jan 2025</span></div>
            </li>
            <li class="item-recent-post">
              <div class="thumbnail-post">
                <img src="{{ asset('frontend/img/blog-01b.webp') }}">
              </div>
              <div class="title-post"><a href="#">Blog Heading</a><span class="post-date"><i class="far fa-calendar-check" aria-hidden="true"></i> 24 Jan 2025</span></div>
            </li>
            <li class="item-recent-post">
              <div class="thumbnail-post">
                <img src="{{ asset('frontend/img/blog-01b.webp') }}">
              </div>
              <div class="title-post"><a href="#">Blog Heading</a> <span class="post-date"><i class="far fa-calendar-check" aria-hidden="true"></i> 24 Jan 2025</span></div>
            </li>
            <li class="item-recent-post">
              <div class="thumbnail-post">
                <img src="{{ asset('frontend/img/blog-01b.webp') }}">
              </div>
              <div class="title-post"><a href="#">Blog Heading</a><span class="post-date"><i class="far fa-calendar-check" aria-hidden="true"></i> 24 Jan 2025</span></div>
            </li> --}}
                            @empty
                                <p class="text-center">{{ __('No Data Found') }}</p>
                            @endforelse
                        </ul>
                    </section>
                    <section id="categories-4" class="widget widget_categories">
                        <h2 class="widget-title">{{ __('Categories') }}</h2>
                        <ul>
                            @forelse($categories->sortBy('translation.title') as $category)
                                <li class="cat-item cat-item-2"><a
                                        href="{{ route('blogs', ['category' => $category->slug]) }}">{{ $category->translation->title }}</a>
                                    <span>({{ $category->posts_count }})</span></li>

                            @empty
                                <li>
                                    {{ __('No Category Found') }}
                                </li>
                            @endforelse
                            {{-- <li class="cat-item cat-item-2"><a href="#">Blog Categories</a> <span>(3)</span></li>
                            <li class="cat-item cat-item-2"><a href="#">Blog Categories</a> <span>(0)</span></li>
                            <li class="cat-item cat-item-2"><a href="#">Blog Categories</a> <span>(1)</span></li>
                            <li class="cat-item cat-item-2"><a href="#">Blog Categories</a> <span>(1)</span></li>
                            <li class="cat-item cat-item-2"><a href="#">Blog Categories</a> <span>(2)</span></li>
                            <li class="cat-item cat-item-2"><a href="#">Blog Categories</a> <span>(3)</span></li> --}}
                        </ul>
                    </section>

                    <section id="categories-4" class="widget widget_categories ">
                        <h2 class="widget-title">{{ __('Tag Cloud') }}</h2>
                        <div class="tag-div">

                            @if ($blog->tags)
                                @foreach (json_decode($blog->tags, true) as $tag)
                                    <a
                                        href="#">{{ htmlspecialchars(is_array($tag) ? $tag['value'] ?? '' : $tag) }}</a>
                                @endforeach

                            @endif
                            {{-- <a href="#">Meditation</a>
                            <a href="#">Peace</a>
                            <a href="#">Fitness</a>
                            <a href="#">Meditation</a>
                            <a href="#">Peace</a> --}}
                        </div>
                    </section>
                </div>

            </div>
        </div>
    </section>
    <!--===================================blog detail Section end ===================================-->




    <!-- blog-details-area -->
    <!--  -->
    <!-- blog-details-area-end -->
@endsection

@push('scripts')
    <script src="{{ asset('frontend/js/shareon.iife.js') }}"></script>

    <script>
        Shareon.init();
    </script>
@endpush
