import { test, expect } from '@playwright/test';

/**
 * Authenticated student panel — index pages + profile form contract.
 */

const STUDENT_PAGES = [
  { path: 'student/dashboard',         label: 'Dashboard' },
  { path: 'student/setting',           label: 'Profile setting' },
  { path: 'student/enrolled-courses',  label: 'Enrolled courses' },
  { path: 'student/orders',            label: 'Orders' },
  { path: 'student/announcements',     label: 'Announcements' },
  { path: 'student/attendance',        label: 'Attendance' },
  { path: 'student/quiz-attempts',     label: 'Quiz attempts' },
  { path: 'student/live-classes',      label: 'Live classes' },
  { path: 'student/fees',              label: 'Fees' },
  { path: 'student/wishlist',          label: 'Wishlist' },
];

for (const { path, label } of STUDENT_PAGES) {
  test(`${label}: GET ${path} loads`, async ({ page }) => {
    const resp = await page.goto(path);
    expect(resp?.status(), `${path} returned ${resp?.status()}`).toBeLessThan(400);
  });
}

test('student profile form has editable fields', async ({ page }) => {
  await page.goto('student/setting');
  // Name + email are the two universal fields.
  await expect(page.locator('input[name="name"]')).toBeVisible();
  await expect(page.locator('input[name="email"]')).toBeVisible();
});

test('student profile rejects name over max (50 chars)', async ({ page }) => {
  await page.goto('student/setting');
  // StudentProfileUpdateRequest caps name at 50.
  const tooLong = 'X'.repeat(120);
  await page.fill('input[name="name"]', tooLong);
  // Scope to the profile form (page has multiple — profile, biography,
  // location, education, experience).
  const form = page.locator('form[action*="profile"]').first();
  await form.locator('button[type="submit"], button:has-text("Save"), button:has-text("Update")').first().click();
  await page.waitForLoadState('networkidle');
  await page.goto('student/setting');
  const saved = await page.locator('input[name="name"]').inputValue();
  expect(saved.length).toBeLessThanOrEqual(50);
});
