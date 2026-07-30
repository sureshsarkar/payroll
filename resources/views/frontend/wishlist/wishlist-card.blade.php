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
        ->paginate(9);
@endphp

{{--
    Wishlist card partial — corporate redesign (2026-05).

    Critical preserved hooks:
      * .wsus-wishlist-remove + data-slug="{slug}" — used by global JS
        elsewhere for the AJAX remove call. Do NOT rename.
      * route('course.show', $course->slug) link target.
      * Currency / price / discount branching unchanged.
--}}

<div class="wl-grid">
    @forelse ($wishlistCourses ?? [] as $course)
        <div class="wl-card">
            <div class="wl-thumb">
                <a href="{{ route('course.show', $course->slug) }}">
                    <img src="{{ asset($course->thumbnail) }}" alt="{{ $course->title }}">
                </a>
                @if ($course->price == 0)
                    <span class="wl-price wl-price--free">{{ __('Free') }}</span>
                @elseif ($course->discount > 0)
                    <span class="wl-price">{{ currency($course->discount) }}</span>
                @else
                    <span class="wl-price">{{ currency($course->price) }}</span>
                @endif

                <a href="javascript:;"
                   class="wl-remove wsus-wishlist-remove"
                   data-slug="{{ $course->slug }}"
                   title="{{ __('Remove from wishlist') }}">
                    <i class="fas fa-times"></i>
                </a>
            </div>

            <div class="wl-body">
                @if (!empty($course->category?->name))
                    <span class="wl-cat">{{ $course->category->name }}</span>
                @endif

                <h3 class="wl-title">
                    <a href="{{ route('course.show', $course->slug) }}">
                        {{ truncate($course->title, 50) }}
                    </a>
                </h3>

                <div class="wl-rating">
                    <i class="fas fa-star"></i>
                    <strong>{{ number_format($course->avg_rating, 1) }}</strong>
                    <span>·</span>
                    <span>{{ $course->reviews_count ?? 0 }} {{ __('reviews') }}</span>
                </div>

                <div class="wl-meta">
                    <div class="wl-meta__item">
                        <i class="fas fa-book"></i>
                        <strong>{{ $course->lessons_count }}</strong> {{ __('lessons') }}
                    </div>
                    <div class="wl-meta__item">
                        <i class="fas fa-user-graduate"></i>
                        <strong>{{ $course->enrollments_count }}</strong> {{ __('students') }}
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="wl-empty">
            <div class="wl-empty__icon"><i class="far fa-heart"></i></div>
            <div class="wl-empty__title">{{ __('Your wishlist is empty') }}</div>
            <div class="wl-empty__text">{{ __('Tap the heart icon on any course to save it here for later.') }}</div>
            <a class="wl-empty__cta" href="{{ url('/courses') }}">
                <i class="fas fa-search" style="font-size:11px;"></i>
                {{ __('Browse courses') }}
            </a>
        </div>
    @endforelse
</div>

@if ($wishlistCourses->hasPages())
    <div class="wl-pagination">{{ $wishlistCourses->links() }}</div>
@endif
