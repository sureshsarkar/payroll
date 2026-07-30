{{--
  Trend badge for dashboard stat cards.
  Expects $trend = ['pct' => float|null, 'dir' => 'up'|'down'|'flat'].
  When previous-period value is zero/missing, dir is 'flat' and pct is null —
  rendered as a dash so we don't fake a percentage out of nothing.
--}}
@if (!empty($trend) && $trend['dir'] !== 'flat' && $trend['pct'] !== null)
    <div class="stat-trend {{ $trend['dir'] }}">
        {!! $trend['dir'] === 'up' ? '&uarr;' : '&darr;' !!} {{ $trend['pct'] }}%
    </div>
@else
    <div class="stat-trend flat" style="color:#9ca3af;">&mdash;</div>
@endif
