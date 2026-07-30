{{-- Contact + Map v1 --}}
@php
    $c = $content;
    $email = !empty($c['email']) ? $c['email'] : ($brand->supportEmail ?? null);
@endphp
<section class="cs-contact cs-pad" id="visit" @if(!empty($appearanceStyle)) style="{{ $appearanceStyle }}" @endif>
    <div class="cs-container cs-contact__grid">
        <div class="cs-contact__info">
            <h2 class="cs-h2">{{ $c['title'] ?? 'Visit us' }}</h2>
            @if(!empty($c['address']))
                <p class="cs-contact__line"><i class="fa-solid fa-location-dot"></i> <span>{!! nl2br(e($c['address'])) !!}</span></p>
            @endif
            @if(!empty($c['phone']))
                <p class="cs-contact__line"><i class="fa-solid fa-phone"></i> <a href="tel:{{ preg_replace('/\s+/', '', $c['phone']) }}">{{ $c['phone'] }}</a></p>
            @endif
            @if(!empty($email))
                <p class="cs-contact__line"><i class="fa-solid fa-envelope"></i> <a href="mailto:{{ $email }}">{{ $email }}</a></p>
            @endif
            @if(!empty($c['hours']))
                <p class="cs-contact__line"><i class="fa-solid fa-clock"></i> <span>{!! nl2br(e($c['hours'])) !!}</span></p>
            @endif
        </div>
        {{-- F7 (audit 2026-06-26) — only render the map iframe when the src is a
             known map provider over https (safe_embed_url returns '' otherwise),
             so a coach can't frame an arbitrary origin for phishing. --}}
        @php $mapSrc = safe_embed_url($c['map_embed_url'] ?? ''); @endphp
        @if($mapSrc !== '')
            <div class="cs-contact__map">
                <iframe src="{{ $mapSrc }}" loading="lazy" allowfullscreen referrerpolicy="no-referrer-when-downgrade" frameborder="0"></iframe>
            </div>
        @endif
    </div>
</section>
