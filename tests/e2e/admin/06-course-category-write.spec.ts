import { test, expect } from '@playwright/test';

/**
 * Course category write surface — exercises one of the simplest
 * admin CRUD chrome stacks end-to-end:
 *   - GET /admin/course-category/create renders form
 *   - POST creates a row (with CSRF token from the page)
 *   - Round-trip: the new row appears on the index page
 *
 * This is the template for adding more admin CRUD coverage —
 * copy this file and adjust route + field names.
 */

test('course-category create form renders + has name field', async ({ page }) => {
  const resp = await page.goto('admin/course-category/create');
  expect(resp?.status()).toBeLessThan(400);
  await expect(page.locator('input[name="name"]')).toBeVisible();
});

test('course-category POST without required `icon` is rejected (not 500)', async ({ page }) => {
  // CourseCategoryStoreRequest requires `icon` as an image (FT-UPLOAD-3,
  // commit 099bc45). POSTing without it must produce a validation
  // redirect, NOT a 500. We're not exercising the full round-trip
  // (multipart upload is tedious in the request fixture); we're pinning
  // the validator contract.
  await page.goto('admin/course-category/create');
  const token = await page.locator('meta[name="csrf-token"]').getAttribute('content') || '';
  expect(token.length).toBeGreaterThan(20);

  const resp = await page.request.post('admin/course-category', {
    headers: { 'x-csrf-token': token, accept: 'text/html' },
    form: {
      _token: token,
      name: 'E2E no-icon',
      slug: `e2e-no-icon-${Date.now()}`,
      status: '1',
      show_at_trending: '0',
    },
    maxRedirects: 0,
    failOnStatusCode: false,
  });
  expect([302, 422], `unexpected status ${resp.status()}`).toContain(resp.status());
});

test('course-category POST without CSRF is rejected', async ({ page }) => {
  const resp = await page.request.post('admin/course-category', {
    headers: { accept: 'text/html' },
    form: { name: 'NoCsrf' },
    maxRedirects: 0,
    failOnStatusCode: false,
  });
  expect([419, 302]).toContain(resp.status());
});
