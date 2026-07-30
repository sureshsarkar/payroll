import { test, expect, Page } from '@playwright/test';

/**
 * UAT + automation — the access-denied experience for staff.
 *
 * When a staff member opens a module they lack permission for, the denial must
 * render IN-PLACE inside the coach dashboard (sidebar + chrome stay), with a
 * professional "Access Denied" card — NOT a jump to the public frontend error
 * page. When they DO hold the permission, the real module renders (no card).
 *
 * Fixtures (tests/e2e/seed-fixtures.php):
 *   e2e-staff@mbsguru.test       role "E2E Limited" → courses, courses-create, coach-students
 *   e2e-instructor@mbsguru.test  real coach (never denied)
 */

const PW = 'e2e!Test#2026';

async function login(page: Page, email: string) {
  await page.goto('login');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', PW);
  await Promise.all([
    page.waitForURL(/\/(instructor|student|home|dashboard)/, { timeout: 20_000 }),
    page.click('button[type="submit"]'),
  ]);
}

test.describe('Staff access-denied renders in-panel', () => {
  test('denied module shows the access-denied card INSIDE the dashboard', async ({ page }) => {
    await login(page, 'e2e-staff@mbsguru.test');
    await page.goto('instructor/coupons', { waitUntil: 'domcontentloaded' });

    // Professional access-denied card is shown…
    await expect(page.locator('.ad-card')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Access Denied' })).toBeVisible();
    // …WITH the coach dashboard shell still present (the "same page" feel)…
    await expect(page.locator('.instructor-sidebar')).toBeVisible();
    // …and NOT the old public frontend error page.
    await expect(page.locator('.error-area')).toHaveCount(0);
  });

  test('the card offers a working Back-to-Dashboard action', async ({ page }) => {
    await login(page, 'e2e-staff@mbsguru.test');
    await page.goto('instructor/coach-orders', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('.ad-card')).toBeVisible();
    await page.getByRole('link', { name: /Back to Dashboard/i }).click();
    await expect(page).toHaveURL(/instructor\/dashboard/);
  });

  test('a permitted module renders the real page (no access-denied card)', async ({ page }) => {
    await login(page, 'e2e-staff@mbsguru.test');
    const resp = await page.goto('instructor/courses', { waitUntil: 'domcontentloaded' });
    expect(resp?.status(), 'staff holds courses → 200').toBeLessThan(400);
    await expect(page.locator('.ad-card')).toHaveCount(0);
  });

  test('a real coach is never shown the access-denied card', async ({ page }) => {
    await login(page, 'e2e-instructor@mbsguru.test');
    for (const path of ['instructor/coupons', 'instructor/coach-orders', 'instructor/staff-role']) {
      await page.goto(path, { waitUntil: 'domcontentloaded' });
      await expect(page.locator('.ad-card'), `coach must see ${path}, not a denial`).toHaveCount(0);
    }
  });
});
