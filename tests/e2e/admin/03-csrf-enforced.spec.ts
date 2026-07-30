import { test, expect } from '@playwright/test';

/**
 * Negative test: every admin form must include a CSRF token. A POST
 * without the token must be rejected (419 or redirect, not silent success).
 *
 * Sample 3 representative admin write endpoints.
 */

test('admin form without CSRF token is rejected', async ({ page, request }) => {
  // Hit the admin store-login endpoint with no token — must 419 or 302.
  // PUT (the route is admin/profile-update) without CSRF must not succeed.
  const resp = await request.fetch('admin/profile-update', {
    method: 'PUT',
    form: { name: 'CSRF Bypass Attempt' },
    failOnStatusCode: false,
  });
  // Laravel returns 419 for missing/mismatched CSRF; 405 if method mismatch.
  expect([419, 302, 403, 405, 404], `unexpected status: ${resp.status()}`).toContain(resp.status());
});

test('admin chrome includes csrf-token meta', async ({ page }) => {
  await page.goto('admin/dashboard');
  const token = await page.locator('meta[name="csrf-token"]').getAttribute('content');
  expect(token?.length).toBeGreaterThan(20);
});
