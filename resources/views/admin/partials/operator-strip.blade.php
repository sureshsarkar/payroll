{{--
    Audit 2026-05-19 phase 3 — admin dashboard "Today at a Glance" strip.

    Sits ABOVE the legacy 8 stat cards (which surface lifetime totals).
    The strip surfaces what an operator needs immediately on landing:

      Row 1: KPI tiles — revenue, orders, signups, classes today, each
             with a trend indicator vs yesterday
      Row 2: Action items chips — only rendered if > 0, sorted by
             severity (high → medium → low), each linking to the page
             that resolves it

    Designed to read at a glance: hero numbers are large, deltas live
    next to them, action items are pill-shaped and color-coded.

    Required: $operator (from DashboardController)
--}}
@php
    $op = $operator ?? null;
@endphp
@if ($op)
@php
    // Delta visual tokens — null delta means "no comparable yesterday".
    $renderDelta = function ($pct) {
        if ($pct === null) {
            return '<span style="font-size:11px; color:#9ca3af;">— '. __('no yesterday data') .'</span>';
        }
        $icon = $pct > 0 ? 'fa-arrow-up' : ($pct < 0 ? 'fa-arrow-down' : 'fa-equals');
        $color = $pct > 0 ? '#10b981' : ($pct < 0 ? '#ef4444' : '#9ca3af');
        return sprintf(
            '<span style="font-size:11px; color:%s; font-weight:600;">
                <i class="fas %s" style="font-size:9px;"></i> %s%% <span style="color:#9ca3af; font-weight:400;">vs %s</span>
            </span>',
            $color,
            $icon,
            number_format(abs($pct), 1),
            __('yesterday')
        );
    };
@endphp

<div class="operator-strip mb-3"
     style="background:#fff; border:1px solid #eef0f3; border-radius:12px; padding:16px 18px;">

    {{-- Header --}}
    <div style="display:flex; align-items:baseline; justify-content:space-between; margin-bottom:12px;">
        <div>
            <strong style="font-size:14px; color:#1c1a4a;">
                <i class="fas fa-bolt me-1" style="color:#f59e0b;"></i>
                {{ __("Today at a glance") }}
            </strong>
            <small class="text-muted ms-2">{{ \Carbon\Carbon::parse($op['date'])->format('l, F j') }}</small>
        </div>
        <small class="text-muted">{{ __('Auto-refreshes every 60 seconds') }}</small>
    </div>

    {{-- KPI tiles --}}
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:10px; margin-bottom:14px;">

        {{-- Revenue today --}}
        <div style="background:#fafbfc; border-left:4px solid #10b981; border-radius:8px;
                    padding:12px 14px; border:1px solid #eef0f3; border-left:4px solid #10b981;">
            <div style="font-size:10px; text-transform:uppercase; color:#6b7280;
                        font-weight:600; letter-spacing:0.4px;">{{ __('Revenue') }}</div>
            <div style="font-size:22px; font-weight:700; color:#1c1a4a; margin-top:2px;">{{ formatMoney($op['revenue_today'], $op['currency'] ?? null) }}</div>
            <div style="margin-top:2px;">{!! $renderDelta($op['revenue_delta_pct']) !!}</div>
        </div>

        {{-- New orders --}}
        <div style="background:#fafbfc; border-radius:8px; padding:12px 14px;
                    border:1px solid #eef0f3; border-left:4px solid #6366f1;">
            <div style="font-size:10px; text-transform:uppercase; color:#6b7280;
                        font-weight:600; letter-spacing:0.4px;">{{ __('New orders') }}</div>
            <div style="font-size:22px; font-weight:700; color:#1c1a4a; margin-top:2px;">{{ number_format($op['new_orders_today']) }}</div>
            <div style="margin-top:2px;">{!! $renderDelta($op['new_orders_delta_pct']) !!}</div>
        </div>

        {{-- New signups --}}
        <div style="background:#fafbfc; border-radius:8px; padding:12px 14px;
                    border:1px solid #eef0f3; border-left:4px solid #3b82f6;">
            <div style="font-size:10px; text-transform:uppercase; color:#6b7280;
                        font-weight:600; letter-spacing:0.4px;">{{ __('New signups') }}</div>
            <div style="font-size:22px; font-weight:700; color:#1c1a4a; margin-top:2px;">{{ number_format($op['new_signups_today']) }}</div>
            <div style="margin-top:2px;">{!! $renderDelta($op['new_signups_delta_pct']) !!}</div>
        </div>

        {{-- Live classes today --}}
        <div style="background:#fafbfc; border-radius:8px; padding:12px 14px;
                    border:1px solid #eef0f3; border-left:4px solid #f59e0b;">
            <div style="font-size:10px; text-transform:uppercase; color:#6b7280;
                        font-weight:600; letter-spacing:0.4px;">{{ __('Live classes today') }}</div>
            <div style="font-size:22px; font-weight:700; color:#1c1a4a; margin-top:2px;">{{ number_format($op['live_classes_today']) }}</div>
            <div style="font-size:11px; color:#9ca3af; margin-top:2px;">
                @if ($op['live_classes_today'] === 0)
                    {{ __('No classes scheduled') }}
                @else
                    {{ __('Scheduled today') }}
                @endif
            </div>
        </div>
    </div>

    {{-- Action items — only render if something needs attention --}}
    @if (!empty($op['action_items']))
        <div style="padding-top:10px; border-top:1px dashed #e5e7eb;">
            <div style="font-size:11px; text-transform:uppercase; color:#6b7280;
                        font-weight:600; letter-spacing:0.4px; margin-bottom:8px;">
                <i class="fas fa-exclamation-circle" style="color:#ef4444;"></i>
                {{ __('Needs your attention') }}
            </div>
            <div style="display:flex; flex-wrap:wrap; gap:8px;">
                @foreach ($op['action_items'] as $item)
                    @php
                        $colors = [
                            'high'   => ['bg' => '#fef2f2', 'fg' => '#991b1b', 'border' => '#fca5a5'],
                            'medium' => ['bg' => '#fffbeb', 'fg' => '#92400e', 'border' => '#fcd34d'],
                            'low'    => ['bg' => '#f3f4f6', 'fg' => '#374151', 'border' => '#d1d5db'],
                        ];
                        $c = $colors[$item['severity']] ?? $colors['low'];
                        $href = '#';
                        try { $href = route($item['route']); } catch (\Throwable $e) {}
                    @endphp
                    <a href="{{ $href }}"
                       style="background:{{ $c['bg'] }}; color:{{ $c['fg'] }}; border:1px solid {{ $c['border'] }};
                              padding:6px 12px; border-radius:999px; font-size:12px; font-weight:600;
                              text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
                        <i class="fas {{ $item['icon'] }}" style="font-size:11px;"></i>
                        {{ $item['label'] }}
                        <i class="fas fa-arrow-right" style="font-size:9px; opacity:0.6;"></i>
                    </a>
                @endforeach
            </div>
        </div>
    @else
        <div style="padding-top:10px; border-top:1px dashed #e5e7eb;
                    font-size:12px; color:#10b981;">
            <i class="fas fa-check-circle"></i>
            {{ __('Nothing needs your attention right now') }}
        </div>
    @endif
</div>
@endif
