@php
    $depth = $depth ?? ($child ?? false ? 2 : 1); // back-compat if a caller still passes $child
    $typeLabels = [
        'page' => __('Page'), 'section' => __('Section'),
        'url' => __('Link'), 'route' => __('Route'), 'home' => __('Home'), 'none' => __('Heading'),
    ];
    $json = [
        'id' => $item->id, 'label' => $item->label, 'link_type' => $item->link_type,
        'page_id' => $item->page_id, 'url' => $item->url, 'section_anchor' => $item->section_anchor,
        'route_name' => $item->route_name, 'target' => $item->target,
        'layout' => $item->layout ?? 'dropdown',
    ];
@endphp
<li class="mnb-item {{ $depth >= 2 ? 'is-child' : '' }} {{ $depth === 3 ? 'is-child-2' : '' }}" draggable="true"
    data-id="{{ $item->id }}" data-json='@json($json, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE)'>
    <span class="mnb-item__handle" title="{{ __('Drag to reorder') }}"><i class="fas fa-grip-vertical"></i></span>
    <span class="mnb-item__label">{{ $item->label }}</span>
    <span class="mnb-item__type">{{ $typeLabels[$item->link_type] ?? $item->link_type }}</span>
    @if(($item->layout ?? 'dropdown') === 'mega' && $depth === 1)
        <span class="mnb-item__mega" title="{{ __('Opens a wide multi-column panel') }}">{{ __('MEGA') }}</span>
    @endif
    @if($item->target === '_blank') <i class="fas fa-up-right-from-square" style="font-size:10px;color:#9aa0bc;" title="{{ __('Opens in new tab') }}"></i> @endif
    @unless($item->is_visible) <span class="mnb-item__hidden">{{ __('hidden') }}</span> @endunless
    <span class="mnb-item__actions">
        <button type="button" class="mnb-btn mnb-btn--ghost mnb-btn--sm mnb-outdent" title="{{ __('Move left (less nested)') }}"><i class="fas fa-outdent"></i></button>
        <button type="button" class="mnb-btn mnb-btn--ghost mnb-btn--sm mnb-indent" title="{{ __('Move right (nest under item above)') }}"><i class="fas fa-indent"></i></button>
        <button type="button" class="mnb-btn mnb-btn--ghost mnb-btn--sm mnb-edit" title="{{ __('Edit') }}"><i class="fas fa-pen"></i></button>
        <button type="button" class="mnb-btn mnb-btn--danger mnb-btn--sm mnb-del" title="{{ __('Delete') }}"><i class="fas fa-trash"></i></button>
    </span>
</li>
