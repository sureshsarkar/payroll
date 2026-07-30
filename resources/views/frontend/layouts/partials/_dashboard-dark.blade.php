{{--
    2026-07-10 (New Changes for UI #4) — application-wide Dark Mode.

    Dark mode was previously only styled for the header. This partial extends
    it across the whole Coach / Staff / Student dashboard by:

      1. Re-defining the design-system SURFACE tokens (--corp-* and the sidebar
         --csb-* / --text-* set) under `html[data-theme="dark"]`. Because every
         corp component (cards, tables, KPIs, pills, forms, the sidebar) is built
         on these tokens, overriding the tokens re-tints the entire panel for
         free — no per-component edits. We deliberately do NOT touch the brand
         accent tokens (--corp-brand*, --accent*) so each coach's white-label
         colour is preserved in dark mode too.

      2. Explicit dark rules for the non-tokenised Bootstrap primitives that
         legacy pages (course create/edit, coupons, fees, live classes) inherit
         from the public theme — form controls, cards, tables, modals, dropdowns,
         pagination, tabs, list groups, alerts. All scoped under `.dashboard__aread`
         so the public marketing site is never affected.

    Specificity note: token overrides use `html[data-theme="dark"]` (0,1,1) which
    outranks both `:root` (0,1,0) and the bare `[data-theme="dark"]` used by the
    topbar (0,1,0), so it wins regardless of where the light :root blocks render
    (the sidebar defines its :root mid-body). The pre-paint boot script in the
    base master already sets data-theme before first paint, so there is no light
    flash; the preference persists via localStorage('mbs-theme').

    Loaded from the base master head, so it applies to every panel page.
--}}
<style>
/* ===== 1. Surface token overrides (drive the whole design system) ===== */
html[data-theme="dark"] {
    /* corp design-system surfaces */
    --corp-bg:        #0f172a;
    --corp-card:      #1e293b;
    --corp-card-grad: linear-gradient(180deg, #1e293b 0%, #1b2637 100%);
    --corp-line:      rgba(148, 163, 184, 0.18);
    --corp-line-soft: rgba(148, 163, 184, 0.10);

    --corp-text:      #e2e8f0;
    --corp-text-2:    #cbd5e1;
    --corp-muted:     #94a3b8;
    --corp-subtle:    #7c8aa0;

    /* Shadows: drop the white "glass" inset (wrong on dark) for soft ambient. */
    --corp-shadow-sm: 0 1px 2px rgba(0,0,0,0.35), 0 2px 6px -2px rgba(0,0,0,0.30);
    --corp-shadow-md: 0 4px 12px -3px rgba(0,0,0,0.45), 0 8px 24px -8px rgba(0,0,0,0.40);
    --corp-shadow-lg: 0 12px 32px -8px rgba(0,0,0,0.55), 0 24px 60px -16px rgba(0,0,0,0.50);

    /* sidebar (--csb-*) surfaces + text — accent tokens left untouched */
    --csb-bg:          #111c2e;
    --csb-surface:     #17233a;
    --csb-surface2:    #1e2c46;
    --csb-border:      #2a3a55;
    --csb-border-soft: #223049;
    --text:            #e2e8f0;
    --text-sub:        #a3b0c2;
    --text-muted:      #7c8aa0;
    --text-label:      #64748b;
    --hover-bg:        #1c2942;
    --active-bg:       rgba(16, 185, 129, 0.16);

    color-scheme: dark;
}

/* ===== 2. Page + generic dashboard surface ===== */
html[data-theme="dark"] body { background: #0f172a; color: #e2e8f0; }
html[data-theme="dark"] .dashboard__aread { background: #0f172a; }
html[data-theme="dark"] .corp-page { color: var(--corp-text); }

/* Preloader card / misc white panels */
html[data-theme="dark"] .dashboard__aread .white-bg,
html[data-theme="dark"] .dashboard__aread .bg-white { background-color: #1e293b !important; }

/* ===== 3. Bootstrap primitives on legacy pages (scoped to the panel) ===== */
html[data-theme="dark"] .dashboard__aread .card,
html[data-theme="dark"] .dashboard__aread .list-group-item {
    background-color: #1e293b;
    border-color: #2a3a55;
    color: #e2e8f0;
}
html[data-theme="dark"] .dashboard__aread .card-header,
html[data-theme="dark"] .dashboard__aread .card-footer {
    background-color: #17233a;
    border-color: #2a3a55;
    color: #e2e8f0;
}

/* Forms — inputs / selects / textareas / file inputs */
html[data-theme="dark"] .dashboard__aread .form-control,
html[data-theme="dark"] .dashboard__aread .form-select,
html[data-theme="dark"] .dashboard__aread textarea,
html[data-theme="dark"] .dashboard__aread .input-group-text,
html[data-theme="dark"] .dashboard__aread .select2-selection {
    background-color: #17233a;
    border-color: #2a3a55;
    color: #e2e8f0;
}
html[data-theme="dark"] .dashboard__aread .form-control::placeholder,
html[data-theme="dark"] .dashboard__aread textarea::placeholder { color: #7c8aa0; }
html[data-theme="dark"] .dashboard__aread .form-control:focus,
html[data-theme="dark"] .dashboard__aread .form-select:focus,
html[data-theme="dark"] .dashboard__aread textarea:focus {
    background-color: #17233a;
    color: #f1f5f9;
    border-color: var(--corp-brand);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--corp-brand) 22%, transparent);
}
html[data-theme="dark"] .dashboard__aread .form-control:disabled,
html[data-theme="dark"] .dashboard__aread .form-select:disabled,
html[data-theme="dark"] .dashboard__aread .form-control[readonly] {
    background-color: #131e30;
    color: #7c8aa0;
    opacity: 1;
}
html[data-theme="dark"] .dashboard__aread .form-label,
html[data-theme="dark"] .dashboard__aread label,
html[data-theme="dark"] .dashboard__aread .form-check-label { color: #cbd5e1; }
html[data-theme="dark"] .dashboard__aread .form-text,
html[data-theme="dark"] .dashboard__aread small.text-muted,
html[data-theme="dark"] .dashboard__aread .text-muted { color: #94a3b8 !important; }

/* Tables (Bootstrap + any raw <table>) */
html[data-theme="dark"] .dashboard__aread table:not(.corp-table) {
    color: #e2e8f0;
}
html[data-theme="dark"] .dashboard__aread .table,
html[data-theme="dark"] .dashboard__aread .table > :not(caption) > * > * {
    background-color: transparent;
    border-color: #2a3a55;
    color: #e2e8f0;
}
html[data-theme="dark"] .dashboard__aread .table thead th,
html[data-theme="dark"] .dashboard__aread thead th { background-color: #17233a; color: #cbd5e1; }
html[data-theme="dark"] .dashboard__aread .table-striped > tbody > tr:nth-of-type(odd) > * {
    background-color: rgba(148, 163, 184, 0.05);
    color: #e2e8f0;
}
html[data-theme="dark"] .dashboard__aread .table-hover > tbody > tr:hover > * {
    background-color: rgba(148, 163, 184, 0.10);
    color: #f1f5f9;
}

/* Dropdowns / menus / popovers */
html[data-theme="dark"] .dashboard__aread .dropdown-menu,
html[data-theme="dark"] .dashboard__aread .popover {
    background-color: #1e293b;
    border-color: #2a3a55;
    box-shadow: 0 10px 30px rgba(0,0,0,0.5);
}
html[data-theme="dark"] .dashboard__aread .dropdown-item { color: #cbd5e1; }
html[data-theme="dark"] .dashboard__aread .dropdown-item:hover,
html[data-theme="dark"] .dashboard__aread .dropdown-item:focus { background-color: #2a3a55; color: #fff; }
html[data-theme="dark"] .dashboard__aread .dropdown-divider { border-color: #2a3a55; }

/* Modals + offcanvas */
html[data-theme="dark"] .modal-content,
html[data-theme="dark"] .offcanvas {
    background-color: #1e293b;
    border-color: #2a3a55;
    color: #e2e8f0;
}
html[data-theme="dark"] .modal-header,
html[data-theme="dark"] .modal-footer { border-color: #2a3a55; }
html[data-theme="dark"] .modal-backdrop.show { opacity: 0.65; }
html[data-theme="dark"] .btn-close { filter: invert(1) grayscale(1) brightness(1.6); }

/* Pagination */
html[data-theme="dark"] .dashboard__aread .page-link {
    background-color: #17233a;
    border-color: #2a3a55;
    color: #cbd5e1;
}
html[data-theme="dark"] .dashboard__aread .page-link:hover { background-color: #2a3a55; color: #fff; }
html[data-theme="dark"] .dashboard__aread .page-item.disabled .page-link {
    background-color: #131e30; color: #64748b; border-color: #2a3a55;
}
html[data-theme="dark"] .dashboard__aread .page-item.active .page-link {
    background-color: var(--corp-brand); border-color: var(--corp-brand); color: #fff;
}

/* Tabs + nav pills */
html[data-theme="dark"] .dashboard__aread .nav-tabs { border-bottom-color: #2a3a55; }
html[data-theme="dark"] .dashboard__aread .nav-tabs .nav-link { color: #94a3b8; }
html[data-theme="dark"] .dashboard__aread .nav-tabs .nav-link.active {
    background-color: #1e293b; border-color: #2a3a55 #2a3a55 #1e293b; color: #f1f5f9;
}

/* Secondary / light buttons that were near-white on light */
html[data-theme="dark"] .dashboard__aread .btn-light,
html[data-theme="dark"] .dashboard__aread .btn-outline-secondary,
html[data-theme="dark"] .dashboard__aread .btn-corp-secondary {
    background-color: #1e293b;
    border-color: #2a3a55;
    color: #e2e8f0;
}
html[data-theme="dark"] .dashboard__aread .btn-light:hover,
html[data-theme="dark"] .dashboard__aread .btn-outline-secondary:hover,
html[data-theme="dark"] .dashboard__aread .btn-corp-secondary:hover {
    background-color: #2a3a55; color: #fff;
}

/* Alerts — keep semantic hue, darken the ground so text stays readable */
html[data-theme="dark"] .dashboard__aread .alert { border-color: #2a3a55; }
html[data-theme="dark"] .dashboard__aread .alert-secondary,
html[data-theme="dark"] .dashboard__aread .alert-light {
    background-color: #17233a; color: #e2e8f0;
}

/* Semantic text helpers stay legible on dark */
html[data-theme="dark"] .dashboard__aread .text-dark,
html[data-theme="dark"] .dashboard__aread .text-body,
html[data-theme="dark"] .dashboard__aread .text-black { color: #e2e8f0 !important; }

/* Empty / loading states */
html[data-theme="dark"] .dashboard__aread .corp-empty__hint,
html[data-theme="dark"] .dashboard__aread .spinner-border { color: #94a3b8; }

/* Horizontal rules + generic borders */
html[data-theme="dark"] .dashboard__aread hr { border-color: #2a3a55; }
html[data-theme="dark"] .dashboard__aread .border,
html[data-theme="dark"] .dashboard__aread .border-top,
html[data-theme="dark"] .dashboard__aread .border-bottom,
html[data-theme="dark"] .dashboard__aread .border-start,
html[data-theme="dark"] .dashboard__aread .border-end { border-color: #2a3a55 !important; }

/* Corp pill neutral variant in dark */
html[data-theme="dark"] .corp-pill--muted { background: #2a3a55; color: #cbd5e1; }
</style>
