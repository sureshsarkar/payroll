import { test, expect, Page } from '@playwright/test';

/**
 * Phase 4 — backend permission enforcement at the URL layer.
 *
 * A staff member with a limited role (has `courses`, NOT `trial-sessions` or any
 * `settings-*`) must be BLOCKED (403) when opening a restricted page by typing
 * its URL — even though the page isn't in their menu. A real coach bypasses the
 * gate entirely. Uses form login (no stored auth) so we can act as either user.
 */

const PW = 'e2e!Test#2026';

async function login(page: Page, email: string) {
  await page.goto('login');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', PW);
  // Wait for the post-login redirect so the auth cookie is set before we navigate.
  await Promise.all([
    page.waitForURL(/\/(instructor|student|home|dashboard)/, { timeout: 15_000 }),
    page.click('button[type="submit"]'),
  ]);
}

test.describe('Staff permission enforcement (URL level)', () => {
  test('staff WITHOUT the permission is blocked (403) on restricted routes', async ({ page }) => {
    await login(page, 'e2e-staff@mbsguru.test');

    for (const path of ['instructor/trial-sessions', 'instructor/zoom-setting', 'instructor/instant-meetings']) {
      const resp = await page.goto(path);
      expect(resp?.status(), `${path} should be forbidden for limited staff`).toBe(403);
    }
  });

  test('staff CAN open a module they DO have permission for', async ({ page }) => {
    await login(page, 'e2e-staff@mbsguru.test');
    const resp = await page.goto('instructor/courses');
    // 200 served, or 302 onboarding — anything but 403.
    expect(resp?.status(), 'courses should be allowed for this staff').not.toBe(403);
    expect(resp?.status()).toBeLessThan(400);
  });

  test('a real coach bypasses the permission gate', async ({ page }) => {
    await login(page, 'e2e-instructor@mbsguru.test');
    for (const path of ['instructor/trial-sessions', 'instructor/zoom-setting', 'instructor/instant-meetings']) {
      const resp = await page.goto(path);
      expect(resp?.status(), `${path} should be open for the coach`).toBeLessThan(400);
    }
  });
});
