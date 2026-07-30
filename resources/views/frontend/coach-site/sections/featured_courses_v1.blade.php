{{-- Featured Courses Carousel v1 (2026-07-08) — the coach hand-picks published
     courses (ordered ids in content_json); course data is pulled LIVE here so
     Course-Module edits reflect automatically. Tenant-safe: only the current
     coach's OWN approved+active courses render, even if ids were tampered with.
     Self-contained styling + behaviour (nonce'd) because sections render
     standalone. --}}
@php
    use Illuminate\Support\Str;

    $c         = $content;
    $ids       = collect($c['course_ids'] ?? [])->map(fn ($v) => (int) $v)->filter()->values();
    $autoplay  = ! array_key_exists('autoplay', $c) || ! empty($c['autoplay']);
    $speed     = (int) ($c['autoplay_speed'] ?? 5); $speed = max(2, min(10, $speed));
    $arrows    = ! array_key_exists('arrows', $c) || ! empty($c['arrows']);
    $dots      = ! array_key_exists('dots', $c) || ! empty($c['dots']);
    $showImage = ! array_key_exists('show_image', $c) || ! empty($c['show_image']);
    $showDesc  = ! array_key_exists('show_desc', $c) || ! empty($c['show_desc']);
    $showMode  = ! array_key_exists('show_mode', $c) || ! empty($c['show_mode']);
    $showBtn   = ! array_key_exists('show_button', $c) || ! empty($c['show_button']);
    $btnText   = trim((string) ($c['btn_text'] ?? '')) ?: __('View Course');
    $slD = (int) ($c['slides_desktop'] ?? 3); $slD = in_array($slD, [2, 3, 4], true) ? $slD : 3;
    $slT = (int) ($c['slides_tablet'] ?? 2);  $slT = in_array($slT, [1, 2, 3], true) ? $slT : 2;
    $slM = (int) ($c['slides_mobile'] ?? 1);  $slM = in_array($slM, [1, 2], true) ? $slM : 1;

    // Tenant-safe live fetch, order preserved to match the coach's arrangement.
    $courses = collect();
    if ($ids->isNotEmpty() && isset($coach) && ! empty($coach->id)) {
        $rows = \App\Models\Course::query()
            ->where('instructor_id', $coach->id)
            ->where('coach_soft_delete', 0)
            ->where('is_approved', 'approved')
            ->where('status', 'active')
            ->whereIn('id', $ids->all())
            ->get(['id', 'title', 'slug', 'thumbnail', 'description', 'type']);
        $byId = $rows->keyBy('id');
        $courses = $ids->map(fn ($id) => $byId->get($id))->filter()->values();
    }

    $modeLabel = fn ($t) => match ((string) $t) {
        'live' => __('Live'), 'hybrid' => __('Hybrid'), 'webinar' => __('Webinar'), default => __('Recorded'),
    };
    $fid = 'csfc-' . $sectionId;
@endphp
<section class="cs-fc cs-pad" @if(!empty($appearanceStyle)) style="{{ $appearanceStyle }}" @endif>
    <div class="cs-container">
        @if(!empty($c['badge']) || !empty($c['title']) || !empty($c['intro']))
            <div class="cs-section-head">
                @if(!empty($c['badge']))<span class="cs-fc__badge">{{ $c['badge'] }}</span>@endif
                @if(!empty($c['title']))<h2 class="cs-h2">{{ $c['title'] }}</h2>@endif
                @if(!empty($c['intro']))<p class="cs-lead">{{ $c['intro'] }}</p>@endif
            </div>
        @endif

        @if($courses->isNotEmpty())
            <div class="cs-fc__wrap" id="{{ $fid }}" data-fc
                 data-autoplay="{{ $autoplay ? '1' : '0' }}"
                 data-speed="{{ $speed * 1000 }}"
                 data-arrows="{{ $arrows ? '1' : '0' }}"
                 data-dots="{{ $dots ? '1' : '0' }}"
                 style="--fc-d:{{ $slD }};--fc-t:{{ $slT }};--fc-m:{{ $slM }};">

                <div class="cs-fc__track">
                    @foreach($courses as $course)
                        @php
                            $thumb = $showImage
                                ? ($course->thumbnail
                                    ? (Str::startsWith($course->thumbnail, ['http://', 'https://']) ? $course->thumbnail : asset($course->thumbnail))
                                    : asset('uploads/website-images/placeholder.jpg'))
                                : null;
                            $short = trim(Str::limit(strip_tags((string) $course->description), 110));
                            $courseUrl = url('/course/' . $course->slug)
                                . '?ref=coach_site&utm_source=coach_site'
                                . '&utm_medium=' . urlencode($page->slug ?? 'home')
                                . '&utm_campaign=featured-' . ($sectionId ?? '0');
                        @endphp
                        <article class="cs-fc__card">
                            @if($showImage)
                                <a href="{{ $courseUrl }}" class="cs-fc__media" tabindex="-1" aria-hidden="true">
                                    <img src="{{ $thumb }}" alt="{{ $course->title }}" loading="lazy">
                                    @if($showMode)<span class="cs-fc__mode">{{ $modeLabel($course->type) }}</span>@endif
                                </a>
                            @endif
                            <div class="cs-fc__body">
                                @if(!$showImage && $showMode)<span class="cs-fc__mode cs-fc__mode--inline">{{ $modeLabel($course->type) }}</span>@endif
                                <h3 class="cs-fc__title"><a href="{{ $courseUrl }}">{{ $course->title }}</a></h3>
                                @if($showDesc && $short !== '')<p class="cs-fc__desc">{{ $short }}</p>@endif
                                @if($showBtn)
                                    <div class="cs-fc__foot">
                                        <a href="{{ $courseUrl }}" class="cs-btn cs-btn--primary cs-btn--sm">{{ $btnText }}</a>
                                    </div>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>

                @if($arrows)
                    <button type="button" class="cs-fc__nav cs-fc__nav--prev" aria-label="{{ __('Previous') }}"><i class="fa-solid fa-chevron-left"></i></button>
                    <button type="button" class="cs-fc__nav cs-fc__nav--next" aria-label="{{ __('Next') }}"><i class="fa-solid fa-chevron-right"></i></button>
                @endif
                @if($dots)<div class="cs-fc__dots" aria-hidden="true"></div>@endif
            </div>
        @elseif($isOwnerPreview ?? false)
            <div class="cs-empty">{{ __('No courses selected yet — pick some in the editor (Select courses).') }}</div>
        @endif
    </div>
</section>

@if($courses->isNotEmpty())
<style nonce="{{ csp_nonce() }}">
.cs-fc__badge{display:inline-block;font-size:12px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--cs-primary,#4f46e5);margin-bottom:8px;}
.cs-fc__wrap{position:relative;}
.cs-fc__track{display:flex;gap:20px;overflow-x:auto;scroll-snap-type:x mandatory;scroll-behavior:smooth;-webkit-overflow-scrolling:touch;scrollbar-width:none;padding:4px;}
.cs-fc__track::-webkit-scrollbar{display:none;}
.cs-fc__card{flex:0 0 calc((100% - (var(--fc-d) - 1) * 20px) / var(--fc-d));scroll-snap-align:start;background:#fff;border:1px solid rgba(15,23,42,.08);border-radius:14px;overflow:hidden;display:flex;flex-direction:column;transition:box-shadow .25s ease,transform .25s ease;}
.cs-fc__card:hover{box-shadow:0 16px 40px rgba(15,23,42,.12);transform:translateY(-4px);}
.cs-fc__media{position:relative;display:block;aspect-ratio:16/9;background:#f1f5f9;overflow:hidden;}
.cs-fc__media img{width:100%;height:100%;object-fit:cover;display:block;}
.cs-fc__mode{position:absolute;top:10px;left:10px;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;background:rgba(15,23,42,.82);color:#fff;padding:4px 9px;border-radius:999px;}
.cs-fc__mode--inline{position:static;display:inline-block;background:#eef2ff;color:var(--cs-primary,#4f46e5);margin-bottom:6px;}
.cs-fc__body{padding:16px;display:flex;flex-direction:column;gap:9px;flex:1;}
.cs-fc__title{margin:0;font-size:16.5px;font-weight:700;line-height:1.35;}
.cs-fc__title a{color:inherit;text-decoration:none;}
.cs-fc__title a:hover{color:var(--cs-primary,#4f46e5);}
.cs-fc__desc{margin:0;font-size:13.5px;line-height:1.55;color:var(--cs-muted,#64748b);}
.cs-fc__foot{margin-top:auto;padding-top:6px;}
.cs-fc__nav{position:absolute;top:38%;transform:translateY(-50%);width:42px;height:42px;border-radius:50%;background:#fff;border:1px solid rgba(15,23,42,.1);box-shadow:0 6px 18px rgba(15,23,42,.14);cursor:pointer;display:flex;align-items:center;justify-content:center;z-index:5;color:#0f172a;transition:background .2s,transform .2s;}
.cs-fc__nav:hover{background:#f8fafc;}
.cs-fc__nav--prev{left:-14px;} .cs-fc__nav--next{right:-14px;}
.cs-fc__dots{display:flex;gap:7px;justify-content:center;margin-top:16px;}
.cs-fc__dots button{width:8px;height:8px;border-radius:50%;background:#cbd5e1;border:0;cursor:pointer;padding:0;transition:background .2s,width .2s;}
.cs-fc__dots button.is-active{background:var(--cs-primary,#4f46e5);width:20px;border-radius:999px;}
@media (max-width:1024px){
    .cs-fc__card{flex:0 0 calc((100% - (var(--fc-t) - 1) * 20px) / var(--fc-t));}
    .cs-fc__nav{display:none;}
}
@media (max-width:640px){
    .cs-fc__card{flex:0 0 calc((100% - (var(--fc-m) - 1) * 20px) / var(--fc-m));}
}
</style>
<script nonce="{{ csp_nonce() }}">
(function () {
    if (window.__csFcInit) { window.__csFcScan && window.__csFcScan(); return; }
    window.__csFcInit = true;

    function initFc(root) {
        if (root.dataset.csInit) return;
        root.dataset.csInit = '1';
        var track = root.querySelector('.cs-fc__track');
        var cards = Array.prototype.slice.call(track.querySelectorAll('.cs-fc__card'));
        if (!cards.length) return;
        var step = function () { var f = cards[0]; return f ? f.getBoundingClientRect().width + 20 : track.clientWidth; };

        var prev = root.querySelector('.cs-fc__nav--prev');
        var next = root.querySelector('.cs-fc__nav--next');
        if (prev) prev.addEventListener('click', function () { track.scrollBy({ left: -step(), behavior: 'smooth' }); });
        if (next) next.addEventListener('click', function () { track.scrollBy({ left: step(), behavior: 'smooth' }); });

        var dotsWrap = root.querySelector('.cs-fc__dots');
        if (dotsWrap && root.dataset.dots === '1') {
            cards.forEach(function (it, i) {
                var d = document.createElement('button');
                d.type = 'button'; d.setAttribute('aria-label', 'Go to slide ' + (i + 1));
                d.addEventListener('click', function () { track.scrollTo({ left: i * step(), behavior: 'smooth' }); });
                dotsWrap.appendChild(d);
            });
            var dots = Array.prototype.slice.call(dotsWrap.children);
            var syncDots = function () {
                var active = Math.round(track.scrollLeft / step());
                dots.forEach(function (d, i) { d.classList.toggle('is-active', i === active); });
            };
            track.addEventListener('scroll', function () { window.requestAnimationFrame(syncDots); });
            syncDots();
        }

        if (root.dataset.autoplay === '1') {
            var delay = parseInt(root.dataset.speed, 10) || 5000;
            var timer = null;
            var tick = function () {
                var atEnd = track.scrollLeft + track.clientWidth >= track.scrollWidth - 4;
                if (atEnd) track.scrollTo({ left: 0, behavior: 'smooth' });
                else track.scrollBy({ left: step(), behavior: 'smooth' });
            };
            var play = function () { if (!timer) timer = setInterval(tick, delay); };
            var stop = function () { if (timer) { clearInterval(timer); timer = null; } };
            root.addEventListener('mouseenter', stop);
            root.addEventListener('mouseleave', play);
            root.addEventListener('touchstart', stop, { passive: true });
            root.addEventListener('focusin', stop);
            play();
        }
    }

    window.__csFcScan = function () { document.querySelectorAll('.cs-fc__wrap[data-fc]').forEach(initFc); };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', window.__csFcScan);
    else window.__csFcScan();
})();
</script>
@endif
