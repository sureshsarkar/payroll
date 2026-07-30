import { test as setup, expect } from '@playwright/test';
import { ACCOUNTS } from './fixtures';

/**
 * Logs each role in once and saves the resulting storageState JSON.
 * Subsequent test projects depend on this and start "already logged in"
 * by loading the matching state file.
 *
 * If a login form changes (selector, field name, route), update the
 * corresponding block here — every role suite reuses the artifact.
 */

setup('admin login', async ({ page }) => {
  await page.goto(ACCOUNTS.admin.loginUrl);
  await page.fill('input[name="email"]', ACCOUNTS.admin.email);
  await page.fill('input[name="password"]', ACCOUNTS.admin.password);
  await Promise.all([
    page.waitForURL(/\/admin\/(dashboard|two-factor)/, { timeout: 15_000 }),
    page.click('button[type="submit"]'),
  ]);
  // If 2FA prompt appears, we treat it as a setup failure — caller
  // must disable 2FA for the E2E admin or extend this block.
  expect(page.url()).toMatch(/\/admin\/dashboard/);
  await page.context().storageState({ path: 'tests/e2e/.auth/admin.json' });
});

setup('instructor login', async ({ page }) => {
  await page.goto(ACCOUNTS.instructor.loginUrl);
  await page.fill('input[name="email"]', ACCOUNTS.instructor.email);
  await page.fill('input[name="password"]', ACCOUNTS.instructor.password);
  await Promise.all([
    page.waitForURL(/\/(instructor|student|home)/, { timeout: 15_000 }),
    page.click('button[type="submit"]'),
  ]);
  await page.context().storageState({ path: 'tests/e2e/.auth/instructor.json' });
});

setup('student login', async ({ page }) => {
  await page.goto(ACCOUNTS.student.loginUrl);
  await page.fill('input[name="email"]', ACCOUNTS.student.email);
  await page.fill('input[name="password"]', ACCOUNTS.student.password);
  await Promise.all([
    page.waitForURL(/\/(student|home)/, { timeout: 15_000 }),
    page.click('button[type="submit"]'),
  ]);
  await page.context().storageState({ path: 'tests/e2e/.auth/student.json' });
});
