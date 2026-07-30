import { test, expect, Page } from '@playwright/test';

/**
 * "Role Permission Test" doc (2026-07-06) — live menu/route gating.
 *
 * Limited staff (role "E2E Limited" → courses, courses-create, coach-students)
 * must NOT see Analytics, My Plan, Instant Meeting or Live Classes in the sidebar
 * NAV (issues A + D), and must be blocked (403) from the analytics route. A real
 * coach sees everything. Assertions target `.sb-nav a` (the menu links) so the
 * stats-strip labels don't create false matches.
 */

const PW = 'e2e!Test#2026';

async function login(page: Page, email: string) {
  await page.goto('login');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', PW);
  await Promise.all([
    page.waitForURL(/\/(instructor|student|home|dashboard)/, { timeout: 25_000 }),
    page.click('button[type="submit"]'),
  ]);
}

const navLink = (page: Page, label: string) =>
  page.locator('.instructor-sidebar .sb-nav a').filter({ hasText: label });

test.describe('Role-permission doc: menu + route gating', () => {
  test('limited staff sidebar hides ungranted modules', async ({ page }) => {
    await login(page, 'e2e-staff@mbsguru.test');
    await expect(page.locator('.instructor-sidebar')).toBeVisible();

    // Held → visible
    await expect(navLink(page, 'Courses').first()).toBeVisible();
    await expect(navLink(page, 'My Students').first()).toBeVisible();

    // Not held → hidden (issues A + D)
    for (const label of ['Analytics', 'My Plan', 'Instant Meeting', 'Live Classes']) {
      await expect(navLink(page, label), `${label} must be hidden`).toHaveCount(0);
    }
  });

  test('limited staff is blocked (403) from the analytics route', async ({ page }) => {
    await login(page, 'e2e-staff@mbsguru.test');
    const resp = await page.goto('instructor/analytics', { waitUntil: 'domcontentloaded' });
    expect(resp?.status(), 'analytics must be forbidden for staff without the permission').toBe(403);
  });

  test('a real coach sees the gated modules', async ({ page }) => {
    await login(page, 'e2e-instructor@mbsguru.test');
    for (const label of ['Analytics', 'My Plan', 'Instant Meeting', 'Live Classes']) {
      await expect(navLink(page, label).first(), `coach should see ${label}`).toBeVisible();
    }
  });
});
