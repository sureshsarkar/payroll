import { test, expect } from '@playwright/test';

/**
 * "Update Batch Date Format in Course Enrollment Modal" (2026-07-08).
 * On a live/hybrid course, the "Choose your batch" modal must show Start/End
 * dates as "D MMM YYYY" (e.g. 7 Jul 2026) with NO time, and the
 * "Confirm & add to cart" footer must be sticky. Uses a seeded demo live course.
 */

const LIVE_COURSE_SLUG = 'advanced-java-programming-for-software';   // demo live course with future batches
const DMY = /^\d{1,2} [A-Z][a-z]{2} \d{4}$/;   // e.g. "7 Jul 2026"

test('batch modal shows D MMM YYYY dates (no time) and a sticky confirm button', async ({ page }) => {
  const resp = await page.goto(`course/${LIVE_COURSE_SLUG}`, { waitUntil: 'domcontentloaded' });
  test.skip(!resp || resp.status() !== 200, 'demo live course not present');

  await page.locator('.add-to-cart').first().click();
  const modal = page.locator('#addToCartModal');
  await expect(modal).toBeVisible();

  // Batch cards load via AJAX.
  await page.waitForSelector('#batchContainer .batch-card', { timeout: 15_000 });
  const cards = page.locator('#batchContainer .batch-card');
  const n = await cards.count();
  expect(n, 'at least one batch card').toBeGreaterThan(0);

  // EVERY batch: Starts + Ends are date-only "D MMM YYYY".
  for (let i = 0; i < n; i++) {
    for (const key of ['Starts', 'Ends']) {
      const val = (await cards.nth(i).locator('.batch-meta li', { hasText: key }).locator('.bm-v').innerText()).trim();
      expect(val, `${key} on card ${i} must be "D MMM YYYY"`).toMatch(DMY);
      expect(val, `${key} must not include a clock time`).not.toContain(':');
    }
    // The separate Time row still carries the time (unchanged).
    await expect(cards.nth(i).locator('.batch-meta li', { hasText: 'Time' })).toBeVisible();
  }

  // Footer is sticky.
  const pos = await page.locator('#addToCartModal .bm-foot').evaluate((el) => getComputedStyle(el).position);
  expect(pos, 'footer must be sticky').toBe('sticky');

  // Selecting a batch reveals the confirm button (which is inside the sticky footer).
  await cards.first().locator('.choose-batch-btn').click();
  await expect(page.locator('#confirmBatch')).toBeVisible();
});
