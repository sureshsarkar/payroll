<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link
    href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800&display=swap"
    rel="stylesheet">

<style>
    /* ============================================
   COACH SIDEBAR — Light Professional Theme
   ============================================ */
    :root {
        --csb-bg: #ffffff;
        --csb-surface: #f8fafc;
        --csb-surface2: #f1f5f9;
        --csb-border: #e8ecf2;
        --csb-border-soft: #f1f5f9;

        --accent: #10b981;
        --accent-dark: #065f46;
        --accent-light: #ecfdf5;
        --accent-border: #a7f3d0;
        --accent2: #0ea5e9;

        --text: #0f172a;
        --text-sub: #64748b;
        --text-muted: #94a3b8;
        --text-label: #cbd5e1;

        --danger: #dc2626;
        --danger-light: #fef2f2;
        --danger-border: #fecaca;

        --gold: #f59e0b;

        --hover-bg: #f8fafc;
        --active-bg: #ecfdf5;
        --radius: 9px;
    }


    /* Chrome, Edge, Safari */
    ::-webkit-scrollbar {
        display: none;
    }

    /* Firefox */
    body {
        scrollbar-width: none;
    }

    /* IE / old Edge */
    body {
        -ms-overflow-style: none;
    }

    .overflow--scroll {
        height: 100vh !important;
        overflow-y: scroll !important;
    }


    /* ── Base ── */
    .instructor-sidebar {
        font-family: 'DM Sans', sans-serif;
        width: 100%;
        background: var(--csb-bg);
        display: flex;
        flex-direction: column;
        border-right: 1px solid var(--csb-border);
        position: relative;
        overflow: hidden;
        border-top-left-radius: 14px;
        border-bottom-left-radius: 14px;
        box-shadow: 2px 0 12px rgba(0, 0, 0, 0.04);
    }

    .dashboard__aread {
        background: #f0faf8;
    }

    /* Top accent stripe */
    .csb-topstripe {
        height: 4px;
        background: linear-gradient(90deg, #0f9d7e, #10b981, #34d399);
        flex-shrink: 0;
    }

    /* ── Landing Banner ── */
    .sb-landing-banner {
        margin: 14px 14px 0;
        background: var(--accent-light);
        border: 1px solid var(--accent-border);
        border-radius: 11px;
        padding: 9px 13px;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all .2s ease;
        text-decoration: none;
    }

    .sb-landing-banner:hover {
        background: #d1fae5;
        border-color: #6ee7b7;
        transform: translateY(-1px);
        box-shadow: 0 4px 14px rgba(16, 185, 129, 0.12);
    }

    .sb-landing-banner a {
        font-size: 11px;
        font-weight: 700;
        color: var(--accent-dark);
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 6px;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        flex: 1;
    }

    .sb-landing-banner a:hover {
        color: #064e3b;
        text-decoration: none;
    }

    .sb-landing-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: var(--accent);
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.18);
        flex-shrink: 0;
        animation: sb-blink 2.2s ease-in-out infinite;
    }

    @keyframes sb-blink {

        0%,
        100% {
            opacity: 1;
        }

        50% {
            opacity: 0.3;
        }
    }

    /* ── Profile Header ── */
    .sb-profile {
        padding: 14px 14px 12px;
        border-bottom: 1px solid var(--csb-border-soft);
    }

    .sb-profile-inner {
        display: flex;
        align-items: center;
        gap: 11px;
        background: var(--csb-surface);
        border: 1px solid var(--csb-border);
        border-radius: 12px;
        padding: 12px 13px;
        position: relative;
        overflow: hidden;
    }

    /* Colored left accent bar */
    .sb-profile-inner::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 3px;
        background: linear-gradient(to bottom, var(--accent), var(--accent2));
        border-radius: 3px 0 0 3px;
    }

    .sb-avatar {
        width: 42px;
        height: 42px;
        border-radius: 11px;
        background: linear-gradient(135deg, var(--accent), var(--accent2));
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        font-weight: 800;
        color: #fff;
        flex-shrink: 0;
        position: relative;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.28);
    }

    .sb-avatar-online {
        position: absolute;
        bottom: -2px;
        right: -2px;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: #22c55e;
        border: 2px solid var(--csb-surface);
    }

    .sb-greet {
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .1em;
        color: var(--text-muted);
        margin-bottom: 1px;
    }

    .sb-name {
        font-size: 13.5px;
        font-weight: 700;
        color: var(--text);
        line-height: 1.2;
    }

    .sb-role-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: var(--accent-light);
        color: var(--accent-dark);
        border: 1px solid var(--accent-border);
        border-radius: 20px;
        font-size: 9px;
        font-weight: 700;
        padding: 2px 8px;
        letter-spacing: .07em;
        text-transform: uppercase;
        margin-top: 4px;
    }

    /* ── Stats Strip ── */
    .sb-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 7px;
        padding: 12px 14px;
        border-bottom: 1px solid var(--csb-border-soft);
    }

    .sb-stat {
        background: var(--csb-surface);
        border: 1px solid var(--csb-border);
        border-radius: 9px;
        padding: 9px 6px;
        text-align: center;
        transition: all .18s ease;
        cursor: default;
    }

    .sb-stat:hover {
        border-color: var(--accent-border);
        background: var(--accent-light);
        transform: translateY(-1px);
        box-shadow: 0 3px 10px rgba(16, 185, 129, 0.09);
    }

    .sb-stat-val {
        font-family: 'DM Sans', sans-serif;
        font-size: 16px;
        font-weight: 800;
        line-height: 1;
    }

    .sb-stat-val.g {
        color: var(--accent);
    }

    .sb-stat-val.b {
        color: var(--accent2);
    }

    .sb-stat-val.o {
        color: var(--gold);
    }

    .sb-stat-lbl {
        font-size: 9px;
        font-weight: 600;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: .06em;
        margin-top: 3px;
    }

    /* ── Section Label ── */
    .sb-section {
        padding: 12px 10px 4px;
    }

    .sb-section-label {
        font-size: 9.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .14em;
        color: var(--text-label);
        padding: 0 6px;
        margin-bottom: 5px;
    }

    /* ── Nav Items ── */
    .sb-nav {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: 1px;
    }

    .sb-nav li a {
        display: flex;
        align-items: center;
        gap: 9px;
        padding: 8px 10px;
        border-radius: var(--radius);
        font-size: 13px;
        font-weight: 500;
        color: var(--text-sub);
        text-decoration: none;
        border: 1px solid transparent;
        transition: all .16s ease;
        position: relative;
    }

    .sb-nav li a:hover {
        background: var(--hover-bg);
        color: var(--text);
        border-color: var(--csb-border);
        text-decoration: none;
    }

    .sb-nav li.active>a {
        background: var(--active-bg);
        color: var(--accent-dark);
        border-color: var(--accent-border);
        font-weight: 600;
    }

    /* Active left bar */
    .sb-nav li.active>a::before {
        content: '';
        position: absolute;
        left: 0;
        top: 50%;
        transform: translateY(-50%);
        width: 3px;
        height: 55%;
        background: var(--accent);
        border-radius: 0 3px 3px 0;
    }

    /* ── Icon Wrapper ── */
    .sb-icon {
        width: 28px;
        height: 28px;
        border-radius: 7px;
        background: var(--csb-surface2);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 13px;
        color: var(--text-muted);
        transition: all .16s ease;
    }

    .sb-nav li a:hover .sb-icon {
        background: var(--csb-border);
        color: var(--text-sub);
    }

    .sb-nav li.active>a .sb-icon {
        background: #d1fae5;
        color: var(--accent);
    }

    /* ── Badge Pill ── */
    .sb-pill {
        margin-left: auto;
        padding: 2px 7px;
        border-radius: 20px;
        font-size: 9px;
        font-weight: 700;
    }

    .sb-pill.red {
        background: var(--danger-light);
        color: var(--danger);
        border: 1px solid var(--danger-border);
    }

    .sb-pill.green {
        background: var(--accent-light);
        color: var(--accent-dark);
        border: 1px solid var(--accent-border);
    }

    .sb-pill.blue {
        background: #eff6ff;
        color: #1d4ed8;
        border: 1px solid #bfdbfe;
    }

    /* ── Dropdown ── */
    .sb-nav li.menu-dropdown>a .sb-arrow {
        margin-left: auto;
        width: 14px;
        height: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: transform .2s ease;
    }

    .sb-nav li.menu-dropdown>a .sb-arrow::before {
        content: '';
        width: 6px;
        height: 6px;
        border-right: 1.5px solid var(--text-muted);
        border-bottom: 1.5px solid var(--text-muted);
        transform: rotate(45deg) translateY(-2px);
        display: block;
    }

    .sb-nav li.menu-dropdown.open>a .sb-arrow {
        transform: rotate(-180deg);
    }

    .sb-nav li.menu-dropdown.open>a .sb-arrow::before {
        border-color: var(--accent);
    }

    .submenu {
        display: none;
        list-style: none;
        margin: 3px 0 3px 16px;
        padding: 0 0 0 10px;
        border-left: 2px solid var(--accent-border);
    }

    .menu-dropdown.open .submenu {
        display: block;
    }

    .submenu li a {
        font-size: 12px;
        padding: 7px 10px;
        color: var(--text-sub);
        border-radius: 8px;
    }

    .submenu li a:hover {
        color: var(--text);
        background: var(--hover-bg);
    }

    .submenu li.active a {
        color: var(--accent-dark);
        background: var(--active-bg);
        font-weight: 600;
    }

    .submenu li a .sb-icon {
        width: 22px;
        height: 22px;
        border-radius: 6px;
        font-size: 11px;
    }

    /* ── Divider ── */
    .sb-divider {
        height: 1px;
        background: var(--csb-border-soft);
        margin: 4px 14px;
    }

    /* ── Logout ── */
    .sb-nav li a.logout-link {
        color: var(--text-muted);
    }

    .sb-nav li a.logout-link:hover {
        background: var(--danger-light);
        color: var(--danger);
        border-color: var(--danger-border);
    }

    .sb-nav li a.logout-link:hover .sb-icon {
        background: #fee2e2;
        color: var(--danger);
    }

    /* ── Footer ── */
    .sb-footer {
        margin-top: auto;
        padding: 12px 14px 16px;
        border-top: 1px solid var(--csb-border-soft);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .sb-footer-brand {
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .12em;
        background: linear-gradient(90deg, var(--accent), var(--accent2));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .sb-footer-ver {
        font-size: 9px;
        color: var(--text-muted);
        background: var(--csb-surface);
        border: 1px solid var(--csb-border);
        padding: 2px 7px;
        border-radius: 5px;
    }

    /* ════════════════════════════════════════════════════════════
       2026-05-20 — IA upgrade: Quick Add, collapsible groups,
       attention-badge pill variants.
       ════════════════════════════════════════════════════════════ */

    /* ── Quick Add ── */
    .sb-quick-add {
        position: relative;
    }

    .sb-quick-add__btn {
        width: 100%;
        display: flex;
        align-items: center;
        gap: 9px;
        padding: 9px 11px;
        border-radius: var(--radius);
        background: linear-gradient(135deg, var(--accent), var(--accent2));
        color: #fff;
        border: 1px solid transparent;
        font-size: 12.5px;
        font-weight: 700;
        cursor: pointer;
        transition: all .18s ease;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.22);
        letter-spacing: .02em;
    }

    .sb-quick-add__btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(16, 185, 129, 0.32);
    }

    .sb-quick-add__btn .sb-icon {
        width: 24px;
        height: 24px;
        border-radius: 6px;
        background: rgba(255, 255, 255, 0.22);
        color: #fff;
        font-size: 13px;
    }

    .sb-quick-add__chev {
        margin-left: auto;
        font-size: 10px;
        opacity: .85;
        transition: transform .2s ease;
    }

    .sb-quick-add.is-open .sb-quick-add__chev {
        transform: rotate(180deg);
    }

    .sb-quick-add__menu {
        position: absolute;
        top: calc(100% + 6px);
        left: 0;
        right: 0;
        z-index: 30;
        background: var(--csb-bg);
        border: 1px solid var(--csb-border);
        border-radius: 11px;
        box-shadow: 0 12px 28px rgba(15, 23, 42, 0.12);
        padding: 6px;
        display: none;
        max-height: 320px;
        overflow-y: auto;
    }

    .sb-quick-add.is-open .sb-quick-add__menu {
        display: block;
        animation: sb-qa-in .14s ease;
    }

    @keyframes sb-qa-in {
        from { opacity: 0; transform: translateY(-4px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    .sb-quick-add__item {
        display: flex;
        align-items: center;
        gap: 9px;
        padding: 8px 10px;
        border-radius: 8px;
        font-size: 12.5px;
        font-weight: 500;
        color: var(--text-sub);
        text-decoration: none;
        transition: all .14s ease;
    }

    .sb-quick-add__item:hover {
        background: var(--accent-light);
        color: var(--accent-dark);
        text-decoration: none;
    }

    .sb-quick-add__item .sb-icon {
        width: 26px;
        height: 26px;
        font-size: 12px;
    }

    .sb-quick-add__item:hover .sb-icon {
        background: #d1fae5;
        color: var(--accent);
    }

    /* ── Collapsible Group (native <details>) ── */
    .sb-group {
        padding: 8px 10px 0;
    }

    .sb-group__head {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 6px 6px 6px 6px;
        font-size: 9.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .14em;
        color: var(--text-label);
        cursor: pointer;
        list-style: none;
        user-select: none;
        border-radius: 6px;
        transition: color .14s ease, background .14s ease;
    }

    .sb-group__head::-webkit-details-marker { display: none; }
    .sb-group__head::marker { content: ''; }

    .sb-group__head:hover {
        color: var(--text-sub);
        background: var(--csb-surface);
    }

    .sb-group__chev {
        margin-left: auto;
        font-size: 10px;
        color: var(--text-muted);
        transition: transform .2s ease;
    }

    .sb-group[open] > .sb-group__head .sb-group__chev {
        transform: rotate(180deg);
        color: var(--accent);
    }

    /* ── Pill variants ── */
    .sb-pill--muted {
        background: var(--csb-surface2);
        color: var(--text-sub);
        border: 1px solid var(--csb-border);
    }

    .sb-pill--warning {
        background: #fff7ed;
        color: #c2410c;
        border: 1px solid #fed7aa;
        display: inline-flex;
        align-items: center;
        gap: 3px;
    }

    .sb-pill--danger {
        background: var(--danger-light);
        color: var(--danger);
        border: 1px solid var(--danger-border);
        animation: sb-pill-pulse 2.4s ease-in-out infinite;
    }

    .sb-pill--live {
        background: #fef2f2;
        color: var(--danger);
        border: 1px solid var(--danger-border);
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-weight: 800;
    }

    .sb-pill__dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: var(--danger);
        box-shadow: 0 0 0 2px rgba(220, 38, 38, 0.18);
        animation: sb-blink 1.4s ease-in-out infinite;
    }

    @keyframes sb-pill-pulse {
        0%, 100% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0); }
        50%      { box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.08); }
    }

    /* ============================================================
       2026-06-01 RESPONSIVE AUDIT — mobile off-canvas drawer (RWD-2)

       On desktop the coach sidebar lives in a col-lg-2 column and both
       columns get .overflow--scroll{height:100vh} independent scroll
       panes. Below the lg breakpoint that grid stacks, which previously
       produced a FULL-VIEWPORT-HEIGHT empty sidebar pane sitting above
       the content, with no way to collapse it (the coach sidebar had no
       mobile handling, unlike the student one).

       Fix, mobile-only (≤991.98px — matches the col-lg stack point):
         1. Neutralise the 100vh scroll panes so the page scrolls
            naturally once the columns stack.
         2. Turn the sidebar into an off-canvas drawer that slides in
            when the SHARED topbar hamburger (#mbsSidebarToggle) toggles
            the `.mbs-sidebar-collapsed` class its JS already emits on
            both <body> and `.instructor-sidebar`. No new button needed
            — that hamburger was previously a dead no-op for coaches.
       Desktop (≥992px) is completely untouched. ============================ */
    .instructor-sidebar-overlay { display: none; }

    @media (max-width: 991.98px) {
        /* 1. stacked columns must flow, not trap a 100vh pane each */
        .overflow--scroll {
            height: auto !important;
            overflow-y: visible !important;
        }

        /* 2. off-canvas drawer */
        .instructor-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 284px;
            max-width: 86vw;
            height: 100dvh;
            z-index: 1000;
            border-radius: 0 14px 14px 0;
            transform: translateX(-100%);
            transition: transform 0.28s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 4px 0 40px rgba(15, 23, 42, 0.28);
            overflow-y: auto;
        }
        .instructor-sidebar.mbs-sidebar-collapsed,
        body.mbs-sidebar-collapsed .instructor-sidebar {
            transform: translateX(0);
        }

        /* dim backdrop behind the open drawer */
        .instructor-sidebar-overlay {
            display: block;
            position: fixed;
            inset: 0;
            background: rgba(5, 20, 60, 0.55);
            backdrop-filter: blur(2px);
            z-index: 999;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.28s ease;
        }
        body.mbs-sidebar-collapsed .instructor-sidebar-overlay {
            opacity: 1;
            pointer-events: auto;
        }
    }
</style>

<style>
/* 2026-07-10 (New Changes for UI #4) — dark mode for this page's bespoke components. */
/* The --csb-*/--text* root tokens are already re-themed by the global dark
   partial; the only hard-coded non-brand light surface here is the main
   dashboard content wash. Every emerald accent is deliberately kept. */
html[data-theme="dark"] .dashboard__aread { background: #17233a; }
</style>

<div class="instructor-sidebar-overlay" id="instructorSidebarOverlay"></div>

<aside class="instructor-sidebar dashboard__sidebar-wrap11" id="instructorSidebar">

    {{-- Top green stripe --}}
    <div class="csb-topstripe"></div>

    {{-- Landing Page Banner --}}
    @if ($landingPageDomain != '')
        <div class="sb-landing-banner">
            <span class="sb-landing-dot"></span>
            <a href="https://{{ $landingPageDomain }}" target="_blank">
                <i class="bi bi-box-arrow-up-right" style="font-size:10px;"></i>
                {{ __('Public Landing Page') }}
            </a>
        </div>
    @endif

    {{-- Profile --}}
    <div class="sb-profile">
        <div class="sb-profile-inner">
            <div class="sb-avatar">
                {{ strtoupper(substr(userAuth()->name, 0, 1)) }}
                <div class="sb-avatar-online"></div>
            </div>
            <div>
                <div class="sb-greet">{{ __('Welcome back') }}</div>
                <div class="sb-name">{{ userAuth()->name }}</div>
                <div class="sb-role-badge">
                    <i class="bi bi-patch-check-fill" style="font-size:8px;"></i>
                    {{ userAuth()->role == 'instructor' ? __('Coach') : (userAuth()->role == 'institute-branch' ? __('Branch') : __('Staff')) }}
                </div>
            </div>
        </div>
    </div>

    {{-- Stats Strip --}}
    <div class="sb-stats">
        <div class="sb-stat">
            <div class="sb-stat-val g">{{ $totalCoachStudents ?? 0 }}</div>
            <div class="sb-stat-lbl">{{ __('Students') }}</div>
        </div>
        <div class="sb-stat">
            <div class="sb-stat-val b">{{ $totalCouachCourses ?? 0 }}</div>
            <div class="sb-stat-lbl">{{ __('Courses') }}</div>
        </div>
        <div class="sb-stat">
            <div class="sb-stat-val o">{{ $totalCoachOrders ?? 0 }}</div>
            <div class="sb-stat-lbl">{{ __('Orders') }}</div>
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════════════════
         2026-05-20 — Sidebar IA upgrade. 4 semantic sections, collapsible
         via native <details>, with smart attention badges that auto-hide
         when there's nothing to flag. Quick-add dropdown at the top.
         All badge counts come from $sidebarBadges (cached 60s in
         AppServiceProvider).
         ════════════════════════════════════════════════════════════════ --}}
    @php
        $sbb = $sidebarBadges ?? [
            'announcement_drafts' => 0, 'enquiries_new' => 0,
            'fees_overdue' => 0, 'orders_pending' => 0,
            'batches_active' => 0,
        ];
        // 2026-05-20 P7 — Teacher panel.
        // Sidebar terminology branches on whether the caller is a
        // CoachStaff teacher (role != 'instructor'). A teacher sees
        // "My Batches", "My Students" etc. — making it visually clear
        // the lists are scoped to their assignments, not the coach's
        // full inventory. Coach experience is unchanged.
        $sbIsTeacher = (userAuth()?->role ?? null) !== 'instructor';
    @endphp

    {{-- Quick Add ─────────────────────────────────────────────── --}}
    <div class="sb-section" style="padding-top:10px;">
        <div class="sb-quick-add">
            <button type="button" class="sb-quick-add__btn" id="sbQuickAddBtn"
                    aria-haspopup="true" aria-expanded="false">
                <span class="sb-icon"><i class="bi bi-plus-lg"></i></span>
                <span>{{ __('Quick Add') }}</span>
                <span class="sb-quick-add__chev"><i class="bi bi-chevron-down"></i></span>
            </button>
            <div class="sb-quick-add__menu" id="sbQuickAddMenu" role="menu">
                @php
                    // Build menu entries defensively — each route() call
                    // is try/caught so a missing route doesn't 500 the
                    // whole layout.
                    //
                    // 2026-05-20 P7 — a teacher cannot create courses,
                    // batches, or students; those are coach-owned.
                    // They CAN create announcements and record fee demands
                    // on their assigned batches.
                    // 2026-07-06 (Role Permission Test doc) — each Quick Add item
                    // carries the permission slug that gates it, so a staff member
                    // only sees shortcuts to modules they can actually open.
                    // 2026-07-18 (Dashboard Nav Enhancement #3) — "Start Live Class"
                    // removed from Quick Add; live classes are reached from their
                    // own module only, so the shortcut is no longer duplicated here.
                    $quickAddItems = $sbIsTeacher ? [
                        ['route' => 'instructor.announcements.create',    'icon' => 'bi-megaphone',     'label' => __('Add Announcement'), 'perm' => 'announcements'],
                        ['route' => 'instructor.fees.index',              'icon' => 'bi-coin',          'label' => __('Add Fee Demand'),   'perm' => 'fees'],
                    ] : [
                        ['route' => 'instructor.courses.create',          'icon' => 'bi-mortarboard',   'label' => __('Add Course'),       'perm' => 'courses'],
                        ['route' => 'instructor.course-batches.index',    'icon' => 'bi-collection',    'label' => __('Add Batch'),        'perm' => 'course-batches'],
                        ['route' => 'instructor.announcements.create',    'icon' => 'bi-megaphone',     'label' => __('Add Announcement'), 'perm' => 'announcements'],
                        ['route' => 'instructor.fees.index',              'icon' => 'bi-coin',          'label' => __('Add Fee Demand'),   'perm' => 'fees'],
                        ['route' => 'instructor.my-students.create',      'icon' => 'bi-people',        'label' => __('Add Student'),      'perm' => 'coach-students'],
                    ];
                @endphp
                @foreach ($quickAddItems as $it)
                    @continue(!empty($it['perm']) && ! checkPermissionView($it['perm']))
                    @php try { $url = route($it['route']); } catch (\Throwable $e) { $url = null; } @endphp
                    @if ($url)
                        <a href="{{ $url }}" role="menuitem" class="sb-quick-add__item">
                            <span class="sb-icon"><i class="bi {{ $it['icon'] }}"></i></span>
                            {{ $it['label'] }}
                        </a>
                    @endif
                @endforeach
            </div>
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════════════════
         2026-07-18 — Sidebar IA v2 (Dashboard Nav Enhancement #4).
         Reorganised into 7 professional category groups, in a fixed order:
           1. Overview  2. Academic Management  3. People Management
           4. Sales & Operations  5. Communication  6. Reports  7. Configuration
         Every item keeps its EXACT route, permission gate (checkPermissionView),
         tenant scoping, badge pill, and $sbIsTeacher label — only the grouping
         changed. Nothing is duplicated: each feature lives in exactly one group.
         Reports currently holds Analytics; the Revenue/Payments/Invoices reports
         are added to this same group in Phase C. Only currently-functional items
         are listed. ════════════════════════════════════════════════════════ --}}

    {{-- GROUP 1 — OVERVIEW ─────────────────────────────────────── --}}
    <details class="sb-group" data-sb-key="overview" open>
        <summary class="sb-group__head">
            <span>{{ __('Overview') }}</span>
            <i class="bi bi-chevron-down sb-group__chev"></i>
        </summary>
        <ul class="sb-nav">
            <li class="{{ Route::is('instructor.dashboard') ? 'active' : '' }}">
                <a href="{{ route('instructor.dashboard') }}">
                    <span class="sb-icon"><i class="bi bi-grid-1x2"></i></span>
                    {{ __('Dashboard') }}
                </a>
            </li>
        </ul>
    </details>

    {{-- GROUP — PAYROLL & HR (LMS→Payroll conversion) ───────────── --}}
    <details class="sb-group" data-sb-key="payroll-hr" open>
        <summary class="sb-group__head">
            <span>{{ __('Payroll & HR') }}</span>
            <i class="bi bi-chevron-down sb-group__chev"></i>
        </summary>
        <ul class="sb-nav">
            <li class="{{ Route::is('hr.employees.*') ? 'active' : '' }}">
                <a href="{{ route('hr.employees.index') }}">
                    <span class="sb-icon"><i class="bi bi-people"></i></span>
                    <span>{{ __('Employees') }}</span>
                </a>
            </li>
            <li class="{{ Route::is('hr.departments.*') ? 'active' : '' }}">
                <a href="{{ route('hr.departments.index') }}">
                    <span class="sb-icon"><i class="bi bi-diagram-3"></i></span>
                    <span>{{ __('Departments') }}</span>
                </a>
            </li>
            <li class="{{ Route::is('hr.attendance.*') ? 'active' : '' }}">
                <a href="{{ route('hr.attendance.team') }}">
                    <span class="sb-icon"><i class="bi bi-calendar-check"></i></span>
                    <span>{{ __('Team Attendance') }}</span>
                </a>
            </li>
            <li class="{{ Route::is('hr.leave.*') ? 'active' : '' }}">
                <a href="{{ route('hr.leave.index') }}">
                    <span class="sb-icon"><i class="bi bi-calendar2-week"></i></span>
                    <span>{{ __('Leave Approvals') }}</span>
                </a>
            </li>
            <li class="{{ Route::is('hr.salary.*') ? 'active' : '' }}">
                <a href="{{ route('hr.salary.index') }}">
                    <span class="sb-icon"><i class="bi bi-cash-stack"></i></span>
                    <span>{{ __('Salary Structures') }}</span>
                </a>
            </li>
            <li class="{{ Route::is('hr.payroll.*') ? 'active' : '' }}">
                <a href="{{ route('hr.payroll.index') }}">
                    <span class="sb-icon"><i class="bi bi-receipt"></i></span>
                    <span>{{ __('Payroll Runs') }}</span>
                </a>
            </li>
        </ul>
    </details>

    {{-- GROUP 2 — ACADEMIC MANAGEMENT ──────────────────────────── --}}
    <details class="sb-group" data-sb-key="academic" open>
        <summary class="sb-group__head">
            <span>{{ __('Academic Management') }}</span>
            <i class="bi bi-chevron-down sb-group__chev"></i>
        </summary>
        <ul class="sb-nav">
            @if (checkPermissionView('courses'))
                <li class="{{ Route::is('instructor.courses.index') ? 'active' : '' }}">
                    <a href="{{ route('instructor.courses.index') }}">
                        <span class="sb-icon"><i class="bi bi-mortarboard"></i></span>
                        <span>{{ __('Courses') }}</span>
                        @if (($totalCouachCourses ?? 0) > 0)
                            <span class="sb-pill sb-pill--muted">{{ number_format($totalCouachCourses) }}</span>
                        @endif
                    </a>
                </li>
            @endif

            @if (checkPermissionView('course-batches'))
                <li class="{{ Route::is('instructor.course-batches.*') ? 'active' : '' }}">
                    <a href="{{ route('instructor.course-batches.index') }}">
                        <span class="sb-icon"><i class="bi bi-collection"></i></span>
                        <span>{{ $sbIsTeacher ? __('My Batches') : __('Batches') }}</span>
                        @if ($sbb['batches_active'] > 0)
                            <span class="sb-pill sb-pill--muted">{{ number_format($sbb['batches_active']) }}</span>
                        @endif
                    </a>
                </li>
            @endif

            @if (checkPermissionView('live-classes'))
                <li class="{{ Route::is('instructor.live-classes.*') ? 'active' : '' }}">
                    <a href="{{ route('instructor.live-classes.index') }}">
                        <span class="sb-icon"><i class="bi bi-camera-reels"></i></span>
                        <span>{{ __('Live Classes') }}</span>
                        @if (($totalCoachUpcomingLive ?? 0) > 0)
                            <span class="sb-pill sb-pill--live"><span class="sb-pill__dot"></span>{{ $totalCoachUpcomingLive }} LIVE</span>
                        @endif
                    </a>
                </li>
            @endif

            {{-- 2026-07-06 (Role Permission Test doc, issue D) — Instant Meeting
                 gets its OWN permission gate. It was nested inside the live-classes
                 gate, so granting Live Classes wrongly surfaced Instant Meeting too. --}}
            @if (checkPermissionView('instant-meetings'))
                <li class="{{ Route::is('instructor.instant-meetings.*') ? 'active' : '' }}">
                    <a href="{{ route('instructor.instant-meetings.index') }}">
                        <span class="sb-icon"><i class="bi bi-person-video3"></i></span>
                        <span>{{ __('Instant Meeting 1:1') }}</span>
                    </a>
                </li>
            @endif

            {{-- 2026-06-12 — per-coach certificate builder. Gated by its own
                 certificate view permission. --}}
            @if (checkPermissionView('certificate'))
                <li class="{{ Route::is('instructor.certificate-builder.*') ? 'active' : '' }}">
                    <a href="{{ route('instructor.certificate-builder.index') }}">
                        <span class="sb-icon"><i class="bi bi-award"></i></span>
                        <span>{{ __('Certificate') }}</span>
                    </a>
                </li>
            @endif

            @if (Module::has('CourseBundle') && Module::isEnabled('CourseBundle'))
                <li class="{{ Route::is('instructor.course.bundle.*') ? 'active' : '' }}">
                    <a href="{{ route('instructor.course.bundle.index') }}">
                        <span class="sb-icon"><i class="bi bi-boxes"></i></span>
                        {{ __('Course Bundle') }}
                    </a>
                </li>
            @endif
        </ul>
    </details>

    {{-- GROUP 3 — PEOPLE MANAGEMENT ────────────────────────────── --}}
    <details class="sb-group" data-sb-key="people" open>
        <summary class="sb-group__head">
            <span>{{ __('People Management') }}</span>
            <i class="bi bi-chevron-down sb-group__chev"></i>
        </summary>
        <ul class="sb-nav">
            @if (checkPermissionView('coach-students'))
                <li class="{{ Route::is('instructor.my-students.index') ? 'active' : '' }}">
                    <a href="{{ route('instructor.my-students.index') }}">
                        <span class="sb-icon"><i class="bi bi-people"></i></span>
                        <span>{{ $sbIsTeacher ? __('My Students') : __('Students') }}</span>
                        @if (! $sbIsTeacher && ($totalCoachStudents ?? 0) > 0)
                            <span class="sb-pill sb-pill--muted">{{ number_format($totalCoachStudents) }}</span>
                        @endif
                    </a>
                </li>
                {{-- 2026-07-15 — date-specific temporary batch slots. --}}
                <li class="{{ Route::is('instructor.temporary-slots.*') ? 'active' : '' }}">
                    <a href="{{ route('instructor.temporary-slots.index') }}">
                        <span class="sb-icon"><i class="bi bi-calendar-day"></i></span>
                        <span>{{ __('Temporary Slots') }}</span>
                    </a>
                </li>
            @endif

            {{-- 2026-05-20 — grant teachers access to specific batches. Coach-only
                 by default; a regular teacher never has the 'teacher-batches' slug
                 (default-deny), so this stays hidden for them. 2026-07-07: gate on
                 the permission (not raw role) so it stays consistent with the now
                 permission-gated route — a coach who deliberately delegates
                 teacher-batch management to a senior staff can reach it. --}}
            @if (checkPermissionView('teacher-batches'))
                <li class="{{ Route::is('instructor.teacher-batches.*') ? 'active' : '' }}">
                    <a href="{{ route('instructor.teacher-batches.index') }}">
                        <span class="sb-icon"><i class="bi bi-person-check"></i></span>
                        <span>{{ __('Teacher Access') }}</span>
                    </a>
                </li>
            @endif

            {{-- 2026-07-18 (Dashboard Nav Enhancement) — Trainers + Trainer Bookings
                 SURFACED here (were previously off-menu). This is the single master
                 CRUD for trainers + their session-package bookings; the Website
                 Builder only renders the public-facing trainer profile/booking
                 section (same trainer master data — no duplicate CRUD). Gated by the
                 granular `trainers` permission; tenant-scoped in TrainerController. --}}
            @if (checkPermissionView('trainers'))
                {{-- 2026-07-18 — resolve defensively: on a prod build that predates
                     the Trainers feature (or has a stale route cache) route() would
                     throw and 500 the whole panel. try/caught → the item simply
                     hides until the routes exist, matching the Quick Add pattern. --}}
                @php
                    try { $sbTrainersUrl = route('instructor.trainers.index'); } catch (\Throwable $e) { $sbTrainersUrl = null; }
                    try { $sbTrainerBookingsUrl = route('instructor.trainers.bookings'); } catch (\Throwable $e) { $sbTrainerBookingsUrl = null; }
                @endphp
                @if ($sbTrainersUrl)
                    <li class="{{ Route::is('instructor.trainers.index', 'instructor.trainers.create', 'instructor.trainers.edit', 'instructor.trainers.show') ? 'active' : '' }}">
                        <a href="{{ $sbTrainersUrl }}">
                            <span class="sb-icon"><i class="bi bi-person-badge"></i></span>
                            <span>{{ __('Trainers') }}</span>
                        </a>
                    </li>
                @endif
                @if ($sbTrainerBookingsUrl)
                    <li class="{{ Route::is('instructor.trainers.bookings', 'instructor.trainers.bookings.*') ? 'active' : '' }}">
                        <a href="{{ $sbTrainerBookingsUrl }}">
                            <span class="sb-icon"><i class="bi bi-calendar2-week"></i></span>
                            <span>{{ __('Trainer Bookings') }}</span>
                        </a>
                    </li>
                @endif
            @endif
        </ul>
    </details>

    {{-- GROUP 4 — SALES & OPERATIONS ───────────────────────────── --}}
    <details class="sb-group" data-sb-key="sales" open>
        <summary class="sb-group__head">
            <span>{{ __('Sales & Operations') }}</span>
            <i class="bi bi-chevron-down sb-group__chev"></i>
        </summary>
        <ul class="sb-nav">
            @if (checkPermissionView('course-batches'))
                <li class="{{ Route::is('instructor.fees.*') ? 'active' : '' }}">
                    <a href="{{ route('instructor.fees.index') }}">
                        <span class="sb-icon"><i class="bi bi-coin"></i></span>
                        <span>{{ __('Fees') }}</span>
                        @if ($sbb['fees_overdue'] > 0)
                            <span class="sb-pill sb-pill--warning"><i class="bi bi-exclamation-triangle-fill" style="font-size:9px;"></i> {{ $sbb['fees_overdue'] }} {{ __('overdue') }}</span>
                        @endif
                    </a>
                </li>
            @endif

            @if (checkPermissionView('coach-orders'))
                <li class="{{ Route::is('instructor.my-sells.index') ? 'active' : '' }}">
                    <a href="{{ route('instructor.my-sells.index') }}">
                        <span class="sb-icon"><i class="bi bi-bar-chart-line"></i></span>
                        <span>{{ __('Orders') }}</span>
                        @if ($sbb['orders_pending'] > 0)
                            <span class="sb-pill sb-pill--warning">{{ $sbb['orders_pending'] }} {{ __('pending') }}</span>
                        @endif
                    </a>
                </li>
            @endif

            {{-- 2026-07-11 — Offline Payment (record-only). Its OWN permission slug
                 so a coach can grant staff offline-payment access independently.
                 Tenant-scoped in the controller. --}}
            @if (checkPermissionView('offline-payments'))
                <li class="{{ Route::is('instructor.offline-payments.*') ? 'active' : '' }}">
                    <a href="{{ route('instructor.offline-payments.index') }}">
                        <span class="sb-icon"><i class="bi bi-cash-stack"></i></span>
                        <span>{{ __('Offline Payment') }}</span>
                    </a>
                </li>
            @endif

            @if (checkPermissionView('coach-coupons'))
                <li class="{{ Route::is('instructor.coupons.*') ? 'active' : '' }}">
                    <a href="{{ route('instructor.coupons.index') }}">
                        <span class="sb-icon"><i class="bi bi-ticket-perforated"></i></span>
                        <span>{{ __('Coupons') }}</span>
                    </a>
                </li>
            @endif

            {{-- 2026-07-18 — Enquiries (landing-page leads) sit in Sales & Operations
                 as the top of the lead pipeline. Same route/permission as before. --}}
            @if (checkPermissionView('landing-page-enquiry'))
                <li class="{{ Route::is('instructor.landing-page-enquiry.*') ? 'active' : '' }}">
                    <a href="{{ route('instructor.landing-page-enquiry.index') }}">
                        <span class="sb-icon"><i class="bi bi-chat-dots"></i></span>
                        <span>{{ __('Enquiries') }}</span>
                        @if ($sbb['enquiries_new'] > 0)
                            <span class="sb-pill sb-pill--danger">{{ $sbb['enquiries_new'] }} {{ __('NEW') }}</span>
                        @endif
                    </a>
                </li>
            @endif
        </ul>
    </details>

    {{-- GROUP 5 — COMMUNICATION ────────────────────────────────── --}}
    {{-- Only currently-functional communication surfaces: Announcements +
         Email Notifications. SMS / WhatsApp are intentionally NOT here (no
         placeholder / dummy toggle) — documented for a future phase. --}}
    <details class="sb-group" data-sb-key="communication" open>
        <summary class="sb-group__head">
            <span>{{ __('Communication') }}</span>
            <i class="bi bi-chevron-down sb-group__chev"></i>
        </summary>
        <ul class="sb-nav">
            {{-- 2026-07-04 — gate by its OWN permission slug, not course-batches
                 (was a copy-paste proxy that bundled announcements with batches). --}}
            @if (checkPermissionView('announcements'))
                <li class="{{ Route::is('instructor.announcements.*') ? 'active' : '' }}">
                    <a href="{{ route('instructor.announcements.index') }}">
                        <span class="sb-icon"><i class="bi bi-megaphone"></i></span>
                        <span>{{ __('Announcements') }}</span>
                        @if ($sbb['announcement_drafts'] > 0)
                            <span class="sb-pill sb-pill--warning">{{ $sbb['announcement_drafts'] }} {{ __('draft') }}</span>
                        @endif
                    </a>
                </li>
            @endif

            {{-- 2026-07-18 — Email Notifications cross-linked from the Settings hub
                 (per-coach email template editor, route instructor.email-templates.*,
                 perm settings-email). Single source — no duplicate module. --}}
            @if (checkPermissionView('settings-email'))
                @php try { $sbEmailTplUrl = route('instructor.email-templates.index'); } catch (\Throwable $e) { $sbEmailTplUrl = null; } @endphp
                @if ($sbEmailTplUrl)
                    <li class="{{ Route::is('instructor.email-templates.*') ? 'active' : '' }}">
                        <a href="{{ $sbEmailTplUrl }}">
                            <span class="sb-icon"><i class="bi bi-envelope-paper"></i></span>
                            <span>{{ __('Email Notifications') }}</span>
                        </a>
                    </li>
                @endif
            @endif
        </ul>
    </details>

    {{-- GROUP 6 — REPORTS ──────────────────────────────────────── --}}
    {{-- Analytics + the centralised Reports module (Revenue / Payments /
         Invoices / Attendance), all gated by the analytics permission. --}}
    @if (checkPermissionView('analytics'))
        <details class="sb-group" data-sb-key="reports" open>
            <summary class="sb-group__head">
                <span>{{ __('Reports') }}</span>
                <i class="bi bi-chevron-down sb-group__chev"></i>
            </summary>
            <ul class="sb-nav">
                <li class="{{ Route::is('instructor.analytics.*') ? 'active' : '' }}">
                    <a href="{{ route('instructor.analytics.index') }}">
                        <span class="sb-icon"><i class="bi bi-graph-up-arrow"></i></span>
                        {{ __('Analytics') }}
                    </a>
                </li>
                {{-- 2026-07-18 (Dashboard Nav Enhancement #6/#7) — Reports module.
                     Each route() is try/caught so a prod build predating this
                     module hides the item instead of 500-ing the panel. --}}
                @php try { $sbReportsUrl = route('instructor.reports.index'); } catch (\Throwable $e) { $sbReportsUrl = null; } @endphp
                @if ($sbReportsUrl)
                    <li class="{{ Route::is('instructor.reports.index') && !request()->route('type') ? 'active' : '' }}">
                        <a href="{{ $sbReportsUrl }}">
                            <span class="sb-icon"><i class="bi bi-collection"></i></span>
                            {{ __('Reports Overview') }}
                        </a>
                    </li>
                    @foreach ([
                        ['type' => 'revenue',    'label' => __('Revenue'),    'icon' => 'bi-cash-stack'],
                        ['type' => 'payments',   'label' => __('Payments'),   'icon' => 'bi-credit-card'],
                        ['type' => 'invoices',   'label' => __('Invoices'),   'icon' => 'bi-receipt'],
                        ['type' => 'attendance', 'label' => __('Attendance'), 'icon' => 'bi-calendar-check'],
                    ] as $rep)
                        <li class="{{ Route::is('instructor.reports.show') && request()->route('type') === $rep['type'] ? 'active' : '' }}">
                            <a href="{{ route('instructor.reports.show', $rep['type']) }}">
                                <span class="sb-icon"><i class="bi {{ $rep['icon'] }}"></i></span>
                                {{ $rep['label'] }}
                            </a>
                        </li>
                    @endforeach
                @endif
            </ul>
        </details>
    @endif

    {{-- GROUP 7 — CONFIGURATION ────────────────────────────────── --}}
    {{-- Plan & Billing + the Settings hub. The hub's detailed sub-items
         (General, Zoom Live, Youtube, Staff, Roles, Permissions, Brand,
         Website Builder, Blog, Email Templates, Subscription History, Payout,
         Tax, Pricing Enquiries, Trial Sessions, Membership, Refer & Earn,
         Payment Gateway) render in the settings-hub side-nav on settings
         routes — surfacing them here too would duplicate that menu. --}}
    <details class="sb-group" data-sb-key="configuration" open>
        <summary class="sb-group__head">
            <span>{{ __('Configuration') }}</span>
            <i class="bi bi-chevron-down sb-group__chev"></i>
        </summary>
        <ul class="sb-nav">
            {{-- My Plan & Billing (2026-06-24, Phase 4) — read-only plan view.
                 2026-07-06 (Role Permission Test doc, issue A) — gated by the
                 new `my-plan` permission so a coach's billing isn't exposed to
                 every staff member by default. --}}
            @if (checkPermissionView('my-plan'))
                <li class="{{ Route::is('instructor.my-plan.*') ? 'active' : '' }}">
                    <a href="{{ route('instructor.my-plan.index') }}">
                        <span class="sb-icon"><i class="bi bi-card-checklist"></i></span>
                        <span>{{ __('Plan & Billing') }}</span>
                    </a>
                </li>
            @endif

            <li class="{{ Route::is('instructor.youtube-setting.*', 'instructor.zoom-setting.*', 'instructor.coach-staff.*', 'instructor.coach-staff-role.*', 'instructor.coach-staff-permission.*', 'instructor.setting.*', 'instructor.brand-settings.*', 'instructor.website-builder.*', 'instructor.payout.*', 'instructor.subscription-histories.*', 'instructor.tax.*', 'instructor.blogs.*', 'instructor.pricing-enquiries.*', 'instructor.trial-sessions.*', 'instructor.payment-gateways.*', 'membership.*', 'referral.*') ? 'active' : '' }}">
                <a href="{{ route('instructor.setting.index') }}">
                    <span class="sb-icon"><i class="bi bi-gear"></i></span>
                    {{ __('Settings') }}
                </a>
            </li>
        </ul>
    </details>

    <div class="sb-divider" style="margin-top:8px;"></div>

    {{-- Sign-out — a standalone action, not a category. --}}
    <div class="sb-section" style="padding-top:6px;">
        <nav>
            <ul class="sb-nav">
                <li>
                    <a href="{{ route('logout') }}" class="logout-link"
                        onclick="event.preventDefault(); $('#logout-form').trigger('submit');">
                        <span class="sb-icon"><i class="bi bi-box-arrow-left"></i></span>
                        {{ __('Logout') }}
                    </a>
                </li>
            </ul>
        </nav>
    </div>

    <div class="sb-footer">
        {{-- 2026-05-21 P4 — per-coach white-label.
             Coach sees the platform brand here (this is the COACH's
             panel, not their students' view), but if the platform
             admin has set a name in settings.app_name, that wins. --}}
        <span class="sb-footer-brand">{{ $brand->name ?? 'Coaching Platform' }}</span>
        <span class="sb-footer-ver">v1.0</span>
    </div>

</aside>

<form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">@csrf</form>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        document.querySelectorAll(".menu-dropdown > a").forEach(function(el) {
            el.addEventListener("click", function(e) {
                // If the parent is a real route ('Settings' now navigates to
                // /instructor/setting), allow the navigation. The chevron click
                // below handles toggle separately. Only the legacy javascript:void(0)
                // parents need the click-to-toggle behavior.
                var href = this.getAttribute("href") || "";
                if (href === "" || href === "#" || href.toLowerCase().startsWith("javascript:")) {
                    e.preventDefault();
                    this.parentElement.classList.toggle("open");
                }
            });
        });

        // Chevron toggle — works for both legacy and real-href parents.
        document.querySelectorAll(".menu-dropdown > a > .sb-arrow").forEach(function(el) {
            el.addEventListener("click", function(e) {
                e.preventDefault();
                e.stopPropagation();
                this.closest(".menu-dropdown").classList.toggle("open");
            });
        });

        // ── 2026-05-20 Quick Add dropdown ──
        var qaWrap = document.querySelector(".sb-quick-add");
        var qaBtn  = document.getElementById("sbQuickAddBtn");
        if (qaWrap && qaBtn) {
            qaBtn.addEventListener("click", function(e) {
                e.stopPropagation();
                var isOpen = qaWrap.classList.toggle("is-open");
                qaBtn.setAttribute("aria-expanded", isOpen ? "true" : "false");
            });
            document.addEventListener("click", function(e) {
                if (!qaWrap.contains(e.target)) {
                    qaWrap.classList.remove("is-open");
                    qaBtn.setAttribute("aria-expanded", "false");
                }
            });
            document.addEventListener("keydown", function(e) {
                if (e.key === "Escape" && qaWrap.classList.contains("is-open")) {
                    qaWrap.classList.remove("is-open");
                    qaBtn.setAttribute("aria-expanded", "false");
                    qaBtn.focus();
                }
            });
        }

        // ── 2026-06-01 Responsive: mobile off-canvas drawer close handlers ──
        // The shared topbar hamburger (#mbsSidebarToggle) OPENS the drawer by
        // toggling .mbs-sidebar-collapsed on <body> + .instructor-sidebar.
        // Here we wire the ways to CLOSE it: tap the backdrop, tap a nav link,
        // or press Escape. We clear the class from BOTH targets the topbar
        // toggles so the open/closed state never desyncs.
        (function () {
            var sb = document.getElementById("instructorSidebar");
            var ov = document.getElementById("instructorSidebarOverlay");
            if (!sb) return;
            function closeDrawer() {
                document.body.classList.remove("mbs-sidebar-collapsed");
                sb.classList.remove("mbs-sidebar-collapsed");
            }
            if (ov) ov.addEventListener("click", closeDrawer);
            document.addEventListener("keydown", function (e) {
                if (e.key === "Escape" && document.body.classList.contains("mbs-sidebar-collapsed")) {
                    closeDrawer();
                }
            });
            sb.querySelectorAll("a[href]").forEach(function (a) {
                a.addEventListener("click", function () {
                    if (window.innerWidth <= 991.98) closeDrawer();
                });
            });
        })();

        // ── 2026-05-20 Collapsible groups: restore + persist state ──
        // Each <details data-sb-key="X"> stores its open/closed state in
        // localStorage as sb_group_open_X so the IA layout the coach
        // chose stays put between page loads.
        document.querySelectorAll("details.sb-group[data-sb-key]").forEach(function(d) {
            var key = "sb_group_open_" + d.dataset.sbKey;
            try {
                var saved = localStorage.getItem(key);
                if (saved === "false") d.removeAttribute("open");
                else if (saved === "true") d.setAttribute("open", "");
            } catch (_) {}
            d.addEventListener("toggle", function() {
                try { localStorage.setItem(key, d.open ? "true" : "false"); } catch (_) {}
            });
        });
    });
</script>
