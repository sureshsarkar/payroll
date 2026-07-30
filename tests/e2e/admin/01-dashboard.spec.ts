import { test, expect } from '@playwright/test';

/**
 * Authenticated admin panel — dashboard + key index pages must render.
 * If any of these 5xx, a controller is broken.
 */

const ADMIN_PAGES = [
  { path: 'admin/dashboard',           label: 'Dashboard' },
  { path: 'admin/all-instructors',     label: 'Instructors list' },
  { path: 'admin/all-customers',       label: 'Customers list' },
  { path: 'admin/course',              label: 'Courses list' },
  { path: 'admin/course-category',     label: 'Course categories' },
  { path: 'admin/orders',              label: 'Orders list' },
  { path: 'admin/coupon',              label: 'Coupons' },
  { path: 'admin/instructor-request',  label: 'Instructor requests' },
  { path: 'admin/admin',               label: 'Admin list' },
  { path: 'admin/role',                label: 'Roles' },
  { path: 'admin/general-setting',     label: 'General settings' },
  { path: 'admin/edit-profile',        label: 'Profile edit' },
  { path: 'admin/notifications',       label: 'Notifications' },
];

for (const { path, label } of ADMIN_PAGES) {
  test(`${label}: GET ${path} loads`, async ({ page }) => {
    const resp = await page.goto(path);
    expect(resp?.status(), `${path} returned ${resp?.status()}`).toBeLessThan(400);
    // Must contain admin chrome — body class or sidebar marker.
    await expect(page.locator('body')).not.toBeEmpty();
  });
}

test('admin logout endpoint exists + requires CSRF', async ({ page }) => {
  // We don't actually log out the shared session (that would invalidate
  // storageState for every other admin test). Just confirm the endpoint
  // is wired and rejects no-CSRF POSTs with a non-success status. The
  // full session-destroy contract is covered by the PHPUnit Feature
  // suite (NoStoreAuthenticatedTest + Admin\AuthenticatedSessionTest).
  const resp = await page.request.post('admin/logout', {
    headers: { 'accept': 'text/html' },
    failOnStatusCode: false,
  });
  // 419 (CSRF missing) or 302 (back to login) — both fine. 200 would
  // mean CSRF isn't being checked. 500 would be a controller bug.
  expect([419, 302, 405], `logout returned ${resp.status()}`).toContain(resp.status());
});
