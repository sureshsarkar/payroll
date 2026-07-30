import { test, expect } from '@playwright/test';

/**
 * Task 2 — a 1:1 instant meeting appears in the student's Live Classes list with
 * a "1:1 Meeting" badge and a working Join link. Requires an active meeting
 * seeded for the e2e student (see the deploy notes / test seeding).
 */

test.describe('Instant meeting in Student Live Classes', () => {
  test('the unified list shows the 1:1 meeting with a Join link', async ({ page }) => {
    const resp = await page.goto('student/live-classes');
    expect(resp?.status(), `page returned ${resp?.status()}`).toBeLessThan(400);

    // The type badge proves the instant meeting was merged into the list.
    await expect(page.getByText('1:1 Meeting').first()).toBeVisible();
    await expect(page.getByText('E2E Doubt Session').first()).toBeVisible();

    // A Join link points at the instant-meeting room (coach has joined → live).
    const joinLink = page.locator('a[href*="/instant-meeting/"][href*="/room"]').first();
    await expect(joinLink).toHaveCount(1);
  });

  test('the empty-state copy is not the old "orders" text', async ({ page }) => {
    await page.goto('student/live-classes');
    // The seeded meeting means the list is non-empty; the fixed empty copy must
    // never mention orders/purchased courses.
    await expect(page.locator('body')).not.toContainText('No orders yet');
    await expect(page.locator('body')).not.toContainText('Your purchased courses will appear here');
  });
});
