{{-- Coach Marketing Website — premium full-viewport editor --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $page->title }} — {{ __('Website Editor') }}</title>

    {{-- Prevent browser caching the editor shell — iframe src and other
         URLs change as routes evolve; stale cached HTML would point to
         removed URLs and produce Apache 404 in the iframe. --}}
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap">
    <link rel="stylesheet" href="{{ asset('frontend/css/fontawesome-all.min.css') }}">

    <style>
/* ──────────────────────────────────────────────────────────────────
   2026-06-24 — Font Awesome 6 → 5 shim. The platform ships Font Awesome 5
   (fontawesome-all.min.css) but the builder UI + the section registry author
   icons with FA6 classes (fa-solid / fa-regular / fa-brands). FA5 doesn't
   style those family classes, so every section icon rendered as an empty box.
   Map the FA6 families to the FA5 font-family, and alias the handful of
   FA6-only icon NAMES (no glyph in FA5) to their FA5 equivalents.
   ────────────────────────────────────────────────────────────────── */
.fa-solid, .fa-regular { font-family: 'Font Awesome 5 Free' !important; }
.fa-solid  { font-weight: 900; }
.fa-regular { font-weight: 400; }
.fa-brands { font-family: 'Font Awesome 5 Brands' !important; font-weight: 400; }
.fa-grip::before                       { content: "\f58d"; } /* → grip-horizontal */
.fa-circle-play::before                { content: "\f144"; } /* → play-circle */
.fa-circle-nodes::before               { content: "\f542"; } /* → project-diagram */
.fa-circle-question::before            { content: "\f059"; } /* → question-circle */
.fa-location-dot::before               { content: "\f3c5"; } /* → map-marker-alt */
.fa-file-lines::before                 { content: "\f15c"; } /* → file-alt */
.fa-mobile-screen::before              { content: "\f3cd"; } /* → mobile-alt */
.fa-arrow-up-right-from-square::before { content: "\f35d"; } /* → external-link-alt */
.fa-circle-check::before               { content: "\f058"; } /* → check-circle */

/* ──────────────────────────────────────────────────────────────────
   Design tokens — premium corporate SaaS palette
   ────────────────────────────────────────────────────────────────── */
:root {
    /* Brand */
    --cs-brand-50:  #ecfdf5;
    --cs-brand-100: #E0E7FF;
    --cs-brand-200: #a7f3d0;
    --cs-brand-500: #10b981;
    --cs-brand-600: #4F46E5;
    --cs-brand-700: #065f46;
    --cs-brand-900: #312E81;
    --cs-gradient-brand: linear-gradient(135deg, #10b981 0%, #059669 50%, #EC4899 100%);
    --cs-gradient-brand-soft: linear-gradient(135deg, #ecfdf5 0%, #FAE8FF 100%);
    --cs-gradient-surface: linear-gradient(180deg, #FFFFFF 0%, #FAFBFF 100%);

    /* Neutrals (slate scale) */
    --cs-slate-25:  #FCFCFD;
    --cs-slate-50:  #F8FAFC;
    --cs-slate-100: #F1F5F9;
    --cs-slate-200: #E2E8F0;
    --cs-slate-300: #CBD5E1;
    --cs-slate-400: #94A3B8;
    --cs-slate-500: #64748B;
    --cs-slate-600: #475569;
    --cs-slate-700: #334155;
    --cs-slate-800: #1E293B;
    --cs-slate-900: #0F172A;

    /* Status */
    --cs-success: #10B981;
    --cs-success-soft: #D1FAE5;
    --cs-warn: #F59E0B;
    --cs-warn-soft: #FEF3C7;
    --cs-danger: #EF4444;
    --cs-danger-soft: #FEE2E2;

    /* Category accent colors (premium) */
    --cs-cat-hero:       #10b981;
    --cs-cat-content:    #0EA5E9;
    --cs-cat-trust:      #F59E0B;
    --cs-cat-media:      #EC4899;
    --cs-cat-conversion: #10B981;
    --cs-cat-footer:     #64748B;

    /* Spacing scale */
    --cs-bar-h: 64px;
    --cs-rail-w: 300px;
    --cs-panel-w: 400px;

    /* Shadows */
    --cs-sh-xs: 0 1px 2px rgba(15, 23, 42, 0.04);
    --cs-sh-sm: 0 2px 8px rgba(15, 23, 42, 0.06);
    --cs-sh-md: 0 6px 24px rgba(15, 23, 42, 0.08);
    --cs-sh-lg: 0 20px 50px rgba(15, 23, 42, 0.12);
    --cs-sh-focus: 0 0 0 4px rgba(16, 185, 129, 0.18);

    /* Motion */
    --cs-ease: cubic-bezier(0.4, 0, 0.2, 1);
}

/* ──────────────────────────────────────────────────────────────────
   Reset + base
   ────────────────────────────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; }
html, body { height: 100%; margin: 0; }
body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    color: var(--cs-slate-900);
    background: var(--cs-slate-50);
    line-height: 1.5;
    overflow: hidden;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    letter-spacing: -0.011em;
}
button, input, textarea, select { font-family: inherit; font-size: inherit; color: inherit; }
::-webkit-scrollbar { width: 10px; height: 10px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--cs-slate-200); border-radius: 10px; border: 2px solid var(--cs-slate-50); }
::-webkit-scrollbar-thumb:hover { background: var(--cs-slate-300); }

/* ──────────────────────────────────────────────────────────────────
   Layout shell
   ────────────────────────────────────────────────────────────────── */
.cse { display: flex; flex-direction: column; height: 100vh; background: var(--cs-slate-50); }

/* Top bar — glass-morphism premium look */
.cse__bar {
    height: var(--cs-bar-h);
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: saturate(180%) blur(12px);
    -webkit-backdrop-filter: saturate(180%) blur(12px);
    border-bottom: 1px solid var(--cs-slate-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0 20px;
    gap: 16px;
    flex-shrink: 0;
    z-index: 50;
    box-shadow: var(--cs-sh-xs);
}
.cse__bar-left, .cse__bar-right { display: flex; align-items: center; gap: 12px; }

/* Back button — refined */
.cse__back {
    width: 38px; height: 38px;
    border-radius: 10px;
    background: var(--cs-slate-100);
    color: var(--cs-slate-600);
    font-size: 14px;
    display: inline-flex; align-items: center; justify-content: center;
    text-decoration: none;
    transition: all 0.15s var(--cs-ease);
    border: 1px solid transparent;
}
.cse__back:hover { background: var(--cs-brand-50); color: var(--cs-brand-600); border-color: var(--cs-brand-100); transform: translateX(-1px); }

/* Title input — invisible until hover */
.cse__title-wrap { display: flex; align-items: center; gap: 8px; }
.cse__title-icon { color: var(--cs-slate-400); font-size: 12px; }
.cse__title {
    border: 1px solid transparent;
    background: transparent;
    font-size: 15px;
    font-weight: 600;
    min-width: 240px;
    max-width: 380px;
    padding: 7px 12px;
    border-radius: 8px;
    color: var(--cs-slate-900);
    font-family: 'Plus Jakarta Sans', 'Inter', sans-serif;
    letter-spacing: -0.015em;
    transition: all 0.15s var(--cs-ease);
}
.cse__title:hover { background: var(--cs-slate-100); }
.cse__title:focus { background: #fff; outline: 0; border-color: var(--cs-brand-500); box-shadow: var(--cs-sh-focus); }

/* Status pill — premium */
.cse__pub {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 12px; font-weight: 600;
    padding: 5px 10px;
    border-radius: 999px;
    background: var(--cs-slate-100);
    color: var(--cs-slate-600);
    letter-spacing: -0.005em;
}
.cse__pub--live { background: var(--cs-success-soft); color: #047857; }
.cse__pub-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; box-shadow: 0 0 0 3px rgba(16,185,129,.15); }

/* Device toggle — segmented control */
.cse__devices {
    display: flex; gap: 2px;
    background: var(--cs-slate-100);
    padding: 3px;
    border-radius: 10px;
    border: 1px solid var(--cs-slate-200);
}
.cse__dev {
    border: 0; background: transparent;
    height: 30px; padding: 0 10px;
    border-radius: 7px;
    cursor: pointer;
    color: var(--cs-slate-500);
    transition: all 0.15s var(--cs-ease);
    display: inline-flex; align-items: center; justify-content: center; gap: 6px;
    font-size: 12px; font-weight: 600;
    font-family: inherit;
}
.cse__dev:hover { color: var(--cs-slate-800); }
.cse__dev--active { background: #fff; color: var(--cs-brand-600); box-shadow: var(--cs-sh-xs); }
.cse__dev-label { display: inline-block; }
@media (max-width: 1200px) {
    .cse__dev-label { display: none; }   /* compact when space is tight */
}

/* Buttons */
.cse__btn {
    border: 0; padding: 9px 16px;
    border-radius: 10px;
    font-weight: 600; font-size: 13px;
    cursor: pointer;
    display: inline-flex; gap: 7px; align-items: center;
    text-decoration: none;
    transition: all 0.15s var(--cs-ease);
    letter-spacing: -0.005em;
    white-space: nowrap;
}
.cse__btn--ghost { background: var(--cs-slate-100); color: var(--cs-slate-700); border: 1px solid var(--cs-slate-200); }
.cse__btn--ghost:hover { background: #fff; border-color: var(--cs-slate-300); color: var(--cs-slate-900); transform: translateY(-1px); box-shadow: var(--cs-sh-sm); }
.cse__btn--primary { background: var(--cs-gradient-brand); color: #fff; box-shadow: 0 6px 16px rgba(16, 185, 129, 0.28); }
.cse__btn--primary:hover { transform: translateY(-1px); box-shadow: 0 10px 24px rgba(16, 185, 129, 0.34); filter: brightness(1.05); }
.cse__btn--sm { padding: 7px 12px; font-size: 12px; }

/* Save state indicator */
.cse__save {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 12px; color: var(--cs-slate-500);
    min-width: 130px; justify-content: flex-end;
    font-weight: 500;
}
.cse__save-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--cs-slate-400); }
.cse__save--saving { color: var(--cs-warn); }
.cse__save--saving .cse__save-dot { background: var(--cs-warn); animation: cs-pulse 1s infinite; }
.cse__save--saved { color: var(--cs-success); }
.cse__save--saved .cse__save-dot { background: var(--cs-success); }
.cse__save--error { color: var(--cs-danger); }
.cse__save--error .cse__save-dot { background: var(--cs-danger); }
@keyframes cs-pulse { 0%,100%{opacity:1} 50%{opacity:.5} }

/* ──────────────────────────────────────────────────────────────────
   Main 3-column layout
   ────────────────────────────────────────────────────────────────── */
.cse__layout { flex: 1; display: grid; grid-template-columns: var(--cs-rail-w) 1fr var(--cs-panel-w); min-height: 0; }
.cse__layout[data-panel-state="closed"] { grid-template-columns: var(--cs-rail-w) 1fr; }

/* ── Left rail ─────────────────────────────────────────────────── */
.cse__rail { background: #fff; border-right: 1px solid var(--cs-slate-200); overflow-y: auto; min-height: 0; }
.cse__rail-head {
    display: flex; justify-content: space-between; align-items: center;
    padding: 18px 18px;
    border-bottom: 1px solid var(--cs-slate-100);
    position: sticky; top: 0; background: #fff; z-index: 1;
}
.cse__rail-head h3 {
    margin: 0; font-size: 11px; font-weight: 700;
    color: var(--cs-slate-500); text-transform: uppercase; letter-spacing: 1.2px;
}
.cse__rail-head-count {
    display: inline-block; font-size: 11px; color: var(--cs-slate-400); font-weight: 500;
    margin-left: 4px;
}
.cse__rail-add {
    width: 32px; height: 32px;
    border: 0; border-radius: 8px;
    background: var(--cs-gradient-brand);
    color: #fff;
    cursor: pointer;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 14px;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.30);
    transition: all 0.15s var(--cs-ease);
}
.cse__rail-add:hover { transform: scale(1.06); box-shadow: 0 6px 18px rgba(16, 185, 129, 0.40); }
.cse__rail-add:active { transform: scale(0.98); }

.cse__sections { padding: 10px; display: flex; flex-direction: column; gap: 6px; }
.cse__sec {
    /* 2026-05-29 Doc-C-PageBuilder: position relative so the actions
       can pin to the right edge instead of pushing the name off. */
    position: relative;
    display: flex; align-items: center; gap: 10px;
    padding: 10px 12px;
    /* Reserve right-edge space for the hover-actions overlay so the
       section name doesn't run into them on wide names. */
    padding-right: 14px;
    background: #fff;
    border: 1.5px solid var(--cs-slate-200);
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.18s var(--cs-ease);
    user-select: none;
}
.cse__sec:hover { border-color: var(--cs-brand-200); background: var(--cs-slate-25); box-shadow: var(--cs-sh-sm); transform: translateY(-1px); }
.cse__sec--active {
    border-color: var(--cs-brand-500);
    background: var(--cs-brand-50);
    box-shadow: var(--cs-sh-focus);
}
.cse__sec--active .cse__sec-icon { background: var(--cs-gradient-brand); color: #fff; }
.cse__sec-handle { color: var(--cs-slate-300); cursor: grab; font-size: 12px; }
.cse__sec-handle:active { cursor: grabbing; }
.cse__sec-main { flex: 1; display: flex; align-items: center; gap: 11px; min-width: 0; }
.cse__sec-icon {
    width: 36px; height: 36px;
    border-radius: 10px;
    background: var(--cs-brand-50);
    color: var(--cs-brand-600);
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; flex-shrink: 0;
    transition: all 0.18s var(--cs-ease);
}
.cse__sec-name {
    font-size: 13.5px; font-weight: 600;
    color: var(--cs-slate-800);
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    letter-spacing: -0.005em;
}
.cse__sec-actions {
    /* 2026-05-29 Doc-C-PageBuilder: absolutely positioned so the
       buttons don't steal flex space from .cse__sec-name. Hidden
       until hover; on hover, slide-in with backdrop so the name
       underneath isn't unreadable. */
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    display: flex;
    gap: 4px;
    opacity: 0;
    pointer-events: none;
    background: rgba(255, 255, 255, 0.96);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    padding: 4px 4px 4px 12px;
    border-radius: 999px;
    transition: opacity 0.15s;
}
.cse__sec:hover .cse__sec-actions, .cse__sec--active .cse__sec-actions {
    opacity: 1;
    pointer-events: auto;
}
.cse__sec-action {
    border: 0; background: var(--cs-slate-50);
    color: var(--cs-slate-500);
    cursor: pointer;
    width: 30px; height: 30px;
    border-radius: 8px;
    font-size: 11.5px;
    display: inline-flex; align-items: center; justify-content: center;
    transition: all 0.15s var(--cs-ease);
}
.cse__sec-action:hover { background: var(--cs-brand-100); color: var(--cs-brand-700); transform: translateY(-1px); }
.cse__sec-action--danger:hover { background: var(--cs-danger-soft); color: var(--cs-danger); }

.cse__rail-empty { padding: 60px 24px 40px; text-align: center; }
.cse__rail-empty-ic {
    width: 56px; height: 56px;
    border-radius: 16px;
    background: var(--cs-gradient-brand-soft);
    color: var(--cs-brand-600);
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 22px;
    margin-bottom: 14px;
}
.cse__rail-empty p { font-size: 13px; color: var(--cs-slate-500); margin: 0 0 14px; line-height: 1.6; }

/* CSS-based tooltips — works on any element with [data-tip] attribute.
   Replaces browser-default title="" with a premium dark pill. */
[data-tip] { position: relative; }
[data-tip]::after {
    content: attr(data-tip);
    position: absolute;
    bottom: calc(100% + 8px);
    left: 50%;
    transform: translateX(-50%) translateY(4px);
    background: var(--cs-slate-900);
    color: #fff;
    padding: 5px 10px;
    border-radius: 7px;
    font-size: 11px;
    font-weight: 500;
    white-space: nowrap;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.18s, transform 0.18s;
    z-index: 1000;
    box-shadow: 0 6px 16px rgba(15, 23, 42, 0.18);
    letter-spacing: 0.01em;
}
[data-tip]::before {
    content: '';
    position: absolute;
    bottom: calc(100% + 3px);
    left: 50%;
    transform: translateX(-50%) translateY(4px);
    border: 5px solid transparent;
    border-top-color: var(--cs-slate-900);
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.18s, transform 0.18s;
    z-index: 1000;
}
[data-tip]:hover::after, [data-tip]:hover::before {
    opacity: 1;
    transform: translateX(-50%) translateY(0);
}
[data-tip-position="bottom"]::after {
    bottom: auto; top: calc(100% + 8px);
}
[data-tip-position="bottom"]::before {
    bottom: auto; top: calc(100% + 3px);
    border-top-color: transparent; border-bottom-color: var(--cs-slate-900);
}
[data-tip-position="left"]::after {
    bottom: auto; top: 50%; right: calc(100% + 10px); left: auto;
    transform: translateY(-50%) translateX(4px);
}
[data-tip-position="left"]::before {
    bottom: auto; top: 50%; right: calc(100% + 5px); left: auto;
    transform: translateY(-50%) translateX(4px);
    border-top-color: transparent; border-left-color: var(--cs-slate-900);
}
[data-tip-position="left"]:hover::after, [data-tip-position="left"]:hover::before {
    transform: translateY(-50%) translateX(0);
}

/* Tabs */
.cse__tabs { display: flex; gap: 2px; padding: 8px; background: var(--cs-slate-50); border-bottom: 1px solid var(--cs-slate-200); position: sticky; top: 0; z-index: 2; }
.cse__tab {
    flex: 1; border: 0; background: transparent;
    padding: 9px 6px;
    font-size: 11.5px; font-weight: 600;
    color: var(--cs-slate-500);
    cursor: pointer; border-radius: 8px;
    display: inline-flex; align-items: center; justify-content: center; gap: 5px;
    transition: all 0.15s var(--cs-ease);
}
.cse__tab:hover { color: var(--cs-slate-800); background: var(--cs-slate-100); }
.cse__tab--active { background: #fff; color: var(--cs-brand-600); box-shadow: var(--cs-sh-xs); }
.cse__tab i { font-size: 11px; }
.cse__tab-content { display: block; }
.cse__tab-content[hidden] { display: none; }

/* Section hidden state */
.cse__sec--hidden { opacity: 0.5; background: var(--cs-slate-50); }
.cse__sec--hidden .cse__sec-name::after { content: ' (hidden)'; font-weight: 400; color: var(--cs-slate-400); font-size: 11px; }

/* Pages tab list */
.cse__pages-list { padding: 10px; display: flex; flex-direction: column; gap: 6px; }
.cse__page-item {
    display: flex; align-items: center; justify-content: space-between; gap: 10px;
    padding: 10px 12px;
    background: #fff;
    border: 1.5px solid var(--cs-slate-200);
    border-radius: 10px;
    transition: all 0.15s var(--cs-ease);
}
.cse__page-item:hover { border-color: var(--cs-brand-200); box-shadow: var(--cs-sh-sm); }
.cse__page-item--current { border-color: var(--cs-brand-500); background: var(--cs-brand-50); }
.cse__page-item-main { flex: 1; min-width: 0; }
.cse__page-item-link { display: flex; align-items: center; gap: 8px; text-decoration: none; color: var(--cs-slate-900); font-weight: 600; font-size: 13.5px; }
.cse__page-item-link:hover { color: var(--cs-brand-600); }
.cse__page-item-link i { color: var(--cs-slate-400); font-size: 12px; }
.cse__page-item-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cse__page-item-meta { display: flex; gap: 6px; margin-top: 5px; align-items: center; }
.cse__page-tag { font-size: 10px; padding: 2px 7px; border-radius: 5px; background: var(--cs-slate-100); color: var(--cs-slate-600); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
.cse__page-pill { font-size: 10px; padding: 2px 7px; border-radius: 999px; font-weight: 600; }
.cse__page-pill--live { background: var(--cs-success-soft); color: #047857; }

/* Toggle switch */
.cse__toggle { position: relative; width: 32px; height: 18px; flex-shrink: 0; cursor: pointer; }
.cse__toggle input { opacity: 0; width: 0; height: 0; position: absolute; }
.cse__toggle span {
    position: absolute; inset: 0;
    background: var(--cs-slate-300);
    border-radius: 999px;
    transition: background 0.2s;
}
.cse__toggle span::before {
    content: ''; position: absolute;
    top: 2px; left: 2px;
    width: 14px; height: 14px;
    background: #fff;
    border-radius: 50%;
    transition: transform 0.2s;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}
.cse__toggle input:checked + span { background: var(--cs-brand-500); }
.cse__toggle input:checked + span::before { transform: translateX(14px); }

/* Color picker field */
.cse__field--color .cse__color {
    display: flex; align-items: center; gap: 8px;
    padding: 4px 4px 4px 6px;
    border: 1.5px solid var(--cs-slate-200);
    border-radius: 9px;
    background: #fff;
    transition: border-color 0.15s;
}
.cse__field--color .cse__color:focus-within { border-color: var(--cs-brand-500); box-shadow: var(--cs-sh-focus); }
.cse__field--color input[type="color"] {
    width: 32px; height: 32px;
    padding: 0; border: 1px solid var(--cs-slate-200);
    border-radius: 7px; cursor: pointer;
    background: transparent;
    flex-shrink: 0;
}
.cse__field--color input[type="color"]::-webkit-color-swatch-wrapper { padding: 2px; }
.cse__field--color input[type="color"]::-webkit-color-swatch { border-radius: 5px; border: 0; }
.cse__field--color input[type="text"] {
    flex: 1; min-width: 0;
    border: 0 !important; padding: 6px 4px !important;
    background: transparent; font-family: monospace; font-size: 12.5px;
    text-transform: uppercase;
}
.cse__field--color input[type="text"]:focus { box-shadow: none !important; }
.cse__color-clear {
    border: 0; background: var(--cs-slate-100);
    color: var(--cs-slate-500);
    width: 26px; height: 26px;
    border-radius: 6px;
    cursor: pointer; font-size: 10px;
    display: inline-flex; align-items: center; justify-content: center;
    transition: background 0.15s;
}
.cse__color-clear:hover { background: var(--cs-slate-200); color: var(--cs-slate-800); }

/* Quick-add suggestions */
.cse__suggest { padding: 0 12px 16px; margin-top: 14px; }
.cse__suggest-head { font-size: 11px; font-weight: 700; color: var(--cs-slate-500); text-transform: uppercase; letter-spacing: 1.2px; margin-bottom: 10px; padding-top: 14px; border-top: 1px dashed var(--cs-slate-200); }
.cse__suggest-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.cse__suggest-chip {
    display: flex; flex-direction: column; align-items: center; gap: 6px;
    padding: 12px 8px;
    border: 1.5px dashed var(--cs-slate-300);
    border-radius: 10px;
    background: var(--cs-slate-25);
    color: var(--cs-slate-700);
    cursor: pointer;
    transition: all 0.15s var(--cs-ease);
    font-family: inherit; font-size: 12px; font-weight: 600;
}
.cse__suggest-chip:hover { border-color: var(--cs-brand-500); background: var(--cs-brand-50); color: var(--cs-brand-700); transform: translateY(-2px); border-style: solid; }
.cse__suggest-chip i { font-size: 14px; color: var(--cs-brand-600); }

/* Editor dialog (Add page modal) */
.cse__dialog { border: 0; border-radius: 16px; padding: 0; box-shadow: 0 30px 80px rgba(15, 23, 42, 0.25); min-width: 420px; max-width: 90vw; background: #fff; }
.cse__dialog::backdrop { background: rgba(15, 23, 42, 0.45); backdrop-filter: blur(2px); }
.cse__dialog-head { display: flex; justify-content: space-between; align-items: center; padding: 18px 22px; border-bottom: 1px solid var(--cs-slate-100); }
.cse__dialog-head h3 { margin: 0; font-size: 16px; font-weight: 700; font-family: 'Plus Jakarta Sans', 'Inter', sans-serif; letter-spacing: -0.015em; }
.cse__dialog-close { border: 0; background: var(--cs-slate-100); color: var(--cs-slate-500); width: 32px; height: 32px; border-radius: 8px; cursor: pointer; }
.cse__dialog-close:hover { background: var(--cs-slate-200); color: var(--cs-slate-900); }
.cse__dialog-body { padding: 20px 22px; display: flex; flex-direction: column; gap: 14px; }
.cse__dialog-foot { display: flex; justify-content: flex-end; gap: 8px; padding: 14px 22px; border-top: 1px solid var(--cs-slate-100); background: var(--cs-slate-50); border-bottom-left-radius: 16px; border-bottom-right-radius: 16px; }

/* ═══════════════════════════════════════════════════════════════
   ONBOARDING TOUR — full-viewport overlay with cutout spotlight
   ═══════════════════════════════════════════════════════════════ */
.cse__tour { position: fixed; inset: 0; z-index: 9999; pointer-events: none; }
.cse__tour[hidden] { display: none; }
.cse__tour-mask {
    position: absolute; inset: 0;
    background: rgba(15, 23, 42, 0.65);
    backdrop-filter: blur(2px);
    pointer-events: auto;
    transition: opacity 0.25s;
}
.cse__tour-spotlight {
    position: absolute;
    border-radius: 14px;
    box-shadow: 0 0 0 9999px rgba(15, 23, 42, 0.7);
    transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    pointer-events: none;
    border: 2px solid var(--cs-brand-500);
}
.cse__tour-card {
    position: absolute;
    background: #fff;
    border-radius: 14px;
    padding: 22px 24px;
    width: 340px;
    box-shadow: 0 30px 70px rgba(15, 23, 42, 0.35);
    pointer-events: auto;
    z-index: 10000;
    transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
}
.cse__tour-step { font-size: 11.5px; font-weight: 700; color: var(--cs-brand-600); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 8px; }
.cse__tour-step span { font-family: 'Plus Jakarta Sans', 'Inter', sans-serif; }
.cse__tour-card h3 { margin: 0 0 6px; font-family: 'Plus Jakarta Sans', 'Inter', sans-serif; font-size: 17px; font-weight: 800; letter-spacing: -0.015em; color: var(--cs-slate-900); }
.cse__tour-card p { margin: 0 0 18px; font-size: 13.5px; color: var(--cs-slate-600); line-height: 1.55; }
.cse__tour-actions { display: flex; justify-content: space-between; gap: 10px; }

/* ═══════════════════════════════════════════════════════════════
   SITE CHECKLIST — floating progress card bottom-left
   ═══════════════════════════════════════════════════════════════ */
.cse__checklist {
    position: fixed; bottom: 22px; left: 22px;
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 12px 36px rgba(15, 23, 42, 0.18), 0 2px 6px rgba(15, 23, 42, 0.04);
    z-index: 90;
    width: 280px;
    max-width: calc(100vw - 44px);
    overflow: hidden;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}
.cse__checklist[data-collapsed="true"] .cse__checklist-body { display: none; }
.cse__checklist[data-collapsed="true"] .cse__checklist-chev { transform: rotate(180deg); }

.cse__checklist-toggle {
    width: 100%;
    border: 0; background: transparent;
    padding: 14px 16px;
    display: flex; align-items: center; gap: 12px;
    cursor: pointer;
    font-family: inherit;
    text-align: left;
    border-bottom: 1px solid var(--cs-slate-100);
}
.cse__checklist-ring {
    --pct: 0;
    width: 38px; height: 38px;
    border-radius: 50%;
    background: conic-gradient(var(--cs-brand-500) calc(var(--pct) * 1%), var(--cs-slate-200) 0);
    display: inline-flex; align-items: center; justify-content: center;
    position: relative;
    flex-shrink: 0;
}
.cse__checklist-ring::after {
    content: ''; position: absolute; inset: 4px;
    background: #fff; border-radius: 50%;
}
.cse__checklist-pct { position: relative; font-size: 10px; font-weight: 800; color: var(--cs-brand-700); z-index: 1; }
.cse__checklist-label { flex: 1; font-weight: 600; font-size: 13.5px; color: var(--cs-slate-900); display: flex; flex-direction: column; gap: 2px; letter-spacing: -0.005em; }
.cse__checklist-label small { font-weight: 500; font-size: 11.5px; color: var(--cs-slate-500); }
.cse__checklist-chev { color: var(--cs-slate-400); font-size: 11px; transition: transform 0.25s; }

.cse__checklist-body { padding: 12px 16px 16px; max-height: 60vh; overflow-y: auto; }
.cse__checklist-items { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 8px; }
.cse__checklist-item { display: flex; gap: 10px; align-items: flex-start; font-size: 13px; line-height: 1.45; color: var(--cs-slate-700); padding: 4px 0; }
.cse__checklist-item i { color: var(--cs-slate-300); font-size: 16px; margin-top: 1px; flex-shrink: 0; }
.cse__checklist-item--done i { color: var(--cs-success); }
.cse__checklist-item--done span { color: var(--cs-slate-400); text-decoration: line-through; }
.cse__checklist-celebrate {
    margin-top: 14px; padding: 12px;
    background: linear-gradient(135deg, #FEF3C7 0%, #FCE7F3 100%);
    border: 1px solid #FDE68A;
    border-radius: 10px;
    text-align: center; color: #92400E; font-weight: 700; font-size: 13.5px;
}
.cse__checklist-celebrate i { color: #F59E0B; font-size: 18px; margin-right: 6px; }

@media (max-width: 768px) {
    .cse__checklist { left: 12px; right: 12px; bottom: 12px; width: auto; }
}

/* Help banner (dismissible) */
.cse__help {
    margin: 12px 14px 0;
    padding: 12px 14px;
    background: linear-gradient(135deg, #ecfdf5 0%, #FAE8FF 100%);
    border: 1px solid var(--cs-brand-200);
    border-radius: 12px;
    font-size: 12px; color: var(--cs-slate-700);
    line-height: 1.55;
    display: flex; gap: 10px; align-items: flex-start;
}
.cse__help-ic { width: 24px; height: 24px; border-radius: 7px; background: var(--cs-gradient-brand); color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 11px; flex-shrink: 0; margin-top: 1px; }
.cse__help-body { flex: 1; }
.cse__help-body strong { color: var(--cs-slate-900); font-weight: 700; }
.cse__help-close { border: 0; background: transparent; color: var(--cs-slate-400); cursor: pointer; font-size: 14px; padding: 2px 6px; }
.cse__help-close:hover { color: var(--cs-slate-700); }

/* Site Settings accordions */
.cse__site-panel { padding: 10px 14px 30px; display: flex; flex-direction: column; gap: 8px; }
/* Typography grid (role × device) */
.cse__typo { display: flex; flex-direction: column; gap: 8px; }
.cse__typo-row { display: grid; grid-template-columns: 1.4fr 1fr 1fr 1fr; gap: 8px; align-items: center; }
.cse__typo-row--head { font-size: 11px; color: var(--cs-slate-500, #64748b); text-align: center; }
.cse__typo-row--head span:first-child { text-align: left; }
.cse__typo-row > label { font-size: 12.5px; color: var(--cs-slate-700, #334155); margin: 0; }
.cse__typo-row > input { width: 100%; text-align: center; padding: 6px 4px; }
.cse__accordion { border: 1px solid var(--cs-slate-200); border-radius: 10px; overflow: hidden; background: #fff; }
.cse__accordion[open] { border-color: var(--cs-brand-300, var(--cs-brand-200)); box-shadow: var(--cs-sh-sm); }
.cse__accordion > summary {
    padding: 11px 14px;
    font-size: 13px; font-weight: 600;
    color: var(--cs-slate-800);
    cursor: pointer;
    list-style: none;
    display: flex; align-items: center; gap: 10px;
}
.cse__accordion > summary::-webkit-details-marker { display: none; }
.cse__accordion > summary::after {
    content: '\f078';
    font-family: 'Font Awesome 5 Free'; font-weight: 900;
    margin-left: auto;
    color: var(--cs-slate-400);
    font-size: 10px;
    transition: transform 0.2s;
}
.cse__accordion[open] > summary::after { transform: rotate(180deg); }
.cse__accordion > summary i { color: var(--cs-brand-600); }
.cse__accordion > .cse__field { padding: 0 14px; }
.cse__accordion > .cse__field:first-of-type { padding-top: 6px; }
.cse__accordion > .cse__field:last-of-type { padding-bottom: 14px; }
.cse__rail-empty-btn {
    background: var(--cs-gradient-brand); color: #fff;
    padding: 10px 20px; border-radius: 10px;
    border: 0; font-weight: 600; font-size: 13px; cursor: pointer;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.30);
    display: inline-flex; align-items: center; gap: 7px;
    transition: all 0.15s var(--cs-ease);
    margin-bottom: 10px;
}
.cse__rail-empty-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 22px rgba(16, 185, 129, 0.40); filter: brightness(1.05); }
.cse__rail-empty-btn--ghost {
    background: transparent !important; color: var(--cs-slate-700) !important;
    border: 1.5px solid var(--cs-slate-200) !important;
    box-shadow: none !important;
}
.cse__rail-empty-btn--ghost:hover { background: var(--cs-slate-50) !important; border-color: var(--cs-slate-300) !important; transform: translateY(-1px); }

/* ── Preview ──────────────────────────────────────────────────────── */
.cse__preview {
    padding: 24px;
    overflow-y: auto;
    display: flex; justify-content: center; align-items: flex-start;
    min-height: 0;
    background: var(--cs-slate-100);
}
.cse__preview-inner {
    width: 100%; max-width: 1240px;
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 10px 40px rgba(15, 23, 42, 0.10), 0 2px 6px rgba(15, 23, 42, 0.04);
    transition: max-width 0.3s var(--cs-ease);
    overflow: hidden;
    height: 100%;
    position: relative;
}
.cse__preview[data-device-frame="mobile"] .cse__preview-inner {
    max-width: 390px;
    border-radius: 38px;
    box-shadow: 0 0 0 12px var(--cs-slate-800), 0 0 0 14px var(--cs-slate-900), 0 30px 60px rgba(15, 23, 42, 0.25);
    margin-top: 16px;
    height: calc(100% - 32px);
}
#csePreview { width: 100%; height: 100%; min-height: 800px; border: 0; display: block; background: #fff; }
.cse__preview[data-device-frame="mobile"] #csePreview { border-radius: 28px; }

/* ── Right panel ─────────────────────────────────────────────────── */
.cse__panel { background: #fff; border-left: 1px solid var(--cs-slate-200); overflow-y: auto; display: none; min-height: 0; }
.cse__panel[data-state="open"] { display: block; animation: cs-slide-in 0.25s var(--cs-ease); }
@keyframes cs-slide-in { from { opacity: 0; transform: translateX(20px); } to { opacity: 1; transform: translateX(0); } }
.cse__panel-head {
    display: flex; justify-content: space-between; align-items: center;
    padding: 18px 20px;
    border-bottom: 1px solid var(--cs-slate-100);
    position: sticky; top: 0; background: #fff; z-index: 1;
}
.cse__panel-head h3 {
    margin: 0; font-size: 15px; font-weight: 700;
    color: var(--cs-slate-900);
    font-family: 'Plus Jakarta Sans', 'Inter', sans-serif;
    letter-spacing: -0.01em;
}
.cse__panel-head-sub { display: block; font-size: 11px; color: var(--cs-slate-500); font-weight: 500; margin-top: 2px; }
.cse__panel-close {
    width: 30px; height: 30px;
    border: 0; background: var(--cs-slate-100);
    color: var(--cs-slate-500);
    cursor: pointer; border-radius: 8px;
    display: inline-flex; align-items: center; justify-content: center;
    transition: all 0.15s var(--cs-ease);
}
.cse__panel-close:hover { background: var(--cs-slate-200); color: var(--cs-slate-800); }
.cse__panel-body { padding: 20px; }
.cse__hint {
    text-align: center;
    padding: 60px 24px;
    color: var(--cs-slate-400); font-size: 13px;
    line-height: 1.6;
}
.cse__hint-ic {
    width: 48px; height: 48px;
    border-radius: 14px;
    background: var(--cs-slate-100);
    color: var(--cs-slate-400);
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 18px;
    margin-bottom: 14px;
}

/* Form fields */
.cse__field { margin-bottom: 18px; }
.cse__field label {
    display: block;
    font-size: 11.5px; font-weight: 600;
    color: var(--cs-slate-700);
    margin-bottom: 6px;
    letter-spacing: -0.005em;
}
.cse__field input[type="text"],
.cse__field input[type="email"],
.cse__field input[type="tel"],
.cse__field input[type="number"],
.cse__field input[type="url"],
.cse__field textarea,
.cse__field select {
    width: 100%;
    padding: 10px 13px;
    border: 1.5px solid var(--cs-slate-200);
    border-radius: 9px;
    font-size: 13px;
    background: #fff;
    color: var(--cs-slate-900);
    transition: all 0.15s var(--cs-ease);
}
.cse__field input:focus, .cse__field textarea:focus, .cse__field select:focus {
    outline: 0; border-color: var(--cs-brand-500); box-shadow: var(--cs-sh-focus);
}
.cse__field input:hover:not(:focus), .cse__field textarea:hover:not(:focus), .cse__field select:hover:not(:focus) {
    border-color: var(--cs-slate-300);
}
.cse__field textarea { min-height: 96px; resize: vertical; line-height: 1.5; }
.cse__field--bool { display: flex; align-items: center; gap: 10px; padding: 12px 14px; background: var(--cs-slate-50); border-radius: 9px; }
.cse__field--bool input { width: 16px; height: 16px; margin: 0; accent-color: var(--cs-brand-600); }
.cse__field--bool label { margin: 0; cursor: pointer; }

/* Lists (per-item rows for testimonials, FAQ etc.) */
.cse__list { display: flex; flex-direction: column; gap: 10px; margin-top: 8px; }
.cse__list-item {
    padding: 14px;
    border: 1.5px solid var(--cs-slate-200);
    border-radius: 12px;
    background: var(--cs-slate-25);
    position: relative;
    transition: border-color 0.15s;
}
.cse__list-item:hover { border-color: var(--cs-slate-300); }
.cse__list-item.is-dragging { opacity: 0.5; border-style: dashed; border-color: var(--cs-brand, #10b981); }
.cse__list-item__drag {
    position: absolute; top: 8px; left: 8px;
    background: transparent; border: 0;
    color: var(--cs-slate-400); cursor: grab;
    width: 26px; height: 26px;
    border-radius: 7px;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 12px;
    transition: all 0.15s;
}
.cse__list-item__drag:hover { background: var(--cs-slate-100); color: var(--cs-slate-600); }
.cse__list-item__drag:active { cursor: grabbing; }
.cse__list-item { padding-left: 38px; }
.cse__list-item__del {
    position: absolute; top: 8px; right: 8px;
    background: transparent; border: 0;
    color: var(--cs-slate-400); cursor: pointer;
    width: 26px; height: 26px;
    border-radius: 7px;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 11px;
    transition: all 0.15s;
}
.cse__list-item__del:hover { background: var(--cs-danger-soft); color: var(--cs-danger); }
.cse__list-item .cse__field { margin-bottom: 12px; }
.cse__list-item .cse__field:last-child { margin-bottom: 0; }

/* Featured Courses course-picker (2026-07-08) */
.cse__cp-search-wrap { position: relative; }
.cse__cp-search { position: relative; display: flex; align-items: center; }
.cse__cp-search i { position: absolute; left: 12px; color: var(--cs-slate-400, #94a3b8); font-size: 12px; }
.cse__cp-input { width: 100%; padding: 10px 12px 10px 32px; border: 1px solid var(--cs-slate-300, #cbd5e1); border-radius: 8px; font-size: 13px; }
.cse__cp-results { position: absolute; z-index: 30; left: 0; right: 0; margin-top: 4px; max-height: 260px; overflow-y: auto;
    background: #fff; border: 1px solid var(--cs-slate-200, #e2e8f0); border-radius: 10px; box-shadow: 0 12px 30px rgba(15,23,42,.14); padding: 5px; }
.cse__cp-result { display: flex; align-items: center; gap: 9px; width: 100%; padding: 7px 8px; border: 0; background: none;
    border-radius: 7px; cursor: pointer; text-align: left; }
.cse__cp-result:hover { background: var(--cs-slate-100, #f1f5f9); }
.cse__cp-noresult { padding: 12px; text-align: center; color: var(--cs-slate-400, #94a3b8); font-size: 12.5px; }
.cse__cp-thumb { flex: 0 0 auto; width: 40px; height: 28px; border-radius: 5px; overflow: hidden; background: var(--cs-slate-100, #f1f5f9);
    display: flex; align-items: center; justify-content: center; color: var(--cs-slate-400, #94a3b8); font-size: 11px; }
.cse__cp-thumb img { width: 100%; height: 100%; object-fit: cover; }
.cse__cp-title { flex: 1; font-size: 12.5px; font-weight: 600; color: var(--cs-slate-700, #334155); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cse__cp-mode { flex: 0 0 auto; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em;
    background: var(--cs-slate-100, #f1f5f9); color: var(--cs-slate-500, #64748b); padding: 2px 7px; border-radius: 999px; }
.cse__cp-selected { margin-top: 10px; }
.cse__cp-item { display: flex; align-items: center; gap: 9px; padding: 7px 34px 7px 34px; }
.cse__cp-item .cse__cp-meta { display: flex; align-items: center; gap: 8px; flex: 1; min-width: 0; }
.cse__cp-empty { margin-top: 8px; font-size: 12px; color: var(--cs-slate-400, #94a3b8); text-align: center; padding: 6px; }
.cse__list-add {
    width: 100%;
    padding: 11px;
    border: 1.5px dashed var(--cs-slate-300);
    background: transparent;
    color: var(--cs-slate-500);
    cursor: pointer; border-radius: 10px;
    font-size: 12.5px; font-weight: 600;
    margin-top: 8px;
    transition: all 0.15s var(--cs-ease);
    display: inline-flex; align-items: center; justify-content: center; gap: 6px;
}
.cse__list-add:hover { border-color: var(--cs-brand-500); color: var(--cs-brand-600); background: var(--cs-brand-50); }

/* Image upload */
.cse__upload {
    display: flex; gap: 12px; align-items: center;
    padding: 10px;
    background: var(--cs-slate-50);
    border: 1.5px solid var(--cs-slate-200);
    border-radius: 10px;
}
.cse__upload-preview {
    width: 56px; height: 56px;
    object-fit: cover;
    border-radius: 9px;
    border: 1px solid var(--cs-slate-200);
    background: #fff;
}
.cse__upload-empty {
    width: 56px; height: 56px;
    border-radius: 9px;
    background: #fff; border: 1px dashed var(--cs-slate-300);
    display: flex; align-items: center; justify-content: center;
    color: var(--cs-slate-300); font-size: 16px;
}
.cse__upload input[type="file"] { font-size: 12px; color: var(--cs-slate-500); flex: 1; min-width: 0; }
.cse__upload input[type="file"]::file-selector-button {
    background: var(--cs-brand-50); color: var(--cs-brand-700);
    padding: 6px 10px; border: 0; border-radius: 6px;
    font-size: 12px; font-weight: 600; cursor: pointer;
    margin-right: 10px;
}

/* ── Drawer (Add a section) ──────────────────────────────────────── */
.cse__drawer { position: fixed; inset: 0; z-index: 100; display: none; }
.cse__drawer--open { display: block; }
.cse__drawer-mask {
    position: absolute; inset: 0;
    background: rgba(15, 23, 42, 0.50);
    backdrop-filter: blur(2px);
    animation: cs-fade 0.2s var(--cs-ease);
}
@keyframes cs-fade { from { opacity: 0 } to { opacity: 1 } }
.cse__drawer-panel {
    position: absolute; right: 0; top: 0; bottom: 0;
    width: 500px; max-width: 100vw;
    background: #fff;
    overflow-y: auto;
    box-shadow: -16px 0 50px rgba(15, 23, 42, 0.20);
    animation: cs-slide-right 0.3s var(--cs-ease);
}
@keyframes cs-slide-right { from { transform: translateX(100%) } to { transform: translateX(0) } }
.cse__drawer-head {
    display: flex; justify-content: space-between; align-items: center;
    padding: 22px 24px;
    border-bottom: 1px solid var(--cs-slate-100);
    position: sticky; top: 0; background: #fff; z-index: 1;
}
.cse__drawer-head h3 {
    margin: 0; font-size: 18px; font-weight: 700;
    font-family: 'Plus Jakarta Sans', 'Inter', sans-serif;
    letter-spacing: -0.015em;
}
.cse__drawer-head p { font-size: 12.5px; color: var(--cs-slate-500); margin: 3px 0 0; }
.cse__drawer-close {
    width: 36px; height: 36px;
    border: 0; background: var(--cs-slate-100);
    color: var(--cs-slate-500);
    cursor: pointer; border-radius: 10px;
    font-size: 15px;
    display: inline-flex; align-items: center; justify-content: center;
    transition: all 0.15s;
}
.cse__drawer-close:hover { background: var(--cs-slate-200); color: var(--cs-slate-800); }

.cse__drawer-group { padding: 20px 24px; }
.cse__drawer-group + .cse__drawer-group { padding-top: 0; }
.cse__drawer-group h4 {
    font-size: 10.5px; color: var(--cs-slate-500);
    text-transform: uppercase; letter-spacing: 1.5px;
    margin: 0 0 14px; font-weight: 700;
    display: flex; align-items: center; gap: 8px;
}
.cse__drawer-group h4::before {
    content: ''; width: 6px; height: 6px;
    border-radius: 50%; background: currentColor;
    box-shadow: 0 0 0 3px color-mix(in srgb, currentColor 18%, transparent);
}
.cse__drawer-group--hero h4    { color: var(--cs-cat-hero); }
.cse__drawer-group--content h4 { color: var(--cs-cat-content); }
.cse__drawer-group--trust h4   { color: var(--cs-cat-trust); }
.cse__drawer-group--media h4   { color: var(--cs-cat-media); }
.cse__drawer-group--conversion h4 { color: var(--cs-cat-conversion); }
.cse__drawer-group--footer h4  { color: var(--cs-cat-footer); }

.cse__drawer-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.cse__drawer-card {
    display: flex; flex-direction: column; align-items: flex-start;
    gap: 12px;
    padding: 16px 14px;
    border: 1.5px solid var(--cs-slate-200);
    border-radius: 14px;
    background: #fff;
    cursor: pointer;
    transition: all 0.2s var(--cs-ease);
    text-align: left;
    font-family: inherit;
    position: relative;
    overflow: hidden;
}
.cse__drawer-card::before {
    content: ''; position: absolute; inset: 0;
    background: var(--cs-gradient-brand-soft);
    opacity: 0; transition: opacity 0.2s;
}
.cse__drawer-card:hover {
    border-color: var(--cs-brand-300, var(--cs-brand-200));
    transform: translateY(-3px);
    box-shadow: var(--cs-sh-md);
}
.cse__drawer-card:hover::before { opacity: 1; }
.cse__drawer-card > * { position: relative; z-index: 1; }
.cse__drawer-icon {
    width: 38px; height: 38px;
    border-radius: 11px;
    background: var(--cs-brand-50);
    color: var(--cs-brand-600);
    display: flex; align-items: center; justify-content: center;
    font-size: 16px;
    transition: all 0.2s var(--cs-ease);
}
.cse__drawer-card:hover .cse__drawer-icon {
    background: var(--cs-gradient-brand);
    color: #fff;
    transform: rotate(-4deg) scale(1.08);
    box-shadow: 0 6px 14px rgba(16, 185, 129, 0.35);
}
.cse__drawer-group--content .cse__drawer-icon { background: #E0F2FE; color: var(--cs-cat-content); }
.cse__drawer-group--trust   .cse__drawer-icon { background: #FEF3C7; color: var(--cs-cat-trust); }
.cse__drawer-group--media   .cse__drawer-icon { background: #FCE7F3; color: var(--cs-cat-media); }
.cse__drawer-group--conversion .cse__drawer-icon { background: #D1FAE5; color: var(--cs-cat-conversion); }
.cse__drawer-group--footer  .cse__drawer-icon { background: var(--cs-slate-100); color: var(--cs-cat-footer); }
.cse__drawer-name {
    font-size: 13.5px; font-weight: 600;
    color: var(--cs-slate-900);
    letter-spacing: -0.005em;
    line-height: 1.3;
}
.cse__drawer-desc {
    font-size: 11.5px; font-weight: 400;
    color: var(--cs-slate-500);
    line-height: 1.4;
    margin-top: 2px;
}

/* Responsive — collapse panel under 1100px viewport */
@media (max-width: 1100px) {
    .cse__layout, .cse__layout[data-panel-state="closed"] { grid-template-columns: 260px 1fr; }
    /* 2026-06-01 RWD-4 follow-up: clamp the section-settings panel to the
       viewport. At 360px fixed it lost ~40px off the left edge of a 320px
       phone; max-width:100vw makes it a full-width settings sheet there. */
    .cse__panel { position: fixed; right: 0; top: var(--cs-bar-h); bottom: 0; width: 360px; max-width: 100vw; z-index: 60; box-shadow: var(--cs-sh-lg); }
    :root { --cs-rail-w: 260px; }
}
/* 2026-06-01 RWD-4: mobile rail drawer toggle + backdrop.
   Hidden on desktop; the @media block below reveals them on phones so the
   off-canvas rail (Sections / Pages / Site / SEO) is reachable — previously
   it slid off-screen at ≤768px with no control to bring it back. */
.cse__rail-toggle { display: none; }
.cse__rail-mask { display: none; }

@media (max-width: 768px) {
    .cse__layout, .cse__layout[data-panel-state="closed"] { grid-template-columns: 1fr; }
    .cse__rail { position: fixed; left: 0; top: var(--cs-bar-h); bottom: 0; width: 280px; z-index: 60; transform: translateX(-100%); transition: transform 0.25s; box-shadow: var(--cs-sh-lg); }
    .cse__rail--open { transform: translateX(0); }

    /* Show the hamburger in the topbar + a tap-to-close backdrop. */
    .cse__rail-toggle { display: inline-flex; }
    .cse__rail-mask {
        display: block;
        position: fixed;
        left: 0; right: 0; bottom: 0; top: var(--cs-bar-h);
        background: rgba(15, 23, 42, 0.45);
        z-index: 59;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.25s;
    }
    .cse__rail-mask--open { opacity: 1; pointer-events: auto; }
}
    </style>

    <style>
/* 2026-07-10 (New Changes for UI #4) — dark mode for this page's bespoke components.
   The live site preview is an isolated <iframe id="csePreview"> (the public page paints
   its own styles), so only the builder chrome is themed here. Re-map the neutral slate
   token ramp for dark, then fix the hardcoded #fff / glass surfaces and the dark tooltip
   pill. Brand, status and category colours are left as-is. */
html[data-theme="dark"]{
    --cs-slate-25:#223049;
    --cs-slate-50:#17233a;
    --cs-slate-100:#22304a;
    --cs-slate-200:#2a3a55;
    --cs-slate-300:#3a4a63;
    --cs-slate-400:#94a3b8;
    --cs-slate-500:#94a3b8;
    --cs-slate-600:#cbd5e1;
    --cs-slate-700:#cbd5e1;
    --cs-slate-800:#e2e8f0;
    --cs-slate-900:#f1f5f9;
}
/* Hardcoded #fff surfaces → dark card */
html[data-theme="dark"] .cse__title:focus,
html[data-theme="dark"] .cse__dev--active,
html[data-theme="dark"] .cse__btn--ghost:hover,
html[data-theme="dark"] .cse__tab--active,
html[data-theme="dark"] .cse__rail,
html[data-theme="dark"] .cse__rail-head,
html[data-theme="dark"] .cse__sec,
html[data-theme="dark"] .cse__field--color .cse__color,
html[data-theme="dark"] .cse__dialog,
html[data-theme="dark"] .cse__tour-card,
html[data-theme="dark"] .cse__checklist,
html[data-theme="dark"] .cse__checklist-ring::after,
html[data-theme="dark"] .cse__page-item,
html[data-theme="dark"] .cse__accordion,
html[data-theme="dark"] .cse__preview-inner,
html[data-theme="dark"] .cse__panel,
html[data-theme="dark"] .cse__panel-head,
html[data-theme="dark"] .cse__drawer-panel,
html[data-theme="dark"] .cse__drawer-head,
html[data-theme="dark"] .cse__drawer-card,
html[data-theme="dark"] .cse__upload-preview,
html[data-theme="dark"] .cse__upload-empty{ background:#1e293b; }
/* Glass/translucent white surfaces → translucent dark */
html[data-theme="dark"] .cse__bar{ background:rgba(30,41,59,0.85); }
html[data-theme="dark"] .cse__sec-actions{ background:rgba(30,41,59,0.96); }
/* Keep the tooltip pill dark (slate-900 now maps to a light text colour) */
html[data-theme="dark"] [data-tip]::after{ background:#0b1220; color:#fff; }
html[data-theme="dark"] [data-tip]::before{ border-top-color:#0b1220; }
html[data-theme="dark"] [data-tip-position="bottom"]::before{ border-top-color:transparent; border-bottom-color:#0b1220; }
html[data-theme="dark"] [data-tip-position="left"]::before{ border-top-color:transparent; border-left-color:#0b1220; }
    </style>
    {{-- TinyMCE for the Rich Text (wysiwyg) section field --}}
    <script src="{{ asset('frontend/js/tinymce/js/tinymce/tinymce.min.js') }}"></script>
</head>
<body>

<div class="cse">

    {{-- Top bar --}}
    <header class="cse__bar">
        <div class="cse__bar-left">
            <a href="{{ route('instructor.web-page.index') }}" class="cse__back" data-tip="Back to dashboard" data-tip-position="bottom">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            {{-- 2026-06-01 RWD-4: mobile-only toggle for the off-canvas rail. --}}
            <button type="button" class="cse__back cse__rail-toggle" data-rail-toggle
                    aria-label="{{ __('Toggle sections panel') }}" data-tip="{{ __('Sections, pages & settings') }}" data-tip-position="bottom">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div class="cse__title-wrap">
                <i class="fa-solid fa-file-lines cse__title-icon"></i>
                <input type="text" class="cse__title" value="{{ $page->title }}" data-page-title data-tip="Click to rename this page" data-tip-position="bottom">
            </div>
            <span class="cse__pub {{ $page->is_published ? 'cse__pub--live' : '' }}" data-pub-state>
                <span class="cse__pub-dot"></span>
                {{ $page->is_published ? __('Live') : __('Draft') }}
            </span>
        </div>
        <div class="cse__bar-right">
            <span class="cse__save" data-save-state>
                <span class="cse__save-dot"></span>
                {{ __('All changes saved') }}
            </span>
            <div class="cse__devices" data-tip="Toggle preview size" data-tip-position="bottom">
                <button type="button" class="cse__dev cse__dev--active" data-device="desktop">
                    <i class="fa-solid fa-desktop"></i> <span class="cse__dev-label">{{ __('Desktop') }}</span>
                </button>
                <button type="button" class="cse__dev" data-device="mobile">
                    <i class="fa-solid fa-mobile-screen"></i> <span class="cse__dev-label">{{ __('Mobile') }}</span>
                </button>
            </div>
            @php
                $siteSlug = null;
                try { $siteSlug = optional(\App\Models\CoachLandingPage::where('added_by', $page->coach_id)->first())->slug; } catch (\Throwable $e) {}
            @endphp
            @if($siteSlug && $page->is_published)
                <a href="{{ url('/coach/' . $siteSlug . ($page->slug === 'home' ? '' : '/' . $page->slug)) }}" target="_blank" rel="noopener" class="cse__btn cse__btn--ghost" data-tip="{{ __('Open your published page in a new tab') }}" data-tip-position="bottom">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    <span>{{ __('View Live') }}</span>
                </a>
            @endif
            <button type="button" class="cse__btn cse__btn--ghost" data-publish data-tip="{{ $page->is_published ? __('Take this page offline') : __('Make this page live') }}" data-tip-position="bottom">
                <i class="fa-solid fa-{{ $page->is_published ? 'eye-slash' : 'eye' }}"></i>
                <span>{{ $page->is_published ? __('Unpublish') : __('Publish') }}</span>
            </button>
            <button type="button" class="cse__back" data-tour-start data-tip="{{ __('Show me how to use this') }}" data-tip-position="bottom">
                <i class="fa-solid fa-circle-question"></i>
            </button>
        </div>
    </header>

    <div class="cse__layout" data-panel-state="closed">

        {{-- 2026-06-01 RWD-4: backdrop behind the mobile rail drawer. --}}
        <div class="cse__rail-mask" data-rail-mask></div>

        {{-- Left rail with tabs --}}
        <aside class="cse__rail">
            {{-- Tab navigation --}}
            <div class="cse__tabs" role="tablist">
                <button type="button" class="cse__tab cse__tab--active" data-tab="sections" role="tab">
                    <i class="fa-solid fa-layer-group"></i> {{ __('Sections') }}
                </button>
                <button type="button" class="cse__tab" data-tab="pages" role="tab">
                    <i class="fa-solid fa-file-lines"></i> {{ __('Pages') }}
                </button>
                <button type="button" class="cse__tab" data-tab="site" role="tab">
                    <i class="fa-solid fa-sliders"></i> {{ __('Site') }}
                </button>
                <button type="button" class="cse__tab" data-tab="seo" role="tab">
                    <i class="fa-solid fa-magnifying-glass"></i> {{ __('SEO') }}
                </button>
            </div>

            {{-- TAB 1: Sections --}}
            <div class="cse__tab-content" data-tab-content="sections">
                @if($page->sections->count() <= 1)
                    <div class="cse__help" id="cseHelp">
                        <div class="cse__help-ic"><i class="fa-solid fa-lightbulb"></i></div>
                        <div class="cse__help-body">
                            <strong>{{ __('Quick start') }}</strong><br>
                            {{ __('Click') }} <strong>+</strong> {{ __('to add a section. Each section has its own form — text, images, buttons. Try Hero → About → Services → Lead Form.') }}
                        </div>
                        <button type="button" class="cse__help-close" onclick="document.getElementById('cseHelp').remove()"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                @endif
                <div class="cse__rail-head">
                    <h3>{{ __('Sections') }} <span class="cse__rail-head-count">{{ $page->sections->count() }}</span></h3>
                    <button type="button" class="cse__rail-add" data-open-drawer data-tip="{{ __('Add a new section') }}" data-tip-position="left">
                        <i class="fa-solid fa-plus"></i>
                    </button>
                </div>
                @if($page->sections->isEmpty())
                    <div class="cse__rail-empty">
                        <div class="cse__rail-empty-ic"><i class="fa-solid fa-layer-group"></i></div>
                        <p>{{ __('Empty page. Add a single section or use a Quick Start pack to scaffold everything at once.') }}</p>
                        <button type="button" class="cse__rail-empty-btn" data-starter-pack="default">
                            <i class="fa-solid fa-wand-magic-sparkles"></i> {{ __('Quick start pack') }}
                        </button>
                        <button type="button" class="cse__rail-empty-btn cse__rail-empty-btn--ghost" data-open-drawer>
                            <i class="fa-solid fa-plus"></i> {{ __('Add single section') }}
                        </button>
                    </div>
                @else
                    <div class="cse__sections" id="cseSections" data-page-id="{{ $page->id }}">
                        @foreach($page->sections as $s)
                            @php $reg = \App\Services\Site\SectionRegistry::get($s->section_type); @endphp
                            <article class="cse__sec {{ !$s->is_visible ? 'cse__sec--hidden' : '' }}" draggable="true" data-section-id="{{ $s->id }}" data-section-type="{{ $s->section_type }}">
                                <div class="cse__sec-handle"><i class="fa-solid fa-grip-vertical"></i></div>
                                <div class="cse__sec-main">
                                    <div class="cse__sec-icon"><i class="{{ $reg['icon'] ?? 'fa-solid fa-cube' }}"></i></div>
                                    <div class="cse__sec-name">{{ $reg['label'] ?? $s->section_type }}</div>
                                </div>
                                <div class="cse__sec-actions">
                                    <button type="button" class="cse__sec-action" data-toggle-visible="{{ $s->id }}" data-tip="{{ $s->is_visible ? __('Hide from website') : __('Show on website') }}" data-tip-position="bottom">
                                        <i class="fa-solid fa-eye{{ $s->is_visible ? '' : '-slash' }}"></i>
                                    </button>
                                    <button type="button" class="cse__sec-action" data-dup-section="{{ $s->id }}" data-tip="{{ __('Duplicate section') }}" data-tip-position="bottom"><i class="fa-solid fa-copy"></i></button>
                                    <button type="button" class="cse__sec-action" data-edit-section="{{ $s->id }}" data-tip="{{ __('Edit content') }}" data-tip-position="bottom"><i class="fa-solid fa-pen"></i></button>
                                    <button type="button" class="cse__sec-action cse__sec-action--danger" data-del-section="{{ $s->id }}" data-tip="{{ __('Delete section') }}" data-tip-position="bottom"><i class="fa-solid fa-trash"></i></button>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- TAB 2: Pages --}}
            <div class="cse__tab-content" data-tab-content="pages" hidden>
                <div class="cse__rail-head">
                    <h3>{{ __('Pages') }} <span class="cse__rail-head-count">{{ $allPages->count() }}</span></h3>
                    <button type="button" class="cse__rail-add" onclick="document.getElementById('cseAddPage').showModal()" data-tip="{{ __('Add a new page') }}" data-tip-position="left">
                        <i class="fa-solid fa-plus"></i>
                    </button>
                </div>
                <div class="cse__pages-list">
                    @foreach($allPages as $pg)
                        <div class="cse__page-item {{ $pg->id === $page->id ? 'cse__page-item--current' : '' }}" data-pg-id="{{ $pg->id }}">
                            <div class="cse__page-item-main">
                                <a href="{{ route('instructor.web-page.edit', $pg->id) }}" class="cse__page-item-link">
                                    <i class="fa-solid fa-file-lines"></i>
                                    <span class="cse__page-item-name">{{ $pg->title }}</span>
                                </a>
                                <div class="cse__page-item-meta">
                                    <span class="cse__page-tag">{{ ucfirst($pg->page_type) }}</span>
                                    @if($pg->is_published) <span class="cse__page-pill cse__page-pill--live">Live</span> @endif
                                </div>
                            </div>
                            <label class="cse__toggle" title="Show in nav">
                                <input type="checkbox" data-nav-toggle="{{ $pg->id }}" {{ $pg->is_visible_in_nav ? 'checked' : '' }}>
                                <span></span>
                            </label>
                            <label class="cse__toggle" title="{{ __('Show global footer on this page') }}">
                                <input type="checkbox" data-footer-toggle="{{ $pg->id }}" {{ ($pg->use_global_footer ?? true) ? 'checked' : '' }}>
                                <span></span>
                                <i class="fa-solid fa-shoe-prints" style="font-size:11px;color:#9aa0bc;margin-left:4px;"></i>
                            </label>
                        </div>
                    @endforeach
                </div>

                {{-- Quick suggestions for missing common pages --}}
                @php
                    $existingTypes = $allPages->pluck('page_type')->toArray();
                    $suggested = collect([
                        ['type' => 'about',        'title' => 'About Us',     'icon' => 'fa-user-tie'],
                        ['type' => 'services',     'title' => 'Services',     'icon' => 'fa-th'],
                        ['type' => 'pricing',      'title' => 'Pricing',      'icon' => 'fa-tag'],
                        ['type' => 'testimonials', 'title' => 'Testimonials', 'icon' => 'fa-quote-right'],
                        ['type' => 'contact',      'title' => 'Contact Us',   'icon' => 'fa-envelope'],
                    ])->reject(fn ($s) => in_array($s['type'], $existingTypes));
                @endphp
                @if($suggested->isNotEmpty())
                    <div class="cse__suggest">
                        <div class="cse__suggest-head">{{ __('Quick add common pages') }}</div>
                        <div class="cse__suggest-grid">
                            @foreach($suggested as $s)
                                <button type="button" class="cse__suggest-chip" data-add-page-type="{{ $s['type'] }}" data-add-page-title="{{ $s['title'] }}">
                                    <i class="fa-solid {{ $s['icon'] }}"></i>
                                    <span>{{ $s['title'] }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- TAB 3: Site settings --}}
            <div class="cse__tab-content" data-tab-content="site" hidden>
                <div class="cse__rail-head"><h3>{{ __('Site Settings') }}</h3></div>
                <div class="cse__site-panel">
                    <details class="cse__accordion" open>
                        <summary><i class="fa-solid fa-bullhorn"></i> {{ __('Sticky CTA') }}</summary>
                        <div class="cse__field cse__field--bool">
                            <input type="checkbox" id="ss-cta-en" data-ss="sticky_cta_enabled" {{ $settings->sticky_cta_enabled ? 'checked' : '' }}>
                            <label for="ss-cta-en">{{ __('Show floating CTA button') }}</label>
                        </div>
                        <div class="cse__field">
                            <label>{{ __('Button text') }}</label>
                            <input type="text" data-ss="sticky_cta_text" value="{{ $settings->sticky_cta_text }}" placeholder="Book a Call" maxlength="50">
                        </div>
                        <div class="cse__field">
                            <label>{{ __('Button URL') }}</label>
                            <input type="text" data-ss="sticky_cta_url" value="{{ $settings->sticky_cta_url }}" placeholder="https://calendly.com/...">
                        </div>
                    </details>

                    <details class="cse__accordion">
                        <summary><i class="fa-brands fa-whatsapp"></i> {{ __('WhatsApp Chat') }}</summary>
                        <div class="cse__field cse__field--bool">
                            <input type="checkbox" id="ss-wa-en" data-ss="whatsapp_enabled" {{ $settings->whatsapp_enabled ? 'checked' : '' }}>
                            <label for="ss-wa-en">{{ __('Show floating WhatsApp button') }}</label>
                        </div>
                        <div class="cse__field">
                            <label>{{ __('WhatsApp number (with country code)') }}</label>
                            <input type="text" data-ss="whatsapp_number" value="{{ $settings->whatsapp_number }}" placeholder="+919876543210" maxlength="30">
                        </div>
                        <div class="cse__field">
                            <label>{{ __('Default message') }}</label>
                            <input type="text" data-ss="whatsapp_message" value="{{ $settings->whatsapp_message }}" placeholder="Hi, I'd like to know more" maxlength="200">
                        </div>
                    </details>

                    <details class="cse__accordion">
                        <summary><i class="fa-solid fa-share-nodes"></i> {{ __('Social Links') }}</summary>
                        @foreach([
                            'social_facebook'  => ['Facebook',  'fa-brands fa-facebook'],
                            'social_instagram' => ['Instagram', 'fa-brands fa-instagram'],
                            'social_youtube'   => ['YouTube',   'fa-brands fa-youtube'],
                            'social_twitter'   => ['Twitter / X', 'fa-brands fa-x-twitter'],
                            'social_linkedin'  => ['LinkedIn',  'fa-brands fa-linkedin'],
                            'social_tiktok'    => ['TikTok',    'fa-brands fa-tiktok'],
                            'social_pinterest' => ['Pinterest', 'fa-brands fa-pinterest-p'],
                        ] as $key => $info)
                            <div class="cse__field">
                                <label><i class="{{ $info[1] }}"></i> {{ $info[0] }}</label>
                                <input type="text" data-ss="{{ $key }}" value="{{ $settings->{$key} }}" placeholder="https://..." maxlength="500">
                            </div>
                        @endforeach
                    </details>

                    <details class="cse__accordion">
                        <summary><i class="fa-solid fa-chart-line"></i> {{ __('Analytics & Tracking') }}</summary>
                        <div class="cse__field">
                            <label>{{ __('Google Analytics 4 ID') }}</label>
                            <input type="text" data-ss="analytics_ga4_id" value="{{ $settings->analytics_ga4_id }}" placeholder="G-XXXXXXXXXX" maxlength="50">
                        </div>
                        <div class="cse__field">
                            <label>{{ __('Meta Pixel ID') }}</label>
                            <input type="text" data-ss="analytics_meta_pixel_id" value="{{ $settings->analytics_meta_pixel_id }}" placeholder="1234567890" maxlength="50">
                        </div>
                        <div class="cse__field">
                            <label>{{ __('Google Tag Manager ID') }}</label>
                            <input type="text" data-ss="analytics_gtm_id" value="{{ $settings->analytics_gtm_id }}" placeholder="GTM-XXXXXXX" maxlength="50">
                        </div>
                    </details>

                    <details class="cse__accordion">
                        <summary><i class="fa-solid fa-image"></i> {{ __('Favicon') }}</summary>
                        <div class="cse__field">
                            <label>{{ __('Favicon URL') }}</label>
                            <div class="cse__upload">
                                @if($settings->favicon_url)<img class="cse__upload-preview" src="{{ $settings->favicon_url }}">@else<div class="cse__upload-empty"><i class="fa-solid fa-image"></i></div>@endif
                                <input type="hidden" data-ss="favicon_url" value="{{ $settings->favicon_url }}">
                                <input type="file" data-ss-upload="favicon_url" accept="image/*">
                            </div>
                        </div>
                    </details>

                    <details class="cse__accordion">
                        <summary><i class="fa-solid fa-shoe-prints"></i> {{ __('Site-wide Footer') }}</summary>
                        @php $fc = $settings->footer_config ?? []; @endphp
                        <div class="cse__field">
                            <label>{{ __('Footer tagline') }}</label>
                            <textarea data-ss-footer="tagline" maxlength="200">{{ $fc['tagline'] ?? '' }}</textarea>
                        </div>
                        <div class="cse__field">
                            <label>{{ __('Copyright text') }}</label>
                            <input type="text" data-ss-footer="copyright" value="{{ $fc['copyright'] ?? 'All rights reserved.' }}" maxlength="200">
                        </div>
                    </details>

                    <details class="cse__accordion">
                        <summary><i class="fa-solid fa-code"></i> {{ __('Custom Code') }}</summary>
                        <div class="cse__field">
                            <label>{{ __('Custom CSS') }}</label>
                            <textarea data-ss="custom_css" placeholder=".my-class { color: red; }" rows="5">{{ $settings->custom_css }}</textarea>
                        </div>
                        <div class="cse__field">
                            <label>{{ __('Custom <head> scripts (tracking pixels, fonts)') }}</label>
                            <textarea data-ss="custom_head_scripts" placeholder="<script>...</script>" rows="5">{{ $settings->custom_head_scripts }}</textarea>
                        </div>
                        <div class="cse__field">
                            <label>{{ __('Custom <body> scripts (chat widgets)') }}</label>
                            <textarea data-ss="custom_body_scripts" placeholder="<!-- Crisp / Hotjar -->" rows="5">{{ $settings->custom_body_scripts }}</textarea>
                        </div>
                    </details>

                    {{-- 2026-06-23 — Typography: site-wide font sizes per text role,
                         per device. Blank = use the theme default (nothing emitted). --}}
                    @php $typo = is_array($settings->typography_config ?? null) ? $settings->typography_config : []; @endphp
                    <details class="cse__accordion">
                        <summary><i class="fa-solid fa-font"></i> {{ __('Typography') }}</summary>
                        <p class="cse__hint" style="margin:2px 0 10px;color:#94A3B8;font-size:12px;">
                            {{ __('Set font sizes in px. Leave blank to keep the theme default. Tablet / Mobile override Desktop on smaller screens.') }}
                        </p>
                        <div class="cse__typo">
                            <div class="cse__typo-row cse__typo-row--head">
                                <span></span>
                                <span><i class="fa-solid fa-desktop"></i> {{ __('Desktop') }}</span>
                                <span><i class="fa-solid fa-tablet-screen-button"></i> {{ __('Tablet') }}</span>
                                <span><i class="fa-solid fa-mobile-screen-button"></i> {{ __('Mobile') }}</span>
                            </div>
                            @foreach(\App\Services\Site\SiteSettingsService::TYPOGRAPHY_ROLES as $role => $meta)
                                <div class="cse__typo-row">
                                    <label>{{ __($meta['label']) }}</label>
                                    @foreach(['desktop','tablet','mobile'] as $device)
                                        <input type="number" min="8" max="120" inputmode="numeric"
                                               data-ss-typo="{{ $role }}_{{ $device }}"
                                               value="{{ $typo[$role.'_'.$device] ?? '' }}"
                                               placeholder="—" aria-label="{{ __($meta['label']) }} {{ ucfirst($device) }}">
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                        <button type="button" class="cse__list-add" data-typo-reset style="margin-top:10px;">
                            <i class="fa-solid fa-rotate-left"></i> {{ __('Reset typography to defaults') }}
                        </button>
                    </details>
                </div>
            </div>

            {{-- TAB 4: SEO --}}
            <div class="cse__tab-content" data-tab-content="seo" hidden>
                <div class="cse__rail-head"><h3>{{ __('SEO for this page') }}</h3></div>
                <div class="cse__site-panel">
                    <div class="cse__field">
                        <label>{{ __('Meta title') }} <small style="color:#94A3B8">(≤60 chars — Google SERP)</small></label>
                        <input type="text" data-page-meta="meta_title" value="{{ $page->meta_title }}" maxlength="60" placeholder="{{ $page->title }}">
                    </div>
                    <div class="cse__field">
                        <label>{{ __('Meta description') }} <small style="color:#94A3B8">(≤160 chars)</small></label>
                        <textarea data-page-meta="meta_description" maxlength="160" rows="3" placeholder="Short summary of this page for search engines">{{ $page->meta_description }}</textarea>
                    </div>
                    <div class="cse__field">
                        <label>{{ __('Social share image (Open Graph)') }}</label>
                        <div class="cse__upload">
                            @if($page->og_image)<img class="cse__upload-preview" src="{{ $page->og_image }}">@else<div class="cse__upload-empty"><i class="fa-solid fa-image"></i></div>@endif
                            <input type="hidden" data-page-meta="og_image" value="{{ $page->og_image }}">
                            <input type="file" data-page-meta-upload="og_image" accept="image/*">
                        </div>
                    </div>
                    <div class="cse__field">
                        <label>{{ __('Search engine visibility') }}</label>
                        <select data-page-meta="robots">
                            <option value="index" {{ $page->robots === 'index' ? 'selected' : '' }}>{{ __('Indexed (appears in Google)') }}</option>
                            <option value="noindex" {{ $page->robots === 'noindex' ? 'selected' : '' }}>{{ __('Not indexed (hidden from search engines)') }}</option>
                        </select>
                    </div>
                    @if($page->page_type !== 'home')
                        <div class="cse__field">
                            <label>{{ __('URL slug') }}</label>
                            <input type="text" data-page-slug value="{{ $page->slug }}" pattern="[a-z0-9-]+" maxlength="120">
                            <small style="font-size:11px;color:#94A3B8">{{ __('Lowercase letters, numbers, hyphens only') }}</small>
                        </div>
                    @endif

                    <hr style="border: 0; border-top: 1px solid #E2E8F0; margin: 20px 0;">

                    <div class="cse__rail-head" style="border:0; padding:0 0 14px 0;">
                        <h3>{{ __('SEO defaults (whole site)') }}</h3>
                    </div>
                    <div class="cse__field">
                        <label>{{ __('Title suffix') }} <small style="color:#94A3B8">(appended to all pages)</small></label>
                        <input type="text" data-ss="seo_default_title_suffix" value="{{ $settings->seo_default_title_suffix }}" placeholder="| Brand Name" maxlength="60">
                    </div>
                    <div class="cse__field">
                        <label>{{ __('Default description') }}</label>
                        <textarea data-ss="seo_default_description" maxlength="160" rows="2">{{ $settings->seo_default_description }}</textarea>
                    </div>
                    <div class="cse__field">
                        <label>{{ __('Default share image') }}</label>
                        <div class="cse__upload">
                            @if($settings->seo_og_image_default)<img class="cse__upload-preview" src="{{ $settings->seo_og_image_default }}">@else<div class="cse__upload-empty"><i class="fa-solid fa-image"></i></div>@endif
                            <input type="hidden" data-ss="seo_og_image_default" value="{{ $settings->seo_og_image_default }}">
                            <input type="file" data-ss-upload="seo_og_image_default" accept="image/*">
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        {{-- Preview iframe --}}
        <main class="cse__preview" data-device-frame="desktop">
            <div class="cse__preview-inner">
                <iframe id="csePreview"
                        src="{{ route('instructor.coach.site.preview', $page->id) }}"
                        title="Preview"></iframe>
            </div>
        </main>

        {{-- Right panel --}}
        <aside class="cse__panel" id="csePanel" data-state="closed">
            <header class="cse__panel-head">
                <div>
                    <h3 data-panel-title>{{ __('Section settings') }}</h3>
                    <span class="cse__panel-head-sub" data-panel-sub>{{ __('Edit the content of this section') }}</span>
                </div>
                <button type="button" class="cse__panel-close" data-panel-close data-tip="{{ __('Close panel') }}" data-tip-position="bottom"><i class="fa-solid fa-xmark"></i></button>
            </header>
            <div class="cse__panel-body" data-panel-body>
                <div class="cse__hint">
                    <div class="cse__hint-ic"><i class="fa-solid fa-hand-pointer"></i></div>
                    {{ __('Click any section in the left panel to edit it.') }}
                </div>
            </div>
        </aside>
    </div>
</div>

{{-- ════════════════════════════════════════════════════════════════
     ONBOARDING TOUR — spotlight overlay (first-time + restartable)
     ════════════════════════════════════════════════════════════════ --}}
<div class="cse__tour" id="cseTour" hidden>
    <div class="cse__tour-mask"></div>
    <div class="cse__tour-spotlight" id="cseTourSpot"></div>
    <div class="cse__tour-card" id="cseTourCard">
        <div class="cse__tour-step">
            <span data-tour-num>1</span> / <span data-tour-total>6</span>
        </div>
        <h3 data-tour-title>{{ __('Welcome!') }}</h3>
        <p data-tour-body>{{ __('Let me show you around — takes 30 seconds.') }}</p>
        <div class="cse__tour-actions">
            <button type="button" class="cse__btn cse__btn--ghost" data-tour-skip>{{ __('Skip') }}</button>
            <button type="button" class="cse__btn cse__btn--primary" data-tour-next>
                {{ __('Next') }} <i class="fa-solid fa-arrow-right"></i>
            </button>
        </div>
    </div>
</div>

{{-- ════════════════════════════════════════════════════════════════
     SITE CHECKLIST — floating progress card (collapsible)
     ════════════════════════════════════════════════════════════════ --}}
@php
    $checks = [];
    $sectionTypes = $page->sections->pluck('section_type')->toArray();
    $checks[] = ['done' => $page->sections->count() >= 3,                 'label' => __('Add at least 3 sections')];
    $checks[] = ['done' => in_array('hero_v1', $sectionTypes),            'label' => __('Add a Hero (top banner)')];
    $checks[] = ['done' => in_array('about_v1', $sectionTypes),           'label' => __('Add About / Bio section')];
    $checks[] = ['done' => in_array('services_grid_v1', $sectionTypes),   'label' => __('Add Services / Courses')];
    $checks[] = ['done' => in_array('lead_form_v1', $sectionTypes) || in_array('contact_v1', $sectionTypes), 'label' => __('Add a Lead Form or Contact')];
    $checks[] = ['done' => !empty($page->meta_description),               'label' => __('Set SEO description (SEO tab)')];
    $checks[] = ['done' => (bool) $settings->whatsapp_enabled || (bool) $settings->sticky_cta_enabled, 'label' => __('Enable WhatsApp or sticky CTA')];
    $checks[] = ['done' => (bool) $page->is_published,                    'label' => __('Publish the page')];
    $doneCount = collect($checks)->where('done', true)->count();
    $totalCount = count($checks);
    $pct = $totalCount > 0 ? round(($doneCount / $totalCount) * 100) : 0;
@endphp
<div class="cse__checklist" id="cseChecklist">
    <button type="button" class="cse__checklist-toggle" data-checklist-toggle>
        <span class="cse__checklist-ring" style="--pct: {{ $pct }};">
            <span class="cse__checklist-pct">{{ $pct }}%</span>
        </span>
        <span class="cse__checklist-label">{{ __('Site checklist') }}<small>{{ $doneCount }} / {{ $totalCount }} {{ __('done') }}</small></span>
        <i class="fa-solid fa-chevron-up cse__checklist-chev"></i>
    </button>
    <div class="cse__checklist-body">
        <ul class="cse__checklist-items">
            @foreach($checks as $c)
                <li class="cse__checklist-item {{ $c['done'] ? 'cse__checklist-item--done' : '' }}">
                    <i class="fa-{{ $c['done'] ? 'solid fa-circle-check' : 'regular fa-circle' }}"></i>
                    <span>{{ $c['label'] }}</span>
                </li>
            @endforeach
        </ul>
        @if($pct === 100)
            <div class="cse__checklist-celebrate">
                <i class="fa-solid fa-trophy"></i> {{ __('All done — your site is ready!') }}
            </div>
        @endif
    </div>
</div>

{{-- Add page modal --}}
<dialog id="cseAddPage" class="cse__dialog">
    <form id="cseAddPageForm">
        <header class="cse__dialog-head">
            <h3>{{ __('Add new page') }}</h3>
            <button type="button" class="cse__dialog-close" onclick="this.closest('dialog').close()"><i class="fa-solid fa-xmark"></i></button>
        </header>
        <div class="cse__dialog-body">
            <div class="cse__field">
                <label>{{ __('Page type') }}</label>
                <select name="page_type" required>
                    <option value="about">{{ __('About') }}</option>
                    <option value="services">{{ __('Services') }}</option>
                    <option value="pricing">{{ __('Pricing') }}</option>
                    <option value="testimonials">{{ __('Testimonials') }}</option>
                    <option value="contact">{{ __('Contact') }}</option>
                    <option value="custom">{{ __('Custom page') }}</option>
                </select>
            </div>
            <div class="cse__field">
                <label>{{ __('Page title') }}</label>
                <input type="text" name="title" required maxlength="160" placeholder="e.g. About Me, Pricing, Retreats">
            </div>
        </div>
        <footer class="cse__dialog-foot">
            <button type="button" class="cse__btn cse__btn--ghost" onclick="this.closest('dialog').close()">{{ __('Cancel') }}</button>
            <button type="submit" class="cse__btn cse__btn--primary"><i class="fa-solid fa-plus"></i> {{ __('Create page') }}</button>
        </footer>
    </form>
</dialog>

{{-- Section-type drawer (rendered ONCE at end of body, outside .cse) --}}
<div class="cse__drawer" id="cseDrawer">
    <div class="cse__drawer-mask" data-close-drawer></div>
    <div class="cse__drawer-panel">
        <header class="cse__drawer-head">
            <div>
                <h3>{{ __('Add a section') }}</h3>
                <p>{{ __('Pick a block type to add to your page.') }}</p>
            </div>
            <button type="button" class="cse__drawer-close" data-close-drawer data-tip="{{ __('Close') }}" data-tip-position="bottom"><i class="fa-solid fa-xmark"></i></button>
        </header>
        @php
            // Friendly one-line description per section type — helps coach pick the right block
            $sectionDescriptions = [
                'hero_v1'           => __('Big banner with headline + CTA'),
                'about_v1'          => __('Your bio with photo + credentials'),
                'services_grid_v1'  => __('Your courses with buy button'),
                'course_picker_v1'  => __('Feature a single course'),
                'testimonials_v1'   => __('Client reviews with ratings'),
                'youtube_v1'        => __('Latest videos from your channel'),
                'video_gallery_v1'  => __('Your uploaded recordings'),
                'stats_v1'          => __('Numbers / counters of impact'),
                'faq_v1'            => __('Frequently asked questions'),
                'lead_form_v1'      => __('Capture leads to your CRM'),
                'cta_banner_v1'     => __('Big call-to-action strip'),
                'contact_v1'        => __('Address, phone, map'),
                'footer_v1'         => __('Footer with links + copyright'),
            ];
        @endphp
        @foreach($categories as $cat => $catLabel)
            @if(!empty($registry[$cat]))
                <div class="cse__drawer-group cse__drawer-group--{{ $cat }}">
                    <h4>{{ $catLabel }}</h4>
                    <div class="cse__drawer-grid">
                        @foreach($registry[$cat] as $type)
                            <button type="button" class="cse__drawer-card" data-add-type="{{ $type['code'] }}">
                                <span class="cse__drawer-icon"><i class="{{ $type['icon'] }}"></i></span>
                                <span class="cse__drawer-name">{{ $type['label'] }}</span>
                                @if(!empty($sectionDescriptions[$type['code']]))
                                    <span class="cse__drawer-desc">{{ $sectionDescriptions[$type['code']] }}</span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach
    </div>
</div>

<script>
(function(){
    const PAGE_ID  = {{ $page->id }};
    const REGISTRY = @json(\App\Services\Site\SectionRegistry::all());
    const CSRF     = document.querySelector('meta[name="csrf-token"]').content;
    const URLs = {
        addSection:     "{{ route('instructor.web-page.sections.add', $page->id) }}",
        reorder:        "{{ route('instructor.web-page.sections.reorder', $page->id) }}",
        updateSection:  "{{ url('instructor/web-page/sections') }}",
        deleteSection:  "{{ url('instructor/web-page/sections') }}",
        updatePage:     "{{ route('instructor.web-page.update', $page->id) }}",
        publishPage:    "{{ route('instructor.web-page.publish', $page->id) }}",
        media:          "{{ route('instructor.web-page.media') }}",
        courses:        "{{ route('instructor.web-page.courses') }}",
        sectionContent: "{{ url('instructor/web-page/sections') }}",
        siteSettings:   "{{ route('instructor.web-page.settings.update') }}",
        dupSection:     "{{ url('instructor/web-page/sections') }}",       // + /{id}/duplicate
        toggleVisible:  "{{ url('instructor/web-page/sections') }}",       // + /{id}/toggle-visible
        pageNav:        "{{ url('instructor/web-page/pages') }}",          // + /{id}/nav
        pageSlug:       "{{ url('instructor/web-page/pages') }}",          // + /{id}/slug
    };

    let saveTimer = null;
    let activeSectionId = null;

    const $  = (s, r=document) => r.querySelector(s);
    const $$ = (s, r=document) => Array.from(r.querySelectorAll(s));

    function setSave(state, msg) {
        const el = $('[data-save-state]');
        if (!el) return;
        el.classList.remove('cse__save--saving','cse__save--saved','cse__save--error');
        const textNode = el.childNodes[el.childNodes.length - 1];
        if (state === 'saving') { el.classList.add('cse__save--saving'); textNode.textContent = ' ' + (msg || 'Saving…'); }
        else if (state === 'saved') { el.classList.add('cse__save--saved'); textNode.textContent = ' ' + (msg || 'All changes saved'); }
        else if (state === 'error') { el.classList.add('cse__save--error'); textNode.textContent = ' ' + (msg || 'Error saving'); }
    }

    function refreshPreview() {
        const f = $('#csePreview');
        if (f) { try { f.contentWindow.location.reload(); } catch(e){} }
    }

    function api(url, opts={}) {
        return fetch(url, Object.assign({
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            credentials: 'same-origin',
        }, opts)).then(r => r.json());
    }

    // Drawer open/close
    function openDrawer() { $('#cseDrawer').classList.add('cse__drawer--open'); }
    function closeDrawer() { $('#cseDrawer').classList.remove('cse__drawer--open'); }
    $$('[data-open-drawer]').forEach(b => b.addEventListener('click', openDrawer));
    $$('[data-close-drawer]').forEach(b => b.addEventListener('click', closeDrawer));
    document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeDrawer(); closePanel(); } });

    // Device toggle
    $$('.cse__dev').forEach(b => b.addEventListener('click', () => {
        $$('.cse__dev').forEach(x => x.classList.remove('cse__dev--active'));
        b.classList.add('cse__dev--active');
        $('.cse__preview').setAttribute('data-device-frame', b.dataset.device);
    }));

    // Title rename autosave
    $('[data-page-title]').addEventListener('blur', function(){
        const t = this.value.trim(); if (!t) return;
        setSave('saving');
        api(URLs.updatePage, { method:'PUT', body: JSON.stringify({ title: t }) })
            .then(r => { setSave(r.ok ? 'saved' : 'error'); refreshPreview(); })
            .catch(() => setSave('error'));
    });
    $('[data-page-title]').addEventListener('keydown', function(e){ if (e.key === 'Enter') this.blur(); });

    // Publish toggle
    $('[data-publish]').addEventListener('click', function(){
        api(URLs.publishPage).then(r => {
            if (r.ok) location.reload();
            else alert(r.msg || 'Failed to publish');
        });
    });

    // Add section
    $$('[data-add-type]').forEach(b => b.addEventListener('click', function(){
        const type = this.dataset.addType;
        closeDrawer();
        setSave('saving','Adding section…');
        api(URLs.addSection, { body: JSON.stringify({ section_type: type }) })
            .then(r => { if (r.ok) { setSave('saved'); location.reload(); } else setSave('error'); });
    }));

    // Section row click → open panel + section delete
    document.addEventListener('click', function(e){
        const del = e.target.closest('[data-del-section]');
        if (del) {
            e.stopPropagation();
            if (!confirm('Delete this section?')) return;
            const id = del.dataset.delSection;
            setSave('saving','Deleting…');
            api(URLs.deleteSection + '/' + id, { method: 'DELETE' })
                .then(r => { if (r.ok) { setSave('saved'); location.reload(); } else setSave('error'); });
            return;
        }
        const edit = e.target.closest('[data-edit-section]');
        const sec = e.target.closest('.cse__sec');
        if (edit || sec) {
            const row = edit ? edit.closest('.cse__sec') : sec;
            if (row) openPanel(row.dataset.sectionId, row.dataset.sectionType, row);
        }
    });

    function openPanel(sectionId, type, row) {
        activeSectionId = sectionId;
        $$('.cse__sec').forEach(x => x.classList.remove('cse__sec--active'));
        if (row) row.classList.add('cse__sec--active');
        const reg = REGISTRY[type];
        if (!reg) return;
        $('.cse__layout').setAttribute('data-panel-state', 'open');
        $('#csePanel').setAttribute('data-state', 'open');
        $('[data-panel-title]').textContent = reg.label;
        $('[data-panel-sub]').textContent = 'Edit the content of this section';
        const body = $('[data-panel-body]');
        body.innerHTML = '<div class="cse__hint"><div class="cse__hint-ic"><i class="fa-solid fa-spinner fa-spin"></i></div>Loading…</div>';
        fetch(URLs.sectionContent + '/' + sectionId + '/content', { credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                if (!data.ok) { body.innerHTML = '<p style="color:#ef4444">Failed to load.</p>'; return; }
                renderForm(body, type, data.content || {}, reg.schema);
            });
    }
    function closePanel() {
        $('.cse__layout').setAttribute('data-panel-state', 'closed');
        $('#csePanel').setAttribute('data-state', 'closed');
        activeSectionId = null;
        $$('.cse__sec').forEach(x => x.classList.remove('cse__sec--active'));
    }
    $('[data-panel-close]').addEventListener('click', closePanel);

    function initWysiwyg(id) {
        if (!window.tinymce) return;
        tinymce.init({
            selector: '#' + id,
            height: 320,
            menubar: false,
            branding: false,
            plugins: 'lists link autolink',
            toolbar: 'undo redo | blocks fontfamily fontsizeinput | bold italic underline forecolor backcolor | alignleft aligncenter alignright | bullist numlist | link removeformat',
            setup: function (editor) {
                editor.on('change keyup SetContent', function () {
                    editor.save();
                    editor.targetElm.dispatchEvent(new Event('change', { bubbles: true }));
                });
            }
        });
    }

    function renderForm(root, type, content, schema) {
        if (window.tinymce) { try { tinymce.remove(); } catch (e) {} }  // clear any prior WYSIWYG before rebuild
        root.innerHTML = '';
        Object.keys(schema).forEach(key => {
            const field = schema[key];
            const val = content[key] ?? '';
            const wrap = document.createElement('div');
            wrap.className = 'cse__field cse__field--' + field.type;
            const label = field.label || key;
            const req = field.required ? ' <span style="color:#ef4444">*</span>' : '';

            if (field.type === 'wysiwyg') {
                const taId = 'cf-wys-' + key + '-' + Math.random().toString(36).slice(2, 8);
                wrap.innerHTML = `<label>${label}${req}</label><textarea name="${key}" id="${taId}">${escapeHtml(val)}</textarea>`;
                setTimeout(() => initWysiwyg(taId), 0);
            } else if (field.type === 'enum') {
                wrap.innerHTML = `<label>${label}${req}</label>` +
                    `<select name="${key}">${(field.options||[]).map(o => `<option value="${o}" ${o==val?'selected':''}>${o}</option>`).join('')}</select>`;
            } else if (field.type === 'bool') {
                wrap.classList.add('cse__field--bool');
                wrap.innerHTML = `<input type="checkbox" name="${key}" ${val ? 'checked' : ''} id="cf-${key}"><label for="cf-${key}">${label}</label>`;
            } else if (field.type === 'richtext' || field.multiline) {
                wrap.innerHTML = `<label>${label}${req}</label><textarea name="${key}" maxlength="${field.max||5000}">${escapeHtml(val)}</textarea>`;
            } else if (field.type === 'image') {
                wrap.innerHTML = `<label>${label}</label>
                    <div class="cse__upload">
                        ${val ? `<img class="cse__upload-preview" src="${val}">` : '<div class="cse__upload-empty"><i class="fa-solid fa-image"></i></div>'}
                        <input type="hidden" name="${key}" value="${escapeAttr(val)}">
                        <input type="file" data-upload-for="${key}" accept="image/*">
                    </div>`;
            } else if (field.type === 'int') {
                wrap.innerHTML = `<label>${label}${req}</label><input type="number" name="${key}" value="${escapeAttr(val)}" min="${field.min ?? ''}" max="${field.max ?? ''}">`;
            } else if (field.type === 'color') {
                // Color picker with hex text input + native color square + clear button
                wrap.classList.add('cse__field--color');
                wrap.innerHTML = `<label>${label}${req}</label>
                    <div class="cse__color">
                        <input type="color" data-color-pick value="${val || '#6366F1'}">
                        <input type="text" name="${key}" value="${escapeAttr(val)}" placeholder="#RRGGBB or empty" maxlength="20">
                        <button type="button" class="cse__color-clear" data-tip="Clear" data-tip-position="left"><i class="fa-solid fa-xmark"></i></button>
                    </div>`;
                const colorEl = wrap.querySelector('[data-color-pick]');
                const textEl = wrap.querySelector(`input[name="${key}"]`);
                const clrBtn = wrap.querySelector('.cse__color-clear');
                colorEl.addEventListener('input', () => { textEl.value = colorEl.value; queueSave(); });
                clrBtn.addEventListener('click', () => { textEl.value = ''; queueSave(); });
            } else if (field.type === 'list') {
                wrap.innerHTML = `<label>${label}</label>
                    <div class="cse__list" data-list-for="${key}" data-item-type="${field.item_type || 'string'}"></div>
                    <button type="button" class="cse__list-add" data-list-add="${key}"><i class="fa-solid fa-plus"></i> ${field.add_label || 'Add item'}</button>`;
                const list = wrap.querySelector('[data-list-for]');
                (Array.isArray(val) ? val : []).forEach(item => list.appendChild(buildListItem(key, field, item)));
                attachListDnD(list);
            } else if (field.type === 'course_picker') {
                // 2026-07-08 — searchable multi-select of the coach's published
                // courses, drag-reorderable. Reuses the [data-list-for] container
                // so the selected IDs serialize (in DOM order) exactly like a list.
                // NOTE: the [data-list-for] container MUST be a DIRECT child of
                // .cse__field — serializeForm() reads `:scope > [data-list-for]`,
                // so nesting it inside a wrapper would silently drop course_ids on
                // save (the carousel would stay empty). Only search+results live in
                // the relative wrapper (for the absolute results dropdown).
                wrap.classList.add('cse__field--course-picker');
                wrap.innerHTML = `<label>${label}</label>
                    <div class="cse__cp-search-wrap">
                        <div class="cse__cp-search"><i class="fa-solid fa-magnifying-glass"></i>
                            <input type="text" class="cse__cp-input" placeholder="{{ __('Search your published courses…') }}" autocomplete="off">
                        </div>
                        <div class="cse__cp-results" hidden></div>
                    </div>
                    <div class="cse__list cse__cp-selected" data-list-for="${key}" data-item-type="string"></div>
                    <div class="cse__cp-empty">{{ __('No courses selected yet — search above and click to add.') }}</div>`;
                const list = wrap.querySelector('[data-list-for]');
                attachListDnD(list);
                initCoursePicker(wrap, list, Array.isArray(val) ? val : []);
            } else {
                wrap.innerHTML = `<label>${label}${req}</label><input type="text" name="${key}" value="${escapeAttr(val)}" maxlength="${field.max||500}">`;
            }
            root.appendChild(wrap);
        });

        root.querySelectorAll('input, textarea, select').forEach(el => {
            el.addEventListener('input', queueSave);
            el.addEventListener('change', queueSave);
        });
        root.querySelectorAll('[data-upload-for]').forEach(el => el.addEventListener('change', e => handleUpload(e, root, el.dataset.uploadFor)));
        root.querySelectorAll('[data-list-add]').forEach(b => b.addEventListener('click', () => {
            const key = b.dataset.listAdd;
            const list = root.querySelector(`[data-list-for="${key}"]`);
            const field = schema[key];
            list.appendChild(buildListItem(key, field, field.item_type === 'object' ? {} : ''));
            queueSave();
        }));
    }

    async function handleUpload(e, root, key) {
        const file = e.target.files[0]; if (!file) return;
        const fd = new FormData(); fd.append('file', file); fd.append('_token', CSRF);
        setSave('saving','Uploading…');
        try {
            const res = await fetch(URLs.media, { method:'POST', body:fd, credentials:'same-origin' }).then(r=>r.json());
            if (res.url) {
                const target = root.querySelector(`input[name="${key}"]`);
                if (target) { target.value = res.url; queueSave(); }
                const wrap = e.target.parentElement;
                let img = wrap.querySelector('img.cse__upload-preview');
                if (!img) {
                    const empty = wrap.querySelector('.cse__upload-empty');
                    if (empty) empty.remove();
                    img = document.createElement('img');
                    img.className = 'cse__upload-preview';
                    wrap.prepend(img);
                }
                img.src = res.url;
            }
        } catch (err) { setSave('error'); }
    }

    // Enable drag-reorder on a list container: as a grabbed item is dragged,
    // reposition it among its DIRECT siblings based on cursor Y. Scoped with
    // :scope so nested lists don't interfere with their parent list.
    function attachListDnD(listEl) {
        if (!listEl || listEl.dataset.dndOn) return;
        listEl.dataset.dndOn = '1';
        listEl.addEventListener('dragover', (e) => {
            const dragging = listEl.querySelector(':scope > .cse__list-item.is-dragging');
            if (!dragging) return; // a drag from a different list — ignore
            e.preventDefault();
            const items = Array.from(listEl.querySelectorAll(':scope > .cse__list-item:not(.is-dragging)'));
            const after = items.find(it => e.clientY < it.getBoundingClientRect().top + it.offsetHeight / 2);
            if (after) listEl.insertBefore(dragging, after);
            else listEl.appendChild(dragging);
        });
    }

    function buildListItem(key, field, value) {
        const wrap = document.createElement('div');
        wrap.className = 'cse__list-item';

        // Drag-to-reorder handle. Grabbing the handle makes the item
        // draggable; the list container (attachListDnD) repositions it.
        // Items serialize in DOM order, so reordering = reordering the data.
        const handle = document.createElement('button');
        handle.type = 'button'; handle.className = 'cse__list-item__drag'; handle.title = 'Drag to reorder';
        handle.setAttribute('aria-label', 'Drag to reorder');
        handle.innerHTML = '<i class="fa-solid fa-grip-vertical"></i>';
        handle.addEventListener('mousedown', () => { wrap.draggable = true; });
        handle.addEventListener('mouseup', () => { wrap.draggable = false; });
        wrap.appendChild(handle);
        wrap.addEventListener('dragstart', () => wrap.classList.add('is-dragging'));
        wrap.addEventListener('dragend', () => { wrap.classList.remove('is-dragging'); wrap.draggable = false; queueSave(); });

        const close = document.createElement('button');
        close.type = 'button'; close.className = 'cse__list-item__del'; close.title = 'Remove';
        close.innerHTML = '<i class="fa-solid fa-xmark"></i>';
        close.addEventListener('click', () => { wrap.remove(); queueSave(); });
        wrap.appendChild(close);

        if (field.item_type === 'object' && field.item_schema) {
            Object.keys(field.item_schema).forEach(sk => {
                const sf = field.item_schema[sk];
                const sv = (typeof value === 'object' && value !== null) ? (value[sk] ?? '') : '';
                const sub = document.createElement('div');
                sub.className = 'cse__field';
                const sLabel = sf.label || sk;
                if (sf.type === 'image') {
                    sub.innerHTML = `<label>${sLabel}</label>
                        <div class="cse__upload">
                            ${sv ? `<img class="cse__upload-preview" src="${sv}">` : '<div class="cse__upload-empty"><i class="fa-solid fa-image"></i></div>'}
                            <input type="hidden" data-key="${sk}" value="${escapeAttr(sv)}">
                            <input type="file" data-list-upload accept="image/*">
                        </div>`;
                    sub.querySelector('[data-list-upload]').addEventListener('change', async (e) => {
                        const file = e.target.files[0]; if (!file) return;
                        const fd = new FormData(); fd.append('file', file); fd.append('_token', CSRF);
                        setSave('saving','Uploading…');
                        const res = await fetch(URLs.media, { method:'POST', body:fd, credentials:'same-origin' }).then(r=>r.json());
                        if (res.url) {
                            sub.querySelector('input[type=hidden]').value = res.url;
                            const w = e.target.parentElement;
                            let img = w.querySelector('img.cse__upload-preview');
                            if (!img) {
                                const empty = w.querySelector('.cse__upload-empty'); if (empty) empty.remove();
                                img = document.createElement('img'); img.className='cse__upload-preview'; w.prepend(img);
                            }
                            img.src = res.url;
                            queueSave();
                        }
                    });
                } else if (sf.type === 'enum') {
                    sub.innerHTML = `<label>${sLabel}</label><select data-key="${sk}">${(sf.options||[]).map(o=>`<option value="${o}" ${o==sv?'selected':''}>${o}</option>`).join('')}</select>`;
                } else if (sf.multiline || sf.type === 'richtext') {
                    sub.innerHTML = `<label>${sLabel}</label><textarea data-key="${sk}">${escapeHtml(sv)}</textarea>`;
                } else if (sf.type === 'int') {
                    sub.innerHTML = `<label>${sLabel}</label><input type="number" data-key="${sk}" value="${escapeAttr(sv)}">`;
                } else if (sf.type === 'list') {
                    // NESTED LIST — e.g. footer_v1.link_groups[].links[]
                    // Render a sub-list with its own Add button.
                    sub.classList.add('cse__field--nested-list');
                    sub.innerHTML = `<label>${sLabel}</label>
                        <div class="cse__list" data-nested-list="${sk}" data-item-type="${sf.item_type || 'string'}"></div>
                        <button type="button" class="cse__list-add" data-nested-list-add="${sk}"><i class="fa-solid fa-plus"></i> Add</button>`;
                    const nestedList = sub.querySelector('[data-nested-list]');
                    const initial = Array.isArray(sv) ? sv : [];
                    initial.forEach(item => nestedList.appendChild(buildListItem(sk, sf, sf.item_type === 'object' ? item : item)));
                    attachListDnD(nestedList);
                    sub.querySelector('[data-nested-list-add]').addEventListener('click', () => {
                        nestedList.appendChild(buildListItem(sk, sf, sf.item_type === 'object' ? {} : ''));
                        queueSave();
                    });
                } else {
                    sub.innerHTML = `<label>${sLabel}</label><input type="text" data-key="${sk}" value="${escapeAttr(sv)}">`;
                }
                sub.querySelectorAll('input, textarea, select').forEach(i => i.addEventListener('input', queueSave));
                wrap.appendChild(sub);
            });
        } else {
            const inp = document.createElement('input');
            inp.type = 'text'; inp.dataset.key = '_value'; inp.value = (typeof value === 'string' || typeof value === 'number') ? value : '';
            inp.addEventListener('input', queueSave);
            wrap.appendChild(inp);
        }
        return wrap;
    }

    // ── Featured Courses picker (2026-07-08) ──────────────────────────
    // Searchable multi-select of the coach's OWN published courses. Selected
    // courses become .cse__list-item rows (drag-reorderable) with a hidden
    // input[data-key="_value"] = course id, so serializeList yields the ordered
    // id array automatically.
    let _cpCoursesPromise = null;
    function cpLoadCourses() {
        if (!_cpCoursesPromise) {
            _cpCoursesPromise = fetch(URLs.courses, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(r => r.json()).then(j => Array.isArray(j.courses) ? j.courses : []).catch(() => []);
        }
        return _cpCoursesPromise;
    }
    function buildCoursePickerItem(course) {
        const wrap = document.createElement('div');
        wrap.className = 'cse__list-item cse__cp-item';

        const handle = document.createElement('button');
        handle.type = 'button'; handle.className = 'cse__list-item__drag'; handle.title = 'Drag to reorder';
        handle.innerHTML = '<i class="fa-solid fa-grip-vertical"></i>';
        handle.addEventListener('mousedown', () => { wrap.draggable = true; });
        handle.addEventListener('mouseup', () => { wrap.draggable = false; });
        wrap.appendChild(handle);
        wrap.addEventListener('dragstart', () => wrap.classList.add('is-dragging'));
        wrap.addEventListener('dragend', () => { wrap.classList.remove('is-dragging'); wrap.draggable = false; queueSave(); });

        const thumb = document.createElement('span');
        thumb.className = 'cse__cp-thumb';
        thumb.innerHTML = course.thumb ? `<img src="${escapeAttr(course.thumb)}" alt="">` : '<i class="fa-solid fa-image"></i>';
        wrap.appendChild(thumb);

        const meta = document.createElement('span');
        meta.className = 'cse__cp-meta';
        meta.innerHTML = `<span class="cse__cp-title">${escapeHtml(course.title || ('Course #' + course.id))}</span>` +
            (course.mode ? `<span class="cse__cp-mode">${escapeHtml(course.mode)}</span>` : '');
        wrap.appendChild(meta);

        const close = document.createElement('button');
        close.type = 'button'; close.className = 'cse__list-item__del'; close.title = 'Remove';
        close.innerHTML = '<i class="fa-solid fa-xmark"></i>';
        close.addEventListener('click', () => { wrap.remove(); if (wrap._syncEmpty) wrap._syncEmpty(); queueSave(); });
        wrap.appendChild(close);

        const hidden = document.createElement('input');
        hidden.type = 'hidden'; hidden.dataset.key = '_value'; hidden.value = String(course.id);
        wrap.appendChild(hidden);
        return wrap;
    }
    function initCoursePicker(wrap, list, selectedIds) {
        const input = wrap.querySelector('.cse__cp-input');
        const results = wrap.querySelector('.cse__cp-results');
        const empty = wrap.querySelector('.cse__cp-empty');
        const selectedSet = () => new Set(Array.from(list.querySelectorAll('input[data-key="_value"]')).map(i => String(i.value)));
        const syncEmpty = () => { empty.style.display = list.querySelector('.cse__cp-item') ? 'none' : ''; };

        // Course list loads async; keep a live reference + a byId map. Listeners
        // are attached IMMEDIATELY (not inside the fetch) so a coach who types
        // before the list loads still gets results once it arrives.
        let courses = [];
        const byId = {};

        function renderResults(q) {
            const sel = selectedSet();
            const ql = q.toLowerCase();
            const matches = courses.filter(c => !sel.has(String(c.id)))
                .filter(c => !ql || (c.title || '').toLowerCase().includes(ql)).slice(0, 12);
            if (!matches.length) {
                results.innerHTML = `<div class="cse__cp-noresult">${q ? '{{ __('No matching courses') }}' : (courses.length ? '{{ __('All courses added') }}' : '{{ __('Loading…') }}')}</div>`;
            } else {
                results.innerHTML = matches.map(c =>
                    `<button type="button" class="cse__cp-result" data-id="${c.id}">
                        <span class="cse__cp-thumb">${c.thumb ? `<img src="${escapeAttr(c.thumb)}" alt="">` : '<i class="fa-solid fa-image"></i>'}</span>
                        <span class="cse__cp-title">${escapeHtml(c.title || ('Course #' + c.id))}</span>
                        ${c.mode ? `<span class="cse__cp-mode">${escapeHtml(c.mode)}</span>` : ''}
                    </button>`).join('');
            }
            results.hidden = false;
            results.querySelectorAll('.cse__cp-result').forEach(btn => btn.addEventListener('click', () => {
                const c = byId[String(btn.dataset.id)];
                if (c && !selectedSet().has(String(c.id))) {
                    const it = buildCoursePickerItem(c); it._syncEmpty = syncEmpty; list.appendChild(it);
                    syncEmpty(); queueSave();
                }
                renderResults(input.value.trim());
            }));
        }

        input.addEventListener('focus', () => renderResults(input.value.trim()));
        input.addEventListener('input', () => renderResults(input.value.trim()));
        document.addEventListener('click', (e) => { if (!wrap.contains(e.target)) results.hidden = true; });

        cpLoadCourses().then(loaded => {
            courses = loaded;
            loaded.forEach(c => byId[String(c.id)] = c);
            // Render already-selected courses (stored order), skipping any that
            // are no longer published.
            (selectedIds || []).forEach(id => {
                const c = byId[String(id)];
                if (c) { const it = buildCoursePickerItem(c); it._syncEmpty = syncEmpty; list.appendChild(it); }
            });
            syncEmpty();
            // If the coach already has the search focused, refresh now.
            if (document.activeElement === input) renderResults(input.value.trim());
        });
    }

    function queueSave() {
        clearTimeout(saveTimer);
        setSave('saving');
        saveTimer = setTimeout(actuallySave, 700);
    }
    function actuallySave() {
        if (!activeSectionId) return;
        const body = $('[data-panel-body]');
        const content = serializeForm(body);
        api(URLs.updateSection + '/' + activeSectionId, { method:'PUT', body: JSON.stringify({ content }) })
            .then(r => { if (r.ok) { setSave('saved'); refreshPreview(); } else setSave('error'); })
            .catch(() => setSave('error'));
    }
    function serializeForm(root) {
        const out = {};
        root.querySelectorAll(':scope > .cse__field').forEach(field => {
            const list = field.querySelector(':scope > [data-list-for]');
            if (list) {
                out[list.dataset.listFor] = serializeList(list);
                return;
            }
            // Look for input/textarea/select WITH NAME — descendant search
            // (the image hidden input is nested inside .cse__upload, not a
            // direct child of .cse__field). Take the FIRST match so we don't
            // grab inputs inside any unrelated nested structure.
            const inp = field.querySelector('input[name], textarea[name], select[name]');
            if (!inp) return;
            out[inp.name] = (inp.type === 'checkbox') ? inp.checked : inp.value;
        });
        return out;
    }

    /**
     * Recursively serialize a list container (top-level `[data-list-for]`
     * OR nested `[data-nested-list]` inside list-items). Walks only DIRECT
     * children so nested lists serialize correctly (e.g. footer's
     * link_groups[].links[]).
     */
    function serializeList(listEl) {
        const itemType = listEl.dataset.itemType;
        const items = [];
        listEl.querySelectorAll(':scope > .cse__list-item').forEach(li => {
            if (itemType === 'object') {
                const obj = {};
                li.querySelectorAll(':scope > .cse__field').forEach(f => {
                    // Nested list?
                    const nested = f.querySelector(':scope > [data-nested-list]');
                    if (nested) { obj[nested.dataset.nestedList] = serializeList(nested); return; }
                    // Image upload?
                    const hidden = f.querySelector(':scope > .cse__upload > input[type=hidden][data-key]');
                    if (hidden) { obj[hidden.dataset.key] = hidden.value; return; }
                    // Regular input / textarea / select
                    const i = f.querySelector(':scope > input[data-key], :scope > textarea[data-key], :scope > select[data-key]');
                    if (i) obj[i.dataset.key] = (i.type === 'checkbox') ? i.checked : i.value;
                });
                items.push(obj);
            } else {
                const v = li.querySelector(':scope > input[data-key], :scope > textarea[data-key], :scope > select[data-key]');
                if (v && v.value !== '') items.push(v.value);
            }
        });
        return items;
    }
    function escapeHtml(s) { return String(s ?? '').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }
    function escapeAttr(s) { return escapeHtml(s).replace(/'/g, '&#39;'); }

    // ─────────────────────────────────────────────────────
    // ONBOARDING TOUR (6-step spotlight walkthrough)
    // ─────────────────────────────────────────────────────
    const TOUR_KEY = 'cse:tour:dismissed';
    const TOUR_STEPS = [
        { sel: '[data-open-drawer]', title: '{{ __("Add sections here") }}', body: '{{ __("Click + to add Hero, About, Services, FAQ etc. Each section is a block on your page.") }}', pos: 'right' },
        { sel: '.cse__sec',          title: '{{ __("Click any section to edit") }}', body: '{{ __("Click on any section row to change text, images, and buttons in the right panel.") }}', pos: 'right' },
        { sel: '.cse__tabs',         title: '{{ __("4 tabs to manage your site") }}', body: '{{ __("Sections — content. Pages — multi-page. Site — branding & buttons. SEO — Google search.") }}', pos: 'bottom' },
        { sel: '.cse__devices',      title: '{{ __("Check mobile view") }}', body: '{{ __("Toggle between Desktop and Mobile preview. Most of your visitors are on phone.") }}', pos: 'bottom' },
        { sel: '#cseChecklist',      title: '{{ __("Your progress") }}', body: '{{ __("This checklist shows what is done and what is pending. Aim for 100%.") }}', pos: 'top' },
        { sel: '[data-publish]',     title: '{{ __("Publish when ready") }}', body: '{{ __("When your page looks good, click Publish to make it live. You can also unpublish anytime.") }}', pos: 'bottom' },
    ];
    let tourIdx = 0;
    function startTour(force = false) {
        if (!force && localStorage.getItem(TOUR_KEY) === '1') return;
        tourIdx = 0;
        $('#cseTour').hidden = false;
        document.body.style.overflow = 'hidden';
        positionTourStep();
    }
    function positionTourStep() {
        const step = TOUR_STEPS[tourIdx];
        const target = step.sel ? $(step.sel) : null;
        if (!target) {
            // Selector missing on this page — skip step
            return nextTourStep();
        }
        const rect = target.getBoundingClientRect();
        const pad = 8;
        const spot = $('#cseTourSpot');
        spot.style.top    = (rect.top - pad) + 'px';
        spot.style.left   = (rect.left - pad) + 'px';
        spot.style.width  = (rect.width + pad * 2) + 'px';
        spot.style.height = (rect.height + pad * 2) + 'px';

        // Card positioning relative to target
        const card = $('#cseTourCard');
        const cardW = 340, cardH = 200;
        let top, left;
        if (step.pos === 'right') { top = rect.top; left = rect.right + 20; }
        else if (step.pos === 'left') { top = rect.top; left = rect.left - cardW - 20; }
        else if (step.pos === 'top')   { top = rect.top - cardH - 20; left = Math.max(20, rect.left); }
        else                            { top = rect.bottom + 20; left = Math.max(20, rect.left); }
        if (left + cardW > window.innerWidth - 20)  left = window.innerWidth - cardW - 20;
        if (top + cardH > window.innerHeight - 20)  top = window.innerHeight - cardH - 20;
        if (top < 20) top = 20;
        if (left < 20) left = 20;
        card.style.top  = top + 'px';
        card.style.left = left + 'px';

        $('[data-tour-num]').textContent = (tourIdx + 1);
        $('[data-tour-total]').textContent = TOUR_STEPS.length;
        $('[data-tour-title]').textContent = step.title;
        $('[data-tour-body]').textContent  = step.body;
        const nextBtn = $('[data-tour-next]');
        nextBtn.innerHTML = (tourIdx === TOUR_STEPS.length - 1)
            ? '<i class="fa-solid fa-check"></i> {{ __("Got it") }}'
            : '{{ __("Next") }} <i class="fa-solid fa-arrow-right"></i>';
    }
    function nextTourStep() {
        if (tourIdx < TOUR_STEPS.length - 1) { tourIdx++; positionTourStep(); }
        else endTour();
    }
    function endTour() {
        $('#cseTour').hidden = true;
        document.body.style.overflow = '';
        localStorage.setItem(TOUR_KEY, '1');
    }
    $('[data-tour-next]')?.addEventListener('click', nextTourStep);
    $('[data-tour-skip]')?.addEventListener('click', endTour);
    $$('[data-tour-start]').forEach(b => b.addEventListener('click', () => startTour(true)));
    // Auto-start on first visit
    setTimeout(() => startTour(false), 800);

    // ─────────────────────────────────────────────────────
    // CHECKLIST toggle (collapse / expand)
    // ─────────────────────────────────────────────────────
    const CL_KEY = 'cse:checklist:collapsed';
    const cl = $('#cseChecklist');
    if (cl) {
        if (localStorage.getItem(CL_KEY) === '1') cl.dataset.collapsed = 'true';
        $('[data-checklist-toggle]')?.addEventListener('click', () => {
            const isCol = cl.dataset.collapsed === 'true';
            cl.dataset.collapsed = isCol ? 'false' : 'true';
            localStorage.setItem(CL_KEY, isCol ? '0' : '1');
        });
    }

    // ── Starter pack (one-click add common sections) ────
    $$('[data-starter-pack]').forEach(b => b.addEventListener('click', function() {
        const pack = this.dataset.starterPack || 'default';
        setSave('saving', 'Adding sections…');
        const fd = new FormData(); fd.append('pack', pack); fd.append('_token', CSRF);
        fetch("{{ url('instructor/web-page/pages/' . $page->id . '/starter-pack') }}", {
            method: 'POST', body: fd, credentials: 'same-origin',
        }).then(r => r.json()).then(d => {
            if (d.ok) { setSave('saved', `Added ${d.added} section${d.added === 1 ? '' : 's'}`); location.reload(); }
            else setSave('error');
        }).catch(() => setSave('error'));
    }));

    // ── Add page (inline modal in editor) ───────────────
    const addPageForm = document.getElementById('cseAddPageForm');
    if (addPageForm) {
        addPageForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const fd = new FormData(this);
            createPage(fd.get('page_type'), fd.get('title'));
        });
    }
    // Quick-add chips for suggested pages
    document.querySelectorAll('[data-add-page-type]').forEach(b => b.addEventListener('click', function() {
        createPage(this.dataset.addPageType, this.dataset.addPageTitle);
    }));
    function createPage(pageType, title) {
        setSave('saving', 'Creating page…');
        const fd = new FormData();
        fd.append('page_type', pageType);
        fd.append('title', title);
        fd.append('_token', CSRF);
        fetch("{{ route('instructor.web-page.pages.store') }}", {
            method: 'POST', body: fd, credentials: 'same-origin',
        }).then(r => {
            // The endpoint redirects to editor.edit — follow it
            if (r.redirected) { window.location.href = r.url; return; }
            return r.text();
        }).then(() => {
            // Fallback: refresh to show the new page in Pages tab
            location.reload();
        }).catch(() => setSave('error'));
    }

    // ── Tabs ────────────────────────────────────────────
    $$('.cse__tab').forEach(t => t.addEventListener('click', () => {
        const target = t.dataset.tab;
        $$('.cse__tab').forEach(x => x.classList.remove('cse__tab--active'));
        t.classList.add('cse__tab--active');
        $$('.cse__tab-content').forEach(c => { c.hidden = c.dataset.tabContent !== target; });
    }));

    // ── Section: duplicate ──────────────────────────────
    document.addEventListener('click', function(e) {
        const dup = e.target.closest('[data-dup-section]');
        if (dup) {
            e.stopPropagation();
            const id = dup.dataset.dupSection;
            setSave('saving', 'Duplicating…');
            api(URLs.dupSection + '/' + id + '/duplicate')
                .then(r => { if (r.ok) { setSave('saved'); location.reload(); } else setSave('error'); });
            return;
        }
        const togV = e.target.closest('[data-toggle-visible]');
        if (togV) {
            e.stopPropagation();
            const id = togV.dataset.toggleVisible;
            setSave('saving');
            api(URLs.toggleVisible + '/' + id + '/toggle-visible')
                .then(r => { if (r.ok) { setSave('saved'); location.reload(); } else setSave('error'); });
            return;
        }
    });

    // ── Pages tab: nav toggle ───────────────────────────
    document.querySelectorAll('[data-nav-toggle]').forEach(inp => {
        inp.addEventListener('change', function() {
            const id = this.dataset.navToggle;
            setSave('saving');
            api(URLs.pageNav + '/' + id + '/nav', {
                body: JSON.stringify({ is_visible_in_nav: this.checked }),
            }).then(r => setSave(r.ok ? 'saved' : 'error'));
        });
    });

    // ── Pages tab: per-page global-footer toggle ────────
    document.querySelectorAll('[data-footer-toggle]').forEach(inp => {
        inp.addEventListener('change', function() {
            const id = this.dataset.footerToggle;
            setSave('saving');
            api(URLs.pageNav + '/' + id + '/nav', {
                body: JSON.stringify({ use_global_footer: this.checked }),
            }).then(r => setSave(r.ok ? 'saved' : 'error'));
        });
    });

    // ── Site settings autosave (debounced 700ms) ────────
    let ssTimer = null;
    function queueSiteSettingsSave() {
        clearTimeout(ssTimer);
        setSave('saving');
        ssTimer = setTimeout(() => {
            const payload = {};
            $$('[data-ss]').forEach(el => {
                const key = el.dataset.ss;
                payload[key] = (el.type === 'checkbox') ? el.checked : el.value;
            });
            // footer config sub-keys
            const footer = {};
            $$('[data-ss-footer]').forEach(el => { footer[el.dataset.ssFooter] = el.value; });
            if (Object.keys(footer).length) payload.footer_config = footer;
            // typography sub-keys ({role}_{device} => px). Always send the full
            // map (blank = null) so clearing a value persists as "use default".
            const typo = {};
            $$('[data-ss-typo]').forEach(el => {
                const v = (el.value || '').trim();
                typo[el.dataset.ssTypo] = v === '' ? null : parseInt(v, 10);
            });
            if (Object.keys(typo).length) payload.typography_config = typo;
            api(URLs.siteSettings, { body: JSON.stringify(payload) })
                .then(r => { setSave(r.ok ? 'saved' : 'error'); refreshPreview(); });
        }, 700);
    }
    $$('[data-ss], [data-ss-footer], [data-ss-typo]').forEach(el => {
        el.addEventListener('input', queueSiteSettingsSave);
        el.addEventListener('change', queueSiteSettingsSave);
    });

    // Typography "Reset to defaults" — clear every typo input then save.
    $$('[data-typo-reset]').forEach(btn => btn.addEventListener('click', () => {
        $$('[data-ss-typo]').forEach(el => { el.value = ''; });
        queueSiteSettingsSave();
    }));

    // Site settings file uploads
    $$('[data-ss-upload]').forEach(inp => {
        inp.addEventListener('change', async function(e) {
            const file = this.files[0]; if (!file) return;
            const key = this.dataset.ssUpload;
            const fd = new FormData(); fd.append('file', file); fd.append('_token', CSRF);
            setSave('saving', 'Uploading…');
            const res = await fetch(URLs.media, { method:'POST', body:fd, credentials:'same-origin' }).then(r=>r.json());
            if (res.url) {
                const target = document.querySelector(`input[type=hidden][data-ss="${key}"]`);
                if (target) target.value = res.url;
                const wrap = this.parentElement;
                let img = wrap.querySelector('img.cse__upload-preview');
                if (!img) {
                    const empty = wrap.querySelector('.cse__upload-empty'); if (empty) empty.remove();
                    img = document.createElement('img'); img.className = 'cse__upload-preview'; wrap.prepend(img);
                }
                img.src = res.url;
                queueSiteSettingsSave();
            }
        });
    });

    // ── SEO tab: per-page meta autosave ─────────────────
    let metaTimer = null;
    $$('[data-page-meta]').forEach(el => {
        el.addEventListener('input', () => {
            clearTimeout(metaTimer);
            setSave('saving');
            metaTimer = setTimeout(() => {
                const payload = {};
                $$('[data-page-meta]').forEach(x => { payload[x.dataset.pageMeta] = x.value; });
                api(URLs.updatePage, { method: 'PUT', body: JSON.stringify(payload) })
                    .then(r => { setSave(r.ok ? 'saved' : 'error'); refreshPreview(); });
            }, 700);
        });
    });
    $$('[data-page-meta-upload]').forEach(inp => {
        inp.addEventListener('change', async function() {
            const file = this.files[0]; if (!file) return;
            const key = this.dataset.pageMetaUpload;
            const fd = new FormData(); fd.append('file', file); fd.append('_token', CSRF);
            setSave('saving', 'Uploading…');
            const res = await fetch(URLs.media, { method:'POST', body:fd, credentials:'same-origin' }).then(r=>r.json());
            if (res.url) {
                const target = document.querySelector(`input[type=hidden][data-page-meta="${key}"]`);
                if (target) target.value = res.url;
                const wrap = this.parentElement;
                let img = wrap.querySelector('img.cse__upload-preview');
                if (!img) {
                    const empty = wrap.querySelector('.cse__upload-empty'); if (empty) empty.remove();
                    img = document.createElement('img'); img.className = 'cse__upload-preview'; wrap.prepend(img);
                }
                img.src = res.url;
                const payload = {}; payload[key] = res.url;
                api(URLs.updatePage, { method:'PUT', body: JSON.stringify(payload) })
                    .then(r => { setSave(r.ok ? 'saved' : 'error'); refreshPreview(); });
            }
        });
    });

    // Page slug manual edit
    const slugInput = $('[data-page-slug]');
    if (slugInput) {
        slugInput.addEventListener('blur', function() {
            const newSlug = (this.value || '').trim();
            if (!newSlug || !/^[a-z0-9-]+$/.test(newSlug)) {
                this.value = "{{ $page->slug }}";
                alert('Slug must contain only lowercase letters, numbers, and hyphens.');
                return;
            }
            setSave('saving');
            fetch("{{ url('instructor/web-page/pages/' . $page->id . '/slug') }}", {
                method: 'PUT',
                headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept':'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ slug: newSlug }),
            }).then(r => r.json()).then(d => {
                if (d.ok) { setSave('saved'); refreshPreview(); }
                else { setSave('error'); alert(d.error || 'Failed'); this.value = "{{ $page->slug }}"; }
            });
        });
    }

    // Drag-reorder
    const list = $('#cseSections');
    let dragEl = null;
    if (list) {
        list.addEventListener('dragstart', e => {
            const sec = e.target.closest('.cse__sec'); if (!sec) return;
            dragEl = sec; sec.style.opacity = '.4';
        });
        list.addEventListener('dragend', () => {
            if (dragEl) dragEl.style.opacity = '';
            dragEl = null;
            const order = $$('.cse__sec', list).map(x => x.dataset.sectionId);
            api(URLs.reorder, { body: JSON.stringify({ order }) }).then(refreshPreview);
        });
        list.addEventListener('dragover', e => {
            e.preventDefault();
            const target = e.target.closest('.cse__sec');
            if (!target || target === dragEl) return;
            const rect = target.getBoundingClientRect();
            const after = (e.clientY - rect.top) > rect.height / 2;
            target.parentNode.insertBefore(dragEl, after ? target.nextSibling : target);
        });
    }
})();
</script>

{{-- 2026-06-01 RWD-4: drive the mobile rail drawer. On phones the rail
     (Sections / Pages / Site / SEO) is off-canvas; the topbar hamburger
     opens it and the backdrop / Escape / picking a page closes it. The
     CSS classes (.cse__rail--open / .cse__rail-mask--open) already exist;
     this only wires the open/close. --}}
<script>
(function () {
    var rail   = document.querySelector('.cse__rail');
    var mask   = document.querySelector('[data-rail-mask]');
    var toggle = document.querySelector('[data-rail-toggle]');
    if (!rail || !toggle) return;

    function openRail()  { rail.classList.add('cse__rail--open');    if (mask) mask.classList.add('cse__rail-mask--open'); }
    function closeRail() { rail.classList.remove('cse__rail--open'); if (mask) mask.classList.remove('cse__rail-mask--open'); }

    toggle.addEventListener('click', function () {
        rail.classList.contains('cse__rail--open') ? closeRail() : openRail();
    });
    if (mask) mask.addEventListener('click', closeRail);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && rail.classList.contains('cse__rail--open')) closeRail();
    });
    // Tapping a page link navigates away anyway; close so the canvas shows
    // through during the load on phones.
    rail.querySelectorAll('a[href]').forEach(function (a) {
        a.addEventListener('click', function () {
            if (window.innerWidth <= 768) closeRail();
        });
    });
})();
</script>

</body>
</html>
