{{-- Video Gallery v1 — doubles as the "Live Class Sessions" course showcase.

     2026-06-15 (white-label, global for ALL coaches): the empty "Recorded
     sessions" video gallery now automatically lists the coach's LIVE-CLASS
     courses (live + hybrid). Requirements from "Error of 15 Jun":
       • title "Recorded Sessions" → "Live Class Sessions"
       • show courses that have Live Classes (live/hybrid)
       • the On-demand (recorded_courses_v1) section keeps showing recorded.
     If the coach has manually uploaded videos, those still render (their
     intent is preserved). No per-coach reconfiguration needed. --}}
@php
    $c = $content;
    $videos = $c['videos'] ?? [];

    // Auto-rename the legacy default; respect a custom title the coach typed.
    $title = $c['title'] ?? 'Live Class Sessions';
    if ($title === 'Recorded sessions' || $title === '') {
        $title = 'Live Class Sessions';
    }

    // Live-class courses for this coach — only when no manual videos exist.
    $liveCourses = collect();
    if (empty($videos) && ! empty($coach)) {
        $liveCourses = \App\Models\Course::query()
            ->where('instructor_id', $coach->id)
            ->where('coach_soft_delete', 0)
            ->where('is_approved', 'approved')
            ->where('status', 'active')
            ->whereIn('type', ['live', 'hybrid'])   // courses that run Live Classes
            ->orderByDesc('id')
            ->limit(6)
            ->get();
    }
@endphp
<section class="cs-vidgallery cs-pad" @if(!empty($appearanceStyle)) style="{{ $appearanceStyle }}" @endif>
    <div class="cs-container">
        <div class="cs-section-head">
            <h2 class="cs-h2">{{ $title }}</h2>
            @if(!empty($c['intro']))
                <p class="cs-lead">{{ $c['intro'] }}</p>
            @endif
        </div>

        @if(!empty($videos))
            {{-- Coach uploaded their own recordings — keep showing them. --}}
            <div class="cs-grid cs-grid--3 cs-vidgallery__grid">
                @foreach($videos as $v)
                    <article class="cs-video">
                        <div class="cs-video__thumb cs-video__player">
                            @if(!empty($v['video_url']))
                                @if(str_contains($v['video_url'], 'youtube.com') || str_contains($v['video_url'], 'youtu.be'))
                                    @php
                                        preg_match('/(?:v=|youtu\.be\/|embed\/)([A-Za-z0-9_\-]{11})/', $v['video_url'], $m);
                                        $ytId = $m[1] ?? null;
                                    @endphp
                                    @if($ytId)
                                        <iframe src="https://www.youtube-nocookie.com/embed/{{ $ytId }}" loading="lazy" allowfullscreen frameborder="0"></iframe>
                                    @endif
                                @else
                                    <video src="{{ $v['video_url'] }}" controls preload="metadata" @if(!empty($v['thumbnail'])) poster="{{ $v['thumbnail'] }}" @endif></video>
                                @endif
                            @endif
                        </div>
                        <h3 class="cs-video__title">{{ $v['title'] ?? '' }}</h3>
                        @if(!empty($v['description']))
                            <p class="cs-video__desc">{{ $v['description'] }}</p>
                        @endif
                    </article>
                @endforeach
            </div>
        @elseif($liveCourses->isNotEmpty())
            {{-- LIVE-CLASS COURSES. They require a batch, so each card links to the
                 course page (batch picker) — never a direct add-to-cart. --}}
            <div class="cs-grid cs-grid--3 cs-livecourses__grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:22px;">
                @foreach($liveCourses as $course)
                    @php
                        $price = ($course->discount ?? 0) > 0 ? $course->discount : $course->price;
                        $thumb = $course->thumbnail
                            ? (str_starts_with($course->thumbnail, 'http') ? $course->thumbnail : asset($course->thumbnail))
                            : asset('uploads/website-images/placeholder.jpg');
                        $courseUrl = url('/course/' . $course->slug) . '?ref=coach_site';
                    @endphp
                    <article class="cs-livecard" style="background:#fff;border:1px solid #e8eaf0;border-radius:16px;overflow:hidden;box-shadow:0 2px 10px rgba(15,23,42,.05);display:flex;flex-direction:column;">
                        <div style="position:relative;aspect-ratio:16/9;background:#f1f5f9;overflow:hidden;">
                            <img src="{{ $thumb }}" alt="{{ $course->title }}" loading="lazy" style="width:100%;height:100%;object-fit:cover;">
                            <span style="position:absolute;top:10px;left:10px;background:rgba(99,102,241,.95);color:#fff;font-size:11px;font-weight:700;padding:4px 10px;border-radius:999px;">
                                <i class="fa-solid fa-calendar-check"></i> {{ __('Live Class') }}
                            </span>
                        </div>
                        <div style="padding:16px 18px;display:flex;flex-direction:column;gap:10px;flex:1;">
                            <h3 style="margin:0;font-size:16px;font-weight:700;color:#0f172a;line-height:1.35;">{{ $course->title }}</h3>
                            <div style="margin-top:auto;display:flex;align-items:center;justify-content:space-between;gap:10px;">
                                <span style="font-size:18px;font-weight:800;color:#0f172a;">{{ $price > 0 ? '₹' . number_format($price, 0) : __('Free') }}</span>
                                <a href="{{ $courseUrl }}" class="cs-btn cs-btn--primary cs-btn--sm">
                                    <i class="fa-solid fa-calendar-check"></i>
                                    <span class="cs-btn__label">{{ __('View & enroll') }}</span>
                                </a>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @else
            <div class="cs-empty">{{ __('No live classes scheduled yet.') }}</div>
        @endif
    </div>
</section>
