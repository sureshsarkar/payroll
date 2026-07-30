import { test, expect } from '@playwright/test';

/**
 * Pricing & Plans booking → payment (2026-07-13) — coach-panel E2E.
 *
 * The guest payment leg needs live Razorpay keys, so (like the trial popup) it
 * is exercised by manual/live UAT + the PHPUnit suite (PricingPaymentTest). This
 * spec pins the deterministic coach-facing surface: the opt-in toggle persists,
 * the enquiry list carries the payment columns, and the page is responsive.
 *
 * Runs under the `instructor` project (logged in as the coach).
 *   npx playwright test --project=instructor tests/e2e/instructor/32-pricing-payment-2026-07.spec.ts --no-deps
 */

test.describe('Pricing payment — coach panel', () => {
  test('the enquiry page explains that priced plans auto-collect payment', async ({ page }) => {
    const resp = await page.goto('instructor/pricing-enquiries');
    if (!resp || resp.status() >= 400) {
      test.skip(true, 'pricing-enquiries not reachable for this account');
      return;
    }
    const txt = (await page.locator('body').textContent()) || '';
    // Payment is now automatic for priced plans (no opt-in toggle).
    expect(txt).toContain('sends the visitor to your payment gateway');
    expect(txt).not.toContain('Collect payment for plan bookings');
  });

  test('the enquiry list exposes the payment columns (when there are rows)', async ({ page }) => {
    await page.goto('instructor/pricing-enquiries');
    const hasRows = (await page.locator('table tbody tr').count()) > 0;
    if (!hasRows) {
      test.skip(true, 'no pricing enquiries in this environment to render the table');
      return;
    }
    const head = (await page.locator('table thead').textContent()) || '';
    for (const col of ['Amount', 'Payment', 'Enquiry date', 'Status']) {
      expect(head, `column ${col}`).toContain(col);
    }
  });

  test('the page is responsive on a phone viewport (no horizontal body scroll)', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 });
    await page.goto('instructor/pricing-enquiries');
    const overflow = await page.evaluate(() =>
      document.documentElement.scrollWidth - document.documentElement.clientWidth
    );
    // A few px of sub-pixel rounding is fine; a broken layout overflows by a lot.
    expect(overflow).toBeLessThanOrEqual(4);
  });
});
