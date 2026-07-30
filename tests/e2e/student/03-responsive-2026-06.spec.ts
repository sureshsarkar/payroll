import { test, expect } from '@playwright/test';

/**
 * Responsive design audit — 2026-06-01 (student panel).
 *
 * Mirrors the instructor responsive guard. The student live-classes
 * listing renders a 5-column `.oh-table` inside an `.oh-card` whose
 * `overflow:hidden` previously clipped the Join column on phones. The
 * fix sets overflow-x:auto so the table swipes inside the card.
 *
 * Run only this file:
 *   npx playwright test --project=student tests/e2e/student/03-responsive-2026-06.spec.ts
 */

const WIDTHS = [320, 375, 768];

const PAGES = [
  { path: 'student/live-classes', label: 'Live Classes' },
  { path: 'student/dashboard', label: 'Dashboard' },
];

test.describe('Responsive audit 2026-06 — student', () => {
  for (const { path, label } of PAGES) {
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

  test('live-classes .oh-card scrolls horizontally (does not clip) at 375px', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 900 });
    const resp = await page.goto('student/live-classes');
    expect(resp?.status() ?? 0).toBeLessThan(500);

    const card = page.locator('.oh-card').first();
    if ((await card.count()) === 0) {
      test.skip(true, 'no .oh-card rendered in this data state');
      return;
    }
    const overflowX = await card.evaluate((el) => getComputedStyle(el).overflowX);
    expect(['auto', 'scroll'], `.oh-card overflow-x computed as "${overflowX}"`).toContain(overflowX);
  });
});
