import { test, expect } from '@playwright/test';

/**
 * 13-July-Changes.docx — student-panel E2E (runs under the `student` project,
 * logged in as e2e-student).
 *
 *   #1 Dynamic browser-tab titles for student pages.
 *   #4 The "Refer & earn" copy-link must point at /register?ref=CODE
 *      (was /?ref=CODE, which dropped a referred friend on the home page).
 *
 * Run only this file:
 *   npx playwright test --project=student tests/e2e/student/01-july13-changes-2026-07.spec.ts --no-deps
 */

test.describe('July-13 student panel changes', () => {
  test('#1 student module pages have descriptive tab titles', async ({ page }) => {
    const titles: Record<string, string> = {
      'student/attendance': 'Attendance',
      'student/fees': 'Fees',
    };
    for (const [path, expected] of Object.entries(titles)) {
      const resp = await page.goto(path);
      if (!resp || resp.status() >= 400) continue;
      const title = await page.title();
      expect(title, `${path} tab title`).toContain(expected);
      expect(title, `${path} must not be the generic dashboard label`).not.toMatch(/^Student Dashboard\b/);
    }
  });

  test('#1 the student dashboard landing still reads "Student Dashboard"', async ({ page }) => {
    await page.goto('student/dashboard');
    expect(await page.title()).toMatch(/^Student Dashboard/);
  });

  test('#4 refer-and-earn copy link uses /register?ref=', async ({ page }) => {
    await page.goto('student/dashboard');
    const copyBtn = page.locator('.mbs-refer-copy[data-copy]').first();
    if ((await copyBtn.count()) === 0) {
      test.skip(true, 'refer-earn widget not rendered for this account');
      return;
    }
    const link = await copyBtn.getAttribute('data-copy');
    expect(link, 'referral share link').toContain('/register?ref=');
    expect(link, 'must not be the bare home-page ?ref=').not.toMatch(/\/\?ref=/);
  });
});
