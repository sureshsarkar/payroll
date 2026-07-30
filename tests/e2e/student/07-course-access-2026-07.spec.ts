import { test, expect } from '@playwright/test';

/**
 * QA audit (2026-07-07) — student post-purchase surfaces must render (no 500 /
 * blank page). PHPUnit covers the access-control LOGIC; this smoke-tests that the
 * pages actually render for a logged-in student. Runs under the `student` project
 * (storageState is the e2e-student session).
 */

const SURFACES: Array<{ path: string; label: string }> = [
  { path: 'student/dashboard', label: 'Dashboard' },
  { path: 'student/enrolled-courses', label: 'My Courses' },
  { path: 'student/orders', label: 'Orders' },
  { path: 'student/live-classes', label: 'Live Classes' },
];

for (const s of SURFACES) {
  test(`student ${s.label} renders without error`, async ({ page }) => {
    const resp = await page.goto(s.path, { waitUntil: 'domcontentloaded' });

    expect(resp?.status(), `${s.path} should return 200`).toBe(200);
    // Must have stayed inside the student area (not bounced to login).
    expect(page.url()).toContain('/student/');

    // No Laravel error / blank page.
    const body = (await page.locator('body').innerText()).toLowerCase();
    for (const bad of ['whoops', 'server error', 'sqlstate', 'undefined variable', 'call to a member']) {
      expect(body, `${s.path} must not show "${bad}"`).not.toContain(bad);
    }
    // Some real content rendered.
    expect(body.trim().length, `${s.path} must not be blank`).toBeGreaterThan(20);
  });
}
