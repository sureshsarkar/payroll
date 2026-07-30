{{-- Shared enterprise styling for the Tax area (settings + report). Scoped to
     .tax-ui so it is independent of the theme's default form/table styles. --}}
<style>
    .tax-ui { --tx-accent:#10b981; --tx-accent-soft:#ecfdf5; --tx-line:#e5e7eb; --tx-ink:#0f172a; --tx-muted:#64748b; --tx-bg:#ffffff; }
    .tax-ui *{ box-sizing:border-box; }
    .tax-ui .tx-head{ display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:18px; }
    .tax-ui .tx-head h2{ font-size:22px; font-weight:800; color:var(--tx-ink); margin:0; letter-spacing:-.01em; }
    .tax-ui .tx-head p{ margin:2px 0 0; font-size:13px; color:var(--tx-muted); }
    .tax-ui .tx-badge{ display:inline-flex; align-items:center; gap:6px; padding:5px 12px; border-radius:999px; font-size:12px; font-weight:700; }
    .tax-ui .tx-badge--on{ background:#ecfdf5; color:#059669; }
    .tax-ui .tx-badge--off{ background:#f1f5f9; color:#64748b; }
    .tax-ui .tx-btn{ display:inline-flex; align-items:center; gap:8px; padding:10px 18px; border-radius:10px; font-size:14px; font-weight:600; border:1px solid transparent; cursor:pointer; text-decoration:none; transition:all .15s ease; }
    .tax-ui .tx-btn--primary{ background:var(--tx-accent); color:#fff; box-shadow:0 1px 2px rgba(16, 185, 129,.3); }
    .tax-ui .tx-btn--primary:hover{ background:#4f46e5; color:#fff; }
    .tax-ui .tx-btn--ghost{ background:#fff; color:var(--tx-ink); border-color:var(--tx-line); }
    .tax-ui .tx-btn--ghost:hover{ background:#f8fafc; }
    .tax-ui .tx-btn--sm{ padding:7px 13px; font-size:13px; }
    .tax-ui .tx-card{ background:var(--tx-bg); border:1px solid var(--tx-line); border-radius:16px; box-shadow:0 1px 3px rgba(15,23,42,.04); margin-bottom:22px; overflow:hidden; }
    .tax-ui .tx-card__head{ display:flex; align-items:center; gap:12px; padding:18px 22px; border-bottom:1px solid var(--tx-line); }
    .tax-ui .tx-card__ic{ width:38px; height:38px; border-radius:10px; display:inline-flex; align-items:center; justify-content:center; background:var(--tx-accent-soft); color:var(--tx-accent); font-size:17px; flex:0 0 38px; }
    .tax-ui .tx-card__title{ font-size:16px; font-weight:700; color:var(--tx-ink); margin:0; }
    .tax-ui .tx-card__sub{ font-size:12.5px; color:var(--tx-muted); margin:2px 0 0; }
    .tax-ui .tx-card__body{ padding:22px; }
    /* Toggle */
    .tax-ui .tx-toggle-row{ display:flex; align-items:center; gap:14px; padding:14px 16px; background:#f8fafc; border:1px solid var(--tx-line); border-radius:12px; margin-bottom:22px; }
    .tax-ui .tx-switch{ position:relative; width:46px; height:26px; flex:0 0 46px; }
    .tax-ui .tx-switch input{ opacity:0; width:0; height:0; }
    .tax-ui .tx-switch .tx-slider{ position:absolute; inset:0; background:#cbd5e1; border-radius:999px; transition:.2s; }
    .tax-ui .tx-switch .tx-slider:before{ content:""; position:absolute; height:20px; width:20px; left:3px; top:3px; background:#fff; border-radius:50%; transition:.2s; box-shadow:0 1px 2px rgba(0,0,0,.2); }
    .tax-ui .tx-switch input:checked + .tx-slider{ background:var(--tx-accent); }
    .tax-ui .tx-switch input:checked + .tx-slider:before{ transform:translateX(20px); }
    .tax-ui .tx-toggle-row .tx-tt{ font-weight:700; color:var(--tx-ink); font-size:14.5px; }
    .tax-ui .tx-toggle-row .tx-ts{ font-size:12.5px; color:var(--tx-muted); }
    /* Grid + fields */
    .tax-ui .tx-grid{ display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px 18px; }
    .tax-ui .tx-field{ display:flex; flex-direction:column; gap:6px; min-width:0; }
    .tax-ui .tx-field.tx-col-2{ grid-column:1 / -1; }
    .tax-ui .tx-field label{ font-size:13px; font-weight:600; color:#334155; margin:0; }
    .tax-ui .tx-field label .req{ color:#ef4444; }
    .tax-ui .tx-field .tx-hint{ font-size:12px; color:var(--tx-muted); }
    .tax-ui .tx-input, .tax-ui select.tx-input{ width:100%; padding:10px 13px; border:1px solid #d1d5db; border-radius:10px; font-size:14px; color:var(--tx-ink); background:#fff; transition:border-color .15s, box-shadow .15s; }
    .tax-ui .tx-input:focus, .tax-ui select.tx-input:focus{ outline:none; border-color:var(--tx-accent); box-shadow:0 0 0 3px rgba(16, 185, 129,.15); }
    .tax-ui .tx-input::placeholder{ color:#9ca3af; }
    .tax-ui .tx-actions{ display:flex; justify-content:flex-end; gap:10px; margin-top:22px; padding-top:18px; border-top:1px solid var(--tx-line); }
    /* Add-rate panel */
    .tax-ui .tx-addrate{ background:#f8fafc; border:1px solid var(--tx-line); border-radius:12px; padding:16px; margin-bottom:18px; }
    .tax-ui .tx-addrate .tx-grid{ gap:14px 16px; }
    .tax-ui .tx-addrate__foot{ display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-top:14px; }
    .tax-ui .tx-check{ display:inline-flex; align-items:center; gap:8px; font-size:13.5px; color:#334155; font-weight:600; cursor:pointer; white-space:nowrap; }
    .tax-ui .tx-check input{ width:16px; height:16px; accent-color:var(--tx-accent); }
    /* Table */
    .tax-ui .tx-table-wrap{ overflow-x:auto; border:1px solid var(--tx-line); border-radius:12px; }
    .tax-ui table.tx-table{ width:100%; min-width:640px; border-collapse:collapse; font-size:13.5px; }
    .tax-ui table.tx-table thead th{ background:#f8fafc; text-align:left; padding:11px 14px; font-size:11.5px; font-weight:700; letter-spacing:.04em; text-transform:uppercase; color:#64748b; border-bottom:1px solid var(--tx-line); white-space:nowrap; }
    .tax-ui table.tx-table tbody td{ padding:10px 14px; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
    .tax-ui table.tx-table tbody tr:last-child td{ border-bottom:none; }
    .tax-ui table.tx-table tbody tr:hover{ background:#fafbff; }
    .tax-ui table.tx-table .tx-input{ padding:7px 10px; border-radius:8px; font-size:13px; }
    .tax-ui .tx-pill{ display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:999px; font-size:11.5px; font-weight:700; }
    .tax-ui .tx-pill--default{ background:var(--tx-accent-soft); color:var(--tx-accent); }
    .tax-ui .tx-pill--active{ background:#ecfdf5; color:#059669; }
    .tax-ui .tx-pill--muted{ background:#f1f5f9; color:#64748b; }
    .tax-ui .tx-empty{ text-align:center; color:#94a3b8; padding:26px 14px; font-size:13.5px; }
    .tax-ui .tx-iconbtn{ width:32px; height:32px; border-radius:8px; border:1px solid var(--tx-line); background:#fff; display:inline-flex; align-items:center; justify-content:center; cursor:pointer; color:#475569; transition:.15s; }
    .tax-ui .tx-iconbtn:hover{ background:#f8fafc; }
    .tax-ui .tx-iconbtn--danger:hover{ background:#fef2f2; border-color:#fecaca; color:#dc2626; }
    .tax-ui .tx-iconbtn--save:hover{ background:var(--tx-accent-soft); border-color:#a7f3d0; color:var(--tx-accent); }
    /* KPI cards (report) */
    .tax-ui .tx-kpis{ display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:16px; margin-bottom:22px; }
    .tax-ui .tx-kpi{ background:var(--tx-bg); border:1px solid var(--tx-line); border-radius:16px; box-shadow:0 1px 3px rgba(15,23,42,.04); padding:18px; display:flex; align-items:center; gap:14px; }
    .tax-ui .tx-kpi__ic{ width:46px; height:46px; border-radius:12px; display:inline-flex; align-items:center; justify-content:center; font-size:20px; flex:0 0 46px; }
    .tax-ui .tx-kpi__val{ font-size:22px; font-weight:800; color:var(--tx-ink); line-height:1; letter-spacing:-.01em; }
    .tax-ui .tx-kpi__lbl{ font-size:12.5px; color:var(--tx-muted); margin-top:5px; }
    /* Filter bar */
    .tax-ui .tx-filter{ display:flex; align-items:flex-end; flex-wrap:wrap; gap:12px; background:#f8fafc; border:1px solid var(--tx-line); border-radius:12px; padding:14px 16px; margin-bottom:22px; }
    .tax-ui .tx-num{ text-align:right; font-variant-numeric:tabular-nums; }
    @media (max-width:760px){ .tax-ui .tx-kpis{ grid-template-columns:1fr; } }
    @media (max-width:640px){ .tax-ui .tx-grid{ grid-template-columns:1fr; } }
</style>

<style>
    /* 2026-07-10 (New Changes for UI #4) — dark mode for this page's bespoke components. */
    /* Re-declare the page-local neutral tokens; the accent (brand green) stays. */
    html[data-theme="dark"] .tax-ui { --tx-line:#2a3a55; --tx-ink:#e2e8f0; --tx-muted:#94a3b8; --tx-bg:#1e293b; }
    /* Literal-hardcoded light values (not driven by the tokens above). */
    html[data-theme="dark"] .tax-ui .tx-badge--off{ background:#22304a; color:#94a3b8; }
    html[data-theme="dark"] .tax-ui .tx-btn--ghost{ background:#1e293b; }
    html[data-theme="dark"] .tax-ui .tx-btn--ghost:hover{ background:#17233a; }
    html[data-theme="dark"] .tax-ui .tx-card{ box-shadow:none; }
    html[data-theme="dark"] .tax-ui .tx-toggle-row{ background:#17233a; }
    html[data-theme="dark"] .tax-ui .tx-field label{ color:#e2e8f0; }
    html[data-theme="dark"] .tax-ui .tx-input, html[data-theme="dark"] .tax-ui select.tx-input{ background:#1e293b; border-color:#2a3a55; }
    html[data-theme="dark"] .tax-ui .tx-input::placeholder{ color:#94a3b8; }
    html[data-theme="dark"] .tax-ui .tx-addrate{ background:#17233a; }
    html[data-theme="dark"] .tax-ui .tx-check{ color:#e2e8f0; }
    html[data-theme="dark"] .tax-ui table.tx-table thead th{ background:#17233a; color:#94a3b8; }
    html[data-theme="dark"] .tax-ui table.tx-table tbody td{ border-bottom-color:#2a3a55; }
    html[data-theme="dark"] .tax-ui table.tx-table tbody tr:hover{ background:#17233a; }
    html[data-theme="dark"] .tax-ui .tx-pill--muted{ background:#22304a; color:#94a3b8; }
    html[data-theme="dark"] .tax-ui .tx-kpi{ box-shadow:none; }
    html[data-theme="dark"] .tax-ui .tx-iconbtn{ background:#1e293b; color:#94a3b8; }
    html[data-theme="dark"] .tax-ui .tx-iconbtn:hover{ background:#17233a; }
    html[data-theme="dark"] .tax-ui .tx-filter{ background:#17233a; }
</style>
