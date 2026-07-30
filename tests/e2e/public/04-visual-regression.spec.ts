import { test, expect } from '@playwright/test';

/**
 * Visual regression for the coach-site (white-label) — the brand-sensitive
 * surface where a coach pays for a page that looks a specific way.
 *
 * Uses Playwright's built-in `toHaveScreenshot()`. Baselines are stored
 * under tests/e2e/public/04-visual-regression.spec.ts-snapshots/.
 *
 * First-run policy:
 *   The first time a test runs, Playwright writes the baseline and
 *   PASSES. Subsequent runs compare against the baseline. To approve
 *   an intentional UI change, run with `--update-snapshots`.
 *
 * Scoped to the public coach-site routes only — the platform admin
 * panel is NOT visually-regression-tested (lower brand sensitivity +
 * higher churn in the admin chrome).
 *
 * Test slug `mbs` is a stable seeded coach landing page. Replace with
 * a fixture-managed slug once tests/e2e/fixtures/ grows a CoachLanding
 * factory.
 */

const COACH_SLUG = 'mbs';

// Tolerances tuned for cross-browser rendering noise. We use
// maxDiffPixelRatio because absolute pixel counts vary by viewport.
const VISUAL_OPTIONS = {
  maxDiffPixelRatio: 0.02,   // <2% diff tolerated
  threshold: 0.2,             // per-pixel color difference threshold
  animations: 'disabled' as const,
};

test.describe('coach-site visual regression', () => {
  // Mask dynamic content: timestamps, year ranges in copyright, cart
  // count badges (depend on session state). These would false-flag
  // every run if left untouched.
  const dynamicMasks = (page: import('@playwright/test').Page) => ([
    page.locator('.cs-cart-drawer__count'),         // cart badge
    page.locator('.cs-nav__cart-count'),            // header cart count
    page.locator('text=/©.*\\d{4}/i'),               // copyright with year
  ]);

  test('landing page above-the-fold', async ({ page }) => {
    await page.goto(`coach/${COACH_SLUG}`);
    // Wait for fonts + fontawesome to load so glyphs render the same
    // on every run.
    await page.evaluate(() => (document as any).fonts?.ready);
    await page.waitForLoadState('networkidle');

    await expect(page).toHaveScreenshot('coach-landing-fold.png', {
      ...VISUAL_OPTIONS,
      mask: dynamicMasks(page),
      clip: { x: 0, y: 0, width: 1280, height: 800 },  // viewport-sized clip
    });
  });

  test('landing page full', async ({ page }) => {
    await page.goto(`coach/${COACH_SLUG}`);
    await page.evaluate(() => (document as any).fonts?.ready);
    await page.waitForLoadState('networkidle');

    await expect(page).toHaveScreenshot('coach-landing-full.png', {
      ...VISUAL_OPTIONS,
      mask: dynamicMasks(page),
      fullPage: true,
    });
  });

  test('coach login page', async ({ page }) => {
    await page.goto(`coach/${COACH_SLUG}/login`);
    await page.evaluate(() => (document as any).fonts?.ready);
    await page.waitForLoadState('networkidle');

    await expect(page).toHaveScreenshot('coach-login.png', {
      ...VISUAL_OPTIONS,
      mask: dynamicMasks(page),
      clip: { x: 0, y: 0, width: 1280, height: 800 },
    });
  });

  test('coach register page', async ({ page }) => {
    await page.goto(`coach/${COACH_SLUG}/register`);
    await page.evaluate(() => (document as any).fonts?.ready);
    await page.waitForLoadState('networkidle');

    await expect(page).toHaveScreenshot('coach-register.png', {
      ...VISUAL_OPTIONS,
      mask: dynamicMasks(page),
      clip: { x: 0, y: 0, width: 1280, height: 800 },
    });
  });

  test('coach cart page (empty state)', async ({ page }) => {
    await page.goto(`coach/${COACH_SLUG}/cart`);
    await page.evaluate(() => (document as any).fonts?.ready);
    await page.waitForLoadState('networkidle');

    await expect(page).toHaveScreenshot('coach-cart-empty.png', {
      ...VISUAL_OPTIONS,
      mask: dynamicMasks(page),
      clip: { x: 0, y: 0, width: 1280, height: 800 },
    });
  });
});
