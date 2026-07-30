{{--
    Shared CSS for the corporate redesign — used by every settings-hub
    page (staff, roles, permissions, profile, payout, subscription-
    histories, membership, teacher-batches) plus the coach + teacher
    dashboards, the permission catalog, and the course-batches list.

    2026-05-20 — premium token upgrade.
      Inter font, indigo→violet gradient brand, multi-layer shadows,
      hairline borders, tabular-nums on KPI values, motion easing.
      Public class names UNCHANGED so every consuming blade gets the
      new look without touching the markup. New optional extensions:
        .corp-kpi__tile--glow   gradient-border featured tile
        .corp-grad-text          indigo→violet text fill
        .corp-pulse              soft attention pulse
        .corp-icon-chip          brand-tinted icon background

    Tokens:
      --corp-bg / --corp-card / --corp-line / --corp-text /
      --corp-muted / --corp-brand / --corp-brand-grad /
      --corp-shadow-sm / --corp-shadow-md / --corp-shadow-lg /
      --corp-ease

    Single include per page — three consumers (settings hub, coach
    dashboard, premium pages) all share the same skin.
--}}
{{-- 2026-07-10 (New Changes for UI #1) — FOUC fix. This partial is
     @include'd in the BODY of 47 dashboard pages; previously its font
     <link> + the ~880-line <style> below rendered mid-body, so the
     browser painted the HTML above them before the render-blocking
     Google-Fonts stylesheet arrived — an unstyled flash on first
     (uncached) load, correct only after a manual refresh. We now @push
     both into the head @stack('styles') (see frontend/layouts/master.blade
     line 26) so the styling is parsed BEFORE first paint. @once guards
     against a double-include duplicating the block. --}}
@once('corp-fonts')
@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet"
      href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
@endpush
@endonce

@php
    // 2026-07-04 — Brand-aware accent (white-label). The corporate skin used to
    // hardcode an indigo→violet gradient, which clashed with the coach panel's
    // emerald chrome and ignored each coach's own brand colour. Resolve the
    // coach's brand primary; fall back to the panel's emerald green. We never
    // force a palette. Every corporate token below derives from this one seed.
    $__corpSeed = null;
    try { $__corpSeed = $brand->primary_color ?? null; } catch (\Throwable $e) {}
    $__corpHex = strtolower(ltrim((string) ($__corpSeed ?: '#10b981'), '#'));
    if (strlen($__corpHex) === 3) {
        $__corpHex = $__corpHex[0].$__corpHex[0].$__corpHex[1].$__corpHex[1].$__corpHex[2].$__corpHex[2];
    }
    if (strlen($__corpHex) === 8) { $__corpHex = substr($__corpHex, 0, 6); } // drop alpha channel
    if (!preg_match('/^[0-9a-f]{6}$/', $__corpHex)) { $__corpHex = '10b981'; }
    $__cr = hexdec(substr($__corpHex, 0, 2));
    $__cg = hexdec(substr($__corpHex, 2, 2));
    $__cb = hexdec(substr($__corpHex, 4, 2));
    // Mix the seed toward a target colour (tr,tg,tb) by weight w (0..1).
    $__mix = function ($tr, $tg, $tb, $w) use ($__cr, $__cg, $__cb) {
        return sprintf('#%02x%02x%02x',
            (int) round($__cr + ($tr - $__cr) * $w),
            (int) round($__cg + ($tg - $__cg) * $w),
            (int) round($__cb + ($tb - $__cb) * $w));
    };
    $corpBrand       = '#' . $__corpHex;
    $corpBrandDeep   = $__mix(0, 0, 0, 0.32);          // darker — deep text / active
    $corpBrandEnd    = $__mix(0, 0, 0, 0.16);          // gradient end — subtle depth
    $corpBrandBg     = $__mix(255, 255, 255, 0.92);    // light tint — chip / hover ground
    $corpBrandBorder = $__mix(255, 255, 255, 0.60);    // soft brand border
    $corpBrandRgb    = "{$__cr}, {$__cg}, {$__cb}";     // for rgba(var(--corp-brand-rgb), a)
@endphp

@once('corp-style')
@push('styles')
<style>
:root {
    /* Surface */
    --corp-bg:        #f7f8fb;
    --corp-card:      #ffffff;
    --corp-card-grad: linear-gradient(180deg, #ffffff 0%, #fafbff 100%);
    --corp-line:      rgba(15, 23, 42, 0.08);
    --corp-line-soft: rgba(15, 23, 42, 0.04);

    /* Text */
    --corp-text:      #0f172a;
    --corp-muted:     #64748b;
    --corp-subtle:    #94a3b8;

    /* Brand — resolved from the coach's brand colour (white-label),
       falling back to the panel's emerald green. Derived in the @php
       block above so the whole skin follows one seed. */
    --corp-brand:       {{ $corpBrand }};
    --corp-brand-2:     {{ $corpBrandEnd }};
    --corp-brand-deep:  {{ $corpBrandDeep }};
    --corp-brand-bg:    {{ $corpBrandBg }};
    --corp-brand-border:{{ $corpBrandBorder }};
    --corp-brand-rgb:   {{ $corpBrandRgb }};
    --corp-brand-grad:  linear-gradient(135deg, {{ $corpBrand }} 0%, {{ $corpBrandEnd }} 100%);
    --corp-brand-glow:  0 4px 14px -2px rgba(var(--corp-brand-rgb), 0.35);

    /* Multi-layer shadows — first layer is an inset 1px highlight
       at the top of the surface (the "glass" cue); second is the
       ambient drop. Lifts cards off the page without grey halo. */
    --corp-shadow-sm:
        inset 0 1px 0 rgba(255, 255, 255, 0.65),
        0 1px 2px rgba(15, 23, 42, 0.04),
        0 2px 6px -2px rgba(15, 23, 42, 0.04);
    --corp-shadow-md:
        inset 0 1px 0 rgba(255, 255, 255, 0.7),
        0 4px 12px -3px rgba(15, 23, 42, 0.08),
        0 8px 24px -8px rgba(15, 23, 42, 0.06);
    --corp-shadow-lg:
        inset 0 1px 0 rgba(255, 255, 255, 0.7),
        0 12px 32px -8px rgba(15, 23, 42, 0.12),
        0 24px 60px -16px rgba(15, 23, 42, 0.10);

    /* Motion — same easing every transition uses for a coherent feel. */
    --corp-ease: cubic-bezier(.22, 1, .36, 1);
}

/* Font: Inter for everything inside a corp-page; we don't override
   the global body so other pages stay on their existing stack. */
.corp-page {
    padding: 4px 0 28px;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}

/* Tabular numerals — every KPI value and table number reads at the
   same width so columns don't jiggle when filters change. */
.corp-page .corp-kpi__value,
.corp-page .corp-table td,
.corp-page .corp-meta-row__v,
.corp-page .pc-insight-row__v {
    font-variant-numeric: tabular-nums;
    font-feature-settings: 'tnum';
}

/* ─────────────────────────────────────────────────────────────
   Header
   ───────────────────────────────────────────────────────────── */
.corp-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 14px;
    margin-bottom: 18px;
}
.corp-header__title h4 {
    font-size: 22px;
    font-weight: 800;
    color: var(--corp-text);
    margin: 0 0 5px;
    letter-spacing: -0.025em;
    line-height: 1.2;
}
.corp-header__title p {
    font-size: 13px;
    color: var(--corp-muted);
    margin: 0;
    line-height: 1.5;
    max-width: 720px;
}
.corp-header__actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
}
.corp-header__actions .btn-corp-primary {
    background: var(--corp-brand-grad);
    color: #fff;
    border: none;
    padding: 10px 18px;
    border-radius: 9px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    transition: transform .18s var(--corp-ease), box-shadow .18s var(--corp-ease);
    box-shadow: var(--corp-brand-glow);
    letter-spacing: -0.01em;
}
.corp-header__actions .btn-corp-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 8px 20px -4px rgba(var(--corp-brand-rgb), 0.45);
    color: #fff;
}
.corp-header__actions .btn-corp-primary:active { transform: translateY(0); }

/* ─────────────────────────────────────────────────────────────
   KPI strip
   ───────────────────────────────────────────────────────────── */
.corp-kpi {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    gap: 12px;
    margin-bottom: 16px;
}
.corp-kpi__tile {
    background: var(--corp-card-grad);
    border: 1px solid var(--corp-line);
    border-radius: 12px;
    padding: 18px 20px;
    position: relative;
    overflow: hidden;
    box-shadow: var(--corp-shadow-sm);
    transition: transform .2s var(--corp-ease), box-shadow .2s var(--corp-ease);
}
.corp-kpi__tile::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    bottom: 0;
    width: 3px;
    background: var(--accent, var(--corp-brand));
}
/* Premium signature — soft radial wash in the top-right corner
   colored to match the tile's accent. Adds depth without noise. */
.corp-kpi__tile::after {
    content: '';
    position: absolute;
    top: -40px;
    right: -40px;
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: radial-gradient(circle, var(--accent, var(--corp-brand)) 0%, transparent 70%);
    opacity: 0.08;
    pointer-events: none;
}
.corp-kpi__tile:hover {
    transform: translateY(-2px);
    box-shadow: var(--corp-shadow-md);
}
.corp-kpi__label {
    font-size: 10.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: var(--corp-muted);
    margin-bottom: 8px;
    position: relative;
}
.corp-kpi__value {
    font-size: 26px;
    font-weight: 800;
    color: var(--corp-text);
    line-height: 1.1;
    letter-spacing: -0.025em;
    position: relative;
}
.corp-kpi__sub {
    font-size: 11.5px;
    color: var(--corp-subtle);
    margin-top: 4px;
    position: relative;
}

/* ─────────────────────────────────────────────────────────────
   Filter bar
   ───────────────────────────────────────────────────────────── */
.corp-filters {
    background: var(--corp-card-grad);
    border: 1px solid var(--corp-line);
    border-radius: 11px;
    padding: 12px 16px;
    margin-bottom: 14px;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    box-shadow: var(--corp-shadow-sm);
}
.corp-filters input[type="search"],
.corp-filters select {
    background: #fff;
    border: 1px solid var(--corp-line);
    border-radius: 9px;
    padding: 8px 12px;
    font-size: 13px;
    color: var(--corp-text);
    min-width: 180px;
    height: 38px;
    font-family: inherit;
    transition: border-color .14s var(--corp-ease), box-shadow .14s var(--corp-ease);
}
.corp-filters input[type="search"]::placeholder { color: var(--corp-subtle); }
.corp-filters input[type="search"]:focus,
.corp-filters select:focus {
    outline: none;
    border-color: var(--corp-brand);
    box-shadow: 0 0 0 3px rgba(var(--corp-brand-rgb), .12);
}
.corp-filters .btn-corp-search {
    background: var(--corp-text);
    color: #fff;
    border: none;
    border-radius: 9px;
    padding: 8px 16px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    height: 38px;
    transition: background .14s var(--corp-ease);
}
.corp-filters .btn-corp-search:hover { background: #1f2937; }
.corp-filters .btn-corp-clear {
    background: transparent;
    border: 1px solid var(--corp-line);
    color: var(--corp-muted);
    border-radius: 9px;
    padding: 8px 14px;
    font-size: 12px;
    text-decoration: none;
    height: 38px;
    display: inline-flex;
    align-items: center;
    font-weight: 500;
    transition: background .14s var(--corp-ease), border-color .14s var(--corp-ease);
}
.corp-filters .btn-corp-clear:hover { background: #f9fafb; border-color: #d1d5db; color: var(--corp-text); }

/* ─────────────────────────────────────────────────────────────
   Data table
   ───────────────────────────────────────────────────────────── */
.corp-table-wrap {
    background: var(--corp-card);
    border: 1px solid var(--corp-line);
    border-radius: 12px;
    /* 2026-06-01 responsive audit: was `overflow: hidden`, which CLIPPED
       wide tables on phones/tablets — the My Sales (10 cols), live-classes,
       my-students, payout, batches and subscription listings all lost
       their right-hand columns (incl. the Actions buttons) below ~900px
       with no way to reach them. Keep vertical clip for the rounded
       corners but allow horizontal scroll so wide tables swipe inside the
       card instead of overflowing the page. */
    overflow: hidden;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    box-shadow: var(--corp-shadow-sm);
}
.corp-table {
    width: 100%;
    border-collapse: collapse;
}
.corp-table thead {
    background: #fafbfc;
}
.corp-table th {
    text-align: left;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--corp-muted);
    padding: 13px 18px;
    border-bottom: 1px solid var(--corp-line);
}
.corp-table td {
    padding: 14px 18px;
    border-bottom: 1px solid var(--corp-line-soft);
    font-size: 13px;
    color: var(--corp-text);
    vertical-align: middle;
}
.corp-table tbody tr:last-child td { border-bottom: none; }
.corp-table tbody tr {
    transition: background .12s var(--corp-ease);
}
.corp-table tbody tr:hover { background: #fafbff; }

/* ─────────────────────────────────────────────────────────────
   Status pills
   ───────────────────────────────────────────────────────────── */
.corp-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 600;
    line-height: 1.4;
    letter-spacing: 0.01em;
}
.corp-pill::before {
    content: '';
    width: 6px; height: 6px;
    border-radius: 50%;
    background: currentColor;
}
.corp-pill--success { background: #ecfdf5; color: #047857; }
.corp-pill--danger  { background: #fef2f2; color: #b91c1c; }
.corp-pill--warning { background: #fffbeb; color: #92400e; }
.corp-pill--muted   { background: #f3f4f6; color: #4b5563; }
.corp-pill--brand   { background: var(--corp-brand-bg); color: var(--corp-brand-deep); }

/* No leading dot variant — for module pills etc. */
.corp-pill--plain::before { display: none; }

/* ─────────────────────────────────────────────────────────────
   Avatar
   ───────────────────────────────────────────────────────────── */
.corp-avatar {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: var(--corp-brand-grad);
    color: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 13px;
    flex-shrink: 0;
    letter-spacing: -0.02em;
    box-shadow: 0 2px 8px -2px rgba(var(--corp-brand-rgb), 0.35);
}

/* ─────────────────────────────────────────────────────────────
   Row actions
   ───────────────────────────────────────────────────────────── */
.corp-actions {
    display: inline-flex;
    gap: 4px;
    align-items: center;
}
.corp-actions__btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px; height: 32px;
    border-radius: 7px;
    background: transparent;
    border: 1px solid var(--corp-line);
    color: var(--corp-muted);
    text-decoration: none;
    font-size: 12px;
    cursor: pointer;
    transition: all .14s var(--corp-ease);
    padding: 0;
}
.corp-actions__btn:hover {
    background: var(--corp-brand-bg);
    color: var(--corp-brand-deep);
    border-color: var(--corp-brand-border);
    transform: translateY(-1px);
}
.corp-actions__btn--danger:hover {
    background: #fef2f2;
    color: #b91c1c;
    border-color: #fca5a5;
}

/* ─────────────────────────────────────────────────────────────
   Empty state
   ───────────────────────────────────────────────────────────── */
.corp-empty {
    text-align: center;
    padding: 52px 24px;
    color: var(--corp-muted);
}
.corp-empty__icon {
    font-size: 36px;
    color: var(--corp-brand-bg);
    margin-bottom: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 64px;
    height: 64px;
    border-radius: 16px;
    background: var(--corp-brand-bg);
}
.corp-empty__icon i { color: var(--corp-brand); }
.corp-empty__title {
    font-size: 16px;
    font-weight: 700;
    color: var(--corp-text);
    margin-bottom: 5px;
    letter-spacing: -0.015em;
}
.corp-empty__hint {
    font-size: 12.5px;
    color: var(--corp-muted);
    max-width: 380px;
    margin: 0 auto;
    line-height: 1.5;
}

/* ─────────────────────────────────────────────────────────────
   Pagination wrapper
   ───────────────────────────────────────────────────────────── */
.corp-pagination {
    padding: 14px 18px;
    border-top: 1px solid var(--corp-line-soft);
    background: #fafbfc;
}
.corp-pagination .pagination { margin: 0; }

/* ─────────────────────────────────────────────────────────────
   Module group header (Permissions page)
   ───────────────────────────────────────────────────────────── */
.corp-group {
    background: var(--corp-card);
    border: 1px solid var(--corp-line);
    border-radius: 12px;
    margin-bottom: 12px;
    overflow: hidden;
    box-shadow: var(--corp-shadow-sm);
}
.corp-group__head {
    padding: 13px 18px;
    background: #fafbfc;
    border-bottom: 1px solid var(--corp-line-soft);
    display: flex;
    align-items: center;
    gap: 8px;
}
.corp-group__title {
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--corp-text);
    margin: 0;
}
.corp-group__count {
    font-size: 11px;
    color: var(--corp-muted);
    margin-left: auto;
}

@media (max-width: 768px) {
    .corp-header__title h4 { font-size: 18px; }
    .corp-kpi__value { font-size: 22px; }
    .corp-kpi__tile { padding: 14px 16px; }
    .corp-table th, .corp-table td { padding: 11px 14px; font-size: 12px; }
}

/* ════════════════════════════════════════════════════════════════
   FORM PRIMITIVES — shared across all create/edit pages
   ════════════════════════════════════════════════════════════════ */
.corp-form-card {
    background: var(--corp-card-grad);
    border: 1px solid var(--corp-line);
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 14px;
    box-shadow: var(--corp-shadow-sm);
}
.corp-form-card__head {
    padding: 15px 20px;
    border-bottom: 1px solid var(--corp-line-soft);
    background: #fafbfc;
}
.corp-form-card__title {
    font-size: 13px; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.5px;
    color: var(--corp-text); margin: 0;
    display: flex; align-items: center; gap: 7px;
}
.corp-form-card__sub { font-size: 11.5px; color: var(--corp-muted); margin: 4px 0 0; }
.corp-form-card__body { padding: 20px; }

.corp-field__label {
    display: block; font-size: 12px; font-weight: 600;
    color: var(--corp-text); margin-bottom: 6px;
    letter-spacing: -0.005em;
}
.corp-field__label .req { color: #ef4444; margin-left: 2px; }
.corp-field__hint { font-size: 11px; color: var(--corp-muted); margin-top: 4px; }
.corp-input, .corp-select {
    width: 100%; height: 40px;
    border: 1px solid var(--corp-line); border-radius: 9px;
    padding: 8px 12px; font-size: 13px;
    color: var(--corp-text); background: #fff;
    font-family: inherit;
    transition: border-color .14s var(--corp-ease), box-shadow .14s var(--corp-ease);
}
.corp-input:focus, .corp-select:focus {
    outline: none; border-color: var(--corp-brand);
    box-shadow: 0 0 0 3px rgba(var(--corp-brand-rgb), .12);
}

.btn-corp-secondary {
    background: #fff; color: var(--corp-text);
    border: 1px solid var(--corp-line);
    padding: 10px 18px; border-radius: 9px;
    font-size: 13px; font-weight: 600;
    text-decoration: none;
    display: inline-flex; align-items: center; gap: 7px;
    cursor: pointer;
    font-family: inherit;
    letter-spacing: -0.01em;
    transition: background .14s var(--corp-ease), border-color .14s var(--corp-ease), transform .14s var(--corp-ease);
}
.btn-corp-secondary:hover {
    background: #f9fafb;
    color: var(--corp-text);
    border-color: #d1d5db;
    transform: translateY(-1px);
}

.btn-corp-ghost {
    background: transparent; color: var(--corp-muted);
    border: none; padding: 8px 14px;
    font-size: 12px; cursor: pointer;
    font-family: inherit;
    display: inline-flex; align-items: center; gap: 6px;
    transition: color .14s var(--corp-ease);
}
.btn-corp-ghost:hover { color: var(--corp-text); }

/* ════════════════════════════════════════════════════════════════
   WIZARD — stepper with progress
   ════════════════════════════════════════════════════════════════ */
.corp-stepper {
    background: var(--corp-card-grad);
    border: 1px solid var(--corp-line);
    border-radius: 12px;
    padding: 20px 24px;
    margin-bottom: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
    box-shadow: var(--corp-shadow-sm);
}
.corp-stepper__item {
    flex: 1; display: flex; align-items: center; gap: 10px;
    color: var(--corp-muted);
    font-size: 13px; font-weight: 500;
    transition: color .15s var(--corp-ease);
}
.corp-stepper__num {
    width: 30px; height: 30px; border-radius: 50%;
    background: #e5e7eb; color: var(--corp-muted);
    display: inline-flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 12px;
    flex-shrink: 0;
    transition: background .15s var(--corp-ease), color .15s var(--corp-ease), box-shadow .2s var(--corp-ease);
}
.corp-stepper__connector {
    flex: 1; height: 2px; background: #e5e7eb;
    margin: 0 4px; border-radius: 1px;
    transition: background .15s var(--corp-ease);
}
.corp-stepper__item.is-active { color: var(--corp-text); font-weight: 600; }
.corp-stepper__item.is-active .corp-stepper__num {
    background: var(--corp-brand-grad); color: #fff;
    box-shadow: 0 0 0 4px rgba(var(--corp-brand-rgb), .15), 0 4px 12px -2px rgba(var(--corp-brand-rgb), .35);
}
.corp-stepper__item.is-done { color: #047857; }
.corp-stepper__item.is-done .corp-stepper__num {
    background: #10b981; color: #fff;
    box-shadow: 0 4px 10px -2px rgba(16, 185, 129, .35);
}
.corp-stepper__item.is-done + .corp-stepper__connector { background: #10b981; }

/* Wizard step panels: only the active step is visible */
.corp-step { display: none; }
.corp-step.is-active { display: block; }

/* ════════════════════════════════════════════════════════════════
   STICKY ACTION FOOTER — sticks to bottom of viewport
   ════════════════════════════════════════════════════════════════ */
.corp-sticky-bar {
    position: sticky;
    bottom: 0;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: saturate(180%) blur(8px);
    -webkit-backdrop-filter: saturate(180%) blur(8px);
    border: 1px solid var(--corp-line);
    border-radius: 12px;
    padding: 14px 20px;
    margin-top: 14px;
    box-shadow: 0 -8px 24px -8px rgba(15, 23, 42, .12);
    display: flex; align-items: center; gap: 12px;
    flex-wrap: wrap;
    z-index: 5;
}
.corp-sticky-bar__summary {
    flex: 1; min-width: 180px;
    font-size: 12.5px; color: var(--corp-muted);
    display: flex; align-items: center; gap: 8px;
    flex-wrap: wrap;
}
.corp-sticky-bar__summary strong { color: var(--corp-text); font-weight: 600; }
.corp-sticky-bar__actions { display: flex; gap: 8px; }

/* ════════════════════════════════════════════════════════════════
   SIDE CONTEXT PANEL — right-rail tips / metadata
   ════════════════════════════════════════════════════════════════ */
.corp-context {
    background: var(--corp-card-grad);
    border: 1px solid var(--corp-line);
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 14px;
    box-shadow: var(--corp-shadow-sm);
}
.corp-context__head {
    padding: 13px 16px;
    border-bottom: 1px solid var(--corp-line-soft);
    background: #fafbfc;
    font-size: 11px; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.5px;
    color: var(--corp-muted);
    display: flex; align-items: center; gap: 7px;
}
.corp-context__body { padding: 14px 16px; font-size: 12.5px; color: var(--corp-text); line-height: 1.55; }
.corp-context__body ul { margin: 0; padding-left: 18px; }
.corp-context__body li { margin-bottom: 7px; }
.corp-context__body code {
    background: #f3f4f6; padding: 2px 6px;
    border-radius: 5px; font-size: 11px;
    color: var(--corp-brand-deep);
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
}
.corp-meta-row {
    display: flex; justify-content: space-between;
    padding: 7px 0; font-size: 12.5px;
    border-bottom: 1px dashed var(--corp-line-soft);
}
.corp-meta-row:last-child { border-bottom: none; }
.corp-meta-row__k { color: var(--corp-muted); font-weight: 500; }
.corp-meta-row__v { color: var(--corp-text); font-weight: 600; font-variant-numeric: tabular-nums; }

/* ════════════════════════════════════════════════════════════════
   PRESET CARDS — quick-start templates
   ════════════════════════════════════════════════════════════════ */
.corp-presets {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 10px;
    margin-bottom: 14px;
}
.corp-preset {
    border: 1px solid var(--corp-line);
    border-radius: 11px;
    padding: 16px 18px;
    cursor: pointer;
    transition: border-color .18s var(--corp-ease), box-shadow .18s var(--corp-ease), transform .18s var(--corp-ease);
    background: var(--corp-card-grad);
    text-align: left;
    box-shadow: var(--corp-shadow-sm);
}
.corp-preset:hover {
    border-color: var(--corp-brand);
    box-shadow: var(--corp-shadow-md), 0 0 0 4px rgba(var(--corp-brand-rgb), .08);
    transform: translateY(-2px);
}
.corp-preset__icon {
    width: 34px; height: 34px;
    border-radius: 9px;
    display: inline-flex; align-items: center; justify-content: center;
    background: var(--corp-brand-bg); color: var(--corp-brand);
    font-size: 14px; margin-bottom: 10px;
}
.corp-preset__name {
    font-size: 13px; font-weight: 700;
    color: var(--corp-text); margin-bottom: 4px;
    letter-spacing: -0.01em;
}
.corp-preset__desc { font-size: 11.5px; color: var(--corp-muted); line-height: 1.45; }
.corp-preset__count {
    font-size: 11px; color: var(--corp-brand);
    font-weight: 700; margin-top: 10px;
}

/* ════════════════════════════════════════════════════════════════
   EFFECTIVE CHIPS — preview of selected permissions
   ════════════════════════════════════════════════════════════════ */
.corp-effective {
    background: #fafbfc;
    border-top: 1px solid var(--corp-line-soft);
    border-bottom: 1px solid var(--corp-line-soft);
    padding: 11px 18px;
    display: flex; align-items: center; gap: 8px;
    flex-wrap: wrap;
}
.corp-effective__label {
    font-size: 11px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .5px;
    color: var(--corp-muted);
    margin-right: 4px;
}
.corp-effective__chip {
    background: var(--corp-card);
    border: 1px solid var(--corp-line);
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 11.5px;
    color: var(--corp-text);
    font-weight: 600;
    display: inline-flex; align-items: center; gap: 5px;
}
.corp-effective__chip--brand { background: var(--corp-brand-bg); border-color: var(--corp-brand-border); color: var(--corp-brand-deep); }
.corp-effective__diff {
    margin-left: auto;
    display: flex; gap: 6px; align-items: center;
}
.corp-diff-pill {
    font-size: 11px; font-weight: 700;
    padding: 3px 10px; border-radius: 999px;
    display: inline-flex; align-items: center; gap: 4px;
}
.corp-diff-pill--add    { background: #ecfdf5; color: #047857; }
.corp-diff-pill--remove { background: #fef2f2; color: #b91c1c; }
.corp-diff-pill--clean  { background: #f3f4f6; color: var(--corp-muted); }

/* ════════════════════════════════════════════════════════════════
   2-COLUMN LAYOUT (main + side panel)
   ════════════════════════════════════════════════════════════════ */
.corp-2col {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 300px;
    gap: 16px;
    align-items: start;
}
@media (max-width: 992px) {
    .corp-2col { grid-template-columns: minmax(0, 1fr); }
}

/* ════════════════════════════════════════════════════════════════
   VIEW/EDIT MODE TOGGLE
   ════════════════════════════════════════════════════════════════ */
.corp-modeswitch {
    display: inline-flex;
    background: #f3f4f6;
    border-radius: 9px;
    padding: 3px;
    gap: 2px;
}
.corp-modeswitch__btn {
    padding: 7px 14px;
    border: none;
    background: transparent;
    border-radius: 7px;
    font-size: 12px; font-weight: 600;
    color: var(--corp-muted);
    cursor: pointer;
    font-family: inherit;
    transition: background .14s var(--corp-ease), color .14s var(--corp-ease);
}
.corp-modeswitch__btn.is-active {
    background: #fff;
    color: var(--corp-text);
    box-shadow: 0 1px 3px rgba(15, 23, 42, .08);
}

/* ════════════════════════════════════════════════════════════════
   READ-ONLY MODE — disable inputs visually
   ════════════════════════════════════════════════════════════════ */
.corp-readonly .corp-input,
.corp-readonly .corp-select,
.corp-readonly input[type="checkbox"] {
    pointer-events: none;
    background: #f9fafb;
    color: var(--corp-muted);
}
.corp-readonly .corp-sticky-bar__actions [type="submit"] { display: none; }

/* ════════════════════════════════════════════════════════════════
   PREMIUM EXTENSIONS — optional opt-in classes for emphasis
   2026-05-20 token upgrade
   ════════════════════════════════════════════════════════════════ */

/* Featured KPI tile — gradient border + soft brand glow.
   Use on the single most-important tile per page (e.g. revenue
   today, pending fees). Add alongside .corp-kpi__tile:
     <div class="corp-kpi__tile corp-kpi__tile--glow"> */
.corp-kpi__tile--glow {
    position: relative;
    background:
        linear-gradient(var(--corp-card), var(--corp-card)) padding-box,
        var(--corp-brand-grad) border-box;
    border: 1px solid transparent;
    box-shadow: var(--corp-shadow-md), 0 0 0 4px rgba(var(--corp-brand-rgb), .06);
}
.corp-kpi__tile--glow::before { display: none; }
.corp-kpi__tile--glow .corp-kpi__value { color: var(--corp-brand-deep); }

/* Gradient text — pair with a heading to call attention.
   <h4>Welcome back, <span class="corp-grad-text">Name</span></h4> */
.corp-grad-text {
    background: var(--corp-brand-grad);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
    -webkit-text-fill-color: transparent;
    font-weight: 800;
}

/* Soft attention pulse — for "new" badges or important counts.
   <span class="corp-pill corp-pill--danger corp-pulse">3 new</span> */
@keyframes corp-pulse-kf {
    0%, 100% { box-shadow: 0 0 0 0 currentColor; }
    50%      { box-shadow: 0 0 0 5px transparent; }
}
.corp-pulse {
    position: relative;
}
.corp-pulse::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: inherit;
    box-shadow: 0 0 0 0 currentColor;
    opacity: 0.4;
    animation: corp-pulse-kf 2s ease-in-out infinite;
    pointer-events: none;
}

/* Icon chip — brand-tinted soft background for icons-next-to-labels.
   <span class="corp-icon-chip"><i class="fas fa-key"></i></span> */
.corp-icon-chip {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 7px;
    background: var(--corp-brand-bg);
    color: var(--corp-brand);
    font-size: 12px;
    flex-shrink: 0;
}

/* Entrance animation — corp-page contents fade up on first paint.
   Applied to direct children of .corp-page so KPI strip, cards,
   tables all stagger gently into view. Respects prefers-reduced-motion. */
@media (prefers-reduced-motion: no-preference) {
    .corp-page > * {
        animation: corp-enter 0.32s var(--corp-ease) both;
    }
    .corp-page > *:nth-child(2) { animation-delay: 30ms; }
    .corp-page > *:nth-child(3) { animation-delay: 60ms; }
    .corp-page > *:nth-child(4) { animation-delay: 90ms; }
    .corp-page > *:nth-child(5) { animation-delay: 120ms; }
    .corp-page > *:nth-child(n+6) { animation-delay: 150ms; }
}
@keyframes corp-enter {
    from { opacity: 0; transform: translateY(6px); }
    to   { opacity: 1; transform: translateY(0); }
}
</style>
@endpush
@endonce
