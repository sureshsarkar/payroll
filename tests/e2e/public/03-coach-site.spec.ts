import { test, expect } from '@playwright/test';

/**
 * Coach-site (Path B white-label) public surface.
 *
 * Targets the path-based access: `coach/{coachSlug}/...`
 * (Domain-based access goes through ResolveCoachByDomain which needs
 * a Host header rewrite — out of scope for this baseline pass.)
 *
 * Coach slug `mbs` is a stable seeded landing page. If your DB doesn't
 * have it, replace with any row from `coach_landing_pages`.
 */

const COACH_SLUG = 'mbs';

test('coach-site landing page loads', async ({ page }) => {
  const resp = await page.goto(`coach/${COACH_SLUG}`);
  expect(resp?.status(), `coach/${COACH_SLUG} returned ${resp?.status()}`).toBeLessThan(400);
  await expect(page.locator('body')).not.toBeEmpty();
});

test('coach-site emits CSP header (P1-4 contract)', async ({ page }) => {
  const resp = await page.goto(`coach/${COACH_SLUG}`);
  const csp = resp?.headers()['content-security-policy-report-only']
        || resp?.headers()['content-security-policy'];
  expect(csp, 'CSP header MUST be present on coach-site responses').toBeTruthy();
  expect(csp).toContain("default-src 'self'");
  expect(csp).toContain('nonce-');
  // frame-ancestors clickjacking defense must be in place.
  expect(csp).toContain("frame-ancestors 'self'");
});

test('coach-site nonce is on inline scripts (verify nonce wiring)', async ({ page, request }) => {
  // IMPORTANT: per CSP3 spec, browsers HIDE the `nonce` attribute from
  // DOM queries after CSP processing — to prevent same-origin scripts
  // from reading and exfiltrating it. So `getAttribute('nonce')`
  // returns "" inside the page, even when the original HTML had a
  // valid nonce. Workaround: fetch the raw HTML via the request
  // fixture (no browser, no CSP-stripping) and verify the nonce in
  // the source bytes directly.
  const resp = await request.get(`coach/${COACH_SLUG}`);
  expect(resp.status()).toBeLessThan(400);
  const html = await resp.text();
  // Pull every nonce="…" value from <script> tags.
  const matches = Array.from(html.matchAll(/<script[^>]*\snonce="([^"]*)"/g));
  expect(matches.length, 'coach-site HTML must contain at least one <script nonce="…">').toBeGreaterThan(0);
  const valid = matches.filter(m => m[1].length === 22 && /^[A-Za-z0-9_-]+$/.test(m[1]));
  expect(valid.length, `expected ≥1 valid 22-char base64 nonce; found nonces: ${JSON.stringify(matches.map(m => m[1]))}`).toBeGreaterThan(0);
});

test('coach-site cart page loads (white-label cart drawer chrome)', async ({ page }) => {
  const resp = await page.goto(`coach/${COACH_SLUG}/cart`);
  // Empty cart on coach-site shows the same chrome as platform cart.
  // 200 (rendered) or 302 (bounce to login) are both fine; 500 is bug.
  expect(resp?.status(), `coach/${COACH_SLUG}/cart returned ${resp?.status()}`).toBeLessThan(500);
});

test('coach-site login page is reachable + form is wired', async ({ page }) => {
  const resp = await page.goto(`coach/${COACH_SLUG}/login`);
  expect(resp?.status()).toBeLessThan(400);
  // Login form should have email + password fields.
  const emailCount = await page.locator('input[name="email"]').count();
  const passCount = await page.locator('input[name="password"]').count();
  expect(emailCount + passCount, 'coach-site login must have email + password').toBeGreaterThanOrEqual(2);
});

test('coach-site register page is reachable', async ({ page }) => {
  const resp = await page.goto(`coach/${COACH_SLUG}/register`);
  expect(resp?.status()).toBeLessThan(400);
});

test('coach-site checkout entry does not 500', async ({ page }) => {
  // On empty cart the route may redirect back to cart — that's fine.
  // 500 = unhandled exception.
  const resp = await page.goto(`coach/${COACH_SLUG}/checkout`);
  expect(resp?.status(), `coach checkout returned ${resp?.status()}`).toBeLessThan(500);
});

test('non-existent coach slug returns 404 (no info leak)', async ({ page }) => {
  const resp = await page.goto('coach/this-coach-definitely-does-not-exist-zzzz');
  // Must be 404 — NOT a generic 500 (which would leak stack), NOT a
  // 200 with empty body (which would mislead callers).
  expect(resp?.status()).toBe(404);
});
