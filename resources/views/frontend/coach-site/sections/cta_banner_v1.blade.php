{{-- CTA Banner v1 --}}
@php $c = $content; @endphp
<section class="cs-cta cs-cta--{{ $c['variant'] ?? 'brand' }}" @if(!empty($appearanceStyle)) style="{{ $appearanceStyle }}" @endif>
    <div class="cs-container cs-cta__inner">
        <div>
            <h2 class="cs-cta__h">{{ $c['headline'] ?? '' }}</h2>
            @if(!empty($c['subhead']))
                <p class="cs-cta__sub">{{ $c['subhead'] }}</p>
            @endif
        </div>
        @if(!empty($c['cta_text']) && !empty($c['cta_url']))
            <a href="{{ safe_url($c['cta_url']) }}" class="cs-btn cs-btn--invert cs-btn--lg">{{ $c['cta_text'] }}</a>{{-- F6: scheme-sanitized --}}
        @endif
    </div>
</section>
