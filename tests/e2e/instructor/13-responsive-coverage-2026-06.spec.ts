import { test, expect } from '@playwright/test';

/**
 * Responsive audit — 2026-06-01 broad coverage (instructor panel).
 *
 * Loads a wide set of coach pages at phone width and asserts no
 * page-level horizontal scroll. This is the "don't skip pages" net that
 * surfaces overflow breaks beyond the hand-picked pages in spec 11.
 *
 * Pages that redirect / 404 for the test account are skipped (not failed).
 *
 * Run only this file:
 *   npx playwright test --project=instructor tests/e2e/instructor/13-responsive-coverage-2026-06.spec.ts
 */

const PAGES = [
  'instructor/dashboard',
  'instructor/setting',
  'instructor/courses',
  'instructor/courses/create',
  'instructor/coach-orders',
  'instructor/announcements',
  'instructor/announcements/create',
  'instructor/lesson-question',
  'instructor/analytics',
  'instructor/wishlist',
  'instructor/fees',
  'instructor/brand-settings',
  'instructor/live-classes',
  'instructor/my-students',
  'instructor/payout',
  'instructor/email-setting',
  'instructor/zoom-setting',
  'instructor/subscription-histories',
];

const WIDTHS = [375, 320];

test.describe('Responsive coverage 2026-06 — instructor', () => {
  for (const path of PAGES) {
    for (const w of WIDTHS) {
      test(`${path} @ ${w}px — no horizontal page scroll`, async ({ page }) => {
        await page.setViewportSize({ width: w, height: 900 });
        const resp = await page.goto(path);
        const status = resp?.status() ?? 0;
        // Skip pages that aren't available for this account (redirect to a
        // membership wall, 404, etc.). We only audit pages that actually render.
        if (status >= 400) {
          test.skip(true, `${path} returned ${status} — not auditable for this account`);
          return;
        }
        const overflow = await page.evaluate(
          () => document.documentElement.scrollWidth - document.documentElement.clientWidth
        );
        expect(overflow, `${path} overflows the page by ${overflow}px at ${w}px`).toBeLessThanOrEqual(1);
      });
    }
  }
});
