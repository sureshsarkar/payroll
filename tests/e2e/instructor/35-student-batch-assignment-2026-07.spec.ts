import { test, expect } from '@playwright/test';

/**
 * Student Batch Assignment / Reassignment (2026-07-15) — coach-panel E2E.
 *
 * The full assign/move + payment-free mutation is covered by PHPUnit
 * (StudentBatchAssignmentTest) + live smoke. This spec pins the deterministic
 * coach-facing surface on the student list: the select-all + per-row batch
 * controls render, the bulk "Assign to batch" bar appears on selection, and the
 * "Manage batch" modal opens + loads its context. Runs under `instructor`.
 *
 *   npx playwright test --project=instructor tests/e2e/instructor/35-student-batch-assignment-2026-07.spec.ts --no-deps
 */

test.describe('Student batch assignment — coach panel', () => {
  test('the student list exposes select-all + a bulk assign bar', async ({ page }) => {
    const resp = await page.goto('instructor/coach-students');
    if (!resp || resp.status() >= 400) {
      test.skip(true, 'coach-students not reachable for this account');
      return;
    }
    // select-all + the (hidden until selection) bulk bar with a batch dropdown.
    await expect(page.locator('#msSelectAll')).toHaveCount(1);
    await expect(page.locator('#msBulkBatch')).toHaveCount(1);

    const rows = page.locator('.ms-check');
    if ((await rows.count()) === 0) {
      test.skip(true, 'no students on the roster to exercise selection');
      return;
    }
    await expect(page.locator('#msBulkBar')).toBeHidden();
    await rows.first().check();
    await expect(page.locator('#msBulkBar')).toBeVisible();
  });

  test('the "Manage batch" modal opens and loads its context', async ({ page }) => {
    const resp = await page.goto('instructor/coach-students');
    if (!resp || resp.status() >= 400) {
      test.skip(true, 'not reachable');
      return;
    }
    const btn = page.locator('.ms-batch-btn').first();
    if ((await btn.count()) === 0) {
      test.skip(true, 'no students to open the batch modal for');
      return;
    }
    await expect(page.locator('#msBatchModal')).toBeHidden();
    await btn.click();
    await expect(page.locator('#msBatchModal')).toBeVisible();
    // The AJAX context resolves (the "Loading…" placeholder is replaced).
    await expect(page.locator('#msmBody')).not.toHaveText(/Loading/, { timeout: 10000 });
  });

  test('the page is responsive on a phone viewport (no horizontal body scroll)', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 });
    const resp = await page.goto('instructor/coach-students');
    if (!resp || resp.status() >= 400) {
      test.skip(true, 'not reachable');
      return;
    }
    const overflow = await page.evaluate(
      () => document.documentElement.scrollWidth - document.documentElement.clientWidth
    );
    expect(overflow).toBeLessThanOrEqual(4);
  });
});
