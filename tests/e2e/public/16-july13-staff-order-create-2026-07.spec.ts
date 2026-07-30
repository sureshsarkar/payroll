import { test, expect, Page } from '@playwright/test';

/**
 * 13-July-Changes.docx #3 — staff Order create/edit permission.
 *
 * The Orders page (instructor/coach-orders) gated its "Add manual order" button
 * on the NON-EXISTENT slug `coach-sells-create`, so a staff member granted
 * "Create Order" (catalog slug `coach-orders-create`) never saw the button. The
 * fix points the gate — and the create/store/update endpoints — at the real
 * catalog slugs coach-orders-create / coach-orders-edit.
 *
 * Requires the RBAC fixtures (tests/e2e/seed-fixtures.php):
 *   e2e-instructor@…            real coach (sees everything)
 *   e2e-staff-all@…            staff with every permission (incl. create/edit)
 *   e2e-staff-orders-view@…    staff with coach-orders (view) but NOT -create
 *
 * Form login (no stored auth) so we can act as each user.
 *
 * Run only this file:
 *   npx playwright test --project=public tests/e2e/public/16-july13-staff-order-create-2026-07.spec.ts
 */

const PW = 'e2e!Test#2026';

async function login(page: Page, email: string) {
  await page.goto('login');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', PW);
  await page.click('button[type="submit"]');
  // The POST sets the session cookie regardless of where the redirect lands; a
  // transient ERR_ABORTED on the follow-up navigation must not fail the test, so
  // swallow the wait and let the explicit goto below assert real access.
  await page.waitForURL(/\/(instructor|student|home|dashboard)/, { timeout: 20_000 }).catch(() => {});
}

const createBtn = (page: Page) => page.getByRole('link', { name: /Add manual order/i });

test.describe('July-13 #3 — staff Order create permission', () => {
  test('a real coach sees the Create button', async ({ page }) => {
    await login(page, 'e2e-instructor@mbsguru.test');
    const resp = await page.goto('instructor/coach-orders');
    expect(resp?.status(), 'coach can open orders').toBeLessThan(400);
    await expect(createBtn(page)).toBeVisible();
  });

  test('staff WITH coach-orders-create sees the button AND can open the create form', async ({ page }) => {
    await login(page, 'e2e-staff-all@mbsguru.test');

    const resp = await page.goto('instructor/coach-orders');
    if (!resp || resp.status() >= 400) {
      test.skip(true, 'all-perms staff fixture missing/unmigrated');
      return;
    }
    // The core regression: this button was invisible before the slug fix.
    await expect(createBtn(page)).toBeVisible();

    // And the endpoint actually serves the create form (not the error view).
    // The student <select> is present in the DOM — assert ATTACHED, not visible,
    // because the form enhances it with select2 (which hides the native select).
    const cr = await page.goto('instructor/coach-orders/create');
    expect(cr?.status(), 'create page served').toBeLessThan(400);
    await expect(page.locator('select[name="user_id"]')).toHaveCount(1);
  });

  test('staff with VIEW-only orders permission does NOT see the button and is blocked from create', async ({ page }) => {
    await login(page, 'e2e-staff-orders-view@mbsguru.test');

    const resp = await page.goto('instructor/coach-orders');
    if (!resp || resp.status() >= 400) {
      test.skip(true, 'orders-view staff fixture missing/unmigrated');
      return;
    }
    // Can view the list, but the Create button is gated away.
    await expect(createBtn(page)).toHaveCount(0);

    // The create endpoint must not serve the form to a staff without create.
    await page.goto('instructor/coach-orders/create');
    await expect(page.locator('select[name="user_id"]')).toHaveCount(0);
  });
});
