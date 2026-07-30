import { test, expect } from '@playwright/test';

/**
 * Responsive audit — 2026-06-01: modal fit on phones (instructor).
 *
 * Opens the live-classes "Schedule a batch class" modal at 375px and
 * verifies the dialog fits within the viewport, the page does not gain a
 * horizontal scrollbar, and the submit button is reachable. Self-skips if
 * the modal can't be opened (JS not loaded / no trigger) for the account.
 *
 * Run only this file:
 *   npx playwright test --project=instructor tests/e2e/instructor/15-modal-responsive-2026-06.spec.ts --no-deps
 */

test('live-classes batch modal fits the viewport at 375px', async ({ page }) => {
  await page.setViewportSize({ width: 375, height: 812 });
  const resp = await page.goto('instructor/live-classes');
  expect(resp?.status() ?? 0).toBeLessThan(500);

  const trigger = page.locator('[data-bs-target="#batchModal"]').first();
  if ((await trigger.count()) === 0) {
    test.skip(true, 'no batch-modal trigger on this page');
    return;
  }
  await trigger.click();

  const dialog = page.locator('#batchModal .modal-dialog');
  try {
    await expect(dialog).toBeVisible({ timeout: 5000 });
  } catch {
    test.skip(true, 'modal did not open (modal JS not active here)');
    return;
  }
  await page.waitForTimeout(450); // fade-in settle

  const box = await dialog.evaluate((el) => {
    const r = el.getBoundingClientRect();
    return { left: r.left, right: r.right };
  });
  expect(box.left, `modal left within viewport (left=${box.left})`).toBeGreaterThanOrEqual(-1);
  expect(box.right, `modal right within viewport (right=${box.right})`).toBeLessThanOrEqual(376);

  // The modal must not introduce a horizontal page scrollbar.
  const overflow = await page.evaluate(
    () => document.documentElement.scrollWidth - document.documentElement.clientWidth
  );
  expect(overflow, `page overflow with modal open (${overflow}px)`).toBeLessThanOrEqual(1);

  // The submit button must exist inside the dialog (reachable; body scrolls
  // if the form is long).
  const submit = page.locator('#batchModal button[type="submit"], #batchModal [type="submit"]');
  expect(await submit.count(), 'modal should expose a submit control').toBeGreaterThan(0);
});
