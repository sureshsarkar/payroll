import { test, expect } from '@playwright/test';

/**
 * 2026-06-01 — coach-generated orders must be created PENDING (not Paid).
 *
 * The coach reported that generating an order showed "Paid" immediately,
 * when it should stay "Pending" until the student pays. store() now creates
 * the order pending + grants no access; the coach later marks it Paid on the
 * order screen (→ markPaid grants access + commission).
 *
 * This drives the real UI flow: create an order via the form, then assert
 * the new My Sales row reads Pending, and the order detail exposes the
 * mark-as-paid control.
 *
 * Self-skips if the account has no assignable student/course.
 *
 * Run only this file:
 *   npx playwright test --project=instructor tests/e2e/instructor/17-coach-order-pending-2026-06.spec.ts --no-deps
 */

test('generating a coach order creates it as Pending (not Paid)', async ({ page }) => {
  await page.goto('instructor/coach-orders/create');

  const studentSel = page.locator('select[name="user_id"]');
  const courseSel = page.locator('select[name="course_id"]');
  const studentCount = await studentSel.locator('option').count();
  const courseCount = await courseSel.locator('option').count();
  if (studentCount < 2 || courseCount < 2) {
    test.skip(true, 'account has no assignable student/course to create an order');
    return;
  }

  // Pick the first real student + course (index 0 is the placeholder).
  await studentSel.selectOption({ index: 1 });
  await courseSel.selectOption({ index: 1 });

  await Promise.all([
    page.waitForURL('**/coach-orders**'),
    page.locator('.btn-submit-sf').click(),
  ]);

  // A validation bounce would keep us on /create — that must not happen for
  // the coach's own student + course.
  expect(page.url(), 'should redirect to My Sales after submit').toContain('coach-orders');
  expect(page.url(), 'should not stay on the create form').not.toContain('/create');

  // Newest order (orderBy id desc) is the first row. Both its Payment and
  // Order badges must read "Pending" — the whole point of the fix.
  const firstRow = page.locator('tbody tr').first();
  await expect(firstRow.locator('td[data-label="Payment"]')).toContainText(/pending/i);
  await expect(firstRow.locator('td[data-label="Order"]')).toContainText(/pending/i);

  // And it must NOT show Paid.
  await expect(firstRow.locator('td[data-label="Payment"]')).not.toContainText(/paid/i);
});

test('order detail exposes a Mark-as-Paid control (so coach can collect later)', async ({ page }) => {
  await page.goto('instructor/coach-orders');

  // Find the first "View order" link → /instructor/coach-orders/{numericId}
  const showHref = await page
    .locator('a[href*="/coach-orders/"]')
    .evaluateAll((els) => {
      const m = els
        .map((e) => (e as HTMLAnchorElement).getAttribute('href'))
        .find((h) => h && /\/coach-orders\/\d+$/.test(h));
      return m || null;
    });
  if (!showHref) {
    test.skip(true, 'no coach orders to open');
    return;
  }
  await page.goto(showHref);

  // The status form must let the coach set Payment = Paid (→ markPaid grants
  // access + commission). This is what makes the pending model usable.
  const paySel = page.locator('select[name="payment_status"]');
  await expect(paySel).toHaveCount(1);
  await expect(paySel.locator('option[value="paid"]')).toHaveCount(1);
  await expect(page.locator('select[name="order_status"] option[value="completed"]')).toHaveCount(1);
});
