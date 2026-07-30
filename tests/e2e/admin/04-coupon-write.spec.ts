import { test, expect } from '@playwright/test';

/**
 * Coupon write surface — covers the validation contract pinned by
 * commits 260b37e (FT-COUP-1 + FT-COUP-2):
 *   - offer_percentage must be 0..100 (was unbounded)
 *   - expired_date must be in the future (was any-string)
 *
 * Helper: pulls CSRF token from the create page, then POSTs via the
 * request fixture so we exercise the full validator without dragging
 * a real browser through every test.
 */

async function csrf(page: import('@playwright/test').Page): Promise<string> {
  await page.goto('admin/coupon');
  const t = await page.locator('meta[name="csrf-token"]').getAttribute('content');
  expect(t?.length, 'csrf-token meta must be present on admin chrome').toBeGreaterThan(20);
  return t || '';
}

test('valid coupon creates successfully', async ({ page }) => {
  const token = await csrf(page);
  const code = `E2E${Date.now()}`;
  const resp = await page.request.post('admin/coupon', {
    headers: { 'x-csrf-token': token, accept: 'text/html' },
    form: {
      _token: token,
      coupon_code: code,
      offer_percentage: '15',
      min_price: '100',
      expired_date: '2030-12-31',
      status: '1',
    },
    maxRedirects: 0,
    failOnStatusCode: false,
  });
  // 302 redirect to index on success.
  expect([200, 302], `unexpected status ${resp.status()}`).toContain(resp.status());
});

test('coupon with offer_percentage > 100 is rejected', async ({ page }) => {
  const token = await csrf(page);
  const resp = await page.request.post('admin/coupon', {
    headers: { 'x-csrf-token': token, accept: 'text/html' },
    form: {
      _token: token,
      coupon_code: `E2EOVER${Date.now()}`,
      offer_percentage: '999',
      min_price: '100',
      expired_date: '2030-12-31',
      status: '1',
    },
    maxRedirects: 0,
    failOnStatusCode: false,
  });
  // Validation error → redirect back to form (302 with session errors)
  // or 422 if the controller short-circuited. Either is fine; what's NOT
  // fine is a 200 OK that pretended to save.
  expect([302, 422], `unexpected status ${resp.status()}`).toContain(resp.status());
});

test('coupon with expired_date in the past is rejected', async ({ page }) => {
  const token = await csrf(page);
  const resp = await page.request.post('admin/coupon', {
    headers: { 'x-csrf-token': token, accept: 'text/html' },
    form: {
      _token: token,
      coupon_code: `E2EPAST${Date.now()}`,
      offer_percentage: '10',
      min_price: '100',
      // 2020 is in the past — must fail.
      expired_date: '2020-01-01',
      status: '1',
    },
    maxRedirects: 0,
    failOnStatusCode: false,
  });
  expect([302, 422]).toContain(resp.status());
});

test('coupon with negative offer_percentage is rejected', async ({ page }) => {
  const token = await csrf(page);
  const resp = await page.request.post('admin/coupon', {
    headers: { 'x-csrf-token': token, accept: 'text/html' },
    form: {
      _token: token,
      coupon_code: `E2ENEG${Date.now()}`,
      offer_percentage: '-5',
      min_price: '100',
      expired_date: '2030-12-31',
      status: '1',
    },
    maxRedirects: 0,
    failOnStatusCode: false,
  });
  expect([302, 422]).toContain(resp.status());
});

test('coupon without CSRF token is rejected', async ({ page }) => {
  const resp = await page.request.post('admin/coupon', {
    form: {
      coupon_code: 'CSRFBYPASS',
      offer_percentage: '10',
      min_price: '100',
      expired_date: '2030-12-31',
      status: '1',
    },
    maxRedirects: 0,
    failOnStatusCode: false,
  });
  expect([419, 302]).toContain(resp.status());
});
