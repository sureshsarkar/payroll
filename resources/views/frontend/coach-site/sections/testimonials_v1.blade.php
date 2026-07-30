{{-- Testimonials v1 --}}
@php $c = $content; $quotes = $c['quotes'] ?? []; @endphp
<section class="cs-testimonials cs-pad" @if(!empty($appearanceStyle)) style="{{ $appearanceStyle }}" @endif>
    <div class="cs-container">
        <div class="cs-section-head">
            <h2 class="cs-h2">{{ $c['title'] ?? 'What clients say' }}</h2>
        </div>
        @if(empty($quotes))
            <div class="cs-empty">{{ __('No testimonials yet.') }}</div>
        @else
            <div class="cs-grid cs-grid--3 cs-testimonials__grid">
                @foreach($quotes as $q)
                    <article class="cs-quote">
                        <i class="fa-solid fa-quote-right cs-quote__icon"></i>
                        @if(!empty($q['rating']))
                            <div class="cs-stars">
                                @for($i = 0; $i < (int) $q['rating']; $i++)
                                    <i class="fa-solid fa-star"></i>
                                @endfor
                            </div>
                        @endif
                        <p class="cs-quote__text">{{ $q['text'] ?? '' }}</p>
                        <div class="cs-quote__author">
                            @if(!empty($q['photo']))
                                <img src="{{ $q['photo'] }}" alt="{{ $q['author'] ?? '' }}" loading="lazy">
                            @endif
                            <div>
                                <strong>{{ $q['author'] ?? '' }}</strong>
                                @if(!empty($q['role']))
                                    <span>{{ $q['role'] }}</span>
                                @endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</section>
