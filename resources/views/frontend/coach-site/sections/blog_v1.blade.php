{{-- Blog v1 — latest N published platform blogs as clickable cards.
     Clicking a card opens the blog detail (/blog/{slug}) in a NEW tab.
     $blogPosts is resolved + passed by SectionRenderer::renderOne. --}}
@php
    $c = $content;
    $cols  = (int) ($c['columns'] ?? 3); $cols = in_array($cols, [2, 3, 4], true) ? $cols : 3;
    $posts = $blogPosts ?? collect();
@endphp
<section class="cs-blog cs-pad" @if(!empty($appearanceStyle)) style="{{ $appearanceStyle }}" @endif>
    <div class="cs-container">
        @if(!empty($c['title']) || !empty($c['intro']))
            <div class="cs-section-head">
                @if(!empty($c['title']))<h2 class="cs-h2">{{ $c['title'] }}</h2>@endif
                @if(!empty($c['intro']))<p class="cs-lead">{{ $c['intro'] }}</p>@endif
            </div>
        @endif

        @if($posts->isNotEmpty())
            <div class="cs-grid cs-grid--{{ $cols }}">
                @foreach($posts as $post)
                    @php
                        $bt   = trim((string) ($post->title ?? ''));
                        $img  = $post->image ? asset($post->image) : asset('frontend/img/blogs.jpg');
                        // Coach blogs expose short_description; platform blogs expose description.
                        $exc  = trim(\Illuminate\Support\Str::limit(strip_tags((string) ($post->short_description ?? $post->description ?? '')), 120));
                    @endphp
                    <a class="cs-card cs-card--link" href="{{ url('/blog/' . $post->slug) }}" rel="noopener">
                        <div class="cs-card__media"><img src="{{ $img }}" alt="{{ $bt }}" loading="lazy"></div>
                        <div class="cs-card__body">
                            @if($bt !== '')<h3 class="cs-card__title">{{ $bt }}</h3>@endif
                            @if($exc !== '')<p class="cs-card__desc">{{ $exc }}</p>@endif
                            <div class="cs-card__foot">
                                @if(!empty($post->created_at))
                                    <span class="cs-eyebrow">{{ $post->created_at->format('d M Y') }}</span>
                                @else<span></span>@endif
                                <span class="cs-readmore">{{ __('Read article') }} <span class="cs-readmore__arr">&rarr;</span></span>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        @elseif($isOwnerPreview ?? false)
            <div class="cs-empty">{{ __('No published blog posts to show yet.') }}</div>
        @endif
    </div>
</section>
