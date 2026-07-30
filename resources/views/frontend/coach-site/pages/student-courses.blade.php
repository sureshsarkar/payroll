{{-- Coach-scoped "My Courses" — only this coach's enrollments. --}}
@extends('frontend.coach-site.layouts.master')

@section('content')
<section class="cs-pad">
    <div class="cs-container" style="max-width:1100px;">
        <h1 class="cs-h2" style="margin-bottom:6px;">{{ __('My courses') }}</h1>
        <p style="color:var(--brand-muted);margin-bottom:28px;">
            {{ __('Courses you purchased from') }} <strong>{{ $brand?->name ?? config('app.name') }}</strong>.
        </p>

        @if($enrolls->isEmpty())
            <div style="text-align:center;padding:60px 20px;background:#F9FAFB;border-radius:12px;">
                <i class="fa-solid fa-graduation-cap" style="font-size:48px;color:#D1D5DB;margin-bottom:18px;display:block;"></i>
                <h3 style="margin:0 0 8px;font-size:18px;color:#111827;">{{ __('No courses yet.') }}</h3>
                <p style="color:#6B7280;margin-bottom:22px;">{{ __('Browse the catalog and add your first course.') }}</p>
                <a href="{{ route('coach.site.path', ['site_slug' => $coachSlug]) }}" class="cs-btn cs-btn--primary">
                    <i class="fa-solid fa-arrow-left"></i> {{ __('Browse courses') }}
                </a>
            </div>
        @else
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:18px;">
                @foreach($enrolls as $enroll)
                    @php $course = $enroll->course; @endphp
                    @if($course)
                        <div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;overflow:hidden;display:flex;flex-direction:column;">
                            @if($course->thumbnail)
                                <img src="{{ str_starts_with($course->thumbnail, 'http') ? $course->thumbnail : asset($course->thumbnail) }}"
                                     style="width:100%;aspect-ratio:16/9;object-fit:cover;display:block;">
                            @endif
                            <div style="padding:16px 18px;flex:1;display:flex;flex-direction:column;">
                                <h3 style="margin:0 0 12px;font-size:15px;color:#111827;flex:1;">{{ $course->title }}</h3>
                                <a href="{{ url('/student/learning/' . $course->slug) }}" class="cs-btn cs-btn--primary cs-btn--sm" style="justify-content:center;">
                                    <i class="fa-solid fa-play"></i> {{ __('Start learning') }}
                                </a>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>

            <div style="margin-top:26px;">
                {{ $enrolls->links() }}
            </div>
        @endif
    </div>
</section>
@endsection
