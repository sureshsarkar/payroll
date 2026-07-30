{{-- Cards v1 — image / title / description / button per card.
     Any empty field (and any fully-empty card) auto-hides on the live site. --}}
@php
    $c = $content;
    $cols  = (int) ($c['columns'] ?? 3); $cols = in_array($cols, [2, 3, 4], true) ? $cols : 3;
    $cards = collect($c['cards'] ?? []);
@endphp
<section class="cs-cards cs-pad" @if(!empty($appearanceStyle)) style="{{ $appearanceStyle }}" @endif>
    <div class="cs-container">
        @if(!empty($c['title']) || !empty($c['intro']))
            <div class="cs-section-head">
                @if(!empty($c['title']))<h2 class="cs-h2">{{ $c['title'] }}</h2>@endif
                @if(!empty($c['intro']))<p class="cs-lead">{{ $c['intro'] }}</p>@endif
            </div>
        @endif

        @if($cards->isNotEmpty())
            <div class="cs-grid cs-grid--{{ $cols }}">
                @foreach($cards as $card)
                    @php
                        $img  = trim((string) ($card['image'] ?? ''));
                        $t    = trim((string) ($card['title'] ?? ''));
                        $desc = trim((string) ($card['description'] ?? ''));
                        $bt   = trim((string) ($card['btn_text'] ?? ''));
                        $bu   = trim((string) ($card['btn_url'] ?? ''));
                        $nt   = ! empty($card['btn_newtab']);
                        // Per-card socials — only the ones the coach filled in.
                        $socials = array_filter([
                            'facebook'  => trim((string) ($card['social_facebook'] ?? '')),
                            'instagram' => trim((string) ($card['social_instagram'] ?? '')),
                            'twitter'   => trim((string) ($card['social_twitter'] ?? '')),
                            'linkedin'  => trim((string) ($card['social_linkedin'] ?? '')),
                            'youtube'   => trim((string) ($card['social_youtube'] ?? '')),
                        ]);
                        $socialIcons = ['facebook' => 'fa-facebook-f', 'instagram' => 'fa-instagram', 'twitter' => 'fa-twitter', 'linkedin' => 'fa-linkedin-in', 'youtube' => 'fa-youtube'];
                    @endphp
                    @continue($img === '' && $t === '' && $desc === '' && $bt === '' && empty($socials))
                    <article class="cs-card">
                        @if($img !== '')
                            <div class="cs-card__media"><img src="{{ $img }}" alt="{{ $t }}" loading="lazy"></div>
                        @endif
                        <div class="cs-card__body">
                            @if($t !== '')<h3 class="cs-card__title">{{ $t }}</h3>@endif
                            @if($desc !== '')<p class="cs-card__desc">{{ $desc }}</p>@endif
                            @if($bt !== '' && $bu !== '')
                                <div class="cs-card__foot">
                                    <a class="cs-btn cs-btn--primary cs-btn--sm" href="{{ safe_url($bu) }}"{{-- F6 --}}
                                       @if($nt) target="_blank" rel="noopener" @endif>{{ $bt }}</a>
                                </div>
                            @endif
                            @if(!empty($socials))
                                <div class="cs-card__social">
                                    @foreach($socials as $k => $url)
                                        <a href="{{ safe_url($url) }}" target="_blank" rel="noopener" aria-label="{{ ucfirst($k) }}"><i class="fa-brands {{ $socialIcons[$k] }}" aria-hidden="true"></i></a>{{-- F6 --}}
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @elseif($isOwnerPreview ?? false)
            <div class="cs-empty">{{ __('No cards yet — add some in the editor.') }}</div>
        @endif
    </div>
</section>
