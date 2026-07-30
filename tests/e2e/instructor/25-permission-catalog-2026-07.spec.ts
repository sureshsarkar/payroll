import { test, expect } from '@playwright/test';

/**
 * Phase 2 — the expanded permission catalog must surface in the Create-Role
 * permission picker, so a coach can now delegate the modules that were
 * previously un-assignable (Coupons, Orders, Live Classes, Staff, Roles) and
 * every Settings sub-page.
 */

test.describe('Permission catalog surfaces in the role picker', () => {
  test('newly-added modules appear as assignable permissions', async ({ page }) => {
    await page.goto('instructor/staff-role/create');
    const html = await page.content();

    // Previously-missing, now assignable (the picker renders resource slugs as <code>).
    for (const slug of ['coach-coupons', 'coach-orders', 'live-classes', 'coach-staff']) {
      expect(html, `picker should expose "${slug}"`).toContain(slug);
    }
    // At least one Settings sub-page permission is now assignable.
    expect(html, 'picker should expose a settings-* permission').toMatch(/settings-(payment-gateway|tax|security|email)/);
  });

  test('picker exposes many permission checkboxes', async ({ page }) => {
    await page.goto('instructor/staff-role/create');
    const boxes = await page.locator('input[name="roles_permissions_id[]"]').count();
    // Catalog is ~120+ permissions — the picker must render a large set now.
    expect(boxes).toBeGreaterThan(60);
  });
});
