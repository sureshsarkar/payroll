import { test, expect } from '@playwright/test';

/**
 * Classes & Schedules "Book a Session" (2026-07-14) — coach-panel E2E.
 *
 * The public booking popup + Razorpay leg need a configured coach site + live
 * keys, so those are covered by the PHPUnit suite (ScheduleBookingTest) + manual
 * UAT. This spec pins the deterministic coach-facing surface: the booking
 * enquiries land in the panel with a payment-status filter, and the page is
 * responsive.
 *
 *   npx playwright test --project=instructor tests/e2e/instructor/33-schedule-booking-2026-07.spec.ts --no-deps
 */

test.describe('Schedule booking — coach panel', () => {
  test('the enquiry list exposes a payment-status filter', async ({ page }) => {
    const resp = await page.goto('instructor/pricing-enquiries');
    if (!resp || resp.status() >= 400) {
      test.skip(true, 'pricing-enquiries not reachable for this account');
      return;
    }
    // Payment filter dropdown (added for the class-booking payment columns).
    await expect(page.locator('select[name="payment"]')).toHaveCount(1);
    const txt = (await page.locator('body').textContent()) || '';
    expect(txt).toContain('All payments');
  });

  test('the page is responsive on a phone viewport (no horizontal body scroll)', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 });
    const resp = await page.goto('instructor/pricing-enquiries');
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
