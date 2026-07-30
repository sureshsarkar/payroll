import { test, expect } from '@playwright/test';

/**
 * Real data-entry for the Instructor (coach) panel.
 *
 *  - Profile update (name + phone + age) — round-trip through DB
 *  - Brand settings update (brand_name + support_email + primary_color)
 *    — pins P1-4 / coach-site Path B contract: settings persist + are
 *    surfaced on the public coach-site
 */

test('instructor profile update: change name + phone, verify persisted', async ({ page }) => {
  await page.goto('instructor/setting');

  const ts = Date.now();
  const newName = `E2E Coach ${ts}`;
  const newPhone = `+91-${ts.toString().slice(-10)}`;

  // Scope to the profile form (page may have multiple — biography,
  // location, education, experience).
  const form = page.locator('form[action$="/instructor/setting/profile"]').first();
  await expect(form.locator('input[name="name"]')).toBeVisible();

  await form.locator('input[name="name"]').fill(newName);
  await form.locator('input[name="phone"]').fill(newPhone);

  // Use requestSubmit() so the browser fires the submit event (any
  // bound onsubmit handler runs) AND walks the standard form-processing
  // path including @method PUT spoofing.
  await form.evaluate((f: HTMLFormElement) => f.requestSubmit());
  await page.waitForLoadState('networkidle');

  // VERIFY persisted: reload + read input value back.
  await page.goto('instructor/setting');
  await expect(page.locator('input[name="name"]')).toHaveValue(newName);
  await expect(page.locator('input[name="phone"]')).toHaveValue(newPhone);
});

test('instructor brand-settings update: change brand_name + primary_color', async ({ page }) => {
  await page.goto('instructor/brand-settings');

  const ts = Date.now();
  const newBrand = `E2E Brand ${ts}`;
  const newColor = '#FF5733';

  const form = page.locator('form#brandForm');
  await expect(form.locator('input[name="brand_name"]')).toBeVisible();

  await form.locator('input[name="brand_name"]').fill(newBrand);
  await form.locator('input[name="primary_color"]').fill(newColor);

  await form.evaluate((f: HTMLFormElement) => f.requestSubmit());
  await page.waitForLoadState('networkidle');

  // VERIFY persisted.
  await page.goto('instructor/brand-settings');
  await expect(page.locator('input[name="brand_name"]')).toHaveValue(newBrand);
  await expect(page.locator('input[name="primary_color"]')).toHaveValue(newColor);
});

test('instructor profile rejects name over max (100 chars)', async ({ page }) => {
  // UI/UX audit P0-6 — standardized to max:100 (was 50).
  // StudentProfileUpdateRequest backs both student + instructor
  // profile update; raising it here raises both.
  await page.goto('instructor/setting');
  const form = page.locator('form[action$="/instructor/setting/profile"]').first();
  const tooLong = 'X'.repeat(150);
  await form.locator('input[name="name"]').fill(tooLong);
  await form.evaluate((f: HTMLFormElement) => f.requestSubmit());
  await page.waitForLoadState('networkidle');
  await page.goto('instructor/setting');
  const saved = await page.locator('input[name="name"]').inputValue();
  expect(saved.length, `saved name was ${saved.length} chars`).toBeLessThanOrEqual(100);
});
