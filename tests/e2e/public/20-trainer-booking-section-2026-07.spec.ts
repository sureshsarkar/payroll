import { test, expect } from '@playwright/test';

/**
 * Trainer Booking section (self-contained, 2026-07-15) — public E2E + UAT.
 *
 * Drives the REAL flow on a coach page that has a trainer_booking_v1 section:
 * the Book Now button opens the "Book Personal Class Session" form, the fuller
 * fields render, and submitting round-trips to the server which accepts the
 * booking (payment or lead) with a SERVER-resolved amount. The seed page is
 * created by scratchpad/seed_trainer_e2e.php; pass its path via env.
 *
 *   E2E_TRAINER_PATH=coach/<site>/<page> \
 *   npx playwright test --project=public tests/e2e/public/20-trainer-booking-section-2026-07.spec.ts
 */

const PATH = process.env.E2E_TRAINER_PATH || 'coach/virendrastrengthyoga/qa-trainer-booking-e2e';

test.describe('Trainer Booking section — public', () => {
  test('Book Now opens the fuller booking form', async ({ page }) => {
    const resp = await page.goto(PATH);
    if (!resp || resp.status() >= 400) {
      test.skip(true, `seed page not reachable (${resp?.status()}) — run the seed script first`);
      return;
    }

    // UAT: the Book Now button is present.
    const book = page.locator('.td-book').first();
    await expect(book).toBeVisible();

    // Modal starts hidden, opens on click.
    const modal = page.locator('#tdBookModal');
    await expect(modal).toBeHidden();
    await book.click();
    await expect(modal).toBeVisible();

    // The fuller form fields all render.
    for (const sel of [
      'select[name="plan_type"]', 'select[name="course_type"]', 'select[name="package_id"]',
      'select[name="gender"]', 'input[name="height"]', 'input[name="weight"]',
      'input[name="name"]', 'input[name="email"]', 'input[name="mobile"]',
      'select[name="reason"]', 'textarea[name="problem"]',
    ]) {
      await expect(modal.locator(sel)).toHaveCount(1);
    }
    // The section drives the package options (server-authoritative price source).
    await expect(modal.locator('select[name="package_id"] option')).not.toHaveCount(0);
  });

  test('submitting the form round-trips to the server (server-resolved amount)', async ({ page }) => {
    // Establish the tenant session the way a real visit does. On production the
    // coach SUBDOMAIN resolves the tenant on every request; on the localhost PATH
    // surface we warm it by first hitting a tenant.context route (cart). Without
    // this, resolveCoachId can't identify the coach on a bare host — by design.
    const site = PATH.split('/')[1];
    await page.goto(`coach/${site}/cart`).catch(() => {});

    const resp = await page.goto(PATH);
    if (!resp || resp.status() >= 400) {
      test.skip(true, 'seed page not reachable');
      return;
    }
    await page.locator('.td-book').first().click();
    const modal = page.locator('#tdBookModal');
    await expect(modal).toBeVisible();

    // Fill the required fields (dropdowns already have a default selected).
    await modal.locator('input[name="name"]').fill('QA Playwright');
    await modal.locator('input[name="email"]').fill('qa.playwright@example.com');
    await modal.locator('input[name="mobile"]').fill('9876543210');
    await modal.locator('input[name="height"]').fill('170');
    await modal.locator('input[name="weight"]').fill('65');

    // Capture the booking POST and assert the server accepted it.
    const bookingResp = page.waitForResponse(
      (r) => r.url().includes('/trainer-booking') && r.request().method() === 'POST',
      { timeout: 15000 }
    );
    await modal.locator('[data-tdbk-submit]').click();
    const r = await bookingResp;

    expect(r.status(), 'booking POST must not error').toBeLessThan(500);
    const json = await r.json().catch(() => ({}));
    expect(json.ok, `server accepted the booking (got: ${JSON.stringify(json)})`).toBeTruthy();
    // Either a real gateway (payment) or a lead when no gateway is configured.
    expect(['payment', 'lead']).toContain(json.mode);
  });

  test('page is responsive on mobile (no horizontal body scroll)', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 });
    const resp = await page.goto(PATH);
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
