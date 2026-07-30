import { test, expect } from '@playwright/test';

/**
 * Authenticated instructor (coach) panel — index pages.
 */

const INSTRUCTOR_PAGES = [
  { path: 'instructor/dashboard',       label: 'Dashboard' },
  { path: 'instructor/setting',         label: 'Profile setting' },
  { path: 'instructor/courses',         label: 'Courses' },
  { path: 'instructor/coach-orders',    label: 'My sales (orders)' },
  { path: 'instructor/announcements',   label: 'Announcements' },
  { path: 'instructor/lesson-question', label: 'Lesson Q&A' },
  { path: 'instructor/analytics',       label: 'Analytics' },
  { path: 'instructor/wishlist',        label: 'Wishlist' },
  { path: 'instructor/fees',            label: 'Fees' },
  { path: 'instructor/brand-settings',  label: 'Brand settings' },
];

for (const { path, label } of INSTRUCTOR_PAGES) {
  test(`${label}: GET ${path} loads`, async ({ page }) => {
    const resp = await page.goto(path);
    // 200 = served, 302 = onboarding/setup redirect (acceptable),
    // 403 = role gate (would mean test account misconfigured).
    expect(resp?.status(), `${path} returned ${resp?.status()}`).toBeLessThan(400);
  });
}

test('instructor sees CSP header on dashboard', async ({ page }) => {
  const resp = await page.goto('instructor/dashboard');
  const csp = resp?.headers()['content-security-policy-report-only']
        || resp?.headers()['content-security-policy'];
  expect(csp, 'CSP header must be present on instructor dashboard').toBeTruthy();
});
