{{--
    Reusable admin stat card — audit fix H6 (2026-05-12).

    Replaces the 8 hand-rolled ~17-line `<div class="card card-statistic-1">`
    blocks that used to live inline on the dashboard. Now one ~25-line
    partial, called with five variables.

    Usage:
      @include('admin.partials.stat-card', [
          'colorClass' => 'primary-back',          // primary-back | success-back | warning-back | danger-back
          'icon'       => 'shopping-cart.png',     // image filename under backend/img/
          'trend'      => $data['trend']['total_orders'] ?? null,  // optional trend array
          'label'      => __('Total Order'),
          'value'      => $data['total_orders'],
      ])

    `trend` is optional — pass null to hide the trend badge. `value` is
    rendered verbatim, so format it at the call site (e.g. via currency()).
--}}
@php
    $_colorClass = $colorClass ?? 'primary-back';
    $_icon       = $icon       ?? 'shopping-cart.png';
    $_trend      = $trend      ?? null;
    $_label      = $label      ?? '';
    $_value      = $value      ?? '';
@endphp
<div class="col-lg-3 col-md-6 col-sm-6 col-12">
    <div class="card card-statistic-1">
        <div class="card-icon-con-div">
            <div class="card-icon bg-primary {{ $_colorClass }}">
                <img src="{{ asset('backend/img/' . $_icon) }}" alt="">
            </div>
            @if ($_trend)
                @include('admin.partials.stat-trend', ['trend' => $_trend])
            @endif
        </div>
        <div class="card-wrap">
            <div class="card-header">
                <h4>{{ $_label }}</h4>
            </div>
            <div class="card-body">
                {{ $_value }}
            </div>
        </div>
    </div>
</div>
