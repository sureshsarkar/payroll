import { test, expect } from '@playwright/test';

/**
 * 2026-06-01 — re-pay must restore the order's Paid state via the UI.
 *
 * Reported: Paid→Pending→Paid left the course removed for the student
 * (markPaid's firstOrCreate didn't update the existing enrollment). The
 * server-side access + wallet correctness is pinned by the rolled-back
 * feature test (CoachOrderTogglePaymentTest). This E2E drives the real
 * order-detail form through the full toggle and asserts the status reflects
 * each change — proving the re-pay sticks through the actual UI path.
 *
 * It ends on Pending so the coach wallet nets to ZERO across the run (each
 * Paid credits, each Pending reverses) — no financial accumulation in CI.
 *
 * Self-skips if the account has no assignable student/course.
 *
 * Run only this file:
 *   npx playwright test --project=instructor tests/e2e/instructor/18-order-status-toggle-2026-06.spec.ts --no-deps
 */

test('order toggles Paid → Pending → Paid via the UI and the status sticks', async ({ page }) => {
  // 1. Create a fresh pending order.
  await page.goto('instructor/coach-orders/create');
  const studentSel = page.locator('select[name="user_id"]');
  const courseSel = page.locator('select[name="course_id"]');
  if ((await studentSel.locator('option').count()) < 2 || (await courseSel.locator('option').count()) < 2) {
    test.skip(true, 'account has no assignable student/course');
    return;
  }
  await studentSel.selectOption({ index: 1 });
  await courseSel.selectOption({ index: 1 });
  await Promise.all([page.waitForURL('**/coach-orders**'), page.locator('.btn-submit-sf').click()]);

  // 2. Capture the new order's detail URL (first /coach-orders/{numericId}).
  const showHref = await page.locator('a[href*="/coach-orders/"]').evaluateAll((els) => {
    return (
      els
        .map((e) => (e as HTMLAnchorElement).getAttribute('href'))
        .find((h) => h && /\/coach-orders\/\d+$/.test(h)) || null
    );
  });
  expect(showHref, 'should find the new order detail link').toBeTruthy();

  const setStatus = async (pay: string, ord: string) => {
    await page.goto(showHref!);
    await page.locator('select[name="payment_status"]').selectOption(pay);
    await page.locator('select[name="order_status"]').selectOption(ord);
    await Promise.all([
      page.waitForURL('**/coach-orders**'),
      page.getByRole('button', { name: /Update Status/i }).click(),
    ]);
  };
  const currentPayment = async () => {
    await page.goto(showHref!);
    return page.locator('select[name="payment_status"]').inputValue();
  };

  // 3. Paid → Pending → Paid (the reported re-pay) → Pending (cleanup).
  await setStatus('paid', 'completed');
  expect(await currentPayment(), 'after marking Paid').toBe('paid');

  await setStatus('pending', 'pending');
  expect(await currentPayment(), 'after marking Pending').toBe('pending');

  await setStatus('paid', 'completed');
  expect(await currentPayment(), 're-Paid must stick — the reported bug').toBe('paid');

  // Cleanup: leave the order Pending so the wallet nets to zero this run.
  await setStatus('pending', 'pending');
  expect(await currentPayment(), 'final cleanup state').toBe('pending');
});
