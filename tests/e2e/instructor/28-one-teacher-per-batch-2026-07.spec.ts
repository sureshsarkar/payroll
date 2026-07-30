import { test, expect } from '@playwright/test';

/**
 * "Restrict Batch Assignment to One Teacher at a Time" (2026-07-08).
 * The seeded batch is owned by Teacher A. Re-assigning it to Teacher B must:
 *   - show the batch's current owner on its card,
 *   - pop a transfer-confirmation dialog on submit,
 *   - Cancel → stay on the form (no transfer),
 *   - Confirm → submit through to the list.
 * Fixture:  php tests/e2e/seed-fixtures.php && php tests/e2e/seed-tba.php
 * Runs under the `instructor` project (e2e-instructor session).
 */

test.describe('one teacher per batch — transfer confirmation', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('instructor/teacher-batches/create');
    await page.selectOption('#course_id', { label: 'E2E TBA Course' });
    await page.waitForSelector('.tba-batch-card', { timeout: 15_000 });
    // The card shows who currently owns the batch.
    await expect(page.locator('.tba-batch-card__owner')).toContainText('E2E Teacher A');

    // Choose Teacher B (option text is "Name — email").
    const val = await page.locator('#teacher_id option', { hasText: 'E2E Teacher B' }).first().getAttribute('value');
    await page.selectOption('#teacher_id', val!);
    await page.locator('input[name="batch_ids[]"]').first().check();
  });

  test('Cancel keeps the current assignment', async ({ page }) => {
    let msg = '';
    page.once('dialog', (d) => { msg = d.message(); d.dismiss(); });
    await page.locator('#submitBtn').click();
    await page.waitForTimeout(600);

    expect(msg, 'dialog names the current teacher').toContain('E2E Teacher A');
    expect(msg).toContain('change the batch owner');
    expect(page.url(), 'stayed on the form after Cancel').toContain('teacher-batches/create');
  });

  test('Confirm transfers ownership', async ({ page }) => {
    page.once('dialog', (d) => d.accept());
    await Promise.all([
      page.waitForURL(/teacher-batches(\/?$|\?)/, { timeout: 15_000 }),
      page.locator('#submitBtn').click(),
    ]);
    // Landed on the assignments list (not the create form) → transfer went through.
    expect(page.url()).not.toContain('/create');
  });
});
