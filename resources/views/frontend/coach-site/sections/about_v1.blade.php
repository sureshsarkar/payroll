{{-- About / Bio v1 --}}
@php
    $c = $content;
    // 2026-07-16 — optional image-right layout. Default (image_left) unchanged.
    $imgRight = ($c['layout_position'] ?? 'image_left') === 'image_right';
@endphp
@once
    {{-- CSS-only flip (defined once, applies only to sections that opt in via the
         modifier class): reverse the 2-column row on desktop; children keep LTR so
         text/alignment are unaffected. On mobile the grid stacks (image on top)
         exactly as the default layout does — fully responsive, brand-safe. --}}
    <style nonce="{{ csp_nonce() }}">
        .cs-about--img-right .cs-about__grid{direction:rtl;}
        .cs-about--img-right .cs-about__grid > *{direction:ltr;}
    </style>
@endonce
<section class="cs-about cs-pad {{ $imgRight ? 'cs-about--img-right' : '' }}" @if(!empty($appearanceStyle)) style="{{ $appearanceStyle }}" @endif>
    <div class="cs-container cs-about__grid">
        @if(!empty($c['image']))
            <div class="cs-about__image">
                <img src="{{ $c['image'] }}" alt="{{ $c['name'] ?? '' }}" loading="lazy">
            </div>
        @endif
        <div class="cs-about__text">
            @if(!empty($c['name']))
                <h2 class="cs-h2">{{ $c['name'] }}</h2>
            @endif
            @if(!empty($c['role']))
                <p class="cs-eyebrow">{{ $c['role'] }}</p>
            @endif
            @if(!empty($c['bio']))
                {{-- F2 (audit 2026-06-26) — Str::markdown() defaults to html_input=allow,
                     so raw <script>/onerror in the bio would execute. Sanitize the
                     rendered HTML with clean() (HTMLPurifier), same as rich_text_v1. --}}
                <div class="cs-prose">{!! clean(\Illuminate\Support\Str::markdown((string) $c['bio'])) !!}</div>
            @endif
            @if(!empty($c['credentials']) && is_array($c['credentials']))
                <ul class="cs-creds">
                    @foreach($c['credentials'] as $cred)
                        <li><i class="fa-solid fa-circle-check"></i> {{ $cred }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</section>
