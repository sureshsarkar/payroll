{{--
  Vertical settings navigation — visual design borrowed from the DHS Cloud
  Services CRM screenshot.

  Active item is auto-detected from the current route name. Override by
  passing $active explicitly:
      @include('frontend.instructor-dashboard.settings.partials.side-nav', ['active' => 'zoom_live'])

  Each item links to its real route. The instructor-dashboard master layout
  conditionally injects this partial on settings routes (see
  layouts/master.blade.php).
--}}

@php
    // 2026-07-04 (RBAC Phase 5) — each item carries the permission slug that
    // gates it. A real coach sees everything (checkPermissionView returns 1);
    // a staff member sees ONLY the settings they hold, so restricted pages are
    // hidden from the menu (matches the route-level `permission:` gate).
    $settingsItems = [
        ['key' => 'settings',         'label' => __('General'),              'icon' => 'bi-gear',              'route' => 'instructor.setting.index',                  'matches' => ['instructor.setting.*'],                'perm' => 'settings-profile'],
        ['key' => 'zoom_live',        'label' => __('Zoom Live'),            'icon' => 'bi-camera-video',      'route' => 'instructor.zoom-setting.index',             'matches' => ['instructor.zoom-setting.*'],           'perm' => 'settings-zoom'],
        ['key' => 'youtube',          'label' => __('Youtube'),              'icon' => 'bi-youtube',           'route' => 'instructor.youtube-setting.index',          'matches' => ['instructor.youtube-setting.*'],        'perm' => 'settings-youtube'],
        // 2026-05-20 — corporate polish:
        //   * "Staffs" → "Staff" (correct English)
        //   * group key 'team' so we can flag them visually as a logical
        //     People & Access cluster in the sidebar.
        ['key' => 'staffs',           'label' => __('Staff'),                'icon' => 'bi-people-fill',       'route' => 'instructor.coach-staff.index',              'matches' => ['instructor.coach-staff.*'],            'group' => 'team', 'perm' => 'coach-staff'],
        ['key' => 'roles',            'label' => __('Roles'),                'icon' => 'bi-shield-lock',       'route' => 'instructor.coach-staff-role.index',         'matches' => ['instructor.coach-staff-role.*'],       'group' => 'team', 'perm' => 'roles'],
        ['key' => 'permissions',      'label' => __('Permissions'),          'icon' => 'bi-key',               'route' => 'instructor.coach-staff-permission.index',   'matches' => ['instructor.coach-staff-permission.*'], 'group' => 'team', 'perm' => 'roles'],
        // 2026-05-21 — Per-coach white-label brand profile (logo,
        // colors, support email, footer text). Sits next to Website
        // Builder since both shape what students see.
        ['key' => 'brand',            'label' => __('Brand'),                'icon' => 'bi-palette',           'route' => 'instructor.brand-settings.edit',            'matches' => ['instructor.brand-settings.*'],         'perm' => 'settings-brand'],
        ['key' => 'website_builder',  'label' => __('Website Builder'),      'icon' => 'bi-layout-text-window','route' => 'instructor.website-builder.index',          'matches' => ['instructor.website-builder.*'],        'perm' => 'website-builder'],
        // 2026-06-29 — Blog moved into the settings hub (website content, next to
        // Website Builder) instead of a top-level sidebar item.
        ['key' => 'blog',             'label' => __('Blog'),                 'icon' => 'bi-pencil-square',     'route' => 'instructor.blogs.index',                    'matches' => ['instructor.blogs.*'],                  'perm' => 'blogs'],
        // 2026-06-26 — per-coach email template customisation lives in the
        // settings hub next to Brand / Website Builder (all shape what students
        // receive), not as a top-level People sidebar item.
        ['key' => 'email_templates',  'label' => __('Email Templates'),      'icon' => 'bi-envelope-paper',    'route' => 'instructor.email-templates.index',          'matches' => ['instructor.email-templates.*'],        'perm' => 'settings-email'],
        ['key' => 'subscription_hist','label' => __('Subscription History'), 'icon' => 'bi-receipt',           'route' => 'instructor.subscription-histories.index',   'matches' => ['instructor.subscription-histories.*'], 'perm' => 'subscription-histories'],
        ['key' => 'request_payout',   'label' => __('Request Payout'),       'icon' => 'bi-wallet2',           'route' => 'instructor.payout.index',                   'matches' => ['instructor.payout.*'],                 'perm' => 'payout'],
        // 2026-06-13 — per-coach Tax Settings live in the settings hub (next to
        // the other money/compliance items), not as a top-level sidebar item.
        ['key' => 'tax',              'label' => __('Tax Settings'),         'icon' => 'bi-percent',           'route' => 'instructor.tax.index',                      'matches' => ['instructor.tax.*'],                    'perm' => 'settings-tax'],
        // 2026-06-29 — Pricing Enquiries (leads from the Pricing & Plans section)
        // moved into the settings hub instead of a top-level sidebar item.
        ['key' => 'pricing_enquiries','label' => __('Pricing Enquiries'),    'icon' => 'bi-card-checklist',    'route' => 'instructor.pricing-enquiries.index',        'matches' => ['instructor.pricing-enquiries.*'],      'perm' => 'pricing-enquiries'],
        // 2026-07-03 — "Book Your Trial Session" popup: settings, time slots,
        // enquiries and payments all live under one settings-hub entry.
        ['key' => 'trial_sessions',  'label' => __('Trial Sessions'),       'icon' => 'bi-calendar-check',    'route' => 'instructor.trial-sessions.index',           'matches' => ['instructor.trial-sessions.*'],         'perm' => 'trial-sessions'],
        ['key' => 'membership',       'label' => __('Membership'),           'icon' => 'bi-patch-check',       'route' => 'membership.index',                          'matches' => ['membership.*'],                        'perm' => 'membership'],
        ['key' => 'refer_earn',       'label' => __('Refer & Earn'),         'icon' => 'bi-gift',              'route' => 'referral.index',                            'matches' => ['referral.*'],                          'perm' => 'referral'],
    ];

    // 2026-06-29 — Payment Gateway is Enterprise-only (the route is also gated
    // by requires.enterprise). Surface it in the settings hub for Enterprise
    // coaches; hidden for everyone else.
    if (userAuth()?->activePlan()?->isEnterprise()) {
        $settingsItems[] = ['key' => 'payment_gateways', 'label' => __('Payment Gateway'), 'icon' => 'bi-credit-card-2-front', 'route' => 'instructor.payment-gateways.index', 'matches' => ['instructor.payment-gateways.*'], 'perm' => 'settings-payment-gateway'];
    }

    // Surface a red dot on the Zoom Live menu when this instructor's
    // OAuth token failed the most-recent daily probe. Without this,
    // a dead token only becomes visible when a student tries to join.
    $zoomDead = false;
    try {
        $zoomCred = userAuth()?->zoom_credential;
        $zoomDead = $zoomCred && $zoomCred->health_status === 'dead';
    } catch (\Throwable) {}

    // Auto-detect active item from the current route if $active wasn't passed in.
    if (!isset($active)) {
        $active = null;
        foreach ($settingsItems as $it) {
            if (\Illuminate\Support\Facades\Route::is(...$it['matches'])) {
                $active = $it['key'];
                break;
            }
        }
    }
@endphp

<style>
    /* Vertical settings navigation */
    .settings-nav-card {
        background: #fff;
        border-radius: 10px;
        padding: 16px 8px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.04);
    }
    .settings-nav-card .settings-nav-title {
        font-size: 18px;
        font-weight: 700;
        color: #1c1a4a;
        padding: 4px 12px 14px 12px;
        margin: 0;
    }
    .settings-nav-list {
        list-style: none;
        margin: 0;
        padding: 0;
    }
    .settings-nav-list a {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 12px;
        margin: 2px 4px;
        border-radius: 8px;
        color: #4b5563;
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        transition: background-color .12s ease, color .12s ease;
        border-left: 3px solid transparent;
    }
    .settings-nav-list a .settings-nav-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 30px;
        height: 30px;
        border-radius: 6px;
        background: var(--corp-brand-bg, #ecfdf5);
        color: var(--corp-brand, #10b981);
        font-size: 14px;
        flex: 0 0 30px;
    }
    .settings-nav-list a:hover {
        background: #f8fafc;
        color: #1c1a4a;
    }
    .settings-nav-list a.is-active {
        background: var(--corp-brand-bg, #ecfdf5);
        color: #1c1a4a;
        border-left-color: var(--corp-brand, #10b981);
        font-weight: 600;
    }
    .settings-nav-list a.is-active .settings-nav-icon {
        background: var(--corp-brand, #10b981);
        color: #fff;
    }
    .settings-nav-badge-dot {
        display: inline-block;
        width: 8px; height: 8px;
        border-radius: 50%;
        background: #dc2626;
        margin-left: 6px;
        vertical-align: middle;
        animation: settings-nav-pulse 1.6s ease-in-out infinite;
    }
    @keyframes settings-nav-pulse {
        0%, 100% { opacity: 1; }
        50%      { opacity: .45; }
    }
</style>

<style>
    /* 2026-07-10 (New Changes for UI #4) — dark mode for this page's bespoke components. */
    html[data-theme="dark"] .settings-nav-card { background: #1e293b; box-shadow: none; }
    html[data-theme="dark"] .settings-nav-card .settings-nav-title { color: #e2e8f0; }
    html[data-theme="dark"] .settings-nav-list a { color: #94a3b8; }
    html[data-theme="dark"] .settings-nav-list a:hover { background: #17233a; color: #e2e8f0; }
    html[data-theme="dark"] .settings-nav-list a.is-active { color: #e2e8f0; }
</style>

<aside class="settings-nav-card">
    <h5 class="settings-nav-title">{{ __('Settings') }}</h5>
    <ul class="settings-nav-list">
        @foreach ($settingsItems as $it)
            {{-- Hide the item when a staff member lacks its permission. A real
                 coach always passes (checkPermissionView returns 1). --}}
            @continue(!empty($it['perm']) && ! checkPermissionView($it['perm']))
            @php
                try { $url = route($it['route']); } catch (\Throwable $e) { $url = '#'; }
            @endphp
            <li>
                <a href="{{ $url }}" class="{{ $active === $it['key'] ? 'is-active' : '' }}">
                    <span class="settings-nav-icon"><i class="bi {{ $it['icon'] }}"></i></span>
                    <span class="settings-nav-label">
                        {{ $it['label'] }}
                        @if ($zoomDead && $it['key'] === 'zoom_live')
                            <span class="settings-nav-badge-dot" title="{{ __('Reconnect your Zoom account') }}"></span>
                        @endif
                    </span>
                </a>
            </li>
        @endforeach
    </ul>
</aside>
