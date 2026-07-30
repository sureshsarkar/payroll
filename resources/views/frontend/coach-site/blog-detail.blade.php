{{-- Public coach blog detail — modern, premium reading layout.
     $blog (CoachBlog), $coach (User), $brand, $related, $recent, $readMinutes.
     Theme-consistent (cs-* classes); styling in coach-site.css (.cs-bd*). --}}
@php
    $authorName = $coach->name ?? ($brand->name ?? '');
    $initials   = collect(explode(' ', trim($authorName)))->filter()->map(fn($w)=>mb_substr($w,0,1))->take(2)->implode('');
    $dateObj    = $blog->published_at ?? $blog->created_at;
    $dateStr    = $dateObj?->format('d M Y');
    $dateIso    = $dateObj?->toIso8601String();
    $img        = $blog->imageUrl();
    $homeUrl    = url('/');
    $postUrl    = url('/blog/' . $blog->slug);
    $shareTitle = rawurlencode($blog->title);
    $shareUrl   = rawurlencode($postUrl);
@endphp
<article class="cs-bd">

    {{-- Breadcrumb --}}
    <div class="cs-container cs-container--narrow">
        <nav class="cs-bd__crumbs" aria-label="Breadcrumb">
            <a href="{{ $homeUrl }}">{{ __('Home') }}</a>
            <span class="cs-bd__crumb-sep">/</span>
            <span class="cs-bd__crumb-label">{{ __('Blog') }}</span>
            <span class="cs-bd__crumb-sep">/</span>
            <span class="cs-bd__crumb-current">{{ \Illuminate\Support\Str::limit($blog->title, 48) }}</span>
        </nav>
    </div>

    {{-- Hero --}}
    <header class="cs-bd__hero">
        <div class="cs-container cs-container--narrow">
            <h1 class="cs-bd__title">{{ $blog->title }}</h1>
            <div class="cs-bd__meta">
                <span class="cs-bd__author">
                    <span class="cs-bd__avatar">{{ $initials ?: 'A' }}</span>
                    <span class="cs-bd__author-name">{{ $authorName }}</span>
                </span>
                @if($dateStr)<span class="cs-bd__meta-item"><i class="fa-regular fa-calendar" aria-hidden="true"></i> <time datetime="{{ $dateIso }}">{{ $dateStr }}</time></span>@endif
                <span class="cs-bd__meta-item"><i class="fa-regular fa-clock" aria-hidden="true"></i> {{ $readMinutes }} {{ __('min read') }}</span>
            </div>
        </div>
        @if($img)
            <div class="cs-container cs-container--narrow">
                <div class="cs-bd__cover"><img src="{{ $img }}" alt="{{ $blog->title }}" loading="eager"></div>
            </div>
        @endif
    </header>

    {{-- Body + sidebar --}}
    <div class="cs-container">
        <div class="cs-bd__layout">

            {{-- Sticky social share (vertical on desktop) --}}
            <aside class="cs-bd__share" aria-label="{{ __('Share this article') }}">
                <span class="cs-bd__share-label">{{ __('Share') }}</span>
                <a class="cs-bd__share-btn" target="_blank" rel="noopener" aria-label="Facebook"
                   href="https://www.facebook.com/sharer/sharer.php?u={{ $shareUrl }}"><i class="fa-brands fa-facebook-f"></i></a>
                <a class="cs-bd__share-btn" target="_blank" rel="noopener" aria-label="X / Twitter"
                   href="https://twitter.com/intent/tweet?url={{ $shareUrl }}&text={{ $shareTitle }}"><i class="fa-brands fa-x-twitter"></i></a>
                <a class="cs-bd__share-btn" target="_blank" rel="noopener" aria-label="LinkedIn"
                   href="https://www.linkedin.com/sharing/share-offsite/?url={{ $shareUrl }}"><i class="fa-brands fa-linkedin-in"></i></a>
                <a class="cs-bd__share-btn" target="_blank" rel="noopener" aria-label="WhatsApp"
                   href="https://wa.me/?text={{ $shareTitle }}%20{{ $shareUrl }}"><i class="fa-brands fa-whatsapp"></i></a>
                <button type="button" class="cs-bd__share-btn cs-bd__copy" data-copy="{{ $postUrl }}" aria-label="{{ __('Copy link') }}"><i class="fa-solid fa-link"></i></button>
            </aside>

            {{-- Main content --}}
            <div class="cs-bd__main">
                @if(!empty($blog->short_description))
                    <p class="cs-bd__lead">{{ $blog->short_description }}</p>
                @endif
                <div class="cs-bd__content cs-prose">
                    {!! $blog->content !!}
                </div>
            </div>

            {{-- Sidebar --}}
            <aside class="cs-bd__sidebar">
                <div class="cs-bd__widget cs-bd__author-card">
                    <span class="cs-bd__avatar cs-bd__avatar--lg">{{ $initials ?: 'A' }}</span>
                    <div>
                        <div class="cs-bd__widget-title">{{ $authorName }}</div>
                        <div class="cs-bd__author-role">{{ __('Author') }}</div>
                    </div>
                </div>

                @if($recent->isNotEmpty())
                    <div class="cs-bd__widget">
                        <h3 class="cs-bd__widget-h">{{ __('Recent posts') }}</h3>
                        <ul class="cs-bd__recent">
                            @foreach($recent as $r)
                                <li>
                                    <a href="{{ url('/blog/' . $r->slug) }}">
                                        <img src="{{ $r->imageUrl() }}" alt="" loading="lazy">
                                        <span>
                                            <span class="cs-bd__recent-title">{{ \Illuminate\Support\Str::limit($r->title, 52) }}</span>
                                            <span class="cs-bd__recent-date">{{ ($r->published_at ?? $r->created_at)?->format('d M Y') }}</span>
                                        </span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </aside>
        </div>
    </div>

    {{-- Related posts --}}
    @if($related->isNotEmpty())
        <div class="cs-bd__related cs-pad">
            <div class="cs-container">
                <div class="cs-section-head"><h2 class="cs-h2">{{ __('Related articles') }}</h2></div>
                <div class="cs-grid cs-grid--3">
                    @foreach($related as $rp)
                        <a class="cs-card cs-card--link" href="{{ url('/blog/' . $rp->slug) }}" rel="noopener">
                            <div class="cs-card__media"><img src="{{ $rp->imageUrl() }}" alt="{{ $rp->title }}" loading="lazy"></div>
                            <div class="cs-card__body">
                                <h3 class="cs-card__title">{{ $rp->title }}</h3>
                                @if($rp->short_description)<p class="cs-card__desc">{{ \Illuminate\Support\Str::limit(strip_tags($rp->short_description), 100) }}</p>@endif
                                <div class="cs-card__foot">
                                    <span class="cs-eyebrow">{{ ($rp->published_at ?? $rp->created_at)?->format('d M Y') }}</span>
                                    <span class="cs-readmore">{{ __('Read') }} <span class="cs-readmore__arr">&rarr;</span></span>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</article>

{{-- SEO: BlogPosting schema (JSON-LD, schema-ready markup) --}}
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type'    => 'BlogPosting',
    'headline' => $blog->title,
    'image'    => $img,
    'datePublished' => $dateIso,
    'dateModified'  => $blog->updated_at?->toIso8601String(),
    'author'   => ['@type' => 'Person', 'name' => $authorName],
    'publisher'=> ['@type' => 'Organization', 'name' => ($brand->name ?? $authorName)],
    'description' => \Illuminate\Support\Str::limit(strip_tags((string) $blog->short_description), 200),
    'mainEntityOfPage' => $postUrl,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>

<script nonce="{{ csp_nonce() }}">
(function(){
    document.querySelectorAll('.cs-bd__copy').forEach(function(b){
        b.addEventListener('click', function(){
            var url = b.getAttribute('data-copy');
            var done = function(){ b.classList.add('is-copied'); setTimeout(function(){ b.classList.remove('is-copied'); }, 1600); };
            if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(url).then(done).catch(done); }
            else { var t=document.createElement('textarea'); t.value=url; document.body.appendChild(t); t.select(); try{document.execCommand('copy');}catch(e){} t.remove(); done(); }
        });
    });
})();
</script>
