import { test, expect, Page } from '@playwright/test';

/**
 * Certificate Builder — live preview + instant template switching (2026-07-09).
 * The right panel shows the selected template live; switching Enterprise/Classic
 * updates instantly (no reload); typing in the form updates the Classic preview
 * with realistic sample data. Form-login flow (public project).
 */

const PW = 'e2e!Test#2026';

async function login(page: Page) {
  await page.goto('login');
  await page.fill('input[name="email"]', 'e2e-instructor@mbsguru.test');
  await page.fill('input[name="password"]', PW);
  await Promise.all([
    page.waitForURL(/\/(instructor|home|dashboard)/, { timeout: 25_000 }),
    page.click('button[type="submit"]'),
  ]);
}

test.describe('Certificate Builder live preview', () => {
  test('template switch is instant and updates the preview', async ({ page }) => {
    await login(page);
    await page.goto('instructor/certificate-builder', { waitUntil: 'domcontentloaded' });

    const entStage = page.locator('#cbStageEnt');
    const classicStage = page.locator('#cbStageClassic');
    const badge = page.locator('#cbStyleBadge');

    // Default = Enterprise.
    await expect(entStage).toBeVisible();
    await expect(classicStage).toBeHidden();
    await expect(badge).toHaveText(/Enterprise/i);
    // Enterprise preview uses realistic sample data (not raw tags).
    await expect(page.locator('#cbEnt')).toContainText('Aarav Sharma');
    await expect(page.locator('#cbEnt')).toContainText('Advanced Yoga Training Program');
    await expect(page.locator('#cbEnt')).not.toContainText('[student_name]');

    // Switch to Classic → instant.
    await page.locator('label.cb-style__opt:has(input[value="classic"])').click();
    await expect(classicStage).toBeVisible();
    await expect(entStage).toBeHidden();
    await expect(badge).toHaveText(/Classic/i);
    // Only the selected card is highlighted.
    await expect(page.locator('label.cb-style__opt:has(input[value="classic"])')).toHaveClass(/is-on/);
    await expect(page.locator('label.cb-style__opt:has(input[value="enterprise"])')).not.toHaveClass(/is-on/);
  });

  test('typing in the form updates the Classic preview live (sample-data fallback)', async ({ page }) => {
    await login(page);
    await page.goto('instructor/certificate-builder', { waitUntil: 'domcontentloaded' });
    await page.locator('label.cb-style__opt:has(input[value="classic"])').click();

    const title = page.locator('#cbCanvas #title');
    // Placeholder tags in the description resolve to sample data (no raw [course]).
    await expect(page.locator('#cbCanvas #description')).toContainText('Aarav Sharma');
    await expect(page.locator('#cbCanvas #description')).not.toContainText('[course]');

    // Live-edit the title → preview mirrors it immediately.
    await page.fill('input[name="title"]', 'Certificate of Excellence 2026');
    await expect(title).toHaveText('Certificate of Excellence 2026');
  });

  test('typography, paper colour, sample data & device toggle drive the preview', async ({ page }) => {
    await login(page);
    await page.goto('instructor/certificate-builder', { waitUntil: 'domcontentloaded' });

    // Font style → hidden input + preview font var.
    await page.locator('#cbFontSeg .cb-seg__b[data-font="sans"]').click();
    await expect(page.locator('#cbFontInput')).toHaveValue('sans');

    // Alignment → hidden input + data-al on the preview.
    await page.locator('#cbAlignSeg .cb-seg__b[data-align="left"]').click();
    await expect(page.locator('#cbAlignInput')).toHaveValue('left');
    await expect(page.locator('#cbEnt')).toHaveAttribute('data-al', 'left');

    // Paper colour → override recorded; "Design default" clears it.
    await page.locator('#cbTones .cb-tone[data-c="#f4f9f6"]').click();
    await expect(page.locator('#cbPaperInput')).toHaveValue('#f4f9f6');
    await page.locator('#cbPaperReset').click();
    await expect(page.locator('#cbPaperInput')).toHaveValue('');

    // Sample data (preview-only) updates the live certificate name.
    await page.fill('#cbSmpStudent', 'Priyanka Deshmukh-Chatterjee');
    await expect(page.locator('#cbEntName')).toHaveText('Priyanka Deshmukh-Chatterjee');

    // Device toggle re-widths the preview frame.
    await page.locator('#cbDevices .cb-dev[data-dev="mobile"]').click();
    await expect(page.locator('.cb-frame')).toHaveClass(/dev-mobile/);
  });
});
