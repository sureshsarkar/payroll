import { test, expect } from '@playwright/test';

/**
 * Anonymous public surface — what a brand-new visitor sees.
 * No auth state.
 */

test('home page loads', async ({ page }) => {
  const resp = await page.goto('');
  expect(resp?.status()).toBeLessThan(400);
  // Body must render something — guard against the silent 200-blank
  // case we've shipped before.
  await expect(page.locator('body')).not.toBeEmpty();
});

test('home page sets CSP-Report-Only header', async ({ page }) => {
  const resp = await page.goto('');
  // P1-4: the CSP middleware should stamp Content-Security-Policy-Report-Only
  // on every HTML response in the web stack. If this header disappears,
  // the per-tenant XSS defense has regressed.
  const csp = resp?.headers()['content-security-policy-report-only'];
  expect(csp, 'CSP-Report-Only must be present on HTML responses').toBeTruthy();
  expect(csp).toContain("default-src 'self'");
  expect(csp).toContain('nonce-');
});

test('home page has no console errors', async ({ page }) => {
  const errors: string[] = [];
  page.on('pageerror', e => errors.push(`pageerror: ${e.message}`));
  page.on('console', m => { if (m.type() === 'error') errors.push(`console: ${m.text()}`); });
  await page.goto('');
  await page.waitForLoadState('networkidle');
  // Ignore noisy 3rd-party tracker errors that may be expected.
  const real = errors.filter(e => !/facebook|google-analytics|hotjar/i.test(e));
  expect(real, `unexpected console errors:\n${real.join('\n')}`).toHaveLength(0);
});

test('login page renders + form is wired', async ({ page }) => {
  await page.goto('login');
  await expect(page.locator('input[name="email"]')).toBeVisible();
  await expect(page.locator('input[name="password"]')).toBeVisible();
  // The login page contains BOTH the login form and a hidden logout form
  // (the logout form ships in the chrome). Scope the CSRF assertion to
  // the actual login form, not any token on the page.
  const loginForm = page.locator('form[action*="user-login"]').first();
  const token = await loginForm.locator('input[name="_token"]').first().getAttribute('value');
  expect(token?.length).toBeGreaterThan(20);
});

test('login rejects wrong password', async ({ page }) => {
  await page.goto('login');
  await page.fill('input[name="email"]', 'e2e-student@mbsguru.test');
  await page.fill('input[name="password"]', 'definitely-wrong-not-the-password');
  await page.click('button[type="submit"]');
  // Either redirect back to /login or stay there. The page MUST NOT
  // reach a student dashboard.
  await page.waitForLoadState('networkidle');
  expect(page.url()).not.toMatch(/\/student\/dashboard/);
});

test('admin login page is reachable + form is wired', async ({ page }) => {
  await page.goto('admin/login');
  await expect(page.locator('input[name="email"]')).toBeVisible();
  await expect(page.locator('input[name="password"]')).toBeVisible();
});

test('404 page is rendered for unknown route', async ({ page }) => {
  const resp = await page.goto('this-route-definitely-does-not-exist-12345');
  expect(resp?.status()).toBe(404);
});
