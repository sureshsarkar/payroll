{{--
    Shared admin empty-state row — audit fix M4 (2026-05-12).

    Renders an empty <tr><td colspan> row with a centered icon, headline,
    and optional subtitle. Replaces three drifting empty-state markups:

      - admin-list/admin.blade.php had NO empty state (@foreach not @forelse)
      - roles/index.blade.php's @empty branch rendered an Update button
        (copy-paste error from another view)
      - membership/plans/index.blade.php used a bare <td colspan>

    Usage inside a @forelse:
        @forelse ($rows as $row)
            … row markup …
        @empty
            @include('admin.partials.empty-state', [
                'colspan'  => 7,
                'icon'     => 'fa-inbox',
                'title'    => __('No plans yet'),
                'subtitle' => __('Create your first one to get started.'),
            ])
        @endforelse
--}}
@php
    $_colspan  = $colspan  ?? 7;
    $_icon     = $icon     ?? 'fa-inbox';
    $_title    = $title    ?? __('No records yet');
    $_subtitle = $subtitle ?? null;
@endphp
<tr>
    <td colspan="{{ $_colspan }}" class="text-center py-5 text-muted">
        <div style="font-size:42px; color:#cbd5e1; margin-bottom:8px;">
            <i class="fa {{ $_icon }}" aria-hidden="true"></i>
        </div>
        <div style="font-weight:600; color:#475569; font-size:15px;">
            {{ $_title }}
        </div>
        @if ($_subtitle)
            <div style="font-size:13px; color:#94a3b8; margin-top:4px;">
                {{ $_subtitle }}
            </div>
        @endif
    </td>
</tr>
