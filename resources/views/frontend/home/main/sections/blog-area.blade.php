 
<section class="blog-area-wrapp">
    <div class="container">
        <div class="home-abt-con-inn categories-head-box">
            <h5><i class="fas fa-star-of-life"></i> {{ __('News & Blogs') }}</h5>
            <h3>{{ __('OurLates') }} <span>{{ __('') }}</span></h3>
            <p>{{ __('NewBlogDescription') }}</p>
        </div>
        <div class="row">

            @foreach ($featuredBlogs as $blog)
                <div class="col-lg-4 col-md-6 col-12">
                    <div class="blog-box">
                        <img src="{{ asset($blog->image ?? 'frontend/img/blogs.jpg') }}">
                        <h2>{{ $blog?->title }}</h2>
                        <a href="{{ route('blog.show', $blog->slug) }}">Read More</a>
                    </div>
                </div>
            @endforeach  
        </div>
    </div>
</section>
