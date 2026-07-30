{{-- Hero section v1 — brand-aware, responsive --}}
@php
    $c = $content;
    $variant = $c['background_variant'] ?? 'gradient';
    $layout  = $c['layout'] ?? 'text-image-right';
    $bgImage = $c['background_image'] ?? null;
    $heroImg = $c['hero_image'] ?? null;
@endphp
@php
    // If coach has set explicit bg colour in appearance, it overrides
    // the uploaded image (most-recent-edit wins UX). Otherwise the image
    // bg applies and we toggle the cs-hero--image-bg class so default
    // text colour goes white.
    $c = $content ?? [];
    $hasAppearanceBg = !empty($c['_bg_color']) || in_array($c['_preset'] ?? 'brand', ['light','soft','dark']);
    $useImageBg = ($variant === 'image' && $bgImage && ! $hasAppearanceBg);

    $imgStyle = $useImageBg
        ? "background-image:linear-gradient(rgba(0,0,0,.45),rgba(0,0,0,.45)),url('{$bgImage}');background-size:cover;background-position:center"
        : '';

    // Order matters in inline CSS — LATER declarations override the
    // earlier shorthand. Put appearance LAST so coach's explicit pick
    // always wins (eg if coach types a text-color, it must show on top
    // of the image-bg dark default).
    $mergedStyle = trim(($imgStyle ? $imgStyle . '; ' : '') . ($appearanceStyle ?? ''));
@endphp
<section class="cs-hero cs-hero--{{ $layout }} {{ $useImageBg ? 'cs-hero--image-bg' : '' }}"
         @if($mergedStyle) style="{{ $mergedStyle }}" @endif>
    <div class="cs-container cs-hero__grid">
        <div class="cs-hero__text">
            <h1 class="cs-hero__headline">{{ $c['headline'] ?? '' }}</h1>
            @if(!empty($c['subhead']))
                <p class="cs-hero__subhead">{{ $c['subhead'] }}</p>
            @endif
            @if(!empty($c['cta_text']) && !empty($c['cta_url']))
                <a href="{{ safe_url($c['cta_url']) }}" class="cs-btn cs-btn--primary cs-hero__cta">{{ $c['cta_text'] }}</a>{{-- F6: scheme-sanitized --}}
            @endif
        </div>
        @if($layout !== 'text-center' && $heroImg)
            <div class="cs-hero__image">
                <img src="{{ $heroImg }}" alt="" loading="eager">
            </div>
        @endif
    </div>
</section>
