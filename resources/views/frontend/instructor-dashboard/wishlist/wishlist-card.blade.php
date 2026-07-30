@php
    $wishlistCourses = userAuth()
        ->favoriteCourses()
        ->with('category.translation', 'instructor:id,name')
        ->withCount([
            'reviews as avg_rating' => function ($query) {
                $query->select(DB::raw('coalesce(avg(rating), 0)'));
            },
        ])
        ->withCount([
            'lessons' => function ($query) {
                $query->where('status', 'active');
            },
        ])
        ->withCount('enrollments')
        ->paginate(3);
@endphp
<div class="row">
    @forelse ($wishlistCourses ?? [] as $course)
        <div class="col-md-4">
            <div class="courses__item-four shine__animate-item">
                <div class="courses__item-thumb-three shine__animate-link">
                    <a href="{{ route('course.show', $course->slug) }}"><img
                            src="{{ asset($course->thumbnail) }}" alt="img"></a>
                    @if ($course->price == 0)
                        <span class="courses__price">{{ __('Free') }}</span>
                    @elseif ($course->price > 0 && $course->discount > 0)
                        <span class="courses__price">{{ currency($course->discount) }}</span>
                    @else
                        <span class="courses__price">{{ currency($course->price) }}</span>
                    @endif
                </div>
                <div class="courses__item-content-three">
                    <ul class="courses__item-meta list-wrap">
                        <li class="courses__item-tag">
                            <a
                                href="{{ route('courses', ['category' => $course->category->id]) }}">{{ $course->category?->name }}</a>
                        </li>
                        <li class="courses__wishlist">
                            <a href="javascript:;" class="wsus-wishlist-remove"
                                data-slug="{{ $course?->slug }}">
                                <img src="{{asset('uploads/website-images/trash.svg')}}">
                            </a>
                        </li>
                    </ul>
                    <h2 class="title"><a
                            href="{{ route('course.show', $course->slug) }}">{{ truncate($course->title, 50) }}</a>
                    </h2>
                    <div class="courses__review">
                        <div class="rating">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                        <span>({{ number_format($course->avg_rating, 1) ?? 0 }}
                            {{ __('Reviews') }})</span>
                    </div>
                    <div class="courses__item-bottom-three">
                        <ul class="list-wrap">
                            <li><i class="flaticon-book"></i>{{ __('Lessons') }}
                                {{ $course?->lessons_count }}</li>
                            <li><i class="flaticon-mortarboard"></i>{{ __('Students') }}
                                {{ $course?->enrollments_count }}</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    @empty
        <h6 class="text-center">{{ __('No Course Found') }}</h6>
    @endforelse
</div>
<nav class="wishlist pagination__wrap mt-25">
    {{ $wishlistCourses->links() }}
</nav>