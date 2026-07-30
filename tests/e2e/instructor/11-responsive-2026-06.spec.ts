import { test, expect } from '@playwright/test';

/**
 * Responsive design audit — 2026-06-01 (instructor panel).
 *
 * Guards the two structural responsive contracts on the coach dashboard:
 *
 *  1. No page-level horizontal scrollbar at phone/tablet widths. A full
 *     page that scrolls sideways is the #1 "broken on mobile" symptom.
 *
 *  2. `.corp-table-wrap` (shared listing-table card used by My Sales,
 *     Live Classes, My Students, Payouts, Batches, …) must allow
 *     HORIZONTAL SCROLL on small screens rather than clipping. It used
 *     `overflow:hidden`, which silently hid the right-hand columns —
 *     including the Actions buttons — with no way to reach them. The fix
 *     keeps the rounded corners (overflow-y hidden) but sets
 *     overflow-x:auto so wide tables swipe inside the card.
 *
 * Run only this file:
 *   npx playwright test --project=instructor tests/e2e/instructor/11-responsive-2026-06.spec.ts
 */

const WIDTHS = [320, 375, 768];

const TABLE_PAGES = [
  { path: 'instructor/coach-orders', label: 'My Sales' },
  { path: 'instructor/live-classes', label: 'Live Classes' },
];

test.describe('Responsive audit 2026-06 — instructor', () => {
  for (const { path, label } of TABLE_PAGES) {
    for (const w of WIDTHS) {
      test(`${label} @ ${w}px — no horizontal page scroll`, async ({ page }) => {
        await page.setViewportSize({ width: w, height: 900 });
        const resp = await page.goto(path);
        expect(resp?.status() ?? 0, `${path} responded`).toBeLessThan(500);

        // The document must not be wider than the viewport (allow 1px for
        // sub-pixel rounding). A wide table must scroll INSIDE its card,
        // never push the whole page sideways.
        const overflow = await page.evaluate(
          () => document.documentElement.scrollWidth - document.documentElement.clientWidth
        );
        expect(overflow, `${path} overflows the page by ${overflow}px at ${w}px`).toBeLessThanOrEqual(1);
      });
    }
  }

  test('corp-table-wrap scrolls horizontally (does not clip) at 375px', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 900 });
    const resp = await page.goto('instructor/coach-orders');
    expect(resp?.status() ?? 0).toBeLessThan(500);

    const wrap = page.locator('.corp-table-wrap').first();
    if ((await wrap.count()) === 0) {
      test.skip(true, 'no corp-table-wrap rendered in this data state');
      return;
    }
    // The whole point of the fix: overflow-x must be a scrolling value,
    // not `hidden` (which clipped the Actions column) or `visible`
    // (which would push the page sideways).
    const overflowX = await wrap.evaluate((el) => getComputedStyle(el).overflowX);
    expect(['auto', 'scroll'], `corp-table-wrap overflow-x computed as "${overflowX}"`).toContain(overflowX);
  });
});
