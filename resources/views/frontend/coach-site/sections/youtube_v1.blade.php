{{-- YouTube v1 — cached 24h via SectionRenderer::resolveYouTube().
     Videos play IN PLACE inside a sandboxed youtube-nocookie iframe so a
     click never navigates the student off the coach's branded site. --}}
@php
    $c = $content;
    $count = (int) ($c['video_count'] ?? 6);
    $videos = $youtubeVideos ?? [];
@endphp
<section class="cs-youtube cs-pad" @if(!empty($appearanceStyle)) style="{{ $appearanceStyle }}" @endif>
    <div class="cs-container">
        <div class="cs-section-head">
            <h2 class="cs-h2">{{ $c['title'] ?? 'Latest videos' }}</h2>
        </div>
        @if(empty($videos))
            <div class="cs-empty">{{ __('Videos coming soon.') }}</div>
            @if(($isOwnerPreview ?? false) && !empty($youtubeDiagnostic ?? null))
                <div class="cs-empty cs-empty--owner-hint" style="margin-top:10px;padding:10px 14px;border-left:3px solid #f0ad4e;background:#fff8e6;color:#664400;font-size:13px;text-align:left;max-width:760px;margin-left:auto;margin-right:auto;">
                    <strong>{{ __('Why is this empty?') }}</strong>
                    {{ $youtubeDiagnostic }}
                </div>
            @endif
        @else
            <div class="cs-grid cs-grid--3 cs-youtube__grid">
                @foreach(array_slice($videos, 0, $count) as $v)
                    <article class="cs-video">
                        <div class="cs-video__thumb" data-cs-yt-tile data-yt-id="{{ $v['id'] }}" role="button" tabindex="0" aria-label="{{ __('Play video') }}">
                            <img src="{{ $v['thumbnail'] }}" alt="{{ $v['title'] }}" loading="lazy">
                            <span class="cs-video__play"><i class="fa-solid fa-play"></i></span>
                        </div>
                        <h3 class="cs-video__title">{{ $v['title'] }}</h3>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</section>
{{-- Inline <script>, NOT @push('scripts'). SectionRenderer renders each
     section in isolation; pushed scripts get flushed before the master
     layout's @stack('scripts') runs. Inline guarantees execution. --}}
{{-- nonce REQUIRED: a per-request 'nonce-…' in the coach-site CSP script-src
     makes browsers ignore 'unsafe-inline', so under CSP_ENFORCE an un-nonced
     inline script is blocked (preview playback would silently die). --}}
<script nonce="{{ csp_nonce() }}">
/* youtube_v1 — in-place sandboxed playback. Clicking a tile swaps the
   thumbnail for a youtube-nocookie iframe. */
(function() {
    if (window.__csYtV1Bound) return; window.__csYtV1Bound = true;
    function embed(tile) {
        const id = tile.dataset.ytId;
        if (!id) return;
        // Loading state so the user knows the click registered.
        tile.innerHTML = '<div class="cs-video__loading">' +
                         '<i class="fa-solid fa-circle-notch fa-spin"></i></div>';
        // No sandbox attribute — strict sandbox was blocking playback in
        // testing. The nocookie domain + referrerpolicy handle the privacy
        // requirements; rel=0 + modestbranding suppress related-video
        // overlays so a click cannot pivot the student off-brand.
        const params = 'autoplay=1&rel=0&modestbranding=1&playsinline=1&iv_load_policy=3&disablekb=1&origin=' + encodeURIComponent(window.location.origin);
        const iframe = document.createElement('iframe');
        iframe.className = 'cs-video__iframe';
        iframe.src = 'https://www.youtube-nocookie.com/embed/' + id + '?' + params;
        iframe.setAttribute('frameborder', '0');
        iframe.setAttribute('allow', 'autoplay; encrypted-media; picture-in-picture');
        iframe.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
        iframe.setAttribute('allowfullscreen', 'false');
        iframe.addEventListener('load', () => {
            const ld = tile.querySelector('.cs-video__loading');
            if (ld) ld.remove();
        });
        tile.appendChild(iframe);
    }
    document.addEventListener('click', function(e) {
        const tile = e.target.closest('[data-cs-yt-tile]');
        if (tile) { e.preventDefault(); embed(tile); }
    });
    document.addEventListener('keydown', function(e) {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        const tile = e.target.closest('[data-cs-yt-tile]');
        if (tile) { e.preventDefault(); embed(tile); }
    });
})();
</script>
