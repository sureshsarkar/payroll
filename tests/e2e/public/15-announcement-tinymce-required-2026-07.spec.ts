import { test, expect, Page } from '@playwright/test';

/**
 * Announcement create — TinyMCE `required` submission crash (2026-07-07).
 *
 * The rich-text field is a hidden <textarea name="announcement" required> that
 * TinyMCE replaces with an iframe. On submit the browser tried to validate the
 * hidden required control and threw
 *   "An invalid form control with name='announcement' is not focusable."
 * blocking the whole form.
 *
 * Fix (public/frontend/js/custom-tinymce.js): once the editor mounts, the native
 * `required` is stripped and remembered as data-required; a global submit guard
 * triggerSave()s the content and enforces it without the native crash.
 */

const PW = 'e2e!Test#2026';

async function login(page: Page, email: string) {
  await page.goto('login');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', PW);
  await Promise.all([
    page.waitForURL(/\/(instructor|student|home|dashboard)/, { timeout: 25_000 }),
    page.click('button[type="submit"]'),
  ]);
}

test.describe('Announcement TinyMCE required fix', () => {
  test('editor mounts, native required is swapped for the soft guard, submit is not blocked', async ({ page }) => {
    await login(page, 'e2e-instructor@mbsguru.test');
    await page.goto('instructor/announcements/create');

    // TinyMCE has mounted (its chrome is present).
    await expect(page.locator('.tox-tinymce')).toBeVisible({ timeout: 20_000 });

    const ta = page.locator('textarea[name="announcement"]');
    // Core of the fix: the hidden textarea must NOT carry native `required`
    // (that is what made it "not focusable"), and must carry the soft flag.
    await expect(ta).toHaveAttribute('data-required', '1');
    expect(await ta.getAttribute('required')).toBeNull();

    // Empty content → the global guard blocks submission and keeps us on the
    // page (crucially: NO "not focusable" browser crash — the click resolves).
    await page.click('button[type="submit"]');
    await page.waitForTimeout(500);
    expect(page.url()).toContain('/instructor/announcements/create');

    // Type into the editor → triggerSave copies it into the textarea, so the
    // required guard is satisfied and the browser no longer blocks the submit.
    await page.locator('.tox-edit-area iframe').first().waitFor({ timeout: 10_000 });
    const frame = page.frameLocator('.tox-edit-area iframe').first();
    await frame.locator('body').click();
    await frame.locator('body').type('Hello students, class update.');

    // The soft-required is now satisfied at the textarea level.
    const synced = await page.evaluate(() => {
      // @ts-ignore
      window.tinymce && window.tinymce.triggerSave();
      const el = document.querySelector('textarea[name="announcement"]') as HTMLTextAreaElement | null;
      return el ? el.value : '';
    });
    expect(synced).toContain('Hello students');
  });
});
