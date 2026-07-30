@extends('frontend.layouts.master')
{{-- aud028-coursedetail-nullsafe-2026-07-17 --}}
@section('meta_title', $course?->title . ' || ' . $setting->app_name)
@push('custom_meta')
    <meta property="description" content="{{ $course->seo_description }}" />
    <meta property="og:title" content="{{ $course?->title }}" />
    <meta property="og:description" content="{{ $course->seo_description }}" />
    <meta property="og:image" content="{{ asset($course->thumbnail) }}" />
    <meta property="og:URL" content="{{ url()->current() }}" />
    <meta property="og:type" content="website" />
@endpush
@push('styles')
    <link rel="stylesheet" href="{{ asset('frontend/css/shareon.min.css') }}">
@endpush
@section('contents')
    <!-- breadcrumb-area -->
    {{-- <x-frontend.breadcrumb :title="__('Course Details')" :links="[
        ['url' => route('home'), 'text' => __('Home')],
        ['url' => route('become-instructor'), 'text' => __('Course Details')],
    ]" /> --}}
    <!-- breadcrumb-area-end -->

    <!-- courses-details-area -->
    <section class="courses__details-area section-py-120">
        <div class="container">
            <div class="row">
                <div class="col-xl-9 col-lg-8">
                    <div class="courses__details-thumb">
                        <img class="w-100" src="{{ asset($course->thumbnail) }}" alt="img">
                        @if ($course->demo_video_source)
                            <a href="{{ $course->demo_video_source }}" class="popup-video"
                                aria-label="{{ $course?->title }}"><i class="fas fa-play"></i></a>
                        @endif
                    </div>
                    <div class="courses__details-content">
                        <ul class="courses__item-meta list-wrap">
                            <li class="courses__item-tag">
                                <a
                                    href="{{ ($course->category ? route('courses', ['category' => $course->category->id]) : 'javascript:;') }}">{{ ($course->category?->translation?->name ?? $course->category?->name ?? '') }}</a>
                            </li>
                            <li class="avg-rating"><i class="fas fa-star"></i>
                                {{ number_format($course->reviews()->avg('rating'), 1) ?? 0 }} {{ __('Reviews') }}</li>
                            <li class="courses__wishlist">
                                <a href="javascript:;" class="wsus-wishlist-btn" aria-label="WishList"
                                    data-slug="{{ $course?->slug }}">
                                    <i class="{{ $course?->favorite_by_client ? 'fas' : 'far' }} fa-heart"></i>
                                </a>
                            </li>
                        </ul>
                        <h2 class="title">{{ $course?->title }}</h2>
                        <div class="courses__details-meta">
                            <ul class="list-wrap">
                                <li class="author-two">
                                    <img src="{{ asset($course->instructor?->image??"") }}" alt="img"
                                        class="instructor-avatar">
                                    {{ __('By') }}
                                    <a
                                        href="{{ ($course->instructor ? route('instructor-details', $course->instructor->id) : 'javascript:;') }}">{{ ($course->instructor?->name ?? __('Unknown')) }}</a>
                                </li>
                                <li class="date"><i
                                        class="flaticon-calendar"></i>{{ formatDate($course->created_at, 'd/M/Y') }}</li>
                                <li><i class="flaticon-mortarboard"></i>{{ $course->enrollments->count() }}
                                    {{ __('Students') }}</li>
                            </ul>
                        </div>
                        <ul class="nav nav-tabs" id="myTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="overview-tab" data-bs-toggle="tab"
                                    data-bs-target="#overview-tab-pane" type="button" role="tab"
                                    aria-controls="overview-tab-pane" aria-selected="true">{{ __('Overview') }}</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="curriculum-tab" data-bs-toggle="tab"
                                    data-bs-target="#curriculum-tab-pane" type="button" role="tab"
                                    aria-controls="curriculum-tab-pane"
                                    aria-selected="false">{{ __('Curriculum') }}</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="instructors-tab" data-bs-toggle="tab"
                                    data-bs-target="#instructors-tab-pane" type="button" role="tab"
                                    aria-controls="instructors-tab-pane" aria-selected="false">{{ __('Coach') }}</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="reviews-tab" data-bs-toggle="tab"
                                    data-bs-target="#reviews-tab-pane" type="button" role="tab"
                                    aria-controls="reviews-tab-pane" aria-selected="false">{{ __('reviews') }}</button>
                            </li>
                        </ul>
                        <div class="tab-content" id="myTabContent">
                            <div class="tab-pane fade show active" id="overview-tab-pane" role="tabpanel"
                                aria-labelledby="overview-tab" tabindex="0">
                                <div class="courses__overview-wrap">
                                    <h3 class="title">{{ __('Course Description') }}</h3>
                                    {!! clean($course->description) !!}

                                </div>
                            </div>
                            <div class="tab-pane fade" id="curriculum-tab-pane" role="tabpanel"
                                aria-labelledby="curriculum-tab" tabindex="0">
                                <div class="courses__curriculum-wrap">
                                    <h3 class="title">{{ __('Course Curriculum') }}</h3>
                                    <p></p>
                                    <div class="accordion" id="accordionExample">
                                        @foreach ($course->chapters as $chapter)
                                            <div class="accordion-item">
                                                <h2 class="accordion-header" id="heading{{ $chapter->id }}">
                                                    <button class="accordion-button collapsed" type="button"
                                                        data-bs-toggle="collapse"
                                                        data-bs-target="#collapse{{ $chapter->id }}"
                                                        aria-expanded="false"
                                                        aria-controls="collapse{{ $chapter->id }}">
                                                        {{ $loop->iteration }}. {{ $chapter?->title }}
                                                    </button>
                                                </h2>
                                                <div id="collapse{{ $chapter->id }}" class="accordion-collapse collapse"
                                                    aria-labelledby="heading{{ $chapter->id }}"
                                                    data-bs-parent="#accordionExample">
                                                    <div class="accordion-body">
                                                        <ul class="list-wrap">
                                                            @foreach ($chapter->chapterItems as $chapterItem)
                                                                @if ($chapterItem?->type == 'lesson')
                                                                    @if ($chapterItem?->lesson?->is_free == 1)
                                                                        @if ($chapterItem?->lesson?->file_type == 'video')
                                                                            @if ($chapterItem?->lesson->storage == 'google_drive')
                                                                                <li class="course-item open-item">
                                                                                    <a href="javascript:;"
                                                                                        data-bs-toggle="modal"
                                                                                        data-bs-target="#videoModal"
                                                                                        data-bs-video="https://drive.google.com/file/d/{{ extractGoogleDriveVideoId($chapterItem?->lesson->file_path) }}/preview"
                                                                                        class="course-item-link">
                                                                                        <span
                                                                                            class="item-name">{{ $chapterItem?->lesson?->title }}</span>
                                                                                        <div class="course-item-meta">
                                                                                            <span
                                                                                                class="item-meta duration">{{ minutesToHours($chapterItem?->lesson?->duration) }}</span>
                                                                                        </div>
                                                                                    </a>
                                                                                </li>
                                                                            @else
                                                                                <li class="course-item open-item">
                                                                                    <a href="@if (!in_array($chapterItem?->lesson->storage, ['wasabi', 'aws'])) {{ $chapterItem?->lesson->file_path }} @else {{ Storage::disk($chapterItem?->lesson->storage)->temporaryUrl($chapterItem?->lesson->file_path, now()->addHours(1)) }} @endif"
                                                                                        class="course-item-link popup-video">
                                                                                        <span
                                                                                            class="item-name">{{ $chapterItem?->lesson?->title??"" }}</span>
                                                                                        <div class="course-item-meta">
                                                                                            <span
                                                                                                class="item-meta duration">{{ minutesToHours($chapterItem?->lesson?->duration) }}</span>
                                                                                        </div>
                                                                                    </a>
                                                                                </li>
                                                                            @endif
                                                                        @else
                                                                            <li class="course-item">
                                                                                <a href="javascript:;"
                                                                                    class="course-item-link">
                                                                                    <span
                                                                                        class="item-name">{{ $chapterItem?->lesson?->title??"" }}</span>
                                                                                    <div class="course-item-meta">
                                                                                        <span class="item-meta duration">
                                                                                            --.-- </span>
                                                                                        <span
                                                                                            class="item-meta course-item-status">
                                                                                            <img src="{{ asset('frontend/img/icons/lock.svg') }}"
                                                                                                alt="icon">
                                                                                        </span>
                                                                                    </div>
                                                                                </a>
                                                                            </li>
                                                                        @endif
                                                                    @else
                                                                        <li class="course-item">
                                                                            <a href="javascript:;"
                                                                                class="course-item-link">
                                                                                <span
                                                                                    class="item-name">{{ $chapterItem?->lesson?->title }}</span>
                                                                                <div class="course-item-meta">
                                                                                    <span
                                                                                        class="item-meta duration">{{ minutesToHours($chapterItem?->lesson?->duration) }}</span>
                                                                                    <span
                                                                                        class="item-meta course-item-status">
                                                                                        <img src="{{ asset('frontend/img/icons/lock.svg') }}"
                                                                                            alt="icon">
                                                                                    </span>
                                                                                </div>
                                                                            </a>
                                                                        </li>
                                                                    @endif
                                                                @elseif($chapterItem?->type == 'document')
                                                                    <li class="course-item">
                                                                        <a href="javascript:;" class="course-item-link">
                                                                            <span
                                                                                class="item-name">{{ $chapterItem?->lesson?->title }}</span>
                                                                            <div class="course-item-meta">
                                                                                <span
                                                                                    class="item-meta duration">{{ minutesToHours($chapterItem?->lesson?->duration) }}</span>
                                                                                <span class="item-meta course-item-status">
                                                                                    <img src="{{ asset('frontend/img/icons/lock.svg') }}"
                                                                                        alt="icon">
                                                                                </span>
                                                                            </div>
                                                                        </a>
                                                                    </li>
                                                                @elseif ($chapterItem->type == 'quiz')
                                                                    <li class="course-item">
                                                                        <a href="javascript:;" class="course-item-link">
                                                                            <span
                                                                                class="item-name">{{ $chapterItem?->quiz?->title }}</span>
                                                                            <div class="course-item-meta">
                                                                                <span
                                                                                    class="item-meta duration">{{ minutesToHours($chapterItem?->lesson?->duration) }}</span>
                                                                                <span class="item-meta course-item-status">
                                                                                    <img src="{{ asset('frontend/img/icons/lock.svg') }}"
                                                                                        alt="icon">
                                                                                </span>
                                                                            </div>
                                                                        </a>
                                                                    </li>
                                                                @endif
                                                            @endforeach
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="instructors-tab-pane" role="tabpanel"
                                aria-labelledby="instructors-tab" tabindex="0">

                                <div class="courses__instructors-wrap">
                                    <div class="courses__instructors-thumb">
                                        <img src="{{ asset($course->instructor?->image) }}" alt="img"
                                            class="instructor-thumb">
                                    </div>
                                    <div class="courses__instructors-content">
                                        <h2 class="title">{{ ($course->instructor?->name ?? __('Unknown')) }}</h2>
                                        <span class="designation">{{ $course->instructor?->job_title }}</span>
                                        <p>{{ $course->instructor?->short_bio }}</p>
                                        <div class="instructor__social">
                                            <ul class="list-wrap justify-content-start">
                                                @if ($course->instructor->facebook)
                                                    <li><a href="{{ $course->instructor->facebook }}"
                                                            aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                                                    </li>
                                                @endif
                                                @if ($course->instructor->twitter)
                                                    <li><a href="{{ $course->instructor->twitter }}"
                                                            aria-label="Twitter"><i class="fab fa-twitter"></i></a></li>
                                                @endif
                                                @if ($course->instructor->linkedin)
                                                    <li><a href="{{ $course->instructor->linkedin }}"
                                                            aria-label="Linkedin"><i class="fab fa-linkedin"></i></a></li>
                                                @endif
                                                @if ($course->instructor->github)
                                                    <li><a href="{{ $course->instructor->github }}"
                                                            aria-label="Github"><i class="fab fa-github"></i></a></li>
                                                @endif

                                                @if ($course->instructor->facebook)
                                                    <li><a href="{{ $course->instructor->facebook }}"
                                                            aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                                                    </li>
                                                @endif
                                                @if ($course->instructor->twitter)
                                                    <li><a href="{{ $course->instructor->twitter }}"
                                                            aria-label="Twitter"><i class="fab fa-twitter"></i></a></li>
                                                @endif
                                                @if ($course->instructor->website)
                                                    <li><a href="{{ $course->instructor->website }}"
                                                            aria-label="Website"><i class="fas fa-link"></i></a></li>
                                                @endif
                                                @if ($course->instructor->github)
                                                    <li><a href="{{ $course->instructor->github }}"
                                                            aria-label="Github"><i class="fab fa-github"></i></a></li>
                                                @endif
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                @if ($course->partnerInstructors->count() > 0)
                                    <h3 class="title mt-3">{{ __('Partner Instructors') }}</h3>
                                    @foreach ($course->partnerInstructors as $instructor)
                                        <div class="courses__instructors-wrap">
                                            <div class="courses__instructors-thumb">
                                                <img src="{{ asset($instructor->instructor?->image) }}" alt="img">
                                            </div>
                                            <div class="courses__instructors-content">
                                                <h2 class="title">{{ ($instructor->instructor?->name ?? __('Unknown')) }}</h2>
                                                <span class="designation">{{ $instructor->instructor?->job_title }}</span>
                                                <p>{{ $instructor->instructor?->short_bio }}</p>
                                                <div class="instructor__social">
                                                    <ul class="list-wrap justify-content-start">
                                                        @if ($instructor->instructor->facebook)
                                                            <li><a href="{{ $instructor->instructor->facebook }}"
                                                                    aria-label="Facebook"><i
                                                                        class="fab fa-facebook-f"></i></a></li>
                                                        @endif
                                                        @if ($instructor->instructor->twitter)
                                                            <li><a href="{{ $instructor->instructor->twitter }}"
                                                                    aria-label="Twitter"><i
                                                                        class="fab fa-twitter"></i></a></li>
                                                        @endif
                                                        @if ($instructor->instructor->website)
                                                            <li><a href="{{ $instructor->instructor->website }}"
                                                                    aria-label="Website"><i class="fas fa-link"></i></a>
                                                            </li>
                                                        @endif
                                                        @if ($instructor->instructor->github)
                                                            <li><a href="{{ $instructor->instructor->github }}"
                                                                    aria-label="Github"><i class="fab fa-github"></i></a>
                                                            </li>
                                                        @endif
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                @endif
                            </div>
                            <div class="tab-pane fade" id="reviews-tab-pane" role="tabpanel"
                                aria-labelledby="reviews-tab" tabindex="0">
                                <div class="courses__rating-wrap">
                                    <h2 class="title">{{ __('Reviews') }}</h2>
                                    <div class="course-rate">
                                        <div class="course-rate__summary">
                                            <div class="course-rate__summary-value">
                                                {{ number_format($course->reviews()->whereHas('course')->whereHas('user')->avg('rating'), 1) ?? 0 }}
                                            </div>
                                            <div class="course-rate__summary-stars">
                                                <i class="fas fa-star"></i>
                                                <i class="fas fa-star"></i>
                                                <i class="fas fa-star"></i>
                                                <i class="fas fa-star"></i>
                                                <i class="fas fa-star"></i>
                                            </div>
                                            <div class="course-rate__summary-text">
                                                {{ $course->reviews()->whereHas('course')->whereHas('user')->where('status', 1)->count() }}
                                                {{ __('Ratings') }}
                                            </div>
                                        </div>
                                        @php
                                            $totalRating = $course->reviews_count;
                                            $fiveStar = $course
                                                ->reviews()
                                                ->where('rating', 5)
                                                ->where('status', 1)
                                                ->whereHas('course')
                                                ->whereHas('user')
                                                ->count();
                                            $fourStar = $course
                                                ->reviews()
                                                ->where('rating', 4)
                                                ->where('status', 1)
                                                ->whereHas('course')
                                                ->whereHas('user')
                                                ->count();
                                            $threeStar = $course
                                                ->reviews()
                                                ->where('rating', 3)
                                                ->where('status', 1)
                                                ->whereHas('course')
                                                ->whereHas('user')
                                                ->count();
                                            $twoStar = $course
                                                ->reviews()
                                                ->where('rating', 2)
                                                ->where('status', 1)
                                                ->whereHas('course')
                                                ->whereHas('user')
                                                ->count();
                                            $oneStar = $course
                                                ->reviews()
                                                ->where('rating', 1)
                                                ->where('status', 1)
                                                ->whereHas('course')
                                                ->whereHas('user')
                                                ->count();
                                            $totalPercentage = $totalRating > 0 ? ($fiveStar / $totalRating) * 100 : 0;
                                            $fourPercentage = $totalRating > 0 ? ($fourStar / $totalRating) * 100 : 0;
                                            $threePercentage = $totalRating > 0 ? ($threeStar / $totalRating) * 100 : 0;
                                            $twoPercentage = $totalRating > 0 ? ($twoStar / $totalRating) * 100 : 0;
                                            $onePercentage = $totalRating > 0 ? ($oneStar / $totalRating) * 100 : 0;
                                        @endphp
                                        <div class="course-rate__details">
                                            <div class="course-rate__details-row">
                                                <div class="course-rate__details-row-star">
                                                    5
                                                    <i class="fas fa-star"></i>
                                                </div>
                                                <div class="course-rate__details-row-value">
                                                    <div class="rating-gray"></div>
                                                    <div class="rating" style="width: {{ $totalPercentage }}%;"
                                                        title="{{ $totalPercentage }}%"></div>
                                                    <span class="rating-count">{{ $fiveStar }}</span>
                                                </div>
                                            </div>
                                            <div class="course-rate__details-row">
                                                <div class="course-rate__details-row-star">
                                                    4
                                                    <i class="fas fa-star"></i>
                                                </div>
                                                <div class="course-rate__details-row-value">
                                                    <div class="rating-gray"></div>
                                                    <div class="rating" style="width: {{ $fourPercentage }}%;"
                                                        title="{{ $fourPercentage }}%"></div>
                                                    <span class="rating-count">{{ $fourStar }}</span>
                                                </div>
                                            </div>
                                            <div class="course-rate__details-row">
                                                <div class="course-rate__details-row-star">
                                                    3
                                                    <i class="fas fa-star"></i>
                                                </div>
                                                <div class="course-rate__details-row-value">
                                                    <div class="rating-gray"></div>
                                                    <div class="rating" style="width: {{ $threePercentage }}%;"
                                                        title="{{ $threePercentage }}%"></div>
                                                    <span class="rating-count">{{ $threeStar }}</span>
                                                </div>
                                            </div>
                                            <div class="course-rate__details-row">
                                                <div class="course-rate__details-row-star">
                                                    2
                                                    <i class="fas fa-star"></i>
                                                </div>
                                                <div class="course-rate__details-row-value">
                                                    <div class="rating-gray"></div>
                                                    <div class="rating" style="width: {{ $twoPercentage }}%;"
                                                        title="{{ $twoPercentage }}%"></div>
                                                    <span class="rating-count">{{ $twoStar }}</span>
                                                </div>
                                            </div>
                                            <div class="course-rate__details-row">
                                                <div class="course-rate__details-row-star">
                                                    1
                                                    <i class="fas fa-star"></i>
                                                </div>
                                                <div class="course-rate__details-row-value">
                                                    <div class="rating-gray"></div>
                                                    <div class="rating" style="width: {{ $onePercentage }}%;"
                                                        title="{{ $onePercentage }}%"></div>
                                                    <span class="rating-count">{{ $oneStar }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    @foreach ($reviews as $review)
                                        <div class="course-review-head">
                                            <div class="review-author-thumb">
                                                <img src="{{ asset($review?->user?->image) }}" alt="img">
                                            </div>
                                            <div class="review-author-content">
                                                <div class="author-name">
                                                    <h5 class="name">{{ $review?->user?->name }}
                                                        <span>{{ formatDate($review->created_at) }}</span>
                                                    </h5>
                                                    <div class="author-rating">
                                                        @for ($i = 1; $i <= $review->rating; $i++)
                                                            <i class="fas fa-star"></i>
                                                        @endfor
                                                    </div>
                                                </div>
                                                <p>{{ $review->review }}</p>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-4">
                    {{-- 2026-06-16 — modern, brand-aware sidebar redesign (Issue 3, phase 1).
                         Scoped to .courses__details-sidebar; accent uses each coach's brand
                         (--tg-theme-primary, set per white-label site) so it matches the
                         coach theme, with a sensible fallback on the platform. --}}
                    <style>
                        .courses__details-sidebar{ --cd-accent: var(--tg-theme-primary, #4f46e5); background:#fff; border:1px solid #e8eaf0; border-radius:18px; box-shadow:0 8px 30px rgba(15,23,42,.07); padding:22px; position:sticky; top:96px; }
                        .courses__details-sidebar .courses__cost-wrap{ background:linear-gradient(135deg, color-mix(in srgb, var(--cd-accent) 12%, #fff), #fff); border:1px solid color-mix(in srgb, var(--cd-accent) 20%, transparent); border-radius:14px; padding:16px 18px; margin-bottom:18px; text-align:left; }
                        .courses__details-sidebar .courses__cost-wrap span{ font-size:12px; color:#64748b; font-weight:700; text-transform:uppercase; letter-spacing:.05em; }
                        .courses__details-sidebar .courses__cost-wrap .title{ font-size:30px; font-weight:800; color:#0f172a; margin:5px 0 0; line-height:1.1; display:flex; align-items:baseline; gap:10px; flex-wrap:wrap; }
                        .courses__details-sidebar .courses__cost-wrap .title del{ font-size:17px; color:#94a3b8; font-weight:500; }
                        .courses__details-sidebar .courses__information-wrap > .title,
                        .courses__details-sidebar .courses__details-social > .title{ font-size:15px; font-weight:700; color:#0f172a; margin:0 0 12px; }
                        .courses__details-sidebar .courses__information-wrap .list-wrap{ list-style:none; margin:0 0 18px; padding:0; }
                        .courses__details-sidebar .courses__information-wrap .list-wrap li{ display:flex; align-items:center; gap:10px; padding:11px 2px; border-bottom:1px solid #f1f5f9; font-size:14px; color:#475569; font-weight:500; }
                        .courses__details-sidebar .courses__information-wrap .list-wrap li:last-child{ border-bottom:none; }
                        .courses__details-sidebar .courses__information-wrap .list-wrap li img{ width:18px; height:18px; opacity:.85; }
                        .courses__details-sidebar .courses__information-wrap .list-wrap li > span{ margin-left:auto; font-weight:700; color:#0f172a; }
                        .courses__details-sidebar .courses__details-social{ margin:16px 0; padding-top:16px; border-top:1px solid #f1f5f9; }
                        .courses__details-sidebar .courses__details-enroll .btn{ display:flex !important; align-items:center; justify-content:center; gap:8px; width:100%; border-radius:12px !important; padding:14px 18px !important; font-size:15px; font-weight:700; transition:transform .15s ease, box-shadow .15s ease; }
                        .courses__details-sidebar .courses__details-enroll .btn.btn-two{ background:var(--cd-accent) !important; color:#fff !important; border:none !important; box-shadow:0 6px 18px color-mix(in srgb, var(--cd-accent) 35%, transparent); }
                        .courses__details-sidebar .courses__details-enroll .btn.btn-two:hover{ transform:translateY(-2px); box-shadow:0 12px 26px color-mix(in srgb, var(--cd-accent) 45%, transparent); }
                        .courses__details-sidebar .courses__details-enroll .btn.btn-four{ background:#fff !important; color:var(--cd-accent) !important; border:1.5px solid color-mix(in srgb, var(--cd-accent) 30%, transparent) !important; }
                        .courses__details-sidebar .courses__details-enroll .btn.btn-four:hover{ background:color-mix(in srgb, var(--cd-accent) 8%, #fff) !important; transform:translateY(-1px); }
                        @media (max-width:991px){ .courses__details-sidebar{ position:static; top:auto; } }
                    </style>
                    <div class="courses__details-sidebar">
                        <div class="courses__cost-wrap">
                            <span>{{ __('This Course Fee') }}:</span>
                            @if ($course->price == 0)
                                <h2 class="title">{{ __('Free') }}</h2>
                            @elseif ($course->discount)
                                <h2 class="title">{{ currency($course->discount) }}
                                    <del>{{ currency($course->price) }}</del>
                                </h2>
                            @else
                                <h2 class="title">{{ currency($course->price) }}</h2>
                            @endif

                        </div>
                        <div class="courses__information-wrap">
                            <h5 class="title">{{ __('Course includes') }}:</h5>
                            <ul class="list-wrap">
                                {{-- 2026-06-15 — Level removed: this attribute isn't maintained in
                                     course data, so it rendered empty/irrelevant to students. --}}
                                <li>
                                    <img src="{{ asset('frontend/img/icons/course_icon02.svg') }}" alt="img"
                                        class="injectable">
                                    {{ __('Duration') }}
                                    <span>{{ minutesToHours($course->duration) }}</span>
                                </li>
                                <li>
                                    <img src="{{ asset('frontend/img/icons/course_icon03.svg') }}" alt="img"
                                        class="injectable">
                                    {{ __('Lessons') }}
                                    <span>{{ $courseLessonCount }}</span>
                                </li>
                                <li>
                                    <img src="{{ asset('frontend/img/icons/course_icon03.svg') }}" alt="img"
                                        class="injectable">
                                    {{ __('Batches') }}
                                    <span>{{ $courseBatchCount }}</span>
                                </li>
                                <li>
                                    <img src="{{ asset('frontend/img/icons/course_icon04.svg') }}" alt="img"
                                        class="injectable">
                                    {{ __('Quizzes') }}
                                    <span>{{ $courseQuizCount }}</span>
                                </li>
                                <li>
                                    <img src="{{ asset('frontend/img/icons/course_icon05.svg') }}" alt="img"
                                        class="injectable">
                                    {{ __('Certifications') }}
                                    @if ($course->certificate)
                                        <span>{{ __('Yes') }}</span>
                                    @else
                                        <span>{{ __('No') }}</span>
                                    @endif
                                </li>
                                {{-- 2026-06-15 — Language removed: not maintained in course data
                                     (rendered empty), so it's no longer shown to students. --}}
                            </ul>
                        </div>
                        <div class="courses__details-social">
                            <h5 class="title">{{ __('Share this course') }}:</h5>
                            <div class="shareon">
                                <a class="facebook"></a>
                                <a class="linkedin"></a>
                                <a class="pinterest"></a>
                                <a class="telegram"></a>
                                <a class="twitter"></a>
                            </div>
                        </div>
                        <div class="courses__details-enroll">
                            <div class="tg-button-wrap">
                                @if (in_array($course->id, session('enrollments') ?? []))
                                    <a href="{{ route('student.enrolled-courses') }}"
                                        class="btn btn-two arrow-btn already-enrolled-btn" data-id="">
                                        <span class="text">{{ __('Enrolled') }}</span>
                                        <i class="flaticon-arrow-right"></i>
                                    </a>
                                @elseif ($course->enrollments->count() >= $course->capacity && $course->capacity != null)
                                    <a href="javascript:;" class="btn btn-two arrow-btn" data-id="{{ $course->id }}">
                                        <span class="text">{{ __('Booked') }}</span>
                                        <i class="flaticon-arrow-right"></i>
                                    </a>
                                @else
                                    {{-- 2026-06-11 — only Live/Batch courses open the batch picker modal;
                                         recorded courses ('course'|'recorded') add directly via cart.js. --}}
                                    <a href="javascript:;" class="btn btn-two arrow-btn add-to-cart"
                                        data-id="{{ $course->id }}" data-type="{{ $course->type }}"
                                        @if(in_array($course->type, ['live', 'hybrid'])) data-bs-toggle="modal" data-bs-target="#addToCartModal" @endif>
                                        <span class="text">{{ __('Add To Cart') }}</span>
                                        <i class="flaticon-arrow-right"></i>
                                    </a>
                                @endif
                            </div>
                            @if (Module::has('GiftCourse') && Module::isEnabled('GiftCourse'))
                                <div class="d-block text-center mt-3">
                                    <a href="{{ route('gift-course', $course->slug) }}" class="btn btn-four arrow-btn">
                                        <i class="fas fa-gift"></i> {{ __('Gift This Course') }}
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- Google Drive player modal Structure -->
    <div class="google_drive_modal">
        <div class="modal fade" id="videoModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><i
                                class="fas fa-times"></i></button>
                    </div>
                    <div class="modal-body">
                        <div class="ratio ratio-16x9">
                            <iframe class="iframe-video" src="" width="640" height="680" allow="autoplay"
                                frameborder="0" allowfullscreen></iframe>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- courses-details-area-end -->

    <!-- Start batch modal -->
    {{-- 2026-06-16 — Enterprise batch picker. WHITE-LABEL: the accent follows
         the coach brand (--tg-theme-primary), so each coach's site renders the
         modal in its OWN colour — no hardcoded purple. Scoped to #addToCartModal. --}}
    <style nonce="{{ csp_nonce() }}">
        #addToCartModal{ --bm-accent:var(--tg-theme-primary,#4f46e5); }
        #addToCartModal .modal-dialog{ filter:drop-shadow(0 40px 90px rgba(15,23,42,.35)); }
        #addToCartModal .modal-content{ border:0; border-radius:24px; overflow:hidden; box-shadow:none; background:linear-gradient(180deg,#ffffff,#fbfcff); }
        #addToCartModal .modal-content::before{ content:""; display:block; height:5px;
            background:linear-gradient(90deg, var(--bm-accent), color-mix(in srgb, var(--bm-accent) 45%, #fff)); }
        #addToCartModal .modal-body{ padding:0; }

        #addToCartModal .bm-head{ padding:26px 32px 20px; }
        #addToCartModal .bm-head__row{ display:flex; align-items:flex-start; justify-content:space-between; gap:16px; }
        #addToCartModal .bm-eyebrow{ display:inline-flex; align-items:center; gap:8px; font-size:11.5px; font-weight:700; letter-spacing:.14em; text-transform:uppercase; color:var(--bm-accent); margin-bottom:9px; }
        #addToCartModal .bm-eyebrow::before{ content:""; width:18px; height:2px; border-radius:2px; background:var(--bm-accent); display:inline-block; }
        #addToCartModal .bm-title{ font-family:'Plus Jakarta Sans',sans-serif; font-weight:800; font-size:25px; letter-spacing:-.03em; margin:0; color:#0f172a; }
        #addToCartModal .bm-sub{ margin:6px 0 0; font-size:14px; color:#64748b; line-height:1.55; max-width:560px; }
        #addToCartModal .bm-close{ border:0; background:#f1f5f9; width:40px; height:40px; border-radius:50%; color:#475569; font-size:20px; line-height:1; flex:0 0 auto; transition:.18s; }
        #addToCartModal .bm-close:hover{ background:#e2e8f0; color:#0f172a; transform:rotate(90deg); }
        #addToCartModal #batchContainer{ padding:8px 32px 28px; margin:0; }

        #addToCartModal .batch-card{ position:relative; background:#fff; border:1.5px solid #e9ecf3; border-radius:18px; padding:22px;
            transition:transform .2s cubic-bezier(.2,.8,.2,1), box-shadow .2s, border-color .2s; }
        #addToCartModal .batch-card::after{ content:""; position:absolute; inset:0; border-radius:18px; pointer-events:none; opacity:0; transition:opacity .2s;
            background:radial-gradient(120% 80% at 50% -10%, color-mix(in srgb, var(--bm-accent) 9%, transparent), transparent 60%); }
        #addToCartModal .batch-card:hover{ border-color:var(--bm-accent); transform:translateY(-4px); box-shadow:0 18px 40px rgba(15,23,42,.12); }
        #addToCartModal .batch-card:hover::after{ opacity:1; }
        #addToCartModal .batch-card.selected{ border-color:var(--bm-accent); box-shadow:0 0 0 3px color-mix(in srgb, var(--bm-accent) 22%, transparent), 0 18px 40px rgba(15,23,42,.12); }

        #addToCartModal .batch-card__check{ position:absolute; top:14px; right:14px; width:26px; height:26px; border-radius:50%; background:var(--bm-accent); color:#fff;
            display:flex; align-items:center; justify-content:center; opacity:0; transform:scale(.4); transition:opacity .2s, transform .25s cubic-bezier(.2,1.4,.4,1); z-index:2; }
        #addToCartModal .batch-card.selected .batch-card__check{ opacity:1; transform:scale(1); }

        #addToCartModal .batch-card__head{ display:flex; align-items:center; gap:11px; margin-bottom:16px; }
        #addToCartModal .batch-card__icon{ flex:0 0 auto; width:38px; height:38px; border-radius:11px; display:flex; align-items:center; justify-content:center;
            color:var(--bm-accent); background:color-mix(in srgb, var(--bm-accent) 12%, #fff); }
        #addToCartModal .batch-title{ font-family:'Plus Jakarta Sans',sans-serif; font-weight:700; font-size:17px; margin:0; color:#0f172a; letter-spacing:-.01em; text-transform:capitalize; }

        #addToCartModal .batch-meta{ list-style:none; margin:0; padding:0; }
        #addToCartModal .batch-meta li{ display:flex; align-items:center; gap:9px; padding:8px 0; border-bottom:1px dashed #eef0f5; font-size:13.5px; }
        #addToCartModal .batch-meta li:last-child{ border-bottom:0; }
        #addToCartModal .bm-ico{ flex:0 0 auto; color:#94a3b8; display:flex; }
        #addToCartModal .bm-k{ color:#64748b; font-weight:500; }
        #addToCartModal .bm-v{ margin-left:auto; color:#0f172a; font-weight:600; text-align:right; }

        #addToCartModal .batch-card__foot{ display:flex; align-items:center; justify-content:space-between; gap:12px; margin-top:18px; padding-top:16px; border-top:1px solid #f1f5f9; }
        #addToCartModal .batch-seats{ display:inline-flex; align-items:center; gap:7px; font-size:12px; font-weight:600; color:#0f766e; background:#ecfdf5; border:1px solid #d1fae5; padding:5px 11px; border-radius:999px; }
        #addToCartModal .bm-dot{ width:7px; height:7px; border-radius:50%; background:#10b981; display:inline-block; box-shadow:0 0 0 3px rgba(16,185,129,.18); }

        #addToCartModal .choose-batch-btn{ border:0; background:var(--bm-accent); color:#fff; font-weight:600; font-size:13.5px; padding:10px 20px; border-radius:11px;
            box-shadow:0 8px 18px rgba(15,23,42,.14); transition:transform .15s, filter .15s, box-shadow .15s; }
        #addToCartModal .choose-batch-btn:hover{ filter:brightness(.94); transform:translateY(-1px); box-shadow:0 12px 24px rgba(15,23,42,.18); }
        #addToCartModal .batch-card.selected .choose-batch-btn{ background:color-mix(in srgb, var(--bm-accent) 82%, #000); }

        /* 2026-07-08 — sticky footer: the "Confirm & add to cart" button stays
           pinned to the bottom of the scrollable modal so the user never has to
           scroll past the batch list to add to cart. */
        #addToCartModal .bm-foot{ position:sticky; bottom:0; z-index:5; padding:14px 32px 24px;
            background:linear-gradient(180deg, rgba(251,252,255,0) 0%, #fbfcff 26%); }
        #addToCartModal #confirmBatch{ border:0; width:100%; color:#fff; font-weight:700; font-size:15.5px; padding:15px 26px; border-radius:14px; letter-spacing:.01em;
            background:linear-gradient(90deg, var(--bm-accent), color-mix(in srgb, var(--bm-accent) 72%, #000));
            box-shadow:0 14px 30px color-mix(in srgb, var(--bm-accent) 35%, transparent); transition:transform .15s, filter .15s; }
        #addToCartModal #confirmBatch:hover{ filter:brightness(1.06); transform:translateY(-2px); }
        #addToCartModal #confirmBatch:active{ transform:translateY(0); }

        @media (max-width:575px){ #addToCartModal #batchContainer{ padding:6px 18px 20px; } #addToCartModal .bm-head{ padding:22px 18px 16px; } #addToCartModal .bm-foot{ padding:6px 18px 22px; } #addToCartModal .bm-title{ font-size:21px; } }
    </style>
    <div class="modal fade" id="addToCartModal" tabindex="-1" role="dialog" aria-labelledby="addToCartModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width:1000px" role="document">
            <div class="modal-content card-bg-color">
                <div class="modal-body">

                    <div class="bm-head">
                        <div class="bm-head__row">
                            <div>
                                <span class="bm-eyebrow">{{ __('Enrollment') }}</span>
                                <h5 class="bm-title" id="addToCartModalTitle">{{ __('Choose your batch') }}</h5>
                                <p class="bm-sub">{{ __('Pick the schedule that suits you, then confirm to add this course to your cart.') }}</p>
                            </div>
                            <button type="button" class="bm-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}">&times;</button>
                        </div>
                    </div>

                    <form id="batchCartForm" action="{{ route('add-to-cart-with-batch') }}" method="POST">
                        @csrf

                        <input type="hidden" name="course_id" id="selectedCourse" value="{{ $course->id }}" required>
                        <input type="hidden" name="batch_id" id="selectedBatch" value="" required>

                        <div class="container-fluid p-0">
                            <div class="row g-4" id="batchContainer">
                                <!-- Batches will be inserted here -->
                            </div>
                        </div>

                        <div class="bm-foot">
                            <button class="d-none" type="submit" id="confirmBatch">
                                {{ __('Confirm & add to cart') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- End batch modal -->

@endsection

@push('scripts')
    <script src="{{ asset('frontend/js/default/course-details.js') }}"></script>
    <script src="{{ asset('frontend/js/shareon.iife.js') }}"></script>
    <script>
        Shareon.init();
    </script>

    @if ($setting->google_tagmanager_status == 'active' && $marketing_setting?->course_details)
        <script>
            $(document).ready(function() {
                dataLayer.push({
                    'event': 'courseDetails',
                    'courses': {
                        'name': '{{ $course?->title }}',
                        'price': '{{ currency($course->price) }}',
                        'instructor': '{{ ($course->instructor?->name ?? __('Unknown')) }}',
                        'category': '{{ ($course->category?->translation?->name ?? $course->category?->name ?? '') }}',
                        'lessons': '{{ $courseLessonCount }}',
                        'batches': '{{ $courseBatchCount }}',
                        'duration': '{{ minutesToHours($course->duration) }}',
                        'url': "{{ route('course.show', $course->slug) }}",
                    }
                });
            });
        </script>
    @endif
@endpush
