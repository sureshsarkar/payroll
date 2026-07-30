import { test, expect } from '@playwright/test';

/**
 * Task 1 — the coach Trial Session enquiries page exposes the new "Student"
 * column that badges whether an account was created / already existed.
 */

test.describe('Trial enquiries — student account column', () => {
  test('enquiries page loads with the Student column', async ({ page }) => {
    const resp = await page.goto('instructor/trial-sessions/enquiries');
    expect(resp?.status(), `page returned ${resp?.status()}`).toBeLessThan(400);

    // Column header present (renders even with zero rows).
    await expect(page.getByRole('columnheader', { name: 'Student' })).toBeVisible();
  });
});
