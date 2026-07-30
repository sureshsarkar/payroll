@extends('admin.master_layout')
@section('title')<title>{{ __('Template Performance Report') }}</title>@endsection
@section('admin-content')
<style>
    .tpr-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:18px; }
    .tpr-stat { background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:16px; }
    .tpr-stat .lbl { font-size:11px; letter-spacing:1px; text-transform:uppercase; color:#6b7280; font-weight:600; }
    .tpr-stat .val { font-size:28px; font-weight:800; color:#1c1a4a; margin-top:4px; }
    .tpr-stat .sub { font-size:12px; color:#94a3b8; }

    .tpr-row td { padding:14px 10px !important; vertical-align:middle; }
    .tpr-thumb { width:90px; height:54px; object-fit:cover; border-radius:5px; }
    .tpr-name { font-weight:700; color:#1c1a4a; font-size:14px; }
    .tpr-vert { display:inline-block; background:#eef2ff; color:#3730a3; padding:2px 8px; border-radius:10px; font-size:10px; font-weight:700; letter-spacing:.5px; text-transform:uppercase; margin-top:2px; }
    .tpr-num { font-weight:700; font-size:16px; color:#1c1a4a; }
    .tpr-num.zero { color:#cbd5e1; font-weight:600; }
    .tpr-bar { background:#f1f5f9; border-radius:6px; overflow:hidden; height:8px; min-width:80px; max-width:160px; margin-top:4px; }
    .tpr-bar-fill { background:linear-gradient(90deg,#0d9488,#34d399); height:100%; }
    .tpr-bar.cold .tpr-bar-fill { background:linear-gradient(90deg,#94a3b8,#cbd5e1); }
    .tpr-status { padding:3px 8px; border-radius:6px; font-size:11px; font-weight:700; letter-spacing:.5px; text-transform:uppercase; }
    .tpr-status.hot   { background:#d1fae5; color:#065f46; }
    .tpr-status.warm  { background:#fef3c7; color:#92400e; }
    .tpr-status.cold  { background:#f1f5f9; color:#475569; }
    .tpr-status.dead  { background:#fee2e2; color:#991b1b; }

    @media (max-width: 900px) { .tpr-stats { grid-template-columns: 1fr 1fr; } }
</style>

<div class="page-content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">{{ __('Template Performance Report') }}</h4>
            <small class="text-muted">{{ __('Adoption · lead volume · recent activity per template') }}</small>
        </div>
        <a href="{{ route('admin.coach-landing-pages.index') }}" class="btn btn-outline-secondary">
            ← {{ __('Back to landing pages') }}
        </a>
    </div>

    <div class="tpr-stats">
        <div class="tpr-stat">
            <div class="lbl">{{ __('Active templates') }}</div>
            <div class="val">{{ number_format($totalTemplates) }}</div>
            <div class="sub">in the catalog</div>
        </div>
        <div class="tpr-stat">
            <div class="lbl">{{ __('Adopted templates') }}</div>
            <div class="val">{{ number_format($adoptedTemplates) }}</div>
            <div class="sub">{{ $totalTemplates ? round($adoptedTemplates / $totalTemplates * 100) : 0 }}% adoption rate</div>
        </div>
        <div class="tpr-stat">
            <div class="lbl">{{ __('Total leads') }}</div>
            <div class="val">{{ number_format($totalLeads) }}</div>
            <div class="sub">across every template, all-time</div>
        </div>
        <div class="tpr-stat">
            <div class="lbl">{{ __('Leads in last 30d') }}</div>
            <div class="val">{{ number_format($totalLeads30) }}</div>
            <div class="sub">rolling window</div>
        </div>
    </div>

    @php
        // Pre-compute the highest lead count so the bar-chart scale is shared
        // across every row — relative comparison reads better than absolute
        // numbers alone.
        $maxLeads = max(1, (int) ($rows->max('leads') ?? 0));
        // Round-4 cleanup (2026-05-12) — used to inline a $sortHref + $sortCaret
        // closure pair here. Replaced with the shared admin.partials.sort-header
        // partial so this page sorts identically to every other admin list.
        $_route = 'admin.coach-landing-pages.templates-report';
    @endphp

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th></th>
                            @include('admin.partials.sort-header', ['key' => 'name',     'label' => __('Template'),    'sort' => $sort, 'dir' => $dir, 'route' => $_route])
                            <th>{{ __('Vertical') }}</th>
                            @include('admin.partials.sort-header', ['key' => 'coaches',  'label' => __('Coaches'),     'sort' => $sort, 'dir' => $dir, 'route' => $_route])
                            @include('admin.partials.sort-header', ['key' => 'pages',    'label' => __('Pages'),       'sort' => $sort, 'dir' => $dir, 'route' => $_route])
                            @include('admin.partials.sort-header', ['key' => 'leads',    'label' => __('Total leads'), 'sort' => $sort, 'dir' => $dir, 'route' => $_route])
                            @include('admin.partials.sort-header', ['key' => 'leads_30', 'label' => __('Last 30d'),    'sort' => $sort, 'dir' => $dir, 'route' => $_route])
                            <th>{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $r)
                            @php
                                // Status bucketing — simple thresholds that
                                // surface stalled templates at a glance.
                                if ($r->leads_30 >= 5) {
                                    $status = ['hot',  __('Hot')];
                                } elseif ($r->leads_30 >= 1) {
                                    $status = ['warm', __('Active')];
                                } elseif ($r->pages > 0 && $r->leads == 0) {
                                    $status = ['dead', __('No leads yet')];
                                } elseif ($r->pages > 0) {
                                    $status = ['cold', __('Cooled off')];
                                } else {
                                    $status = ['cold', __('Unused')];
                                }
                                $widthPct = $maxLeads > 0 ? max(2, round(($r->leads / $maxLeads) * 100)) : 0;
                            @endphp
                            <tr class="tpr-row">
                                <td>
                                    @if ($r->image)
                                        <img src="{{ asset($r->image) }}" class="tpr-thumb" alt="">
                                    @endif
                                </td>
                                <td>
                                    <div class="tpr-name">{{ $r->template_name }}</div>
                                    <div class="tpr-vert">{{ $r->category_name ?: '—' }}</div>
                                </td>
                                <td>—</td>
                                <td class="tpr-num {{ $r->coaches == 0 ? 'zero' : '' }}">{{ number_format($r->coaches) }}</td>
                                <td class="tpr-num {{ $r->pages == 0 ? 'zero' : '' }}">{{ number_format($r->pages) }}</td>
                                <td>
                                    <div class="tpr-num {{ $r->leads == 0 ? 'zero' : '' }}">{{ number_format($r->leads) }}</div>
                                    <div class="tpr-bar {{ $r->leads == 0 ? 'cold' : '' }}">
                                        <div class="tpr-bar-fill" style="width: {{ $widthPct }}%;"></div>
                                    </div>
                                </td>
                                <td class="tpr-num {{ $r->leads_30 == 0 ? 'zero' : '' }}">{{ number_format($r->leads_30) }}</td>
                                <td>
                                    <span class="tpr-status {{ $status[0] }}">{{ $status[1] }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    {{ __('No active templates found.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
