{{-- Services Grid v1 — auto-pulls coach's published courses with attribution-tagged buy CTA --}}
@php
    $c = $content;
    $cols = (int) ($c['columns'] ?? 3);
    $source = $c['source'] ?? 'auto-from-courses';
    $cards = collect();

    if ($source === 'auto-from-courses' && !empty($coach)) {
        // 2026-06-03 (#6) — only list SELLABLE courses. This section's buy CTA
        // links straight to /course/{slug}, and course.show resolves via
        // Course::active() (is_approved=approved + status=active) → firstOrFail.
        // Without these filters a draft/pending/unpublished course showed a
        // "Buy now" button that 404'd on click. Mirror recorded_courses_v1 /
        // Course::scopeActive() (the docblock above always intended "published").
        $cards = \App\Models\Course::query()
            ->where('instructor_id', $coach->id)
            ->where('coach_soft_delete', 0)
            ->where('is_approved', 'approved')
            ->where('status', 'active')
            ->orderByDesc('id')
            ->limit(12)
            ->get(['id', 'title', 'slug', 'thumbnail', 'price', 'discount', 'description']);
    } else {
        $cards = collect($c['manual_cards'] ?? []);
    }
@endphp
<section class="cs-services cs-pad" id="services" @if(!empty($appearanceStyle)) style="{{ $appearanceStyle }}" @endif>
    <div class="cs-container">
        <div class="cs-section-head">
            <h2 class="cs-h2">{{ $c['title'] ?? 'Services' }}</h2>
            @if(!empty($c['intro']))
                <p class="cs-lead">{{ $c['intro'] }}</p>
            @endif
        </div>

        @if($cards->isEmpty())
            <div class="cs-empty">{{ __('No services configured yet.') }}</div>
        @else
            <div class="cs-grid cs-grid--{{ $cols }}">
                @foreach($cards as $card)
                    @if($source === 'auto-from-courses')
                        @php
                            $price = ($card->discount ?? 0) > 0 ? $card->discount : $card->price;
                            $buyUrl = url('/course/' . $card->slug)
                                . '?ref=coach_site'
                                . '&utm_source=coach_site'
                                . '&utm_medium=' . urlencode($page->slug ?? 'home')
                                . '&utm_campaign=' . ($sectionId ?? '0');
                            $thumb = $card->thumbnail
                                ? (str_starts_with($card->thumbnail, 'http') ? $card->thumbnail : asset($card->thumbnail))
                                : asset('uploads/website-images/placeholder.jpg');
                        @endphp
                        <article class="cs-card cs-card--course">
                            <a href="{{ $buyUrl }}" class="cs-card__media">
                                <img src="{{ $thumb }}" alt="{{ $card->title }}" loading="lazy">
                            </a>
                            <div class="cs-card__body">
                                <h3 class="cs-card__title">{{ $card->title }}</h3>
                                @if(!empty($card->description))
                                    <p class="cs-card__desc">{{ \Illuminate\Support\Str::limit(strip_tags($card->description), 110) }}</p>
                                @endif
                                <div class="cs-card__foot">
                                    <span class="cs-price">{{ $price > 0 ? '₹' . number_format($price, 0) : __('Free') }}</span>
                                    <a href="{{ $buyUrl }}" class="cs-btn cs-btn--primary cs-btn--sm">{{ __('Buy now') }}</a>
                                </div>
                            </div>
                        </article>
                    @else
                        {{-- manual card --}}
                        <article class="cs-card">
                            @if(!empty($card['image']))
                                <div class="cs-card__media"><img src="{{ $card['image'] }}" alt="" loading="lazy"></div>
                            @endif
                            <div class="cs-card__body">
                                <h3 class="cs-card__title">{{ $card['title'] ?? '' }}</h3>
                                @if(!empty($card['description']))
                                    <p class="cs-card__desc">{{ $card['description'] }}</p>
                                @endif
                                @if(!empty($card['cta_url']))
                                    <a href="{{ safe_url($card['cta_url']) }}" class="cs-btn cs-btn--primary cs-btn--sm">{{ $card['cta_text'] ?? __('Learn more') }}</a>{{-- F6 --}}
                                @endif
                            </div>
                        </article>
                    @endif
                @endforeach
            </div>
        @endif
    </div>
</section>
