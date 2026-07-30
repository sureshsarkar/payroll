@extends('frontend.instructor-dashboard.layouts.master')

@section('dashboard-contents')
@include('frontend.instructor-dashboard.settings.partials._corporate')

@php
    // Humanise resource / action slugs for table labels.
    $humanise = fn (string $s) => ucwords(str_replace(['-', '_'], ' ', $s));
    $actionLabels = [
        'access' => __('Access'),
        'show'   => __('Show'),
        'create' => __('Create'),
        'edit'   => __('Edit'),
        'delete' => __('Delete'),
    ];
@endphp

<style>
    /* ════════════════════════════════════════════════════════════════
       PERMISSIONS CATALOG — IAM-style matrix view.

       LAYOUT: single column with the matrix + standalone cards at
       full width, then a 4-up insights grid below.

       We cannot use corp-2col on this page because the master
       layout already wraps settings routes in a col-lg-3 (sub-nav)
       + col-lg-9 (content) row — adding corp-2col on top created a
       3rd column and a visible empty gutter (reported 2026-05-20).

       Table is custom-styled because the standard corp-table is
       row-list only; the matrix needs zebra + N/A cells +
       cell-level interactions. Pattern matches AWS IAM /
       Microsoft Entra "Capabilities" tables.
       ════════════════════════════════════════════════════════════════ */

    .perm-insights {
        margin-top: 14px;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 12px;
        align-items: start;
    }
    .perm-insights .corp-context { margin-bottom: 0; }

    /* ── Module chip filter row ── */
    .perm-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        padding: 12px 16px;
        background: var(--corp-card);
        border: 1px solid var(--corp-line);
        border-radius: 10px;
        margin-bottom: 14px;
        align-items: center;
    }
    .perm-chips__label {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .4px;
        color: var(--corp-muted);
        margin-right: 6px;
    }
    .perm-chip {
        background: #fff;
        border: 1px solid var(--corp-line);
        border-radius: 999px;
        padding: 5px 12px;
        font-size: 12px;
        font-weight: 600;
        color: var(--corp-text);
        cursor: pointer;
        user-select: none;
        transition: all .14s;
    }
    .perm-chip:hover {
        border-color: var(--corp-brand);
        color: var(--corp-brand);
    }
    .perm-chip.is-active {
        background: var(--corp-brand);
        color: #fff;
        border-color: var(--corp-brand);
    }
    .perm-chip__count {
        margin-left: 4px;
        opacity: .65;
        font-size: 11px;
    }

    /* ── Matrix table ── */
    .perm-matrix-wrap {
        background: var(--corp-card);
        border: 1px solid var(--corp-line);
        border-radius: 10px;
        overflow: hidden;
    }
    .perm-matrix {
        width: 100%;
        border-collapse: collapse;
    }
    .perm-matrix th, .perm-matrix td {
        padding: 12px 14px;
        text-align: center;
        vertical-align: middle;
        border-bottom: 1px solid var(--corp-line-soft);
    }
    .perm-matrix thead th {
        background: #fafbfc;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .4px;
        color: var(--corp-muted);
        border-bottom: 1px solid var(--corp-line);
        white-space: nowrap;
    }
    .perm-matrix thead th.col-resource {
        text-align: left;
        min-width: 220px;
        background: #f3f4f6;
    }
    .perm-matrix thead th .col-stat {
        display: block;
        font-size: 10px;
        font-weight: 600;
        color: var(--corp-subtle);
        text-transform: none;
        letter-spacing: 0;
        margin-top: 3px;
    }
    .perm-matrix tbody tr:hover { background: #fafbfc; }
    .perm-matrix tbody td.cell-resource {
        text-align: left;
        font-weight: 600;
        color: var(--corp-text);
        background: #fafbfc;
    }
    .perm-matrix tbody td.cell-resource .res-sub {
        display: block;
        font-size: 11px;
        font-weight: 500;
        color: var(--corp-muted);
        margin-top: 2px;
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    }
    .perm-matrix tbody tr:last-child td { border-bottom: none; }

    /* Cell content variants */
    .pc-cell {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 600;
        cursor: help;
        line-height: 1.4;
    }
    .pc-cell--used {
        background: var(--corp-brand-bg);
        color: var(--corp-brand);
        border: 1px solid #a7f3d0;
    }
    .pc-cell--unused {
        background: #f3f4f6;
        color: var(--corp-muted);
        border: 1px solid var(--corp-line);
    }
    .pc-cell--unused::before {
        content: '';
        width: 6px; height: 6px;
        border-radius: 50%;
        background: var(--corp-subtle);
        margin-right: 2px;
    }
    .pc-cell--na {
        display: inline-block;
        color: #d1d5db;
        font-weight: 600;
        background: repeating-linear-gradient(
            -45deg, transparent 0 4px,
            #f3f4f6 4px 8px
        );
        padding: 4px 14px;
        border-radius: 6px;
        cursor: not-allowed;
    }

    /* Per-row completeness pill (last column) */
    .pc-coverage {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 10px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
    }
    .pc-coverage--full {
        background: #ecfdf5;
        color: #047857;
    }
    .pc-coverage--partial {
        background: #fffbeb;
        color: #92400e;
    }
    .pc-coverage__bar {
        width: 40px;
        height: 4px;
        background: #f3f4f6;
        border-radius: 2px;
        overflow: hidden;
    }
    .pc-coverage__bar i {
        display: block;
        height: 100%;
        background: currentColor;
        border-radius: 2px;
    }

    /* Standalone (singleton) capabilities — chip grid below matrix */
    .perm-standalone {
        background: var(--corp-card);
        border: 1px solid var(--corp-line);
        border-radius: 10px;
        margin-top: 14px;
        overflow: hidden;
    }
    .perm-standalone__head {
        padding: 12px 16px;
        background: #fafbfc;
        border-bottom: 1px solid var(--corp-line-soft);
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .4px;
        color: var(--corp-muted);
    }
    .perm-standalone__body {
        padding: 14px 16px;
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 10px;
    }
    @media (max-width: 480px) {
        .perm-standalone__body {
            grid-template-columns: 1fr;
            padding: 10px;
        }
    }
    .perm-standalone-card {
        border: 1px solid var(--corp-line);
        border-radius: 9px;
        padding: 12px 14px;
        background: #fff;
        transition: all .14s;
    }
    .perm-standalone-card:hover {
        border-color: var(--corp-brand);
        background: var(--corp-brand-bg);
    }
    .perm-standalone-card__name {
        font-weight: 600;
        font-size: 13px;
        color: var(--corp-text);
        margin-bottom: 4px;
    }
    .perm-standalone-card__slug {
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        font-size: 11px;
        color: var(--corp-muted);
        margin-bottom: 8px;
    }

    /* Side rail insight cards */
    .pc-insight-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 7px 0;
        border-bottom: 1px dashed var(--corp-line-soft);
        font-size: 12px;
    }
    .pc-insight-row:last-child { border-bottom: none; }
    .pc-insight-row__k {
        color: var(--corp-text);
        font-weight: 500;
    }
    .pc-insight-row__v {
        font-weight: 700;
        color: var(--corp-brand);
        font-size: 13px;
    }

    /* ════════════════════════════════════════════════════════════
       RESPONSIVE
       ════════════════════════════════════════════════════════════ */

    /* Tablet — keep the matrix, allow horizontal scroll inside a
       container with a soft right-edge gradient so users see there's
       more content. Insights grid auto-collapses via its own
       auto-fit minmax — no separate breakpoint needed. */
    @media (max-width: 1024px) {
        .perm-matrix-wrap {
            position: relative;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        /* Right-edge fade hint */
        .perm-matrix-wrap::after {
            content: '';
            position: sticky;
            top: 0;
            right: 0;
            width: 24px;
            height: 100%;
            float: right;
            background: linear-gradient(to left, rgba(255,255,255,.95), transparent);
            pointer-events: none;
            margin-left: -24px;
        }
        .perm-matrix { min-width: 720px; }
        .perm-matrix th, .perm-matrix td { padding: 9px 8px; font-size: 12px; }
        .perm-matrix thead th.col-resource { min-width: 160px; }
    }

    @media (max-width: 768px) {
        .corp-header { gap: 8px; }
        .corp-header__actions { width: 100%; display: flex; gap: 6px; flex-wrap: wrap; }
        .corp-header__actions .btn-corp-primary,
        .corp-header__actions .btn-corp-secondary { flex: 1; justify-content: center; font-size: 12px; padding: 9px 10px; }
        .corp-kpi { grid-template-columns: repeat(2, 1fr); gap: 8px; }
        .corp-kpi__tile { padding: 12px 14px; }
        .corp-kpi__value { font-size: 18px; }
        .perm-chips { padding: 10px 12px; gap: 4px; }
        .perm-chips__label { display: none; }
        .perm-chip { padding: 4px 9px; font-size: 11px; }
        .perm-chips > div { width: 100%; margin-left: 0 !important; }
        .perm-chips input[type=search] { width: 100%; min-width: 0 !important; }
    }

    /* Phone — switch the matrix to a STACKED CARD layout.
       Each <tr> renders as a card; each cell becomes a row inside
       with the column name shown as a label on the left. Same data,
       same checkbox semantics — just a layout that fits a thumb. */
    @media (max-width: 640px) {
        .perm-matrix-wrap::after { display: none; }
        .perm-matrix-wrap { overflow-x: visible; padding: 8px; background: transparent; border: none; }
        .perm-matrix { display: block; min-width: 0; }
        .perm-matrix thead { display: none; }
        .perm-matrix tbody { display: block; }
        .perm-matrix tbody tr {
            display: block;
            background: var(--corp-card);
            border: 1px solid var(--corp-line);
            border-radius: 10px;
            margin-bottom: 10px;
            padding: 6px 0;
            overflow: hidden;
        }
        .perm-matrix tbody tr:hover { background: var(--corp-card); }
        .perm-matrix tbody td {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 9px 14px !important;
            border-bottom: 1px solid var(--corp-line-soft);
            text-align: left;
            font-size: 12px;
        }
        .perm-matrix tbody td:last-child { border-bottom: none; }
        /* Resource cell becomes the card header */
        .perm-matrix tbody td.cell-resource {
            background: var(--corp-brand-bg);
            border-bottom: 1px solid var(--corp-line);
            font-size: 13px;
            padding: 12px 14px !important;
            display: block;
        }
        .perm-matrix tbody td.cell-resource .res-sub { margin-top: 4px; }
        /* Synthetic column label via data-attr — populated by the JS
           below so we don't duplicate it in the blade. */
        .perm-matrix tbody td:not(.cell-resource)::before {
            content: attr(data-label);
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: var(--corp-muted);
        }
        .pc-coverage__bar { display: none; }
    }
</style>

<div class="corp-page" id="permCatalog">

    {{-- Header ────────────────────────────────────────────────── --}}
    <div class="corp-header">
        <div class="corp-header__title">
            <h4>{{ __('Permission Catalog') }}</h4>
            <p>
                {{ __('All capabilities available in the platform, grouped as Resource × Action so you can see at a glance what your roles can be wired up to do.') }}
                <strong>{{ __('Read-only.') }}</strong>
                {{ __('Assign permissions to teammates by creating or editing a Role.') }}
            </p>
        </div>
        <div class="corp-header__actions">
            <a href="{{ route('instructor.coach-staff-role.create') }}" class="btn-corp-primary">
                <i class="fas fa-plus"></i> {{ __('Create Role') }}
            </a>
            <a href="{{ route('instructor.coach-staff-role.index') }}" class="btn-corp-secondary">
                <i class="fas fa-shield-alt"></i> {{ __('Manage Roles') }}
            </a>
        </div>
    </div>

    {{-- KPI icon chip styles (scoped) --}}
    <style>
        #permCatalog .corp-kpi__tile { padding-left: 70px; min-height: 96px; }
        #permCatalog .corp-kpi__tile .sp-kpi-icon {
            position: absolute; top: 18px; left: 18px;
            width: 38px; height: 38px; border-radius: 10px;
            display: inline-flex; align-items: center; justify-content: center;
            background: color-mix(in srgb, var(--accent, var(--corp-brand)) 12%, #ffffff);
            color: var(--accent, var(--corp-brand));
            border: 1px solid color-mix(in srgb, var(--accent, var(--corp-brand)) 18%, transparent);
            font-size: 15px;
        }
        @media (max-width: 768px) {
            /* On mobile, the corp-kpi tiles already shrink padding; let icons too. */
            #permCatalog .corp-kpi__tile { padding-left: 60px; min-height: auto; }
            #permCatalog .corp-kpi__tile .sp-kpi-icon { top: 12px; left: 12px; width: 32px; height: 32px; font-size: 13px; }
        }
    </style>

    <style>
        /* 2026-07-10 (New Changes for UI #4) — dark mode for this page's bespoke components. */
        html[data-theme="dark"] .perm-chip { background: #1e293b; }
        html[data-theme="dark"] .perm-matrix thead th { background: #17233a; }
        html[data-theme="dark"] .perm-matrix thead th.col-resource { background: #22304a; }
        html[data-theme="dark"] .perm-matrix tbody tr:hover { background: #17233a; }
        html[data-theme="dark"] .perm-matrix tbody td.cell-resource { background: #17233a; }
        html[data-theme="dark"] .pc-cell--unused { background: #22304a; }
        html[data-theme="dark"] .pc-cell--na {
            background: repeating-linear-gradient(-45deg, transparent 0 4px, #22304a 4px 8px);
        }
        html[data-theme="dark"] .pc-coverage__bar { background: #22304a; }
        html[data-theme="dark"] .perm-standalone__head { background: #17233a; }
        html[data-theme="dark"] .perm-standalone-card { background: #1e293b; }
        html[data-theme="dark"] #permCatalog .corp-kpi__tile .sp-kpi-icon {
            background: color-mix(in srgb, var(--accent, var(--corp-brand)) 12%, #1e293b);
        }
        @media (max-width: 1024px) {
            html[data-theme="dark"] .perm-matrix-wrap::after {
                background: linear-gradient(to left, rgba(30,41,59,.95), transparent);
            }
        }
    </style>

    {{-- KPI strip — 4 real signals, not vanity counters ──────── --}}
    <div class="corp-kpi">
        <div class="corp-kpi__tile" style="--accent:var(--corp-brand);">
            <span class="sp-kpi-icon"><i class="fas fa-key"></i></span>
            <div class="corp-kpi__label">{{ __('Total Capabilities') }}</div>
            <div class="corp-kpi__value">{{ number_format($kpi['total']) }}</div>
            <div class="corp-kpi__sub">{{ __('platform-wide, fixed catalog') }}</div>
        </div>
        <div class="corp-kpi__tile" style="--accent:#0ea5e9;">
            <span class="sp-kpi-icon"><i class="fas fa-cubes"></i></span>
            <div class="corp-kpi__label">{{ __('Modules') }}</div>
            <div class="corp-kpi__value">{{ $kpi['modules'] }}</div>
            <div class="corp-kpi__sub">
                {{ $kpi['matrix_modules'] }} {{ __('matrix') }} ·
                {{ $kpi['standalone_count'] }} {{ __('standalone') }}
            </div>
        </div>
        <div class="corp-kpi__tile" style="--accent:#f59e0b;">
            <span class="sp-kpi-icon"><i class="fas fa-exclamation-triangle"></i></span>
            <div class="corp-kpi__label">{{ __('Coverage Gaps') }}</div>
            <div class="corp-kpi__value" style="color:{{ $kpi['incomplete_count'] > 0 ? '#92400e' : '#047857' }};">
                {{ $kpi['incomplete_count'] }}
            </div>
            <div class="corp-kpi__sub">
                {{ $kpi['incomplete_count'] > 0
                    ? __('modules missing one or more actions')
                    : __('every module is fully wired') }}
            </div>
        </div>
        <div class="corp-kpi__tile" style="--accent:#10b981;">
            <span class="sp-kpi-icon"><i class="fas fa-user-shield"></i></span>
            <div class="corp-kpi__label">{{ __('Granted In') }}</div>
            <div class="corp-kpi__value">{{ $kpi['distinct_roles'] }}</div>
            <div class="corp-kpi__sub">
                {{ trans_choice('role of yours uses these|roles of yours use these', $kpi['distinct_roles']) }}
            </div>
        </div>
    </div>

    {{-- Module chip filter row ─────────────────────────────────── --}}
    <div class="perm-chips" id="permChips">
        <span class="perm-chips__label"><i class="fas fa-filter"></i> {{ __('Filter') }}</span>
        <button type="button" class="perm-chip is-active" data-chip="__all">
            {{ __('All modules') }}
            <span class="perm-chip__count">{{ count($matrixRows) + count($standalone) }}</span>
        </button>
        @foreach ($moduleChips as $resource)
            <button type="button" class="perm-chip" data-chip="{{ $resource }}">
                {{ $humanise($resource) }}
                <span class="perm-chip__count">{{ count($matrixRows[$resource]) }}</span>
            </button>
        @endforeach
        @if (count($standalone) > 0)
            <button type="button" class="perm-chip" data-chip="__standalone">
                {{ __('Standalone') }}
                <span class="perm-chip__count">{{ count($standalone) }}</span>
            </button>
        @endif
        <div style="margin-left:auto; display:flex; gap:8px; align-items:center;">
            <input type="search" id="permSearch" placeholder="{{ __('Search resource or slug…') }}"
                   value="{{ $q }}"
                   style="height:36px; padding:6px 12px; font-size:12px; border:1px solid var(--corp-line); border-radius:8px; min-width:240px;">
        </div>
    </div>

    {{-- 2026-05-20 layout fix — the master layout already wraps
         settings routes in a col-lg-3 (sub-nav) + col-lg-9 (content)
         grid. Adding corp-2col here on top created a third column
         and a large empty gutter when both side panels tried to
         render. Switched to single-column: matrix + standalone full
         width, insights moved into a 4-up grid BELOW (responsive
         down to 1 col on phone). --}}
    <div class="perm-main">
            {{-- Resource × Action MATRIX ──────────────────────── --}}
            <div class="perm-matrix-wrap" id="matrixWrap">
                <table class="perm-matrix">
                    <thead>
                        <tr>
                            <th class="col-resource">{{ __('Resource') }}</th>
                            @foreach ($matrixActions as $action)
                                <th>
                                    {{ $actionLabels[$action] }}
                                    <span class="col-stat">
                                        {{ $colStats[$action]['present'] }} / {{ $colStats[$action]['total'] }} {{ __('modules') }}
                                    </span>
                                </th>
                            @endforeach
                            <th>{{ __('Coverage') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($matrixRows as $resource => $actions)
                            @php $stats = $rowStats[$resource]; @endphp
                            <tr data-resource="{{ $resource }}">
                                <td class="cell-resource">
                                    {{ $humanise($resource) }}
                                    <span class="res-sub">{{ $resource }}</span>
                                </td>
                                @foreach ($matrixActions as $action)
                                    <td>
                                        @if (isset($actions[$action]))
                                            @php $p = $actions[$action]; @endphp
                                            @if ($p->role_usage_count > 0)
                                                <span class="pc-cell pc-cell--used"
                                                      data-bs-toggle="tooltip"
                                                      title="{{ $p->granting_roles->pluck('name')->join(', ') }}">
                                                    <i class="fas fa-check"></i>
                                                    {{ $p->role_usage_count }}
                                                    {{ trans_choice('role|roles', $p->role_usage_count) }}
                                                </span>
                                            @else
                                                <span class="pc-cell pc-cell--unused"
                                                      title="{{ __('Available but no role grants it yet.') }}">
                                                    {{ __('unused') }}
                                                </span>
                                            @endif
                                        @else
                                            <span class="pc-cell--na" title="{{ __('Not available for this resource') }}">—</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td>
                                    <span class="pc-coverage {{ $stats['complete'] ? 'pc-coverage--full' : 'pc-coverage--partial' }}">
                                        @if ($stats['complete'])
                                            <i class="fas fa-check-circle"></i>
                                        @else
                                            <i class="fas fa-triangle-exclamation"></i>
                                        @endif
                                        {{ $stats['present'] }}/{{ $stats['possible'] }}
                                        <span class="pc-coverage__bar">
                                            <i style="width: {{ round($stats['present'] / max(1, $stats['possible']) * 100) }}%;"></i>
                                        </span>
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($matrixActions) + 2 }}">
                                <div class="corp-empty">
                                    <div class="corp-empty__icon"><i class="fas fa-key"></i></div>
                                    <div class="corp-empty__title">{{ __('No permissions in catalog') }}</div>
                                    <div class="corp-empty__hint">
                                        {{ __('The platform admin has not seeded any capability yet.') }}
                                    </div>
                                </div>
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Standalone capabilities ─────────────────────────── --}}
            @if (count($standalone) > 0)
                <div class="perm-standalone" id="standaloneWrap">
                    <div class="perm-standalone__head">
                        <i class="fas fa-cube"></i>
                        {{ __('Standalone Capabilities') }}
                        <span style="margin-left:auto; font-weight:500; color:var(--corp-muted); text-transform:none; letter-spacing:0;">
                            {{ count($standalone) }} {{ trans_choice('item|items', count($standalone)) }}
                        </span>
                    </div>
                    <div class="perm-standalone__body">
                        @foreach ($standalone as $resource => $p)
                            <div class="perm-standalone-card" data-resource="{{ $resource }}">
                                <div class="perm-standalone-card__name">{{ $humanise($resource) }}</div>
                                <div class="perm-standalone-card__slug">{{ $p->slug }}</div>
                                @if ($p->role_usage_count > 0)
                                    <span class="pc-cell pc-cell--used"
                                          data-bs-toggle="tooltip"
                                          title="{{ $p->granting_roles->pluck('name')->join(', ') }}">
                                        <i class="fas fa-check"></i>
                                        {{ $p->role_usage_count }} {{ trans_choice('role|roles', $p->role_usage_count) }}
                                    </span>
                                @else
                                    <span class="pc-cell pc-cell--unused">{{ __('unused') }}</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
    </div>

    {{-- ───────── Insights grid (was a right-rail aside) ───────── --}}
    <div class="perm-insights">

            {{-- How to read ────────────────────────────────────── --}}
            <div class="corp-context">
                <div class="corp-context__head"><i class="fas fa-info-circle"></i> {{ __('How to read this') }}</div>
                <div class="corp-context__body">
                    <ul style="margin:0;">
                        <li>
                            <strong style="color:var(--corp-brand);">{{ __('Used badge') }}</strong>
                            {{ __('— N roles of yours grant this permission. Hover to see role names.') }}
                        </li>
                        <li>
                            <strong style="color:var(--corp-muted);">{{ __('Unused chip') }}</strong>
                            {{ __('— available but no role uses it yet.') }}
                        </li>
                        <li>
                            <strong>—</strong> {{ __('(dash) — the platform has not exposed this action for that resource.') }}
                        </li>
                        <li>
                            <strong style="color:#92400e;">{{ __('Coverage gap') }}</strong>
                            {{ __('— this resource is missing one or more standard actions.') }}
                        </li>
                    </ul>
                </div>
            </div>

            {{-- Module health ──────────────────────────────────── --}}
            @if ($incompleteModules->isNotEmpty())
                <div class="corp-context">
                    <div class="corp-context__head" style="color:#92400e;">
                        <i class="fas fa-triangle-exclamation"></i> {{ __('Coverage Gaps') }}
                    </div>
                    <div class="corp-context__body">
                        @foreach ($incompleteModules as $m)
                            <div class="pc-insight-row">
                                <span class="pc-insight-row__k">{{ $humanise($m['resource']) }}</span>
                                <span class="pc-insight-row__v" style="color:#92400e;">
                                    {{ $m['present'] }} / {{ $m['possible'] }}
                                </span>
                            </div>
                        @endforeach
                        <p style="margin:10px 0 0; font-size:11px; color:var(--corp-muted);">
                            <i class="fas fa-info-circle"></i>
                            {{ __('These modules can still be used — the missing actions just aren\'t available to grant.') }}
                        </p>
                    </div>
                </div>
            @endif

            {{-- Top used ───────────────────────────────────────── --}}
            @if ($topUsed->isNotEmpty())
                <div class="corp-context">
                    <div class="corp-context__head"><i class="fas fa-fire"></i> {{ __('Top Used') }}</div>
                    <div class="corp-context__body">
                        @foreach ($topUsed as $p)
                            <div class="pc-insight-row">
                                <span class="pc-insight-row__k">{{ $p->name }}</span>
                                <span class="pc-insight-row__v">
                                    {{ $p->role_usage_count }}
                                    {{ trans_choice('role|roles', $p->role_usage_count) }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Unused list ────────────────────────────────────── --}}
            @if ($unusedPerms->isNotEmpty())
                <div class="corp-context">
                    <div class="corp-context__head">
                        <i class="fas fa-minus-circle"></i> {{ __('Unused Permissions') }}
                        <span style="margin-left:auto; color:var(--corp-muted); font-weight:500;">
                            {{ $unusedPerms->count() }}
                        </span>
                    </div>
                    <div class="corp-context__body">
                        <p style="margin:0 0 10px; font-size:12px; color:var(--corp-muted);">
                            {{ __('Available capabilities no role of yours currently grants.') }}
                        </p>
                        <div style="display:flex; flex-wrap:wrap; gap:5px;">
                            @foreach ($unusedPerms as $p)
                                <span class="corp-effective__chip" title="{{ $p->slug }}">{{ $p->name }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
    </div>
</div>

<script>
(function () {
    const root      = document.getElementById('permCatalog');
    const chipsEl   = document.getElementById('permChips');
    const searchEl  = document.getElementById('permSearch');
    const matrixEl  = document.getElementById('matrixWrap');
    const standEl   = document.getElementById('standaloneWrap');

    let activeChip = '__all';
    let query      = (searchEl?.value || '').trim().toLowerCase();

    function applyFilter() {
        // Matrix rows.
        matrixEl.querySelectorAll('tbody tr[data-resource]').forEach(row => {
            const resource = row.dataset.resource || '';
            const text     = row.textContent.toLowerCase();
            const chipOk   = activeChip === '__all' || activeChip === resource;
            const queryOk  = !query || text.includes(query);
            row.style.display = (chipOk && queryOk) ? '' : 'none';
        });

        // Standalone cards — whole block hides when filtered to a
        // matrix-only resource, otherwise individual cards filter on
        // search text.
        if (standEl) {
            const showStand = activeChip === '__all' || activeChip === '__standalone';
            standEl.style.display = showStand ? '' : 'none';
            if (showStand) {
                standEl.querySelectorAll('.perm-standalone-card[data-resource]').forEach(card => {
                    const text = card.textContent.toLowerCase();
                    card.style.display = (!query || text.includes(query)) ? '' : 'none';
                });
            }
        }
    }

    chipsEl.addEventListener('click', e => {
        const btn = e.target.closest('.perm-chip');
        if (!btn) return;
        chipsEl.querySelectorAll('.perm-chip').forEach(c => c.classList.remove('is-active'));
        btn.classList.add('is-active');
        activeChip = btn.dataset.chip;
        applyFilter();
    });

    searchEl?.addEventListener('input', () => {
        query = (searchEl.value || '').trim().toLowerCase();
        applyFilter();
    });

    // "/" focuses search — same shortcut as the permission picker
    // uses, so coaches build muscle memory across pages.
    document.addEventListener('keydown', e => {
        if (e.key === '/' && document.activeElement?.tagName !== 'INPUT' && document.activeElement?.tagName !== 'TEXTAREA') {
            e.preventDefault();
            searchEl?.focus();
        }
    });

    // Phone card layout — stamp data-label on every cell from the
    // <thead> th text so the CSS ::before can show the column name
    // beside each value. Done in JS so the blade stays clean and we
    // don't repeat the label markup per row.
    (function decorateCellsForCardLayout() {
        const headers = Array.from(matrixEl.querySelectorAll('thead th')).map(h => {
            // Strip the col-stat sub-line so we get just "Access" not "Access\n5 / 6 modules"
            const clone = h.cloneNode(true);
            clone.querySelectorAll('.col-stat').forEach(n => n.remove());
            return clone.textContent.trim();
        });
        matrixEl.querySelectorAll('tbody tr').forEach(row => {
            Array.from(row.children).forEach((cell, i) => {
                if (!cell.classList.contains('cell-resource')) {
                    cell.setAttribute('data-label', headers[i] || '');
                }
            });
        });
    })();

    // Bootstrap tooltips on hover badges (Bootstrap 5 is loaded by
    // the dashboard layout). Falls back silently if BS isn't ready.
    try {
        if (window.bootstrap?.Tooltip) {
            root.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
                new window.bootstrap.Tooltip(el, { boundary: 'window' });
            });
        }
    } catch (_) {}

    applyFilter();
})();
</script>
@endsection
