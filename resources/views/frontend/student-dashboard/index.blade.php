@extends('frontend.student-dashboard.layouts.master')

@section('dashboard-contents')

{{--
    Student dashboard — corporate redesign (2026-05).

    Same data shape, same routes, same functional contracts as the
    previous build (commit history shows P1-P6 of student-side work).
    Rebuilt visual layer on the platform-wide corp design tokens:
    Inter font, brand-aware single-color discipline, multi-layer
    shadows, hairline borders, tabular numerals on KPI values.

    Per-coach white-label: $brand is auto-injected by the BrandResolver
    view composer — every brand-tinted surface (resume gradient, KPI
    accents, action icons, links) retints automatically when a
    student visits on a coach's subdomain or custom domain.

    Server-side data contract preserved 1:1:
      * $totalEnrolledCourses, $totalQuizAttempts, $totalReviews,
        $totalOrders  — KPI grid
      * $resume, $resumePercent                                 — hero
      * $orders                                                — recent activity (now rendered)
      * $widgetAnnouncements                                    — announcement widget

    Partials kept as @include — they own their own styling and we
    don't touch their internals:
      * refer-earn-widget
      * profile-completion
--}}

@php
    // PHP-side greeting — uses server tz so it matches the platform's
    // configured timezone rather than the client's clock. Friendlier
    // than always saying "Hello".
    $h         = (int) date('G');
    $greeting  = $h < 12 ? __('Good morning') : ($h < 18 ? __('Good afternoon') : __('Good evening'));
    $firstName = trim(strtok(Auth::user()->name ?? '', ' ')) ?: __('there');
@endphp

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet"
      href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">

<style>
/* ───────────────────────────────────────────────────────────
   Student dashboard — corp tokens scoped to .student-dash
   ─────────────────────────────────────────────────────────── */
.student-dash {
    --sd-brand:        {{ $brand->primaryColor ?: '#10b981' }};
    --sd-brand-2:      {{ $brand->accentColor  ?: '#059669' }};
    --sd-brand-grad:   linear-gradient(135deg, var(--sd-brand) 0%, var(--sd-brand-2) 100%);
    --sd-brand-soft:   color-mix(in srgb, var(--sd-brand) 10%, #ffffff);

    --sd-text:         #0b1220;
    --sd-text-2:       #1f2937;
    --sd-muted:        #6b7280;
    --sd-subtle:       #9ca3af;
    --sd-line:         #e5e7eb;
    --sd-line-soft:    #f1f3f5;
    --sd-bg:           #fafbfc;
    --sd-card:         #ffffff;

    --sd-shadow-sm:
        0 1px 2px rgba(15, 23, 42, 0.04),
        0 2px 6px -2px rgba(15, 23, 42, 0.04);
    --sd-shadow-md:
        0 1px 2px rgba(15, 23, 42, 0.04),
        0 6px 18px -6px rgba(15, 23, 42, 0.08),
        0 12px 36px -12px rgba(15, 23, 42, 0.08);

    --sd-ease:         cubic-bezier(.22, 1, .36, 1);

    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    padding: 4px 8px 28px 0;       /* right gutter clears the floating top-right toolbar */
}
@media (max-width: 768px) {
    .student-dash { padding: 4px 0 20px; }
}
.student-dash *,
.student-dash *::before,
.student-dash *::after { box-sizing: border-box; }

.student-dash .sd-tabular {
    font-variant-numeric: tabular-nums;
    font-feature-settings: 'tnum';
}

/* ── Alerts ─────────────────────────────────────────────── */
.sd-alert {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 13px 16px;
    border-radius: 10px;
    font-size: 13.5px;
    line-height: 1.5;
    margin-bottom: 20px;
    border: 1px solid;
}
.sd-alert i { font-size: 14px; margin-top: 1px; flex-shrink: 0; }
.sd-alert--warning {
    background: #fffbeb;
    color: #92400e;
    border-color: #fde68a;
}
.sd-alert--warning i { color: #d97706; }
.sd-alert--danger  {
    background: #fef2f2;
    color: #991b1b;
    border-color: #fecaca;
}
.sd-alert--danger i { color: #dc2626; }
.sd-alert a { color: inherit; font-weight: 600; text-decoration: underline; }

/* ── Page header ────────────────────────────────────────── */
.sd-header {
    /* 2026-06-01 responsive audit: this is a semantic <header> element,
       and the frontend theme ships a GLOBAL `header { position:absolute }`
       rule for the public site's overlay nav. Without re-asserting flow
       positioning here, that global rule pinned this dashboard header to
       the initial containing block — it escaped its column, sat 12px past
       the viewport edge, and forced a phantom horizontal page scroll on
       mobile (root scrollWidth 387 vs 375) that no ancestor clip could
       contain. position:relative returns it to normal flow. */
    position: relative;
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 24px;
}
.sd-eyebrow {
    font-size: 11px;
    font-weight: 700;
    color: var(--sd-brand);
    text-transform: uppercase;
    letter-spacing: 0.12em;
    margin-bottom: 6px;
}
.sd-header h1 {
    font-size: 26px;
    font-weight: 800;
    color: var(--sd-text);
    margin: 0 0 4px;
    letter-spacing: -0.028em;
    line-height: 1.2;
}
.sd-header__sub {
    font-size: 13.5px;
    color: var(--sd-muted);
    margin: 0;
    line-height: 1.5;
}
.sd-date {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 7px 12px;
    border-radius: 8px;
    background: #fff;
    border: 1px solid var(--sd-line);
    font-size: 12.5px;
    font-weight: 600;
    color: var(--sd-text-2);
    box-shadow: var(--sd-shadow-sm);
}
.sd-date i { color: var(--sd-brand); font-size: 11px; }

/* ── Resume hero ────────────────────────────────────────── */
.sd-resume {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 24px 28px;
    border-radius: 16px;
    background: var(--sd-brand-grad);
    color: #fff;
    text-decoration: none;
    margin-bottom: 28px;
    position: relative;
    overflow: hidden;
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.18),
        0 8px 24px -6px color-mix(in srgb, var(--sd-brand) 30%, transparent);
    transition: transform .2s var(--sd-ease), box-shadow .2s var(--sd-ease);
}
.sd-resume:hover {
    color: #fff;
    text-decoration: none;
    transform: translateY(-1px);
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.22),
        0 12px 32px -6px color-mix(in srgb, var(--sd-brand) 35%, transparent);
}
.sd-resume::before {
    content: '';
    position: absolute;
    top: -100px; right: -100px;
    width: 300px; height: 300px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.08);
    pointer-events: none;
}
.sd-resume__icon {
    flex-shrink: 0;
    width: 56px; height: 56px;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.18);
    display: flex; align-items: center; justify-content: center;
    font-size: 20px;
    position: relative;
}
.sd-resume__body { flex: 1; min-width: 0; position: relative; }
.sd-resume__eyebrow {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    opacity: 0.85;
    margin-bottom: 4px;
}
.sd-resume__title {
    font-size: 17px;
    font-weight: 700;
    margin: 0 0 10px;
    line-height: 1.3;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.sd-resume__progress {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 12px;
}
.sd-resume__bar {
    flex: 1;
    height: 6px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 3px;
    overflow: hidden;
}
.sd-resume__bar-fill {
    height: 100%;
    background: #fff;
    border-radius: 3px;
    transition: width .6s var(--sd-ease);
}
.sd-resume__pct { font-weight: 600; white-space: nowrap; }
.sd-resume__cta {
    flex-shrink: 0;
    padding: 10px 18px;
    background: #fff;
    color: var(--sd-brand);
    border-radius: 9px;
    font-weight: 700;
    font-size: 13px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    position: relative;
    transition: background-color .15s var(--sd-ease);
}
.sd-resume__cta:hover { background: #f5f3ff; color: var(--sd-brand); text-decoration: none; }
.sd-resume__cta i { font-size: 11px; }
@media (max-width: 640px) {
    .sd-resume {
        flex-direction: column;
        align-items: flex-start;
        gap: 16px;
        padding: 20px;
    }
    .sd-resume__cta { width: 100%; justify-content: center; }
}

/* ── KPI grid ───────────────────────────────────────────── */
.sd-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
    margin-bottom: 32px;
}
@media (max-width: 1100px) { .sd-kpi-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 480px)  { .sd-kpi-grid { grid-template-columns: 1fr; } }

.sd-kpi {
    background: var(--sd-card);
    border: 1px solid var(--sd-line);
    border-radius: 14px;
    padding: 18px 18px 16px;
    text-decoration: none;
    color: inherit;
    box-shadow: var(--sd-shadow-sm);
    transition: transform .15s var(--sd-ease),
                box-shadow .15s var(--sd-ease),
                border-color .15s var(--sd-ease);
    display: block;
}
.sd-kpi:hover {
    transform: translateY(-2px);
    box-shadow: var(--sd-shadow-md);
    border-color: color-mix(in srgb, var(--sd-brand) 35%, var(--sd-line));
    text-decoration: none;
    color: inherit;
}
.sd-kpi__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
}
.sd-kpi__icon {
    width: 38px; height: 38px;
    border-radius: 10px;
    background: color-mix(in srgb, var(--sd-brand) 10%, #ffffff);
    color: var(--sd-brand);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    border: 1px solid color-mix(in srgb, var(--sd-brand) 16%, transparent);
}
.sd-kpi__chev {
    width: 20px; height: 20px;
    border-radius: 6px;
    background: var(--sd-line-soft);
    color: var(--sd-subtle);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 9px;
    transition: all .15s var(--sd-ease);
}
.sd-kpi:hover .sd-kpi__chev {
    background: var(--sd-brand);
    color: #fff;
}
.sd-kpi__value {
    font-size: 28px;
    font-weight: 800;
    color: var(--sd-text);
    line-height: 1;
    margin-bottom: 6px;
    letter-spacing: -0.025em;
}
.sd-kpi__label {
    font-size: 12px;
    color: var(--sd-muted);
    font-weight: 600;
    letter-spacing: 0.005em;
}

/* ── Section title ─────────────────────────────────────── */
.sd-section-head {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 14px;
    padding-top: 4px;
}
.sd-section-head h2 {
    font-size: 15px;
    font-weight: 700;
    color: var(--sd-text);
    margin: 0;
    letter-spacing: -0.015em;
}
.sd-section-head__hint {
    font-size: 12.5px;
    color: var(--sd-muted);
}

/* ── Quick action grid ────────────────────────────────── */
.sd-actions {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 32px;
}
@media (max-width: 900px) { .sd-actions { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 480px) { .sd-actions { grid-template-columns: 1fr; } }

.sd-action {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 14px;
    background: var(--sd-card);
    border: 1px solid var(--sd-line);
    border-radius: 12px;
    color: inherit;
    text-decoration: none;
    transition: all .15s var(--sd-ease);
    box-shadow: var(--sd-shadow-sm);
}
.sd-action:hover {
    border-color: color-mix(in srgb, var(--sd-brand) 35%, var(--sd-line));
    transform: translateY(-1px);
    box-shadow: var(--sd-shadow-md);
    text-decoration: none;
    color: inherit;
}
.sd-action__icon {
    width: 36px; height: 36px;
    border-radius: 9px;
    background: color-mix(in srgb, var(--sd-brand) 10%, #ffffff);
    color: var(--sd-brand);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    flex-shrink: 0;
    border: 1px solid color-mix(in srgb, var(--sd-brand) 16%, transparent);
}
.sd-action__body { min-width: 0; flex: 1; }
.sd-action__title {
    font-size: 13.5px;
    font-weight: 600;
    color: var(--sd-text);
    line-height: 1.35;
    margin: 0 0 2px;
}
.sd-action__hint {
    font-size: 11.5px;
    color: var(--sd-muted);
    line-height: 1.4;
}

/* ── Two-column section grid (announcements + orders) ── */
.sd-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}
@media (max-width: 900px) { .sd-grid-2 { grid-template-columns: 1fr; } }

/* ── Generic card ────────────────────────────────────── */
.sd-card {
    background: var(--sd-card);
    border: 1px solid var(--sd-line);
    border-radius: 14px;
    box-shadow: var(--sd-shadow-sm);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}
.sd-card__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 18px;
    border-bottom: 1px solid var(--sd-line-soft);
    gap: 10px;
}
.sd-card__head h3 {
    font-size: 14px;
    font-weight: 700;
    color: var(--sd-text);
    margin: 0;
    letter-spacing: -0.01em;
    display: flex;
    align-items: center;
    gap: 8px;
}
.sd-card__head h3 i {
    color: var(--sd-brand);
    font-size: 13px;
}
.sd-card__pill {
    display: inline-flex;
    align-items: center;
    padding: 2px 8px;
    border-radius: 999px;
    background: color-mix(in srgb, var(--sd-brand) 12%, #ffffff);
    color: var(--sd-brand);
    font-size: 10.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-left: 4px;
}
.sd-card__action {
    font-size: 12px;
    font-weight: 600;
    color: var(--sd-brand);
    text-decoration: none;
}
.sd-card__action:hover { text-decoration: underline; color: var(--sd-brand); }
.sd-card__body { padding: 8px 8px 12px; flex: 1; }

/* ── Announcement item ────────────────────────────────── */
.sd-ann {
    display: block;
    padding: 12px 14px;
    border-radius: 9px;
    text-decoration: none;
    color: inherit;
    transition: background-color .15s var(--sd-ease);
}
.sd-ann:hover {
    background: color-mix(in srgb, var(--sd-brand) 5%, #ffffff);
    text-decoration: none;
    color: inherit;
}
.sd-ann + .sd-ann { margin-top: 2px; }
.sd-ann__head {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 4px;
    flex-wrap: wrap;
}
.sd-ann__pin {
    color: #d97706;
    font-size: 10px;
}
.sd-ann__chip {
    display: inline-flex;
    align-items: center;
    padding: 1px 7px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.sd-ann__chip--all   { background: color-mix(in srgb, var(--sd-brand) 12%, #ffffff); color: var(--sd-brand); }
.sd-ann__chip--batch { background: #ecfdf5; color: #047857; }
.sd-ann__title {
    font-size: 13px;
    font-weight: 600;
    color: var(--sd-text);
    line-height: 1.35;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    min-width: 0;
    flex: 1;
}
.sd-ann__body {
    font-size: 12px;
    color: var(--sd-muted);
    line-height: 1.4;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    margin-bottom: 4px;
}
.sd-ann__meta {
    font-size: 10.5px;
    color: var(--sd-subtle);
    font-weight: 500;
}

/* Announcements widget shell (preserves dismissal JS) */
.sd-ann-widget__close {
    background: transparent;
    border: 0;
    color: var(--sd-subtle);
    font-size: 12px;
    padding: 4px 8px;
    border-radius: 6px;
    cursor: pointer;
    transition: all .15s var(--sd-ease);
}
.sd-ann-widget__close:hover { background: var(--sd-line-soft); color: var(--sd-text); }

/* ── Order row ────────────────────────────────────────── */
.sd-order {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    border-radius: 9px;
    text-decoration: none;
    color: inherit;
    transition: background-color .15s var(--sd-ease);
}
.sd-order:hover {
    background: color-mix(in srgb, var(--sd-brand) 5%, #ffffff);
    text-decoration: none;
    color: inherit;
}
.sd-order + .sd-order { margin-top: 2px; }
.sd-order__icon {
    width: 34px; height: 34px;
    border-radius: 8px;
    background: var(--sd-line-soft);
    color: var(--sd-text-2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    flex-shrink: 0;
}
.sd-order__body { flex: 1; min-width: 0; }
.sd-order__top {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 2px;
}
.sd-order__id {
    font-size: 12.5px;
    font-weight: 700;
    color: var(--sd-text);
    font-variant-numeric: tabular-nums;
}
.sd-order__meta {
    font-size: 11px;
    color: var(--sd-muted);
}
.sd-order__amount {
    font-size: 13px;
    font-weight: 700;
    color: var(--sd-text);
    font-variant-numeric: tabular-nums;
    margin-left: auto;
    text-align: right;
    flex-shrink: 0;
}
.sd-order__status {
    display: inline-flex;
    padding: 1px 7px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.sd-order__status--paid     { background: #ecfdf5; color: #047857; }
.sd-order__status--pending  { background: #fffbeb; color: #92400e; }
.sd-order__status--failed   { background: #fef2f2; color: #991b1b; }

/* ── Empty state ─────────────────────────────────────── */
.sd-empty {
    text-align: center;
    padding: 32px 18px;
    color: var(--sd-muted);
    font-size: 13px;
}
.sd-empty__icon {
    width: 44px; height: 44px;
    margin: 0 auto 12px;
    border-radius: 11px;
    background: var(--sd-line-soft);
    color: var(--sd-subtle);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
}
.sd-empty__title {
    font-size: 13.5px;
    font-weight: 600;
    color: var(--sd-text-2);
    margin-bottom: 4px;
}
.sd-empty a {
    color: var(--sd-brand);
    font-weight: 600;
    text-decoration: none;
}
.sd-empty a:hover { text-decoration: underline; }
</style>

<style>
/* 2026-07-10 (New Changes for UI #4) — dark mode for this page's bespoke components. */
html[data-theme="dark"] .student-dash {
    --sd-text:      #e2e8f0;
    --sd-text-2:    #cbd5e1;
    --sd-muted:     #94a3b8;
    --sd-subtle:    #64748b;
    --sd-line:      #2a3a55;
    --sd-line-soft: #22304a;
    --sd-bg:        #17233a;
    --sd-card:      #1e293b;
    --sd-shadow-sm: none;
    --sd-shadow-md: none;
}
html[data-theme="dark"] .sd-date { background:#1e293b; }
</style>

<div class="student-dash">

    {{-- ── Status alerts (instructor request pending / rejected) ── --}}
    @if (instructorStatus() == 'pending')
        <div class="sd-alert sd-alert--warning" role="alert">
            <i class="fas fa-clock"></i>
            <div>
                <strong>{{ __('Instructor request pending.') }}</strong>
                {{ __('We received your request. Please wait for admin approval.') }}
            </div>
        </div>
    @elseif (instructorStatus() == 'rejected')
        <div class="sd-alert sd-alert--danger" role="alert">
            <i class="fas fa-exclamation-circle"></i>
            <div>
                {{ __('Your instructor request was rejected.') }}
                <a href="{{ route('become-instructor') }}">{{ __('Resubmit here') }}</a>
            </div>
        </div>
    @endif

    {{-- ── Refer & Earn + Profile completion partials ── --}}
    @include('frontend.layouts.partials.refer-earn-widget')
    @include('frontend.layouts.partials.profile-completion')

    {{-- ── Page header ── --}}
    <header class="sd-header">
        <div class="sd-header__l">
            <div class="sd-eyebrow">{{ __('Overview') }}</div>
            <h1>{{ $greeting }}, {{ $firstName }}</h1>
            <p class="sd-header__sub">
                @if($totalEnrolledCourses > 0)
                    {{ __('You have') }}
                    <strong style="color: var(--sd-text-2); font-weight:600;">{{ $totalEnrolledCourses }} {{ trans_choice('active course|active courses', $totalEnrolledCourses) }}</strong>.
                    {{ __('Pick up where you left off.') }}
                @else
                    {{ __("You haven't enrolled in any courses yet — explore the catalog to get started.") }}
                @endif
            </p>
        </div>
        <div class="sd-date" id="sd-today-date">
            <i class="fas fa-calendar-alt"></i>
            <span>—</span>
        </div>
    </header>

    {{-- ── Resume hero (only when there's a course in progress) ── --}}
    @if (!empty($resume) && $resume->course)
        <a href="{{ route('student.learning.index', ['slug' => $resume->course->slug]) }}" class="sd-resume">
            <div class="sd-resume__icon"><i class="fas fa-play"></i></div>
            <div class="sd-resume__body">
                <div class="sd-resume__eyebrow">{{ __('Continue learning') }}</div>
                <div class="sd-resume__title">{{ $resume->course->title }}</div>
                <div class="sd-resume__progress">
                    <div class="sd-resume__bar">
                        <div class="sd-resume__bar-fill" style="width: {{ $resumePercent }}%"></div>
                    </div>
                    <span class="sd-resume__pct sd-tabular">{{ $resumePercent }}%</span>
                </div>
            </div>
            <span class="sd-resume__cta">
                {{ __('Resume') }} <i class="fas fa-arrow-right"></i>
            </span>
        </a>
    @endif

    {{-- ── KPI grid (4 cards) ── --}}
    <div class="sd-kpi-grid">
        <a class="sd-kpi" href="{{ route('student.enrolled-courses') }}">
            <div class="sd-kpi__head">
                <span class="sd-kpi__icon"><i class="fas fa-graduation-cap"></i></span>
                <span class="sd-kpi__chev"><i class="fas fa-arrow-right"></i></span>
            </div>
            <div class="sd-kpi__value sd-tabular">{{ $totalEnrolledCourses }}</div>
            <div class="sd-kpi__label">{{ __('Enrolled courses') }}</div>
        </a>

        <a class="sd-kpi" href="{{ route('student.quiz-attempts') }}">
            <div class="sd-kpi__head">
                <span class="sd-kpi__icon"><i class="fas fa-clipboard-check"></i></span>
                <span class="sd-kpi__chev"><i class="fas fa-arrow-right"></i></span>
            </div>
            <div class="sd-kpi__value sd-tabular">{{ $totalQuizAttempts }}</div>
            <div class="sd-kpi__label">{{ __('Quiz attempts') }}</div>
        </a>

        <a class="sd-kpi" href="{{ route('student.reviews.index') }}">
            <div class="sd-kpi__head">
                <span class="sd-kpi__icon"><i class="fas fa-star"></i></span>
                <span class="sd-kpi__chev"><i class="fas fa-arrow-right"></i></span>
            </div>
            <div class="sd-kpi__value sd-tabular">{{ $totalReviews }}</div>
            <div class="sd-kpi__label">{{ __('Reviews written') }}</div>
        </a>

        <a class="sd-kpi" href="{{ route('student.orders.index') }}">
            <div class="sd-kpi__head">
                <span class="sd-kpi__icon"><i class="fas fa-receipt"></i></span>
                <span class="sd-kpi__chev"><i class="fas fa-arrow-right"></i></span>
            </div>
            <div class="sd-kpi__value sd-tabular">{{ $totalOrders }}</div>
            <div class="sd-kpi__label">{{ __('Total orders') }}</div>
        </a>
    </div>

    {{-- ── Quick actions ── --}}
    <div class="sd-section-head">
        <h2>{{ __('Quick actions') }}</h2>
    </div>
    <div class="sd-actions">
        <a class="sd-action" href="{{ route('student.enrolled-courses') }}">
            <span class="sd-action__icon"><i class="fas fa-play-circle"></i></span>
            <span class="sd-action__body">
                <span class="sd-action__title">{{ __('Continue learning') }}</span>
                <span class="sd-action__hint">{{ __('Jump back into your courses') }}</span>
            </span>
        </a>
        <a class="sd-action" href="{{ route('student.wishlist') }}">
            <span class="sd-action__icon"><i class="fas fa-heart"></i></span>
            <span class="sd-action__body">
                <span class="sd-action__title">{{ __('Wishlist') }}</span>
                <span class="sd-action__hint">{{ __('Saved for later') }}</span>
            </span>
        </a>
        <a class="sd-action" href="{{ route('student.reviews.index') }}">
            <span class="sd-action__icon"><i class="fas fa-comment-dots"></i></span>
            <span class="sd-action__body">
                <span class="sd-action__title">{{ __('My reviews') }}</span>
                <span class="sd-action__hint">{{ __('Feedback you have shared') }}</span>
            </span>
        </a>
        <a class="sd-action" href="{{ route('student.setting.index') }}">
            <span class="sd-action__icon"><i class="fas fa-user-cog"></i></span>
            <span class="sd-action__body">
                <span class="sd-action__title">{{ __('Profile & settings') }}</span>
                <span class="sd-action__hint">{{ __('Account, password, address') }}</span>
            </span>
        </a>
    </div>

    {{-- ── Two-column: Announcements + Recent orders ── --}}
    <div class="sd-grid-2">

        {{-- Announcements widget (preserves dismissal JS) --}}
        @php
            $hasAnn = !empty($widgetAnnouncements) && $widgetAnnouncements->count() > 0;
            $widgetFp = $hasAnn ? $widgetAnnouncements->pluck('id')->sort()->values()->implode(',') : '';
            $widgetUserId = userAuth()?->id ?? 0;
        @endphp
        <div class="sd-card" id="announcements-widget"
             data-ann-fingerprint="{{ $widgetFp }}"
             data-ann-user-id="{{ $widgetUserId }}">
            <div class="sd-card__head">
                <h3>
                    <i class="fas fa-bullhorn"></i>
                    {{ __('Announcements') }}
                    @if ($hasAnn)
                        <span class="sd-card__pill">{{ $widgetAnnouncements->count() }} {{ __('new') }}</span>
                    @endif
                </h3>
                @if ($hasAnn)
                    <div style="display:flex; align-items:center; gap:6px;">
                        <a href="{{ route('student.announcements.index') }}" class="sd-card__action">
                            {{ __('See all') }}
                        </a>
                        <button type="button" id="announcements-widget-close"
                                class="sd-ann-widget__close"
                                aria-label="{{ __('Hide announcements widget') }}"
                                title="{{ __('Hide for now (reappears when new announcements arrive)') }}">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                @endif
            </div>
            <div class="sd-card__body">
                @if ($hasAnn)
                    @foreach ($widgetAnnouncements as $a)
                        <a href="{{ route('student.announcements.show', $a->id) }}" class="sd-ann">
                            <div class="sd-ann__head">
                                @if ($a->is_pinned)
                                    <i class="fas fa-thumbtack sd-ann__pin"></i>
                                @endif
                                @if ($a->audience_type === 'all_students')
                                    <span class="sd-ann__chip sd-ann__chip--all">{{ __('All') }}</span>
                                @else
                                    <span class="sd-ann__chip sd-ann__chip--batch">{{ __('Batch') }}</span>
                                @endif
                                <span class="sd-ann__title">{{ \Illuminate\Support\Str::limit($a->title, 60) }}</span>
                            </div>
                            <div class="sd-ann__body">
                                {{ \Illuminate\Support\Str::limit(strip_tags($a->announcement), 90) }}
                            </div>
                            <div class="sd-ann__meta">
                                {{ $a->instructor?->name ?? __('System') }} ·
                                {{ optional($a->sent_at)->diffForHumans() }}
                            </div>
                        </a>
                    @endforeach
                @else
                    <div class="sd-empty">
                        <div class="sd-empty__icon"><i class="fas fa-bell-slash"></i></div>
                        <div class="sd-empty__title">{{ __('All caught up') }}</div>
                        <div>{{ __('No new announcements right now.') }}</div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Recent orders (uses the $orders variable that was previously unused) --}}
        <div class="sd-card">
            <div class="sd-card__head">
                <h3>
                    <i class="fas fa-receipt"></i>
                    {{ __('Recent orders') }}
                </h3>
                @if ($orders->count() > 0)
                    <a href="{{ route('student.orders.index') }}" class="sd-card__action">
                        {{ __('View all') }}
                    </a>
                @endif
            </div>
            <div class="sd-card__body">
                @forelse ($orders->take(5) as $order)
                    @php
                        $status   = strtolower((string) ($order->payment_status ?? $order->status ?? ''));
                        $isPaid   = in_array($status, ['paid', 'completed', 'success', 'successful']);
                        $isFail   = in_array($status, ['failed', 'cancelled', 'canceled', 'rejected']);
                        $statusKey = $isPaid ? 'paid' : ($isFail ? 'failed' : 'pending');
                        $statusLabel = $isPaid ? __('Paid') : ($isFail ? __('Failed') : __('Pending'));
                        $amount = number_format((float) ($order->payable_amount ?? 0), 2);
                        $currency = $order->payable_currency ?? config('app.currency_symbol', '$');
                    @endphp
                    <a href="{{ route('student.orders.index') }}" class="sd-order">
                        <span class="sd-order__icon"><i class="fas fa-shopping-bag"></i></span>
                        <span class="sd-order__body">
                            <span class="sd-order__top">
                                <span class="sd-order__id">#{{ $order->invoice_id ?? $order->id }}</span>
                                <span class="sd-order__status sd-order__status--{{ $statusKey }}">{{ $statusLabel }}</span>
                            </span>
                            <span class="sd-order__meta">
                                {{ optional($order->created_at)->diffForHumans() ?? '—' }}
                            </span>
                        </span>
                        <span class="sd-order__amount">{{ $currency }}{{ $amount }}</span>
                    </a>
                @empty
                    <div class="sd-empty">
                        <div class="sd-empty__icon"><i class="fas fa-receipt"></i></div>
                        <div class="sd-empty__title">{{ __('No orders yet') }}</div>
                        <div>
                            <a href="{{ url('/courses') }}">{{ __('Browse courses') }}</a>
                            {{ __('to make your first enrollment.') }}
                        </div>
                    </div>
                @endforelse
            </div>
        </div>

    </div>

</div>

<script>
(function () {
    // Localised today's date in the header pill.
    var dateEl = document.querySelector('#sd-today-date span');
    if (dateEl) {
        try {
            var d = new Date();
            dateEl.textContent = d.toLocaleDateString(undefined, {
                weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
            });
        } catch (e) { /* keep "—" if Intl unavailable */ }
    }

    // Announcements widget — dismissal JS preserved from previous build.
    // Fingerprint-keyed: dismissal is honoured only while the set of
    // unread announcement IDs is unchanged. New announcement arrives,
    // fingerprint changes, widget reappears.
    var widget = document.getElementById('announcements-widget');
    if (!widget) return;

    var fp     = widget.dataset.annFingerprint || '';
    var userId = widget.dataset.annUserId || '0';
    var KEY    = 'mbsguru_announcement_dismissed_' + userId;

    if (fp) {
        try {
            var stored = localStorage.getItem(KEY);
            if (stored && stored === fp) {
                widget.style.display = 'none';
            }
        } catch (e) { /* localStorage blocked — ignore */ }
    }

    var closeBtn = document.getElementById('announcements-widget-close');
    if (closeBtn) {
        closeBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            try { localStorage.setItem(KEY, fp); } catch (e) { /* ignore */ }
            widget.style.transition = 'opacity .15s, max-height .2s, margin .2s, padding .2s, border .2s';
            widget.style.opacity = '0';
            widget.style.maxHeight = widget.offsetHeight + 'px';
            setTimeout(function () {
                widget.style.maxHeight = '0';
                widget.style.padding   = '0';
                widget.style.margin    = '0';
                widget.style.border    = '0';
                widget.style.overflow  = 'hidden';
            }, 10);
        });
    }
})();
</script>

@endsection
