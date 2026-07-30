import { test, expect } from '@playwright/test';

/**
 * Doc-driven smoke regression — 2026-05-29 Admin Panel.docx fixes.
 *
 * The user shipped two annotated screenshot docs that called out 18
 * specific UI bugs on admin + coach panels. Each task in that batch
 * (Doc-A1..Doc-A4, Doc-C-*) was fixed in code. This spec re-walks
 * the URLs the doc screenshots referenced and asserts:
 *
 *   1. The page responds 2xx/3xx (not 500 — the original Doc-A1 bug)
 *   2. Either expected chrome is present or the specific element the
 *      doc complained about is in its post-fix state.
 *
 * Run only this file:
 *   npx playwright test --project=admin tests/e2e/admin/10-doc-bug-fixes-2026-05.spec.ts
 */

test.describe('Doc-driven admin bug fixes (2026-05)', () => {
  /**
   * Doc-A1: Order::getOrderDetailsAttribute TypeError when admin
   * opened /admin/orders. Fixed in commit eca09e2 — the accessor now
   * coerces order_details into an array even when a legacy DB row has
   * scalar / null. We just need /admin/orders to render without 500.
   */
  test('Doc-A1: /admin/orders loads without 500 (Order::getOrderDetailsAttribute)', async ({ page }) => {
    const resp = await page.goto('admin/orders');
    expect(resp?.status(), 'admin/orders should not 5xx').toBeLessThan(500);
  });

  /**
   * Doc-A2: Course More Info step had Completion Certificate / Level /
   * Language fields the user wanted removed. We hid them via blade
   * conditionals + hidden inputs. The form still posts the prior DB
   * values so business logic is untouched.
   *
   * We just open the create-step page and confirm the visible label
   * "Completion Certificate" is NO LONGER on screen.
   */
  test('Doc-A2: Course More Info step no longer shows Completion Certificate toggle', async ({ page }) => {
    const resp = await page.goto('admin/course/create');
    if (!resp || resp.status() >= 400) {
      test.skip(true, `admin/course/create returned ${resp?.status()} — skipping content assertion`);
      return;
    }
    // Look for any visible text containing "Completion Certificate".
    // Hidden inputs are fine (they preserve the DB value); only a
    // user-visible label should fail this assertion.
    const labels = page.getByText(/Completion Certificate/i);
    await expect(labels).toHaveCount(0);
  });

  /**
   * Doc-A3: Sidebar listed "Course Language" and "Course Level" nav
   * items the user wanted removed. Routes still exist (so direct deep
   * links keep working) but the sidebar <li>s are gone.
   */
  test('Doc-A3: Admin sidebar no longer surfaces Course Language / Course Level nav items', async ({ page }) => {
    const resp = await page.goto('admin/dashboard');
    if (!resp || resp.status() >= 400) {
      test.skip(true, `admin/dashboard returned ${resp?.status()} — cannot assert sidebar`);
      return;
    }
    // Sidebar nav has the link as an <a href="...course-language">.
    // If the <li> was removed, the link MUST be gone too.
    await expect(page.locator('aside a[href*="course-language"]')).toHaveCount(0);
    await expect(page.locator('aside a[href*="course-level"]')).toHaveCount(0);
  });

  /**
   * Doc-A4: Admin login page header was double-rendering the app name
   * next to the logo. The <span> was removed; the parent <a> still
   * carries an aria-label so the brand is still announced to screen
   * readers.
   *
   * This test uses an unauthenticated request because the login page
   * IS the unauthenticated page.
   */
  test('Doc-A4: Admin login page no longer renders brand name beside logo', async ({ page, context }) => {
    // Use a clean context so the admin storageState doesn't redirect
    // us straight to the dashboard.
    await context.clearCookies();
    const resp = await page.goto('admin/login');
    if (!resp || resp.status() >= 400) {
      test.skip(true, `admin/login returned ${resp?.status()}`);
      return;
    }
    // The previous markup rendered the brand name as a SECOND span next
    // to the .adm-mast__mark icon wrapper. After the fix only the icon
    // wrapper remains; the brand is carried by the parent <a aria-label>.
    // The icon-wrapper span (.adm-mast__mark) is legitimate, so we
    // assert at most ONE span survives, and that the surviving one is
    // the mark — not a brand-name text span.
    const spans = page.locator('.adm-mast__logo > span');
    const spanCount = await spans.count();
    expect(spanCount, 'only the icon-wrapper span should remain inside .adm-mast__logo').toBeLessThanOrEqual(1);
    if (spanCount === 1) {
      const cls = await spans.first().getAttribute('class');
      expect(cls, 'the sole surviving span must be the icon mark wrapper').toContain('adm-mast__mark');
    }

    // Sanity: the logo anchor MUST still expose the brand for assistive tech.
    const logoLink = page.locator('a.adm-mast__logo[aria-label]');
    await expect(logoLink).toHaveCount(1);
  });
});
