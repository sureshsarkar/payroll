import { test, expect } from '@playwright/test';

/**
 * Responsive audit — 2026-06-01 (RWD-4): coach-site builder mobile rail.
 *
 * The page builder's left rail (Sections / Pages / Site / SEO) slides
 * off-canvas at ≤768px. Previously there was no control to bring it back,
 * making the builder unusable on phones/tablets. We added a topbar
 * hamburger (data-rail-toggle) + backdrop (data-rail-mask) that drive the
 * already-present .cse__rail--open class.
 *
 * The editor is reached via the page index and needs a real page id, so
 * this test self-skips when the account has no coach-site page yet.
 *
 * Run only this file:
 *   npx playwright test --project=instructor tests/e2e/instructor/14-coach-site-rail-2026-06.spec.ts
 */

async function openEditor(page: import('@playwright/test').Page): Promise<boolean> {
  await page.goto('instructor/web-page');
  const editLink = page.locator('a[href*="/web-page/pages/"]').first();
  if ((await editLink.count()) === 0) return false;
  const href = await editLink.getAttribute('href');
  if (!href) return false;
  await page.goto(href);
  const hasRail = (await page.locator('.cse__rail').count()) > 0;
  if (hasRail) {
    // The builder shows a first-run onboarding tour overlay (#cseTour) that
    // covers the canvas and intercepts pointer events. It's unrelated to the
    // rail drawer under test, so dismiss it before interacting.
    await page.evaluate(() => document.getElementById('cseTour')?.remove());
  }
  return hasRail;
}

test.describe('RWD-4 coach-site builder mobile rail', () => {
  test('rail is an off-canvas drawer that opens + closes at 375px', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 });
    if (!(await openEditor(page))) {
      test.skip(true, 'no coach-site page available to open the builder for this account');
      return;
    }

    const rail = page.locator('.cse__rail');
    const toggle = page.locator('[data-rail-toggle]');
    const mask = page.locator('[data-rail-mask]');

    // Toggle must be visible on mobile; rail off-canvas to start.
    await expect(toggle).toBeVisible();
    const closedRight = await rail.evaluate((el) => el.getBoundingClientRect().right);
    expect(closedRight, `rail should start off-screen (right=${closedRight})`).toBeLessThanOrEqual(1);

    // Open it.
    await toggle.click();
    await page.waitForTimeout(350);
    const openLeft = await rail.evaluate((el) => el.getBoundingClientRect().left);
    expect(openLeft, `rail should slide in (left=${openLeft})`).toBeGreaterThanOrEqual(-1);
    expect(openLeft).toBeLessThan(40);

    // Backdrop interactive while open.
    const ov = await mask.evaluate((el) => ({
      opacity: parseFloat(getComputedStyle(el).opacity),
      pointer: getComputedStyle(el).pointerEvents,
    }));
    expect(ov.opacity).toBeGreaterThan(0.5);
    expect(ov.pointer).not.toBe('none');

    // Close via backdrop.
    await mask.click({ position: { x: 350, y: 400 } });
    await page.waitForTimeout(350);
    const reclosed = await rail.evaluate((el) => el.getBoundingClientRect().right);
    expect(reclosed, `rail should close back off-screen (right=${reclosed})`).toBeLessThanOrEqual(1);
  });

  test('desktop builder is untouched (rail in normal flow, toggle hidden)', async ({ page }) => {
    await page.setViewportSize({ width: 1366, height: 900 });
    if (!(await openEditor(page))) {
      test.skip(true, 'no coach-site page available to open the builder for this account');
      return;
    }
    const rail = page.locator('.cse__rail');
    const pos = await rail.evaluate((el) => getComputedStyle(el).position);
    expect(pos, 'rail must not be fixed/off-canvas on desktop').not.toBe('fixed');
    await expect(page.locator('[data-rail-toggle]')).toBeHidden();
  });

  test('section-settings panel fits the viewport at 320px', async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 812 });
    if (!(await openEditor(page))) {
      test.skip(true, 'no coach-site page available to open the builder for this account');
      return;
    }
    const panel = page.locator('.cse__panel');
    if ((await panel.count()) === 0) {
      test.skip(true, 'no settings panel in this build');
      return;
    }
    // The panel normally opens when a section is selected; force its open
    // state to verify the responsive clamp without the selection flow.
    await panel.first().evaluate((el) => el.setAttribute('data-state', 'open'));
    await page.waitForTimeout(320); // let the cs-slide-in (translateX) animation settle
    const box = await panel.first().evaluate((el) => {
      const r = el.getBoundingClientRect();
      return { left: r.left, right: r.right, width: r.width };
    });
    // The panel must fit the viewport (was 360px fixed → ~40px clipped off
    // the left at 320px; the max-width:100vw clamp keeps it on-screen).
    // NOTE: we intentionally do NOT assert zero page overflow here — the
    // builder canvas renders a full desktop-width website PREVIEW, which is
    // wider than a phone by design (the "Mobile" device toggle is what
    // scales the preview). This test guards the panel chrome, not the
    // preview content.
    expect(box.left, `panel left edge must not clip off-screen (left=${box.left})`).toBeGreaterThanOrEqual(-1);
    expect(box.right, `panel right edge within viewport (right=${box.right})`).toBeLessThanOrEqual(321);
    expect(box.width, `panel width clamped to viewport (width=${box.width})`).toBeLessThanOrEqual(321);
  });
});
