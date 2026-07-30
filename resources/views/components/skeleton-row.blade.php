{{--
    Skeleton table row — UI/UX audit P2-6 (2026-05-29).

    Renders N rows of skeleton placeholders for use while data loads
    asynchronously. Pairs with the .cs-skeleton-row CSS in
    public/backend/css/ui-audit-2026-05.css.

    Usage:
        <x-skeleton-row :colspan="6" :rows="5" />

    Default 3 rows, 4 columns wide. The placeholder is server-rendered
    static markup so it works without JS — the loading state is purely
    visual.

    Doesn't accept slot content (intentional — would defeat the
    purpose of a skeleton).
--}}
@props([
    'colspan' => 4,
    'rows'    => 3,
])

@for ($i = 0; $i < (int) $rows; $i++)
    <tr class="cs-skeleton-row" aria-hidden="true">
        <td colspan="{{ (int) $colspan }}"></td>
    </tr>
@endfor
