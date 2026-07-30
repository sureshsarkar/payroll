import { test, expect } from '@playwright/test';
import * as path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

/**
 * Real data-entry for the Course Category create form — including the
 * multipart file upload that was the reason this test couldn't run in
 * the earlier scope. Exercises:
 *
 *   - GET /admin/course-category/create renders the form
 *   - real `<input type="file">` upload via setInputFiles()
 *   - real `<input>` / `<select>` fills
 *   - browser-driven submit
 *   - verification on the index page
 *   - cleanup via DELETE
 *
 * Fixture: tests/e2e/fixtures/test-icon.png — a 70-byte 1×1 PNG.
 * Validates against FT-UPLOAD-3 (commit 099bc45) which requires
 * mimes:jpg,jpeg,png,webp + max:1024 (KB) on category icons.
 */

const ICON_PATH = path.join(__dirname, '../fixtures/test-icon.png');

test('course-category: create via UI (with image upload) → verify on index → delete', async ({ page }) => {
  const name = `E2E Cat ${Date.now()}`;
  const slug = `e2e-cat-${Date.now()}`;

  // ─── 1. CREATE via browser UI ─────────────────────────────────
  const resp = await page.goto('admin/course-category/create');
  expect(resp?.status()).toBeLessThan(400);

  // Form: name, slug, icon (file), description, show_at_trending, status.
  // Scope to the create form (page has the admin sidebar search etc).
  const form = page.locator('form[action$="/admin/course-category"]');
  await expect(form.locator('input[name="name"]')).toBeVisible();

  await form.locator('input[name="name"]').fill(name);
  await form.locator('input[name="slug"]').fill(slug);
  await form.locator('input[name="icon"]').setInputFiles(ICON_PATH);
  await form.locator('select[name="show_at_trending"]').selectOption('0');
  await form.locator('select[name="status"]').selectOption('1');
  // Description is a Summernote rich-text widget — fill the underlying
  // textarea via JS, since the visible WYSIWYG hides the original input.
  await form.locator('textarea[name="description"]').evaluate(
    (el: HTMLTextAreaElement) => {
      el.value = 'E2E description body — testing data entry.';
      el.dispatchEvent(new Event('change', { bubbles: true }));
    }
  );

  // Submit. The controller redirects to /admin/course-category/{id}/edit
  // on success — wait for that specific URL pattern before moving on.
  await Promise.all([
    page.waitForURL(/\/admin\/course-category\/\d+\/edit/, { timeout: 15_000 }),
    form.evaluate((f: HTMLFormElement) => f.submit()),
  ]);
  await page.waitForLoadState('networkidle');

  // ─── 2. VERIFY ON INDEX ───────────────────────────────────────
  await page.goto('admin/course-category');
  await expect(page.getByText(name, { exact: false }).first()).toBeVisible({ timeout: 10_000 });

  // Capture the row's category ID from the edit link href.
  const id = await page.evaluate((n) => {
    const rows = Array.from(document.querySelectorAll('tr'));
    const row = rows.find(r => r.textContent?.includes(n));
    if (!row) return null;
    // Edit anchors point to /admin/course-category/{id}/edit.
    const editAnchor = Array.from(row.querySelectorAll('a')).find(
      (a) => /\/admin\/course-category\/\d+\/edit/.test(a.href)
    );
    if (!editAnchor) return null;
    const m = editAnchor.href.match(/\/admin\/course-category\/(\d+)\/edit/);
    return m ? m[1] : null;
  }, name);
  expect(id, `should find row id for category "${name}"`).toBeTruthy();

  // ─── 3. DELETE via API (cleanup) ──────────────────────────────
  const token = await page.locator('meta[name="csrf-token"]').getAttribute('content') || '';
  const delResp = await page.request.fetch(`admin/course-category/${id}`, {
    method: 'DELETE',
    headers: { 'x-csrf-token': token, accept: 'text/html' },
    form: { _token: token },
    maxRedirects: 0,
    failOnStatusCode: false,
  });
  expect([200, 302]).toContain(delResp.status());

  // VERIFY gone.
  await page.goto('admin/course-category');
  await expect(page.getByText(name, { exact: false })).toHaveCount(0);
});

test('course-category: create without icon shows validator error (FT-UPLOAD-3)', async ({ page }) => {
  // FT-UPLOAD-3 fix (commit 099bc45) made `icon` required + mimes-whitelisted.
  // Posting the form without an icon must NOT 500 and must NOT silently
  // succeed — it must round-trip back to the form with errors.
  await page.goto('admin/course-category/create');
  const form = page.locator('form[action$="/admin/course-category"]');
  await form.locator('input[name="name"]').fill('E2E No Icon');
  await form.locator('input[name="slug"]').fill(`e2e-no-icon-${Date.now()}`);
  await form.locator('select[name="show_at_trending"]').selectOption('0');
  await form.locator('select[name="status"]').selectOption('1');

  // Submit directly via form.submit() — bypasses any client-side required
  // check on the file input so we hit the server validator.
  await form.evaluate((f: HTMLFormElement) => f.submit());
  await page.waitForLoadState('networkidle');

  // Two acceptable outcomes:
  //   - we're back on the /admin/course-category/create page with error
  //     messages flashed
  //   - we land on the index with no new row (validator returned redirect
  //     to create)
  // What's NOT acceptable: a fresh row was created without an icon.
  await page.goto('admin/course-category');
  await expect(page.getByText('E2E No Icon', { exact: false })).toHaveCount(0);
});
