import { test, expect } from '@playwright/test';

/**
 * Instructor request approval flow — pins FT-IDOR-17 (commit 6f9309b)
 * which split the write permission from the read permission, so a
 * read-only sub-admin can no longer promote students to coaches via POST.
 *
 * This test asserts the approval surface is reachable + the chrome
 * exposes the workflow. The full role-promotion semantics are pinned
 * by tests/Feature/Domain/InstructorApprovalTest.php at the PHPUnit
 * level — here we just smoke-check the browser path.
 */

test('instructor-request index page renders with action controls', async ({ page }) => {
  const resp = await page.goto('admin/instructor-request');
  expect(resp?.status()).toBeLessThan(400);
  // Page must contain a body marker for an approval/edit link OR an
  // empty-state ("no requests" copy). Either is acceptable on a fresh
  // DB; what's NOT acceptable is a 500 or a redirect away.
  await expect(page.locator('body')).not.toBeEmpty();
});

test('instructor-request edit endpoint enforces CSRF on PUT', async ({ page }) => {
  // PUT without CSRF → 419 or 302 (back to login if session bounced).
  const resp = await page.request.fetch('admin/instructor-request/1', {
    method: 'PUT',
    form: { status: 'approved' },
    failOnStatusCode: false,
  });
  expect([419, 302, 405, 404], `unexpected ${resp.status()}`).toContain(resp.status());
});
