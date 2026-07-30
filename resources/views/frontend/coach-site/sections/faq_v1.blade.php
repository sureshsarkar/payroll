{{-- FAQ v1 --}}
@php $c = $content; $items = $c['items'] ?? []; @endphp
<section class="cs-faq cs-pad" @if(!empty($appearanceStyle)) style="{{ $appearanceStyle }}" @endif>
    <div class="cs-container cs-container--narrow">
        <div class="cs-section-head">
            <h2 class="cs-h2">{{ $c['title'] ?? 'Frequently asked questions' }}</h2>
        </div>
        @if(empty($items))
            <div class="cs-empty">{{ __('No FAQs yet.') }}</div>
        @else
            <div class="cs-accordion">
                @foreach($items as $i => $it)
                    <details class="cs-acc__item" @if($i === 0) open @endif>
                        <summary class="cs-acc__q">
                            {{ $it['question'] ?? '' }}
                            <i class="fa-solid fa-chevron-down cs-acc__chev"></i>
                        </summary>
                        {{-- F2 (audit 2026-06-26) — sanitize markdown HTML (clean/HTMLPurifier) so a coach FAQ answer can't inject <script>. --}}
                        <div class="cs-acc__a cs-prose">{!! clean(\Illuminate\Support\Str::markdown((string) ($it['answer'] ?? ''))) !!}</div>
                    </details>
                @endforeach
            </div>
        @endif
    </div>
</section>
