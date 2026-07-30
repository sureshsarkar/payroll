{{--
    Reusable sortable <th> link — audit fix M6 (2026-05-12).

    Renders one `<th>` cell with a link that toggles sort direction on
    click. Preserves existing filter/search query params so sorting
    doesn't wipe the user's filters. Active column shows a ↓ (desc) or
    ↑ (asc) caret; idle columns show nothing.

    Usage in the view:
        @include('admin.partials.sort-header', [
            'key'   => 'name',
            'label' => __('Name'),
            'sort'  => $sort,
            'dir'   => $dir,
            'route' => 'admin.admin.index',
        ])

    Required vars:
        key   — the sort key (must match the controller's SORTABLE allowlist)
        label — column heading text
        sort  — current active sort key from the controller
        dir   — current sort direction ('asc' or 'desc')
        route — named admin route to link to

    Optional vars:
        width — th width attribute (e.g., '20%')
        class — extra th class
--}}
@php
    $_key   = $key   ?? '';
    $_label = $label ?? '';
    $_sort  = $sort  ?? '';
    $_dir   = ($dir ?? 'desc') === 'asc' ? 'asc' : 'desc';
    $_route = $route ?? '';
    $_width = $width ?? null;
    $_class = $class ?? '';
    $_isActive = ($_sort === $_key);
    $_nextDir  = ($_isActive && $_dir === 'desc') ? 'asc' : 'desc';
    // array_merge keeps existing filter state (q=, status=, etc.) on the URL.
    $_qs = array_merge(request()->query(), ['sort' => $_key, 'dir' => $_nextDir]);
    $_caret = $_isActive ? ($_dir === 'desc' ? ' ↓' : ' ↑') : '';
@endphp
<th @isset($width) width="{{ $_width }}" @endisset class="{{ $_class }}">
    <a href="{{ route($_route, $_qs) }}"
       class="text-decoration-none text-reset"
       aria-label="{{ __('Sort by') }} {{ $_label }}"
       aria-sort="{{ $_isActive ? ($_dir === 'desc' ? 'descending' : 'ascending') : 'none' }}">
        {{ $_label }}{{ $_caret }}
    </a>
</th>
