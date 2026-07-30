{{-- Footer v1 — pulls brand name + social from BrandResolver. Defensive
     about malformed content (string instead of array etc.) so the public
     site never crashes on a half-edited footer. --}}
@php
    $c = is_array($content) ? $content : [];
    $brandName = $brand->name ?? config('app.name');

    // link_groups may legitimately be: null, [], or an array of objects.
    // Older saves could have stashed it as a string due to a now-fixed
    // serializer bug — never trust it blindly.
    $groups = $c['link_groups'] ?? [];
    if (! is_array($groups)) $groups = [];

    // Global-footer social icons (2026-07-16). The "Show social media icons"
    // toggle (show_social) lives in the Global Footer settings but footer_v1
    // never rendered any icons, so the toggle was a no-op. Pull the coach's
    // social URLs from their site settings (same source + markup as the
    // fallback footer) and show only the platforms that have a URL. Fully
    // guarded — a missing settings row simply renders no icons, never a crash.
    $showSocial = (bool) ($c['show_social'] ?? true);
    $fSettings  = ($showSocial && ! empty($coach?->id))
        ? \App\Models\CoachSiteSettings::where('coach_id', $coach->id)->first()
        : null;
@endphp
<footer class="cs-footer">
    <div class="cs-container cs-footer__grid">
        <div class="cs-footer__brand">
            @php
                // Real Brand accessor + ownLogo guard (audit 2026-06-12) — the
                // platform logo must never render on a coach's footer.
                $csFooterLogo = (($brand->ownLogo ?? false) && ! ($brand->isPlatformDefault ?? false) && method_exists($brand, 'logoUrl'))
                    ? $brand->logoUrl() : null;
            @endphp
            @if($csFooterLogo)
                <img src="{{ $csFooterLogo }}" alt="{{ $brandName }}" class="cs-footer__logo">
            @else
                <h3 class="cs-footer__name">{{ $brandName }}</h3>
            @endif
            @if(!empty($c['tagline']))
                <p class="cs-footer__tag">{{ $c['tagline'] }}</p>
            @endif
            @if($fSettings && ($fSettings->social_facebook || $fSettings->social_instagram || $fSettings->social_youtube || $fSettings->social_twitter || $fSettings->social_linkedin || $fSettings->social_tiktok || $fSettings->social_pinterest))
                <div class="cs-footer__social">
                    @if($fSettings->social_facebook)  <a href="{{ $fSettings->social_facebook }}"  target="_blank" rel="noopener" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a> @endif
                    @if($fSettings->social_instagram) <a href="{{ $fSettings->social_instagram }}" target="_blank" rel="noopener" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a> @endif
                    @if($fSettings->social_youtube)   <a href="{{ $fSettings->social_youtube }}"   target="_blank" rel="noopener" aria-label="YouTube"><i class="fa-brands fa-youtube"></i></a> @endif
                    @if($fSettings->social_twitter)   <a href="{{ $fSettings->social_twitter }}"   target="_blank" rel="noopener" aria-label="Twitter / X"><i class="fa-brands fa-x-twitter"></i></a> @endif
                    @if($fSettings->social_linkedin)  <a href="{{ $fSettings->social_linkedin }}"  target="_blank" rel="noopener" aria-label="LinkedIn"><i class="fa-brands fa-linkedin-in"></i></a> @endif
                    @if($fSettings->social_tiktok)    <a href="{{ $fSettings->social_tiktok }}"    target="_blank" rel="noopener" aria-label="TikTok"><i class="fa-brands fa-tiktok"></i></a> @endif
                    @if($fSettings->social_pinterest) <a href="{{ $fSettings->social_pinterest }}" target="_blank" rel="noopener" aria-label="Pinterest"><i class="fa-brands fa-pinterest-p"></i></a> @endif
                </div>
            @endif
        </div>

        @foreach($groups as $g)
            @php
                if (! is_array($g)) continue;
                $links = $g['links'] ?? [];
                if (! is_array($links)) $links = [];
            @endphp
            <div class="cs-footer__col">
                <h4 class="cs-footer__h">{{ is_string($g['title'] ?? null) ? $g['title'] : '' }}</h4>
                <ul class="cs-footer__links">
                    @foreach($links as $l)
                        @if(is_array($l))
                            <li><a href="{{ $l['url'] ?? '#' }}">{{ $l['label'] ?? '' }}</a></li>
                        @endif
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
    <div class="cs-footer__bar">
        <div class="cs-container cs-footer__bar-inner">
            <span>&copy; {{ date('Y') }} {{ $brandName }}. {{ $c['copyright'] ?? 'All rights reserved.' }}</span>
        </div>
    </div>
</footer>
