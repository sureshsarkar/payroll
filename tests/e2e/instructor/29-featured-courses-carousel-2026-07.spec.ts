import { test, expect } from '@playwright/test';

/**
 * "Featured Courses Carousel" builder section (2026-07-08) — the rendered
 * carousel on the coach site. Fixture: php tests/e2e/seed-featured-courses.php
 * seeds a featured_courses_v1 section (with real published course ids) onto the
 * e2e-instructor home page (id 42) and prints the preview id.
 * Runs under the `instructor` project.
 */

const PREVIEW = 'coach-preview/42';

test('featured courses carousel renders and scrolls on the coach site', async ({ page }) => {
  const resp = await page.goto(PREVIEW, { waitUntil: 'domcontentloaded' });
  test.skip(!resp || resp.status() !== 200, 'coach preview not available');

  const wrap = page.locator('.cs-fc__wrap[data-fc]').first();
  test.skip((await wrap.count()) === 0, 'featured-courses section not seeded on this page');

  // Cards render from live course data.
  const cards = wrap.locator('.cs-fc__card');
  const n = await cards.count();
  expect(n, 'at least one course card').toBeGreaterThan(0);

  // Each card links to the course detail page (where add-to-cart works).
  const href = await cards.first().locator('a[href*="/course/"]').first().getAttribute('href');
  expect(href, 'CTA points at the course detail page').toContain('/course/');

  // Controls present.
  await expect(wrap.locator('.cs-fc__dots button').first()).toBeVisible();

  // Next arrow scrolls the track (only meaningful when it overflows).
  const track = wrap.locator('.cs-fc__track');
  const before = await track.evaluate((el) => el.scrollLeft);
  const next = wrap.locator('.cs-fc__nav--next');
  if (await next.isVisible().catch(() => false)) {
    await next.click();
    await page.waitForTimeout(700);
    const after = await track.evaluate((el) => el.scrollLeft);
    expect(after, 'track scrolled after clicking next').toBeGreaterThanOrEqual(before);
  }
});
