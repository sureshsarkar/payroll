import { test, expect } from '@playwright/test';

/**
 * Responsive matrix — 2026-06-01 (auth) extended 2026-06-02 (L-A full set).
 *
 * Confirms NO horizontal page scroll across the full enterprise breakpoint
 * matrix (320 → 1440) for the high-traffic anonymous surface: home, course
 * catalog, instructors, contact, and the auth pages (user + admin). Pages
 * that 404 are skipped. Authed panels carry their own @320/375 specs.
 *
 * Run only this file:
 *   npx playwright test --project=public tests/e2e/public/08-auth-responsive-2026-06.spec.ts
 */

const PAGES = [
  '',                  // home
  'courses',
  'all-coaches',       // public instructors/coaches listing
  'contact',
  'login',
  'register',
  'forgot-password',
  'admin/login',
];

// Full enterprise breakpoint matrix: phones -> tablet -> laptop -> desktop.
const WIDTHS = [320, 375, 414, 768, 1024, 1366, 1440];

for (const path of PAGES) {
  for (const w of WIDTHS) {
    test(`${path || 'home'} @ ${w}px — no horizontal page scroll`, async ({ page }) => {
      await page.setViewportSize({ width: w, height: 900 });
      const resp = await page.goto(path);
      const status = resp?.status() ?? 0;
      if (status >= 400) {
        test.skip(true, `${path} returned ${status}`);
        return;
      }
      const overflow = await page.evaluate(
        () => document.documentElement.scrollWidth - document.documentElement.clientWidth
      );
      expect(overflow, `${path} overflows the page by ${overflow}px at ${w}px`).toBeLessThanOrEqual(1);
    });
  }
}
