import { test, expect } from '@playwright/test';

/**
 * Phase 1 (Advanced Role & Permission) — regression guard.
 *
 * The backbone adds a CoachPermissionService + `permission` middleware alias +
 * staff_permission_overrides table WITHOUT changing behaviour. This spec proves
 * the existing coach-panel Staff / Roles / Permission-catalog pages still load
 * for a coach and the permission-picker matrix still renders — i.e. nothing in
 * the current RBAC UI broke.
 */

test.describe('Role & Permission — coach panel still works', () => {
  const PAGES = [
    { path: 'instructor/coach-staff',        label: 'Staff list' },
    { path: 'instructor/staff-role',         label: 'Roles list' },
    { path: 'instructor/staff-role/create',  label: 'Create role' },
    { path: 'instructor/staff-permission',   label: 'Permission catalog' },
  ];

  for (const { path, label } of PAGES) {
    test(`${label}: GET ${path} loads`, async ({ page }) => {
      const resp = await page.goto(path);
      expect(resp?.status(), `${path} returned ${resp?.status()}`).toBeLessThan(400);
    });
  }

  test('permission-picker matrix renders on the Create Role page', async ({ page }) => {
    await page.goto('instructor/staff-role/create');
    // The resource×action matrix (or its permission checkboxes) must be present.
    const matrix = page.locator('.pp-matrix, input[name="roles_permissions_id[]"]').first();
    await expect(matrix).toHaveCount(1);
  });
});
