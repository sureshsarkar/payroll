import { test, expect } from '@playwright/test';

/**
 * Regression — 2026-06-01: coach "Download invoice" link must not 404.
 *
 * The My Sales listing paginates ORDER ITEMS, so the invoice link passes an
 * order-item id. `printInvoice` previously did
 * `Order::where('id',$id)->where('seller_id',$coachId)->firstOrFail()`,
 * which (a) treated the order-item id as an order id and (b) scoped by
 * seller_id — only set on coach *manual* orders. Result: student-purchased
 * orders were listed in My Sales but their invoice link 404'd
 * (e.g. /instructor/order/invoice/35). Now it resolves via course
 * ownership, identical to the "View order" link.
 *
 * Data-resilient: skips if the test account has no sales row.
 *
 * Run only this file:
 *   npx playwright test --project=instructor tests/e2e/instructor/16-invoice-link-2026-06.spec.ts --no-deps
 */

test('My Sales "Download invoice" link does not 404', async ({ page }) => {
  const resp = await page.goto('instructor/coach-orders');
  expect(resp?.status() ?? 0, 'coach-orders page should load').toBeLessThan(500);

  const invoiceLink = page.locator('a[href*="/order/invoice/"]').first();
  if ((await invoiceLink.count()) === 0) {
    test.skip(true, 'no sales rows / invoice link for this account');
    return;
  }
  const href = await invoiceLink.getAttribute('href');
  expect(href, 'invoice link should have an href').toBeTruthy();

  // Fetch the invoice URL directly (the link opens target="_blank").
  const inv = await page.request.get(href!);
  expect(inv.status(), `invoice ${href} returned ${inv.status()}`).toBeLessThan(400);

  // Must render the invoice, not the 404 page.
  const body = await inv.text();
  expect(body, 'invoice response must not be the 404 page').not.toContain('Go To Home Page');
});

test('"View order" and "Download invoice" use the same id (stay in lockstep)', async ({ page }) => {
  await page.goto('instructor/coach-orders');
  const row = page.locator('.corp-actions').first();
  if ((await row.count()) === 0) {
    test.skip(true, 'no action row for this account');
    return;
  }
  const viewHref = await row.locator('a[href*="/coach-orders/"]').first().getAttribute('href');
  const invHref = await row.locator('a[href*="/order/invoice/"]').first().getAttribute('href');
  if (!viewHref || !invHref) {
    test.skip(true, 'one of the action links is absent');
    return;
  }
  // Both links must carry the SAME trailing id — that's the contract the
  // bug violated (invoice was resolved as an order id, view as an item id).
  const viewId = viewHref.split('/').pop();
  const invId = invHref.split('/').pop();
  expect(invId, `view id ${viewId} vs invoice id ${invId}`).toBe(viewId);
});
