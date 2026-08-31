{{--
  Vertical settings navigation — visual design borrowed from the DHS Cloud
  Services CRM screenshot.

  LMS removal phase 2 (2026-08-31) — rewritten. This used to list 17 settings
  pages (Zoom, YouTube, Staff, Roles, Permissions, Brand, Website Builder,
  Blog, Email Templates, Subscription History, Payout, Tax, Pricing
  Enquiries, Trial Sessions, Membership, Refer & Earn, Payment Gateway); all
  16 of those routes are gone, along with checkPermissionView() (the
  coach-staff permission gate this partial called per-item) and
  User::activePlan() (used to conditionally show the Payment Gateway item).
  instructor.setting.index (General) is the only surviving settings page, so
  the multi-item nav collapsed to a single link.
--}}

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
        <li>
            <a href="{{ route('instructor.setting.index') }}" class="is-active">
                <span class="settings-nav-icon"><i class="bi bi-gear"></i></span>
                <span class="settings-nav-label">{{ __('General') }}</span>
            </a>
        </li>
    </ul>
</aside>
