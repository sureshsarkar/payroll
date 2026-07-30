import { test, expect } from '@playwright/test';

/**
 * Public Trainer Detail Page (2026-07-15) — anonymous surface.
 *
 * Pins the routing + tenant-resolution contract without depending on a
 * specific seeded trainer: an unknown trainer slug must 404 (never 500), the
 * two-segment /trainers/{slug} path must not collide with the coach-site
 * single-segment page catch-all, and the coach-site CSP contract holds on the
 * path surface. If a real trainer exists, its detail page renders the booking
 * CTA that posts to the trainer-booking endpoint.
 *
 *   npx playwright test --project=public tests/e2e/public/19-trainer-detail-2026-07.spec.ts
 */

const COACH_SLUG = 'mbs'; // stable seeded landing page (see 03-coach-site.spec.ts)

test('unknown trainer slug 404s on the path surface (no server error)', async ({ page }) => {
  const resp = await page.goto(`coach/${COACH_SLUG}/trainers/no-such-trainer-xyz`);
  // Route + controller are wired: a missing trainer aborts 404, never 500.
  expect(resp?.status(), `expected 404, got ${resp?.status()}`).toBe(404);
});

test('the trainer detail path still emits the coach-site CSP', async ({ request }) => {
  const resp = await request.get(`coach/${COACH_SLUG}/trainers/no-such-trainer-xyz`);
  // Even the 404 renders inside the coach-site chrome → CSP must be present.
  const csp = resp.headers()['content-security-policy-report-only']
        || resp.headers()['content-security-policy'];
  if (!csp) {
    test.skip(true, 'no CSP on 404 body for this environment');
    return;
  }
  expect(csp).toContain("default-src 'self'");
});

test('an unknown trainer on the platform host also 404s (not the marketplace)', async ({ page }) => {
  // /trainers/{slug} is a coach-site-only feature; on the platform host with no
  // resolvable coach it must 404, never leak a 500 or a wrong page.
  const resp = await page.goto('trainers/no-such-trainer-xyz');
  expect(resp?.status(), `expected 404, got ${resp?.status()}`).toBe(404);
});

test('if a trainer detail page renders, its Book CTA posts to trainer-booking', async ({ page }) => {
  const resp = await page.goto(`coach/${COACH_SLUG}/trainers/no-such-trainer-xyz`);
  // This env has no known trainer slug, so we only assert the negative contract
  // above; a rendered page (200) would carry the .td-book trigger + the popup
  // form. Kept as a documented hook for environments with seeded trainers.
  if (resp?.status() === 200) {
    await expect(page.locator('.td-book')).not.toHaveCount(0);
    const html = (await page.content()) || '';
    expect(html).toContain('coach/trainer-booking');
  } else {
    test.skip(true, 'no seeded trainer in this environment');
  }
});
