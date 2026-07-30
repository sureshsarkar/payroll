import { test, expect, Page } from '@playwright/test';

/**
 * QA audit (2026-07-07) — server-side RBAC for the two coach money/admin modules
 * closed this pass (Fee Management, Teacher-Batch Assignment). Browser-level proof
 * that a staff member WITHOUT the permission is blocked (in-panel 403 card) while
 * a real coach reaches the module. Complements the PHPUnit gate test.
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

// Limited staff (courses, courses-create, coach-students) — has NEITHER fees nor
// teacher-batches, so both must be forbidden.
for (const mod of ['instructor/fees', 'instructor/teacher-batches']) {
  test(`limited staff is blocked from ${mod}`, async ({ page }) => {
    await login(page, 'e2e-staff@mbsguru.test');
    const resp = await page.goto(mod, { waitUntil: 'domcontentloaded' });
    expect(resp?.status(), `${mod} must be 403 for staff without the permission`).toBe(403);
    // The denial renders INSIDE the coach panel, not the public error page.
    await expect(page.locator('.ad-card, .instructor-sidebar').first()).toBeVisible();
  });

  test(`real coach reaches ${mod}`, async ({ page }) => {
    await login(page, 'e2e-instructor@mbsguru.test');
    const resp = await page.goto(mod, { waitUntil: 'domcontentloaded' });
    expect(resp?.status(), `${mod} must load for the coach`).toBe(200);
    await expect(page.locator('.instructor-sidebar')).toBeVisible();
  });
}
