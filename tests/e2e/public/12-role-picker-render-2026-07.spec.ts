import { test, expect, Page } from '@playwright/test';

/**
 * Phase B (matrix polish) — the Create Role permission picker renders with the
 * new module grouping, sticky header, and "Only granted" filter, and the
 * checkbox wire format is intact. Form-login as the coach (public project, no
 * storageState → independent of the flaky admin setup).
 */

const PW = 'e2e!Test#2026';

async function login(page: Page, email: string) {
  await page.goto('login');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', PW);
  await Promise.all([
    page.waitForURL(/\/(instructor|student|home|dashboard)/, { timeout: 15_000 }),
    page.click('button[type="submit"]'),
  ]);
}

test.describe('Create Role picker — grouped matrix', () => {
  test('matrix renders with module group headers and the Only-granted filter', async ({ page }) => {
    await login(page, 'e2e-instructor@mbsguru.test');
    const resp = await page.goto('instructor/staff-role/create');
    expect(resp?.status(), 'coach can open Create Role').toBeLessThan(400);

    // Matrix + checkboxes present (wire format preserved).
    await expect(page.locator('.pp-matrix')).toBeVisible();
    const boxes = page.locator('.pp-matrix input[type=checkbox][name]');
    expect(await boxes.count()).toBeGreaterThan(20);

    // New: at least three module section headers (Content / People / Commerce / Settings).
    const groups = page.locator('.pp-group-row');
    expect(await groups.count(), 'matrix is grouped by module').toBeGreaterThanOrEqual(3);

    // New: the Only-granted filter exists and toggles its pressed state.
    const filter = page.locator('.pp-only-granted');
    await expect(filter).toBeVisible();
    await filter.click();
    await expect(filter).toHaveAttribute('aria-pressed', 'true');

    // With nothing checked yet, "Only granted" hides all resource rows.
    const visibleRows = page.locator('.pp-matrix tbody tr[data-resource]:visible');
    expect(await visibleRows.count(), 'only-granted hides ungranted rows').toBe(0);

    // Tick one resource, and it (plus its group header) becomes visible again.
    await filter.click(); // turn off to interact
    await expect(filter).toHaveAttribute('aria-pressed', 'false');
    // The real <input> is an opacity:0 proxy behind .pp-matrix__cb-box, so
    // force the check (this is how a user's click on the styled box behaves).
    await boxes.first().check({ force: true });
    await filter.click(); // back on
    expect(await page.locator('.pp-matrix tbody tr[data-resource]:visible').count()).toBeGreaterThan(0);
  });
});
