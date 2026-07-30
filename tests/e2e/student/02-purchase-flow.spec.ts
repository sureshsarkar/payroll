import { test, expect } from '@playwright/test';

/**
 * Student purchase flow — public catalog → cart → checkout entry.
 *
 * What this pins:
 *   - course catalog renders for an authenticated student
 *   - empty cart page renders without 500
 *   - checkout entry doesn't 500 on an empty cart (FT-LOGIC-2 contract:
 *     re-validate coupon at checkout entry, no null-deref)
 *   - apply-coupon endpoint validates input (FT-COUP-1/2 + FT-VAL-5)
 *   - remove-coupon endpoint reachable
 *
 * What this does NOT cover:
 *   - Adding a real course to cart (needs a course row + the add-to-cart
 *     route uses different shape per course type — live/recorded — and
 *     requires a batch_id for live courses)
 *   - The payment-success path (out of scope for E2E without sandbox keys)
 *   - The order-success / order-failed pages (need a real Stripe/Razorpay
 *     redirect flow which can't run on localhost without a tunnel)
 */

test('course catalog renders for student', async ({ page }) => {
  // Public catalog routes vary across deploys: /courses vs /course.
  for (const url of ['courses', 'course']) {
    const resp = await page.goto(url);
    if (resp && resp.status() < 400) {
      await expect(page.locator('body')).not.toBeEmpty();
      return;
    }
  }
  throw new Error('Neither /courses nor /course responded with 2xx');
});

test('cart page renders (empty state OK)', async ({ page }) => {
  const resp = await page.goto('cart');
  expect(resp?.status()).toBeLessThan(400);
  // Empty cart shows a "Your cart is empty" copy; we just want non-500.
  await expect(page.locator('body')).not.toBeEmpty();
});

test('checkout entry does not 500 on empty cart', async ({ page }) => {
  // FT-LOGIC-2 fix (commit 1dd0ee0) ensures checkout entry re-validates
  // any session coupon before rendering. An empty cart used to NPE in
  // some branches; this pins that the route is safe.
  const resp = await page.goto('checkout');
  // Allow 200 (renders empty checkout) or 302 (bounces back to cart).
  // 500 means a regression.
  expect(resp?.status(), `checkout returned ${resp?.status()}`).toBeLessThan(500);
});

test('apply-coupon with invalid code is rejected (not 500)', async ({ page }) => {
  // FT-VAL-5 (commit 1b17fd1) tightened coupon input typing. A garbage
  // coupon code must return a 4xx, not a 500.
  await page.goto('cart');
  const token = await page.locator('meta[name="csrf-token"]').getAttribute('content') || '';
  expect(token.length).toBeGreaterThan(20);

  const resp = await page.request.post('apply-coupon', {
    headers: { 'x-csrf-token': token, accept: 'application/json' },
    form: {
      _token: token,
      coupon_code: 'NONEXISTENT_COUPON_CODE_E2E',
    },
    maxRedirects: 0,
    failOnStatusCode: false,
  });
  // Controller may return 200 with {success: false} OR 302 redirect to
  // cart with a flash error OR 422. 500 is the failure mode.
  expect(resp.status(), `apply-coupon returned ${resp.status()}`).toBeLessThan(500);
});

test('apply-coupon without CSRF is rejected', async ({ page }) => {
  const resp = await page.request.post('apply-coupon', {
    headers: { accept: 'application/json' },
    form: { coupon_code: 'X' },
    maxRedirects: 0,
    failOnStatusCode: false,
  });
  expect([419, 302]).toContain(resp.status());
});

test('remove-coupon is reachable', async ({ page }) => {
  // Idempotent — removing a coupon that's not applied should just be a no-op.
  const resp = await page.goto('remove-coupon');
  expect(resp?.status(), `remove-coupon returned ${resp?.status()}`).toBeLessThan(500);
});

test('add-to-cart for non-existent course returns 4xx (not 500)', async ({ page }) => {
  // FT-IDOR-18 + FT-VAL-2 (commit 2d0198d) tightened LearningController
  // cross-course IDOR. The cart adds should also reject a non-existent
  // course gracefully.
  await page.goto('cart');
  const token = await page.locator('meta[name="csrf-token"]').getAttribute('content') || '';

  const resp = await page.request.post('add-to-cart/999999999', {
    headers: { 'x-csrf-token': token, accept: 'application/json' },
    form: { _token: token },
    maxRedirects: 0,
    failOnStatusCode: false,
  });
  // 404 (model not found) or 422 or 302 (back to catalog with error).
  // 500 = unhandled exception path.
  expect(resp.status(), `add-to-cart bogus id returned ${resp.status()}`).toBeLessThan(500);
});
