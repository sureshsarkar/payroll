{{-- Stats v1 --}}
@php $c = $content; $stats = $c['stats'] ?? []; @endphp
<section class="cs-stats cs-pad" @if(!empty($appearanceStyle)) style="{{ $appearanceStyle }}" @endif>
    <div class="cs-container">
        <div class="cs-grid cs-grid--{{ min(count($stats) ?: 1, 4) }} cs-stats__grid">
            @foreach($stats as $s)
                <div class="cs-stat">
                    @if(!empty($s['icon']))
                        <i class="{{ $s['icon'] }} cs-stat__icon"></i>
                    @endif
                    <div class="cs-stat__num">{{ $s['number'] ?? '' }}<small>{{ $s['suffix'] ?? '' }}</small></div>
                    <div class="cs-stat__label">{{ $s['label'] ?? '' }}</div>
                </div>
            @endforeach
        </div>
    </div>
</section>
