import { test, expect } from '@playwright/test';

/**
 * Responsive audit — 2026-06-01 broad coverage (student panel).
 *
 * Loads a wide set of learner pages at phone width and asserts no
 * page-level horizontal scroll. Pages that redirect / 404 for the test
 * account are skipped (not failed).
 *
 * Run only this file:
 *   npx playwright test --project=student tests/e2e/student/04-responsive-coverage-2026-06.spec.ts
 */

const PAGES = [
  'student/dashboard',
  'student/live-classes',
  'student/enrolled-courses',
  'student/orders',
  'student/setting',
  'student/quiz-attempts',
  'student/reviews',
  'student/wishlist',
  'student/certificates',
  'student/my-courses',
];

const WIDTHS = [375, 320];

test.describe('Responsive coverage 2026-06 — student', () => {
  for (const path of PAGES) {
    for (const w of WIDTHS) {
      test(`${path} @ ${w}px — no horizontal page scroll`, async ({ page }) => {
        await page.setViewportSize({ width: w, height: 900 });
        const resp = await page.goto(path);
        const status = resp?.status() ?? 0;
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
