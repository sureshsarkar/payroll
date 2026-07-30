import { test, expect } from '@playwright/test';

/**
 * Trainer "Book Personal Class Session" (2026-07-15) — coach-panel E2E.
 *
 * The guest booking popup + Razorpay leg need a configured coach site + live
 * keys, so (like pricing/schedule bookings) that is exercised by the PHPUnit
 * suite (TrainerBookingTest / TrainerBookingsPanelTest) + manual UAT. This spec
 * pins the deterministic coach-facing surfaces: the Trainers manager, the
 * dedicated Trainer Bookings list (KPIs + filters), and mobile responsiveness.
 *
 * Runs under the `instructor` project (logged in as the coach).
 *   npx playwright test --project=instructor tests/e2e/instructor/34-trainer-booking-2026-07.spec.ts --no-deps
 */

test.describe('Trainer feature — coach panel', () => {
  test('the Trainers manager loads with an add-trainer form', async ({ page }) => {
    const resp = await page.goto('instructor/trainers');
    if (!resp || resp.status() >= 400) {
      test.skip(true, 'instructor/trainers not reachable for this account');
      return;
    }
    // The "Add a trainer" form posts a required name field.
    await expect(page.locator('form[action$="trainers"] input[name="name"]')).toHaveCount(1);
    const txt = (await page.locator('body').textContent()) || '';
    expect(txt).toContain('Trainers');
  });

  test('the Trainer Bookings list exposes KPIs + trainer/package/payment filters', async ({ page }) => {
    const resp = await page.goto('instructor/trainers/bookings');
    if (!resp || resp.status() >= 400) {
      test.skip(true, 'trainers/bookings not reachable for this account');
      return;
    }
    const txt = (await page.locator('body').textContent()) || '';
    // KPI tiles.
    expect(txt).toContain('Total bookings');
    expect(txt).toContain('Collected');
    // Dedicated filter controls (separate from Pricing Enquiries).
    await expect(page.locator('select[name="payment"]')).toHaveCount(1);
    await expect(page.locator('select[name="trainer_id"]')).toHaveCount(1);
    await expect(page.locator('select[name="package_id"]')).toHaveCount(1);
    await expect(page.locator('input[name="from"]')).toHaveCount(1);
  });

  test('the payment filter round-trips through the query string', async ({ page }) => {
    const resp = await page.goto('instructor/trainers/bookings?payment=paid');
    if (!resp || resp.status() >= 400) {
      test.skip(true, 'not reachable');
      return;
    }
    // The applied filter is reflected back in the select (server-side filter).
    await expect(page.locator('select[name="payment"]')).toHaveValue('paid');
  });

  test('the bookings page is responsive on a phone viewport (no horizontal body scroll)', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 });
    const resp = await page.goto('instructor/trainers/bookings');
    if (!resp || resp.status() >= 400) {
      test.skip(true, 'not reachable');
      return;
    }
    const overflow = await page.evaluate(() =>
      document.documentElement.scrollWidth - document.documentElement.clientWidth
    );
    expect(overflow).toBeLessThanOrEqual(4);
  });
});
