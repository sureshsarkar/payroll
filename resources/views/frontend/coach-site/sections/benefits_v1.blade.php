{{-- Benefits / Features v1 — center image + benefit items (split / grid /
     timeline). Icon defaults to a check when no image. Styling in coach-site.css
     (.cs-benefits*). Responsive: split stacks to a single column on mobile. --}}
@php
    $c        = $content;
    $layout   = $c['layout'] ?? 'split';    $layout    = in_array($layout, ['split','grid','timeline'], true) ? $layout : 'split';
    $cols     = (int) ($c['columns'] ?? 3); $cols      = in_array($cols, [2,3,4], true) ? $cols : 3;
    $connector= $c['connector'] ?? 'dotted'; $connector = in_array($connector, ['dotted','dashed','none'], true) ? $connector : 'dotted';
    $iconColor= trim((string) ($c['icon_color'] ?? ''));
    $center   = trim((string) ($c['center_image'] ?? ''));
    $items    = collect($c['items'] ?? [])->filter(function ($i) {
        return trim((string)($i['title'] ?? '')) !== '' || trim((string)($i['description'] ?? '')) !== '' || trim((string)($i['icon'] ?? '')) !== '';
    })->values();
    $iconStyle = $iconColor !== '' ? "color:{$iconColor};border-color:{$iconColor}" : '';
@endphp

@php
    // Reusable benefit-item renderer (icon + text), $side controls icon side.
    $renderItem = function ($it, $side) use ($iconStyle) {
        $img  = trim((string) ($it['icon'] ?? ''));
        $t    = trim((string) ($it['title'] ?? ''));
        $d    = trim((string) ($it['description'] ?? ''));
        $icon = '<span class="cs-benefits__icon" style="'.$iconStyle.'">'
              . ($img !== '' ? '<img src="'.e($img).'" alt="'.e($t).'" loading="lazy">' : '<i class="fa-solid fa-check" aria-hidden="true"></i>')
              . '</span>';
        $text = '<div class="cs-benefits__text">'
              . ($t !== '' ? '<h3 class="cs-benefits__title">'.e($t).'</h3>' : '')
              . ($d !== '' ? '<p class="cs-benefits__desc">'.e($d).'</p>' : '')
              . '</div>';
        return '<div class="cs-benefits__item cs-benefits__item--'.$side.'">'
             . ($side === 'left' ? $text.$icon : $icon.$text)
             . '</div>';
    };
@endphp

<section class="cs-benefits cs-benefits--{{ $layout }} cs-benefits--conn-{{ $connector }} cs-pad"
         @if(!empty($appearanceStyle)) style="{{ $appearanceStyle }}" @endif>
    <div class="cs-container">
        @if(!empty($c['eyebrow']) || !empty($c['title']) || !empty($c['subtitle']))
            <div class="cs-section-head">
                @if(!empty($c['eyebrow']))<span class="cs-eyebrow">{{ $c['eyebrow'] }}</span>@endif
                @if(!empty($c['title']))<h2 class="cs-h2">{{ $c['title'] }}</h2>@endif
                @if(!empty($c['subtitle']))<p class="cs-lead">{{ $c['subtitle'] }}</p>@endif
            </div>
        @endif

        @if($items->isNotEmpty())
            @if($layout === 'split')
                @php
                    $half  = (int) ceil($items->count() / 2);
                    $left  = $items->slice(0, $half);
                    $right = $items->slice($half);
                @endphp
                <div class="cs-benefits__split">
                    <div class="cs-benefits__col cs-benefits__col--left">
                        @foreach($left as $it){!! $renderItem($it, 'left') !!}@endforeach
                    </div>
                    <div class="cs-benefits__center">
                        <span class="cs-benefits__rings" aria-hidden="true"></span>
                        @if($center !== '')
                            <img class="cs-benefits__hero" src="{{ $center }}" alt="" loading="lazy">
                        @endif
                    </div>
                    <div class="cs-benefits__col cs-benefits__col--right">
                        @foreach($right as $it){!! $renderItem($it, 'right') !!}@endforeach
                    </div>
                </div>

            @elseif($layout === 'timeline')
                <div class="cs-benefits__timeline">
                    @foreach($items as $it)
                        @php $img=trim((string)($it['icon']??'')); $t=trim((string)($it['title']??'')); $d=trim((string)($it['description']??'')); @endphp
                        <div class="cs-benefits__tl-item">
                            <span class="cs-benefits__icon" style="{{ $iconStyle }}">
                                @if($img!=='')<img src="{{ $img }}" alt="{{ $t }}" loading="lazy">@else<i class="fa-solid fa-check" aria-hidden="true"></i>@endif
                            </span>
                            <div class="cs-benefits__text">
                                @if($t!=='')<h3 class="cs-benefits__title">{{ $t }}</h3>@endif
                                @if($d!=='')<p class="cs-benefits__desc">{{ $d }}</p>@endif
                            </div>
                        </div>
                    @endforeach
                </div>

            @else {{-- grid --}}
                <div class="cs-grid cs-grid--{{ $cols }} cs-benefits__grid">
                    @foreach($items as $it)
                        @php $img=trim((string)($it['icon']??'')); $t=trim((string)($it['title']??'')); $d=trim((string)($it['description']??'')); @endphp
                        <div class="cs-benefits__card">
                            <span class="cs-benefits__icon" style="{{ $iconStyle }}">
                                @if($img!=='')<img src="{{ $img }}" alt="{{ $t }}" loading="lazy">@else<i class="fa-solid fa-check" aria-hidden="true"></i>@endif
                            </span>
                            @if($t!=='')<h3 class="cs-benefits__title">{{ $t }}</h3>@endif
                            @if($d!=='')<p class="cs-benefits__desc">{{ $d }}</p>@endif
                        </div>
                    @endforeach
                </div>
            @endif
        @elseif($isOwnerPreview ?? false)
            <div class="cs-empty">{{ __('No benefits yet — add some in the editor.') }}</div>
        @endif
    </div>
</section>
