{{-- Rich Text v1 — WYSIWYG (TinyMCE) HTML, sanitized with clean() on render. --}}
@php
    $c = $content;
    $align = in_array(($c['align'] ?? 'left'), ['left', 'center', 'right'], true) ? $c['align'] : 'left';
@endphp
<section class="cs-richtext cs-pad" @if(!empty($appearanceStyle)) style="{{ $appearanceStyle }}" @endif>
    <div class="cs-container">
        @if(!empty($c['title']))
            <h2 class="cs-h2" style="text-align: {{ $align }};">{{ $c['title'] }}</h2>
        @endif
        @if(!empty($c['body']))
            <div class="cs-prose">{!! clean((string) $c['body']) !!}</div>
        @elseif($isOwnerPreview ?? false)
            <div class="cs-empty">{{ __('Add your content in the editor.') }}</div>
        @endif
    </div>
</section>
