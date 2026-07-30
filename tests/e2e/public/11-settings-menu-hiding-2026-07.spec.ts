import { test, expect, Page } from '@playwright/test';

/**
 * Phase 5 — menu-hiding (spec Step 9/10): restricted items must not appear in
 * the Settings side-nav for a staff member who lacks the permission. A real
 * coach sees every item.
 *
 * Fixtures (tests/e2e/seed-fixtures.php):
 *   e2e-staff-settings@mbsguru.test  role "E2E Settings" → courses, settings-zoom
 *   e2e-instructor@mbsguru.test      real coach (sees everything)
 *
 * The staff CAN open Zoom Live (they hold settings-zoom), so the settings hub
 * renders — and there we assert Zoom Live shows but Trial Sessions / Tax
 * Settings / Brand (which they lack) are hidden.
 */

const PW = 'e2e!Test#2026';

async function login(page: Page, email: string) {
  await page.goto('login');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', PW);
  await Promise.all([
    page.waitForURL(/\/(instructor|student|home|dashboard)/, { timeout: 15_000 }),
    page.click('button[type="submit"]'),
  ]);
}

test.describe('Settings menu-hiding by permission', () => {
  test('staff sees only the settings items they hold', async ({ page }) => {
    await login(page, 'e2e-staff-settings@mbsguru.test');

    const resp = await page.goto('instructor/zoom-setting');
    expect(resp?.status(), 'staff holds settings-zoom → 200').toBeLessThan(400);

    const nav = page.locator('.settings-nav-list');
    await expect(nav).toBeVisible();
    // The one permission they hold is present…
    await expect(nav.getByText('Zoom Live', { exact: true })).toBeVisible();
    // …everything they lack is hidden from the menu.
    await expect(nav.getByText('Trial Sessions', { exact: true })).toHaveCount(0);
    await expect(nav.getByText('Tax Settings', { exact: true })).toHaveCount(0);
    await expect(nav.getByText('Brand', { exact: true })).toHaveCount(0);
    await expect(nav.getByText('Roles', { exact: true })).toHaveCount(0);
  });

  test('a real coach sees every settings item', async ({ page }) => {
    await login(page, 'e2e-instructor@mbsguru.test');
    await page.goto('instructor/zoom-setting');

    const nav = page.locator('.settings-nav-list');
    await expect(nav).toBeVisible();
    for (const label of ['Zoom Live', 'Trial Sessions', 'Tax Settings', 'Brand', 'Staff', 'Roles']) {
      await expect(nav.getByText(label, { exact: true }).first(), `coach should see ${label}`).toBeVisible();
    }
  });
});
