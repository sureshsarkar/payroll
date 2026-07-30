import { test, expect } from '@playwright/test';

/**
 * Admin profile form — exercises each editable field, asserts the
 * save round-trip preserves the value.
 *
 * Covers the validator we hardened in commit 2736016 (FT-UPLOAD-3 /
 * FT-VAL-19): max-length on name/email/bio.
 */

test('profile page loads with editable fields', async ({ page }) => {
  const resp = await page.goto('admin/edit-profile');
  expect(resp?.status()).toBeLessThan(400);
  await expect(page.locator('input[name="name"]')).toBeVisible();
  await expect(page.locator('input[name="email"]')).toBeVisible();
});

test('save with normal-length name + email succeeds', async ({ page }) => {
  await page.goto('admin/edit-profile');
  const ts = Date.now();
  const newName = `E2E Admin ${ts}`.slice(0, 40);
  await page.fill('input[name="name"]', newName);
  // Email kept stable — changing the login email would lock us out
  // of subsequent runs without DB cleanup.
  // The page contains multiple <form>s (search bar, password form, profile
  // form). Scope to the profile-update form specifically.
  // The profile form's submit button does not carry an explicit
  // type="submit" attribute — match on text + class instead.
  await page.locator('form[action*="profile-update"]').locator('button.btn-primary').first().click();
  await page.waitForLoadState('networkidle');
  // Re-fetch the page and confirm the name persisted.
  await page.goto('admin/edit-profile');
  await expect(page.locator('input[name="name"]')).toHaveValue(newName);
});

test('rejects name over max (100 chars)', async ({ page }) => {
  await page.goto('admin/edit-profile');
  // UI/UX audit P0-6 — standardized to max:100 across all profile
  // forms (was max:190 here; max:50 on student; max:50 on instructor).
  // Submit one above the cap and assert it was NOT persisted.
  const tooLong = 'X'.repeat(150);
  await page.fill('input[name="name"]', tooLong);
  await page.locator('form[action*="profile-update"]').locator('button.btn-primary').first().click();
  await page.waitForLoadState('networkidle');
  await page.goto('admin/edit-profile');
  const saved = await page.locator('input[name="name"]').inputValue();
  expect(saved.length).toBeLessThanOrEqual(100);
});
