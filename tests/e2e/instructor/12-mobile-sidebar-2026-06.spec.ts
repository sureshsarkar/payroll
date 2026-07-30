import { test, expect } from '@playwright/test';

/**
 * Responsive audit — 2026-06-01 (RWD-2): instructor mobile sidebar drawer.
 *
 * Desktop: the coach sidebar sits in a col-lg-2 column (position: relative).
 * Below 992px the grid stacks; previously the sidebar had no mobile handling
 * and the col's .overflow--scroll{height:100vh} produced a full-screen empty
 * pane above the content with no way to collapse it.
 *
 * Now the sidebar is an off-canvas drawer driven by the shared topbar
 * hamburger (#mbsSidebarToggle), with an overlay + Escape/link/overlay close.
 *
 * Run only this file:
 *   npx playwright test --project=instructor tests/e2e/instructor/12-mobile-sidebar-2026-06.spec.ts
 */

test.describe('RWD-2 instructor mobile sidebar drawer', () => {
  test('sidebar is an off-canvas drawer that opens + closes at 375px', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 });
    const resp = await page.goto('instructor/dashboard');
    expect(resp?.status() ?? 0).toBeLessThan(500);

    const sidebar = page.locator('#instructorSidebar');
    await expect(sidebar).toHaveCount(1);

    // On mobile the drawer is position:fixed and off-canvas (translated
    // fully left), so its right edge sits at/left of the viewport origin.
    const pos = await sidebar.evaluate((el) => getComputedStyle(el).position);
    expect(pos, 'sidebar should be fixed (off-canvas) on mobile').toBe('fixed');

    const closedRight = await sidebar.evaluate((el) => el.getBoundingClientRect().right);
    expect(closedRight, `drawer should start off-screen (right=${closedRight})`).toBeLessThanOrEqual(1);

    // No horizontal page scroll while the drawer is closed.
    const overflowClosed = await page.evaluate(
      () => document.documentElement.scrollWidth - document.documentElement.clientWidth
    );
    expect(overflowClosed, `page overflow while closed = ${overflowClosed}`).toBeLessThanOrEqual(1);

    // Open via the SHARED topbar hamburger (no dedicated button added).
    await page.locator('#mbsSidebarToggle').click();
    await page.waitForTimeout(400); // let the 0.28s slide transition finish

    const openLeft = await sidebar.evaluate((el) => el.getBoundingClientRect().left);
    expect(openLeft, `drawer should slide on-screen (left=${openLeft})`).toBeGreaterThanOrEqual(-1);
    expect(openLeft, 'drawer left edge should be near the viewport origin').toBeLessThan(40);

    // Overlay must be interactive while open.
    const overlay = page.locator('#instructorSidebarOverlay');
    const ovState = await overlay.evaluate((el) => {
      const cs = getComputedStyle(el);
      return { opacity: parseFloat(cs.opacity), pointer: cs.pointerEvents };
    });
    expect(ovState.opacity, 'overlay should be visible when drawer open').toBeGreaterThan(0.5);
    expect(ovState.pointer).not.toBe('none');

    // Tapping the overlay closes the drawer.
    await overlay.click({ position: { x: 350, y: 400 } });
    await page.waitForTimeout(400);
    const reclosedRight = await sidebar.evaluate((el) => el.getBoundingClientRect().right);
    expect(reclosedRight, `drawer should close back off-screen (right=${reclosedRight})`).toBeLessThanOrEqual(1);

    // Still no horizontal page scroll after the round-trip.
    const overflowAfter = await page.evaluate(
      () => document.documentElement.scrollWidth - document.documentElement.clientWidth
    );
    expect(overflowAfter).toBeLessThanOrEqual(1);
  });

  test('the 100vh scroll panes are neutralised on mobile', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 });
    await page.goto('instructor/dashboard');

    // The content column carries .overflow--scroll. On desktop it is a
    // 100vh independent scroll pane; on mobile it must flow with the page
    // (overflow-y: visible) so there is no trapped full-height pane.
    const overflowY = await page.evaluate(() => {
      const panes = Array.from(document.querySelectorAll('.overflow--scroll'));
      return panes.map((p) => getComputedStyle(p as Element).overflowY);
    });
    expect(overflowY.length, 'expected .overflow--scroll panes present').toBeGreaterThan(0);
    for (const oy of overflowY) {
      expect(oy, 'each .overflow--scroll pane must flow (visible) on mobile').toBe('visible');
    }
  });

  test('desktop layout is untouched (sidebar in normal flow, visible)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.goto('instructor/dashboard');

    const sidebar = page.locator('#instructorSidebar');
    const pos = await sidebar.evaluate((el) => getComputedStyle(el).position);
    expect(pos, 'sidebar must NOT be fixed on desktop (no drawer)').not.toBe('fixed');

    // Visible and on-screen on desktop.
    await expect(sidebar).toBeVisible();
    const rect = await sidebar.evaluate((el) => el.getBoundingClientRect());
    expect(rect.left, 'desktop sidebar should be within the viewport').toBeGreaterThanOrEqual(-1);
    expect(rect.width, 'desktop sidebar should have real width').toBeGreaterThan(80);
  });
});
