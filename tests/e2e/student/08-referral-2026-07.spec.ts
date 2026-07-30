import { test, expect } from '@playwright/test';

/**
 * "Coach Order amount Issue" doc (2026-07-07), referral items:
 *   Issue 2 — the referral page must show ONLY the student-panel layout, not the
 *             public / coach custom website header.
 *   Issue 3 — the share link must point at /register?ref=CODE (not /?ref=CODE),
 *             so it lands the visitor straight on registration (code preserved).
 * Runs under the `student` project (storageState = e2e-student session).
 */

test('referral page hides the public website header and links to /register', async ({ page }) => {
  const resp = await page.goto('referral', { waitUntil: 'domcontentloaded' });
  expect(resp?.status(), 'referral page should load').toBe(200);

  const body = await page.locator('body').innerHTML();

  // Issue 3 — share link goes to registration, preserving the ref code.
  expect(body, 'share link must use /register?ref=').toContain('register?ref=');
  expect(/\/\?ref=/.test(body), 'the old /?ref= link must be gone').toBeFalsy();

  // Issue 2 — no public / coach custom website header markers on this page.
  for (const marker of ['coach-public-header', 'tgmenu', 'header__area', 'main-header']) {
    expect(body, `public header marker "${marker}" must be absent`).not.toContain(marker);
  }

  // The student panel layout IS present.
  await expect(page.locator('.dashboard__aread')).toBeVisible();
});
