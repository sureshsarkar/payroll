import { test, expect } from '@playwright/test';

/**
 * Featured Courses Carousel — EDITOR SAVE round-trip (2026-07-08 regression).
 * Bug: the course-picker's selected list wasn't a direct child of .cse__field, so
 * serializeForm() dropped course_ids on save → carousel stayed empty. This test
 * exercises the real path: open the section, pick a course, confirm it SAVES,
 * survives a reload, and renders in the preview.
 *
 * Fixture: php tests/e2e/seed-featured-courses.php --empty   (section w/ 0 courses)
 * Runs under the `instructor` project (e2e-instructor session).
 */

const EDITOR = 'instructor/web-page/pages/42';
const PREVIEW = 'coach-preview/42';

async function openSection(page) {
  // The first-run onboarding tour + progress checklist overlay the page and
  // intercept clicks; suppress + remove them (they aren't under test here).
  await page.addInitScript(() => {
    try { localStorage.setItem('cse:tour:dismissed', '1'); localStorage.setItem('cse:checklist:collapsed', '1'); } catch (e) {}
  });
  await page.goto(EDITOR, { waitUntil: 'domcontentloaded' });
  await page.evaluate(() => { document.querySelector('#cseTour')?.remove(); document.querySelector('#cseChecklist')?.remove(); });
  const sec = page.locator('.cse__sec[data-section-type="featured_courses_v1"]').first();
  await expect(sec, 'Featured Courses section exists on the page').toBeVisible({ timeout: 15_000 });
  // Click the left-side icon — the hover action buttons sit on the right and
  // would intercept a click aimed at the middle of the row.
  await sec.locator('.cse__sec-icon').click();
  // Picker renders in the right panel.
  await expect(page.locator('.cse__field--course-picker .cse__cp-input')).toBeVisible({ timeout: 10_000 });
}

test('picking a course saves course_ids, persists, and renders', async ({ page }) => {
  await openSection(page);

  const selected = page.locator('.cse__field--course-picker .cse__cp-item');
  const before = await selected.count();

  // Search + add the first matching published course.
  await page.locator('.cse__cp-input').fill('a');
  const firstResult = page.locator('.cse__cp-results .cse__cp-result').first();
  await expect(firstResult, 'search returns published courses').toBeVisible({ timeout: 10_000 });

  await firstResult.click();
  await expect(selected).toHaveCount(before + 1, { timeout: 5_000 });

  // Let the debounced save + its PUT settle (the save is the thing that used to
  // drop course_ids), then confirm it PERSISTED by reloading — the real proof.
  await page.waitForResponse(
    (r) => /\/web-page\/sections\/\d+/.test(r.url()) && r.request().method() === 'PUT' && r.ok(),
    { timeout: 15_000 },
  ).catch(() => {});
  await page.waitForTimeout(900);

  await openSection(page);
  await expect(
    page.locator('.cse__field--course-picker .cse__cp-item'),
    'selection survived reload — course_ids really saved',
  ).toHaveCount(before + 1, { timeout: 10_000 });

  // Renders in the live preview.
  await page.goto(PREVIEW, { waitUntil: 'domcontentloaded' });
  await expect(page.locator('.cs-fc__wrap[data-fc] .cs-fc__card').first(), 'carousel shows the course').toBeVisible({ timeout: 10_000 });
});
