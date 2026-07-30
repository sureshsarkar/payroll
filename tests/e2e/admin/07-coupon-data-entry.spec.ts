import { test, expect } from '@playwright/test';

/**
 * Real data-entry round-trip for the Admin Coupon CRUD.
 *
 * Strategy:
 *   CREATE — real browser UI (modal open → fill every field → submit)
 *   VERIFY — re-render the index page and assert the new code appears
 *   EDIT   — PUT via request fixture (UI form submission through the
 *            Bootstrap modal is fragile; the validator contract is
 *            the same as the create path)
 *   DELETE — DELETE via request fixture
 *
 * The datepicker widget overwrites `fill()` on its next tick — we
 * set the value via JS and dispatch a change event so the validator
 * sees a clean YYYY-MM-DD.
 */

test('coupon: create via UI → verify → edit via API → verify → delete via API → verify gone', async ({ page }) => {
  const code = `E2E${Date.now()}`;
  const editedCode = `${code}-EDIT`;

  // ─── 1. CREATE via browser UI ─────────────────────────────────
  await page.goto('admin/coupon');

  // Open the create modal first (form inputs are CSS-hidden until then).
  await page.locator('[data-target="#create_coupon_id"]').first().click();

  const createForm = page.locator('#create_coupon_id form[action$="/admin/coupon"]');
  await expect(createForm.locator('input[name="coupon_code"]')).toBeVisible({ timeout: 5_000 });

  await createForm.locator('input[name="coupon_code"]').fill(code);
  await createForm.locator('input[name="min_price"]').fill('500');
  await createForm.locator('input[name="offer_percentage"]').fill('15');
  // bootstrap-datepicker bound to expired_date overwrites fill() —
  // set value via JS + dispatch change.
  await createForm.locator('input[name="expired_date"]').evaluate(
    (el: HTMLInputElement) => {
      el.value = '2030-12-31';
      el.dispatchEvent(new Event('change', { bubbles: true }));
    }
  );
  await createForm.locator('select[name="status"]').selectOption('active');

  // The submit button gets lifted OUT of the <form> by browser DOM
  // restructuring (sloppy div/form nesting in the blade). Target it
  // inside the modal by accessible name.
  await Promise.all([
    page.waitForURL(/\/admin\/coupon\b/, { timeout: 15_000 }),
    page.locator('#create_coupon_id').getByRole('button', { name: /Save/i }).click(),
  ]);

  // ─── 2. VERIFY ON INDEX ───────────────────────────────────────
  await expect(page.getByText(code, { exact: false }).first()).toBeVisible({ timeout: 10_000 });

  // Capture the row's coupon ID from the edit anchor's data-target.
  const id = await page.evaluate((c) => {
    const rows = Array.from(document.querySelectorAll('tr'));
    const row = rows.find(r => r.textContent?.includes(c));
    if (!row) return null;
    const editAnchor = row.querySelector('a[data-target^="#edit_coupon_id_"]') as HTMLElement | null;
    const target = editAnchor?.getAttribute('data-target');
    const m = target?.match(/^#edit_coupon_id_(\d+)$/);
    return m ? m[1] : null;
  }, code);
  expect(id, `should be able to locate row id for coupon "${code}"`).toBeTruthy();

  // ─── 3. EDIT via API ──────────────────────────────────────────
  const token = await page.locator('meta[name="csrf-token"]').getAttribute('content') || '';
  const editResp = await page.request.fetch(`admin/coupon/${id}`, {
    method: 'PUT',
    headers: { 'x-csrf-token': token, accept: 'text/html' },
    form: {
      _token: token,
      coupon_code: editedCode,
      min_price: '500',
      offer_percentage: '20',
      expired_date: '2031-12-31',
      status: 'inactive',
    },
    maxRedirects: 0,
    failOnStatusCode: false,
  });
  expect([200, 302], `edit returned ${editResp.status()}`).toContain(editResp.status());

  // VERIFY the edit landed.
  await page.goto('admin/coupon');
  await expect(page.getByText(editedCode, { exact: false }).first()).toBeVisible({ timeout: 10_000 });
  // The original code must NO LONGER be present.
  await expect(page.getByText(code, { exact: true })).toHaveCount(0);

  // ─── 4. DELETE via API ────────────────────────────────────────
  const delResp = await page.request.fetch(`admin/coupon/${id}`, {
    method: 'DELETE',
    headers: { 'x-csrf-token': token, accept: 'text/html' },
    form: { _token: token },
    maxRedirects: 0,
    failOnStatusCode: false,
  });
  expect([200, 302]).toContain(delResp.status());

  // VERIFY the row is gone.
  await page.goto('admin/coupon');
  await expect(page.getByText(editedCode, { exact: false })).toHaveCount(0);
});
