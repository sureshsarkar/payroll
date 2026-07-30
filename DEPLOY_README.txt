MBSGuru - Super Admin dashboard + menu coverage
Date: 2026-07-20

FILES (3):
  - app/Http/Controllers/Admin/DashboardController.php
  - resources/views/admin/dashboard.blade.php
  - resources/views/admin/sidebar.blade.php
  - public/backend/css/admin-light-theme-2026-06.css

DASHBOARD (rebuilt to the approved design)
  Platform overview header, action queue (from the controller's existing
  action_items), 6 consolidated KPIs (was ~25 cards), sales chart (same data
  + year/month filter), Top coaches, Membership health, Referrals, System status.
  All money comes from FinancialReportingService (commission_by_currency and
  coachLifetime) - no commission formula is re-derived.
  System status shows ONLY real values (queue driver, failed jobs, queued jobs).
  'Last backup' / 'storage used' from the mockup are intentionally omitted -
  there is no reliable source for them and they must not be invented.

SIDEBAR THEME
  admin-light-theme-2026-06.css retuned to the emerald accent shared with the
  coach + student panels, plus spacing/typography refinement. Tokens only -
  every rule stays scoped to .main-sidebar, so no other admin surface changes.
  Markup, dropdown JS, module includes and permission gates are untouched.

SIDEBAR (menu coverage)
  Surfaced 5 pages that had NO menu entry: Zoom Health (true orphan),
  Referral Commissions, Coach Memberships, Plan Conversion, Subscriptions.
  Existing 8 groups, module guards and permission gates are unchanged.

NOTE: resources/views/admin/partials/stat-card.blade.php is now unused (the 8
lifetime stat cards it served were consolidated into the 6 KPIs). It is left in
place - delete only if you are sure nothing else will need it.

DEPLOY:
  1. Extract at the Laravel APP ROOT.
  2. php artisan view:clear

Do NOT run optimize:clear / cache:clear (wipes the 'setting' cache -> 500s).
No migration. No route change.
