import { test, expect } from '@playwright/test';

/**
 * Phase 3 — staff permission override UI. Selecting a role in the Add-Staff form
 * loads the role's permission matrix with the new "Reset to role" control and
 * override overlay (grant/revoke dots), proving the override layer is wired.
 */

test.describe('Staff override UI', () => {
  test('Add-Staff and Edit pages load', async ({ page }) => {
    for (const path of ['instructor/coach-staff', 'instructor/coach-staff/create']) {
      const resp = await page.goto(path);
      expect(resp?.status(), `${path} returned ${resp?.status()}`).toBeLessThan(400);
    }
  });

  test('selecting a role loads the picker with the Reset-to-role control', async ({ page }) => {
    await page.goto('instructor/coach-staff/create');

    // The role select lives in step 3 of the wizard — walk there first.
    await page.fill('#staff-name', 'QA Override Tester');
    await page.fill('#staff-email', `qa-ovr-${Date.now()}@example.com`);
    await page.click('#btn-next');                     // → Account Access
    await page.fill('#staff-password', 'Test1234');
    await page.click('#btn-next');                     // → Role & Permissions

    // Pick the seeded role → the picker is fetched + injected via AJAX.
    const role = page.locator('select#role');
    await expect(role).toBeVisible();
    await role.selectOption({ label: 'E2E Manager' });

    // The permission checkboxes appear.
    const picker = page.locator('#staff-picker-host input[name="permissions[]"]').first();
    await expect(picker).toBeVisible({ timeout: 10_000 });

    // The Phase-3 override controls are present.
    await expect(page.locator('#staff-picker-host .pp-reset-role')).toBeVisible();
    // Role defaults arrived → the container carries the override dataset.
    const hasDefaults = await page.locator('#staff-picker-host [data-role-defaults]').count();
    expect(hasDefaults).toBeGreaterThan(0);
  });
});
