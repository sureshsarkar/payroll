{{--
    Auto-breadcrumb — derives a chain from request()->segments().
    Renders nothing on the root dashboard pages.
--}}
@auth('web')
@php
    $segs = request()->segments();
    // Skip if at the root of either dashboard (e.g. /student/dashboard, /instructor/dashboard)
    $isRootDashboard = (count($segs) === 2 && in_array($segs[0], ['student', 'instructor']) && $segs[1] === 'dashboard');
@endphp
@if (!$isRootDashboard && count($segs) > 0)
    @php
        // Build crumbs incrementally with their cumulative URL.
        $crumbs = [];
        $accum = '';
        foreach ($segs as $i => $seg) {
            $accum .= '/' . $seg;
            // Strip URL-id-like segments (purely numeric or UUIDs) from labels but keep them in URL.
            $isIdish = preg_match('/^\d+$/', $seg) || preg_match('/^[0-9a-f-]{16,}$/i', $seg);
            if ($isIdish) continue;
            $label = ucwords(str_replace(['-', '_'], ' ', $seg));
            // The bare /instructor and /student paths have no route (they 404);
            // each dashboard lives at /{role}/dashboard. Point the root crumb
            // there so the first breadcrumb link resolves. Global — url() uses
            // the current coach host, so this works on every white-label domain.
            $crumbUrl = ($i === 0 && in_array($seg, ['instructor', 'student']))
                ? url($accum . '/dashboard')
                : url($accum);
            $crumbs[] = ['url' => $crumbUrl, 'label' => $label, 'last' => false];
        }
        if (count($crumbs)) $crumbs[count($crumbs) - 1]['last'] = true;
    @endphp
    <nav class="mbs-breadcrumb" aria-label="Breadcrumb"
         style="padding: 8px 20px; font-size: 12.5px; color:#6b7280;">
        @foreach ($crumbs as $c)
            @if ($c['last'])
                <span style="color:#1c1a4a; font-weight:500;">{{ $c['label'] }}</span>
            @else
                <a href="{{ $c['url'] }}"
                   style="color:#10b981; text-decoration:none;">{{ $c['label'] }}</a>
                <i class="fas fa-chevron-right" style="font-size:9px; margin: 0 8px; opacity:0.4;"></i>
            @endif
        @endforeach
    </nav>
    <style>
        [data-theme="dark"] .mbs-breadcrumb { color: #94a3b8; }
        [data-theme="dark"] .mbs-breadcrumb span { color: #e2e8f0; }
    </style>
@endif
@endauth
