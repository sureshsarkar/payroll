import { test, expect } from '@playwright/test';

/**
 * Responsive audit — 2026-06-01 (admin panel coverage).
 *
 * Systematic no-horizontal-page-scroll guard across the main admin
 * back-office pages at phone widths. The admin panel is Bootstrap 4.3.1;
 * BS4 table-responsive + grid handle most cases, so this is primarily a
 * regression net that flags any page whose content forces the whole page
 * to scroll sideways on a phone.
 *
 * Run only this file:
 *   npx playwright test --project=admin tests/e2e/admin/11-responsive-2026-06.spec.ts
 */

const WIDTHS = [320, 375];

const ADMIN_PAGES = [
  { path: 'admin/dashboard', label: 'Dashboard' },
  { path: 'admin/all-instructors', label: 'Instructors' },
  { path: 'admin/all-customers', label: 'Customers' },
  { path: 'admin/course', label: 'Courses' },
  { path: 'admin/course-category', label: 'Categories' },
  { path: 'admin/orders', label: 'Orders' },
  { path: 'admin/coupon', label: 'Coupons' },
  { path: 'admin/instructor-request', label: 'Instructor requests' },
  { path: 'admin/role', label: 'Roles' },
  { path: 'admin/general-setting', label: 'General settings' },
  { path: 'admin/edit-profile', label: 'Profile' },
  { path: 'admin/settings', label: 'Settings hub' },
];

test.describe('Responsive audit 2026-06 — admin coverage', () => {
  for (const { path, label } of ADMIN_PAGES) {
    for (const w of WIDTHS) {
      test(`${label} @ ${w}px — no horizontal page scroll`, async ({ page }) => {
        await page.setViewportSize({ width: w, height: 900 });
        const resp = await page.goto(path);
        expect(resp?.status() ?? 0, `${path} responded`).toBeLessThan(500);

        const overflow = await page.evaluate(
          () => document.documentElement.scrollWidth - document.documentElement.clientWidth
        );
        expect(overflow, `${path} overflows the page by ${overflow}px at ${w}px`).toBeLessThanOrEqual(1);
      });
    }
  }
});
