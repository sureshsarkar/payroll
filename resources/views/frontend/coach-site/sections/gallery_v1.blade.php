{{-- Image Gallery v1 — Grid or Carousel layout, optional lightbox.
     Per-image caption + description. Empty images auto-skip. Styling lives in
     coach-site.css (.cs-gallery*, .cs-lightbox*); behaviour is self-contained
     below (guarded, nonce'd) because sections render standalone so @push to the
     master @stack is flushed before the layout renders. --}}
@php
    $c        = $content;
    $cols     = (int) ($c['columns'] ?? 3); $cols = in_array($cols, [2, 3, 4], true) ? $cols : 3;
    $layout   = in_array(($c['layout'] ?? 'grid'), ['grid', 'carousel'], true) ? $c['layout'] : 'grid';
    $lightbox = ! array_key_exists('lightbox', $c) || ! empty($c['lightbox']);
    $autoplay = ! empty($c['autoplay']);
    $speed    = (int) ($c['autoplay_speed'] ?? 5); $speed = max(2, min(15, $speed));
    $arrows   = ! array_key_exists('arrows', $c) || ! empty($c['arrows']);
    $dots     = ! array_key_exists('pagination', $c) || ! empty($c['pagination']);
    $images   = collect($c['images'] ?? [])->filter(fn ($i) => trim((string) ($i['image'] ?? '')) !== '')->values();
    $gid      = 'csg-' . $sectionId;
@endphp
<section class="cs-gallery cs-gallery--{{ $layout }} cs-pad"
         @if(!empty($appearanceStyle)) style="{{ $appearanceStyle }}" @endif>
    <div class="cs-container">
        @if(!empty($c['title']) || !empty($c['intro']))
            <div class="cs-section-head">
                @if(!empty($c['title']))<h2 class="cs-h2">{{ $c['title'] }}</h2>@endif
                @if(!empty($c['intro']))<p class="cs-lead">{{ $c['intro'] }}</p>@endif
            </div>
        @endif

        @if($images->isNotEmpty())
            <div class="cs-gallery__wrap" id="{{ $gid }}"
                 data-gallery
                 data-layout="{{ $layout }}"
                 data-lightbox="{{ $lightbox ? '1' : '0' }}"
                 @if($layout === 'carousel')
                     data-autoplay="{{ $autoplay ? '1' : '0' }}"
                     data-speed="{{ $speed * 1000 }}"
                     data-arrows="{{ $arrows ? '1' : '0' }}"
                     data-dots="{{ $dots ? '1' : '0' }}"
                 @endif>

                <div class="cs-gallery__track {{ $layout === 'grid' ? 'cs-grid cs-grid--'.$cols : '' }}">
                    @foreach($images as $idx => $img)
                        @php
                            $src  = trim((string) ($img['image'] ?? ''));
                            $cap  = trim((string) ($img['title'] ?? ''));
                            $desc = trim((string) ($img['description'] ?? ''));
                        @endphp
                        <figure class="cs-gallery__item"
                                @if($lightbox)
                                    role="button" tabindex="0"
                                    data-lb-src="{{ $src }}"
                                    data-lb-cap="{{ $cap }}"
                                    data-lb-desc="{{ $desc }}"
                                @endif>
                            <div class="cs-gallery__media">
                                <img src="{{ $src }}" alt="{{ $cap !== '' ? $cap : __('Gallery image') }}" loading="lazy">
                                @if($lightbox)<span class="cs-gallery__zoom" aria-hidden="true"><i class="fa-solid fa-magnifying-glass-plus"></i></span>@endif
                            </div>
                            @if($cap !== '' || $desc !== '')
                                <figcaption class="cs-gallery__caption">
                                    @if($cap !== '')<span class="cs-gallery__title">{{ $cap }}</span>@endif
                                    @if($desc !== '')<span class="cs-gallery__desc">{{ $desc }}</span>@endif
                                </figcaption>
                            @endif
                        </figure>
                    @endforeach
                </div>

                @if($layout === 'carousel' && $arrows)
                    <button type="button" class="cs-gallery__nav cs-gallery__nav--prev" aria-label="{{ __('Previous') }}"><i class="fa-solid fa-chevron-left"></i></button>
                    <button type="button" class="cs-gallery__nav cs-gallery__nav--next" aria-label="{{ __('Next') }}"><i class="fa-solid fa-chevron-right"></i></button>
                @endif
                @if($layout === 'carousel' && $dots)
                    <div class="cs-gallery__dots" aria-hidden="true"></div>
                @endif
            </div>
        @elseif($isOwnerPreview ?? false)
            <div class="cs-empty">{{ __('No images yet — add some in the editor.') }}</div>
        @endif
    </div>
</section>

@if($images->isNotEmpty())
<script nonce="{{ csp_nonce() }}">
(function () {
    if (window.__csGalleryInit) { window.__csGalleryScan && window.__csGalleryScan(); return; }
    window.__csGalleryInit = true;

    /* ---- shared lightbox (built once, reused by every gallery) ---- */
    var lb;
    function buildLightbox() {
        if (lb) return lb;
        lb = document.createElement('div');
        lb.className = 'cs-lightbox';
        lb.innerHTML =
            '<button class="cs-lightbox__close" aria-label="Close">&times;</button>' +
            '<button class="cs-lightbox__nav cs-lightbox__nav--prev" aria-label="Previous"><i class="fa-solid fa-chevron-left"></i></button>' +
            '<figure class="cs-lightbox__fig"><img alt=""><figcaption></figcaption></figure>' +
            '<button class="cs-lightbox__nav cs-lightbox__nav--next" aria-label="Next"><i class="fa-solid fa-chevron-right"></i></button>';
        document.body.appendChild(lb);
        var imgEl = lb.querySelector('img');
        var capEl = lb.querySelector('figcaption');
        var current = [], idx = 0;
        function show(i) {
            if (!current.length) return;
            idx = (i + current.length) % current.length;
            var it = current[idx];
            imgEl.src = it.src;
            var html = '';
            if (it.cap)  html += '<span class="cs-lightbox__title">' + it.cap + '</span>';
            if (it.desc) html += '<span class="cs-lightbox__desc">' + it.desc + '</span>';
            capEl.innerHTML = html;
            capEl.style.display = html ? '' : 'none';
        }
        lb.open = function (items, start) { current = items; lb.classList.add('is-open'); document.body.style.overflow = 'hidden'; show(start); };
        function close() { lb.classList.remove('is-open'); document.body.style.overflow = ''; }
        lb.querySelector('.cs-lightbox__close').addEventListener('click', close);
        lb.querySelector('.cs-lightbox__nav--prev').addEventListener('click', function () { show(idx - 1); });
        lb.querySelector('.cs-lightbox__nav--next').addEventListener('click', function () { show(idx + 1); });
        lb.addEventListener('click', function (e) { if (e.target === lb) close(); });
        document.addEventListener('keydown', function (e) {
            if (!lb.classList.contains('is-open')) return;
            if (e.key === 'Escape') close();
            else if (e.key === 'ArrowLeft') show(idx - 1);
            else if (e.key === 'ArrowRight') show(idx + 1);
        });
        return lb;
    }

    function initGallery(root) {
        if (root.dataset.csInit) return;
        root.dataset.csInit = '1';
        var items = Array.prototype.slice.call(root.querySelectorAll('.cs-gallery__item'));

        /* lightbox wiring */
        if (root.dataset.lightbox === '1') {
            var data = items.map(function (it) {
                return { src: it.getAttribute('data-lb-src'), cap: it.getAttribute('data-lb-cap'), desc: it.getAttribute('data-lb-desc') };
            });
            items.forEach(function (it, i) {
                function open() { buildLightbox().open(data, i); }
                it.addEventListener('click', open);
                it.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(); } });
            });
        }

        /* carousel behaviour (scroll-snap based — responsive + touch-friendly) */
        if (root.dataset.layout === 'carousel') {
            var track = root.querySelector('.cs-gallery__track');
            var step = function () { var f = track.querySelector('.cs-gallery__item'); return f ? f.getBoundingClientRect().width + 20 : track.clientWidth; };
            var prev = root.querySelector('.cs-gallery__nav--prev');
            var next = root.querySelector('.cs-gallery__nav--next');
            if (prev) prev.addEventListener('click', function () { track.scrollBy({ left: -step(), behavior: 'smooth' }); });
            if (next) next.addEventListener('click', function () { track.scrollBy({ left: step(), behavior: 'smooth' }); });

            /* pagination dots */
            var dotsWrap = root.querySelector('.cs-gallery__dots');
            if (dotsWrap && root.dataset.dots === '1') {
                items.forEach(function (it, i) {
                    var d = document.createElement('button');
                    d.type = 'button'; d.className = 'cs-gallery__dot'; d.setAttribute('aria-label', 'Go to image ' + (i + 1));
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

            /* autoplay (pauses on hover / interaction) */
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
                play();
            }
        }
    }

    window.__csGalleryScan = function () {
        document.querySelectorAll('.cs-gallery__wrap[data-gallery]').forEach(initGallery);
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', window.__csGalleryScan);
    else window.__csGalleryScan();
})();
</script>
@endif
