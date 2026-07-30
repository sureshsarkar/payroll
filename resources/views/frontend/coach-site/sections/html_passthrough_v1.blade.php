{{-- Legacy GrapesJS passthrough — set ONLY by the one-time migration script. --}}
{{-- Intentionally renders raw because the migrated content is the coach's own previously-saved markup. --}}
@php $c = $content; @endphp
@if(!empty($c['css']))
    <style>{!! $c['css'] !!}</style>
@endif
@if(!empty($c['html']))
    {!! $c['html'] !!}
@endif
