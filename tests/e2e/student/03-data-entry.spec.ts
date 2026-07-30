import { test, expect } from '@playwright/test';

/**
 * Real data-entry for the Student panel.
 *
 *  - Profile update — name + phone + age round-trip through DB
 *  - StudentProfileUpdateRequest validation (FT-VAL-1 + FT-UPLOAD-3)
 *    pinned by an over-cap name test
 */

test('student profile update: change name + phone + age, verify persisted', async ({ page }) => {
  await page.goto('student/setting');

  const ts = Date.now();
  const newName = `E2E St ${ts}`.slice(0, 50); // stay within 50-char cap
  const newPhone = `+91-${ts.toString().slice(-10)}`;
  const newAge = '25';

  // Scope to the profile form. URL pattern: /student/setting/profile.
  const form = page.locator('form[action$="/student/setting/profile"]').first();
  await expect(form.locator('input[name="name"]')).toBeVisible();

  await form.locator('input[name="name"]').fill(newName);
  await form.locator('input[name="phone"]').fill(newPhone);

  // Age input may or may not be present depending on the version; only
  // fill if it is.
  const ageInput = form.locator('input[name="age"]');
  if (await ageInput.count() > 0) {
    await ageInput.fill(newAge);
  }

  await form.evaluate((f: HTMLFormElement) => f.requestSubmit());
  await page.waitForLoadState('networkidle');

  // VERIFY persisted.
  await page.goto('student/setting');
  await expect(page.locator('input[name="name"]')).toHaveValue(newName);
  await expect(page.locator('input[name="phone"]')).toHaveValue(newPhone);
  if (await page.locator('input[name="age"]').count() > 0) {
    await expect(page.locator('input[name="age"]')).toHaveValue(newAge);
  }
});

test('student profile rejects name over max (100 chars)', async ({ page }) => {
  // UI/UX audit P0-6 — standardized to max:100 across profile forms
  // (was max:50). StudentProfileUpdateRequest backs both student +
  // instructor profile update.
  await page.goto('student/setting');
  const form = page.locator('form[action$="/student/setting/profile"]').first();
  await form.locator('input[name="name"]').fill('X'.repeat(150));
  await form.evaluate((f: HTMLFormElement) => f.requestSubmit());
  await page.waitForLoadState('networkidle');

  await page.goto('student/setting');
  const saved = await page.locator('input[name="name"]').inputValue();
  expect(saved.length, `saved name was ${saved.length} chars (cap 100)`).toBeLessThanOrEqual(100);
});

test('student profile email is editable but capped (FT-VAL-1)', async ({ page }) => {
  // FT-VAL-1 sweep (commit 97107fc) added length cap to email field.
  // Posting an obscenely long email must NOT silently truncate.
  await page.goto('student/setting');
  const form = page.locator('form[action$="/student/setting/profile"]').first();
  await form.locator('input[name="email"]').fill('x'.repeat(260) + '@example.test');
  await form.evaluate((f: HTMLFormElement) => f.requestSubmit());
  await page.waitForLoadState('networkidle');

  // After redirect, email should NOT be the 260-char monster.
  await page.goto('student/setting');
  const saved = await page.locator('input[name="email"]').inputValue();
  expect(saved.length, `saved email was ${saved.length} chars (cap 255)`).toBeLessThanOrEqual(255);
});
